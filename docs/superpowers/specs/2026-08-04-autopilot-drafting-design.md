# Autopilot Drafting — Design

**Date:** 2026-08-04
**Status:** Approved for planning
**Branch context:** `feature/linkedin-social-agent`

## Problem

Every draft in bobbyai starts with a human typing a brief. `PostController::store()`
creates the post and dispatches `DraftPostContent`; nothing else in the application
ever creates a post. `routes/console.php` schedules only invitation cleanup and
screenshot pruning.

The approval half of the product is already complete and safe — `PostApprovalController`
is the sole path that creates an `AgentRun` and dispatches `PublishPostRun`, and it
requires an authenticated team member plus the exact body being approved. The
generation half is entirely manual.

The goal is that the operator only ever arrives to edit and approve. Bobby decides
when to write, what to write about, and in what order, so that a feed of posts reads
as one voice with a thread running through it.

## Decisions

| Decision | Choice |
| --- | --- |
| Trigger | Fixed cadence — weekdays plus a time, per team |
| Topic source | Persistent idea backlog, falling back to unused brand pillars |
| Idea kind | Bobby classifies asynchronously; user correction is final |
| Flow | Both — backlog sequenced for variety, drafting conditioned on recent posts |
| Surfacing | Email per batch plus a studio badge |
| Architecture | Scheduled command over small, independently testable services |
| Agent attribution | `posts.author_id` nullable; null renders as Bobby |
| Notification recipients | Per-schedule `notify_emails`, seeded with the team owner |
| Default timezone | `Africa/Kampala` |

Rejected: a queue-depth trigger that tops up to a target (less predictable than a
posting rhythm); classifying nothing and having a model sequence the whole backlog at
schedule time (a second inference call on an 11 GB CPU-only box, and untestable);
an idea lifecycle state machine with a job per transition (machinery for load this
system does not have); seeding brand pillars into the backlog as rows (pollutes a
list the operator is supposed to own).

## Constraints

- **One queue worker.** `docs/deployment.md` mandates exactly one worker, shared with
  publishing runs. A scheduled draft dispatched at 06:00 queues behind any run in
  flight. This is correct behaviour, not a defect, and should not be worked around
  with a second worker.
- **CPU-only inference.** Prompt length is inference time. The narrative digest is
  capped rather than unbounded, and classification is kept off the scheduled path.
- **App timezone is UTC** (`config/app.php`). Cadence times are meaningless without a
  stored zone.

## Architecture

A scheduled command orchestrating small services. The command is glue; the logic
lives in units that can be tested without a model call.

```
agent:draft-scheduled  (every 15 minutes)
  └─ for each due PostingSchedule
       ├─ BacklogSequencer::next(team)      → PostIdea | pillar brief | null
       ├─ create Post (status: Draft, author_id: null)
       ├─ NarrativeContext::for(team)       → digest of recent published posts
       └─ Bus::batch([DraftPostContent, …])
            └─ then: DraftsReadyForReview notification

PostIdea created  →  ClassifyPostIdea job  →  IdeaClassifierAgent  →  kind
```

`DraftPostContent`, `Composer`, the post workspace and the entire approval path are
reused unchanged. The gap is the trigger, and this fills only the trigger.

## Data model

### `posting_schedules`

One per team. Separate from `brand_profiles` because posting rhythm is not brand
voice, and that table already carries voice, audience, topics, banned terms and
examples.

```
id
team_id            unique, constrained, cascadeOnDelete
social_account_id  nullable, constrained — the account to draft for
days               json, e.g. ["monday","wednesday","friday"]
time_of_day        string, "HH:MM"
timezone           string, default "Africa/Kampala"
posts_per_run      unsigned int, default 1
notify_emails      json, seeded with the team owner's address
is_active          boolean, default true
last_ran_at        nullable timestamp
timestamps
```

`last_ran_at` is the idempotency guard: a command that crashes and is retried must
not draft the same slot twice.

### `post_ideas`

```
id
team_id      constrained, cascadeOnDelete
body         text — the idea in the operator's words
kind         nullable string: announcement | lesson | story | opinion
kind_source  nullable string: agent | user
position     unsigned int — manual ordering
used_at      nullable timestamp
post_id      nullable, constrained, nullOnDelete
timestamps

index (team_id, used_at)
```

`kind` is nullable so an idea is usable the moment it is typed. If Ollama is down,
the result is an unclassified idea, not an error.

### `posts.author_id` → nullable

Agent-generated posts have no human author. Attributing machine output to the team
owner would hide which posts were requested and which were written unprompted.

## Components

### `BacklogSequencer`

Pure logic, no model calls. Returns the next thing to draft for a team:

1. Candidates are ideas with `used_at` null, ordered by `position`.
2. Reject any whose `kind` equals the **previous kind**: the `kind` of the idea
   linked to the team's most recently created post. A post with no linked idea
   (pillar-sourced) has no kind, in which case nothing is rejected. This is the
   no-two-announcements-in-a-row rule.
3. Among the remainder, prefer the kind least recently used; tie-break by `position`.
4. Ideas with a null `kind` are wildcards: never rejected, ordered by position alone.
   A classifier outage degrades ordering to FIFO rather than stalling the schedule.
5. Empty backlog → synthesise a brief from a brand pillar not written about in 14
   days, reusing the window `SuggestionBuilder::pillars()` already applies. No
   `post_ideas` row is created; a pillar-sourced post is simply one with a null
   author and no linked idea.
6. No pillars either → return null. The command logs the skip and moves on. Skipping
   beats inventing.

### `NarrativeContext`

A digest of the team's last three **published** posts, each trimmed to roughly 400
characters, rendered into the composer prompt as prior context: build on these where
natural, do not reuse these angles.

Published only. A draft that was never approved is not part of the feed, and
conditioning on rejected work would teach the model to repeat what the operator
turned down.

### `DraftContext` value object

`Composer::streamFor()` already takes five parameters and continuity would make six.
Brief, brand profile, `revisionOf` and narrative context collapse into a
`DraftContext` in `app/Agent/Values/`, alongside the existing `RunContract` and
`Observation`. Two call sites move: `PostController::store()` and
`PostMessageController::store()`.

### `agent:draft-scheduled`

Runs every 15 minutes, so a `06:00` cadence means 06:00 and not some time in the
6 o'clock hour.

A schedule is due when, **in its own timezone**: today is one of its `days`, the
local clock has passed `time_of_day`, and `last_ran_at` does not already fall on
today's local date.

"Has passed" rather than "equals" is deliberate: if the box is down at 06:00, the
08:15 tick still drafts. A missed local day is a skip, not a backfill — nobody wants
Tuesday's post arriving on Thursday.

Guards, each logged and each a skip rather than a failure:

- schedule inactive
- social account missing or not `Connected` per `AccountStatus::isPublishable()`
- sequencer returns null

Then, `posts_per_run` times: take the next idea, create the post at
`PostStatus::Draft` with a null author, mark the idea used and link `post_id`. All
drafts for one slot go out as a `Bus::batch` — the `job_batches` table already
exists — whose `then` callback sends the review notification, so the email only ever
describes drafts that actually landed.

### `ClassifyPostIdea` + `IdeaClassifierAgent`

Dispatched when an idea is created. The agent lives in `app/Ai/Agents/` alongside the
four existing agents. It writes `kind` and `kind_source: 'agent'`, and never
overwrites `kind_source: 'user'`. On failure it leaves `kind` null and stops.

## Interface

### Autopilot page — `/{team}/autopilot`

Cadence at the top: days, time, timezone, target account, posts per run, notify
emails, an on/off switch. Backlog below: add an idea in one field, drag to reorder,
and an editable kind chip per idea. Correcting a chip sets `kind_source: 'user'` and
locks it against re-classification.

One page rather than two, because the two halves answer one question — what Bobby
will write, and when. Splitting cadence into settings would put the posting rhythm in
a different room from the ideas it consumes, and would grow the nav twice.

Routes, under the existing `{current_team}` prefix and `EnsureTeamMembership`:

```
GET    autopilot                 autopilot.index
PUT    autopilot/schedule        autopilot.schedule.update
POST   autopilot/ideas           autopilot.ideas.store
PUT    autopilot/ideas/{idea}    autopilot.ideas.update
DELETE autopilot/ideas/{idea}    autopilot.ideas.destroy
PUT    autopilot/ideas/order     autopilot.ideas.reorder
```

### Studio board

Two additions to `StudioController`'s existing payload: a count of drafts awaiting
review on the Draft column, and a Bobby marker on cards whose `author_id` is null.

### Email

`DraftsReadyForReview`, following the pattern in `app/Notifications/Teams/`. Subject
is the brief for a single draft, a count for several; body links to the post
workspace. Sent from the batch `then` callback to the schedule's `notify_emails`.

### Post workspace

No changes. An agent-drafted post opens exactly like a briefed one — same thread,
same pinned draft, same approve button. That is the point of routing through
`DraftPostContent` rather than building a parallel path.

## Error handling

| Failure | Behaviour |
| --- | --- |
| Ollama down at classification | Idea saved with null `kind`; sequencer treats it as a wildcard |
| Ollama down at drafting | Existing `DraftPostContent::failed()` writes an error message to the thread |
| Account disconnected | Slot skipped and logged; no post created |
| Backlog and pillars both empty | Slot skipped and logged |
| Command crashes mid-slot | `last_ran_at` guard prevents a duplicate slot on retry |
| Worker busy publishing | Draft queues behind the run; expected on one worker |

## Testing

Unit:

- `BacklogSequencer`: same-kind rejection, least-recently-used preference,
  unclassified wildcards, position tie-breaks, pillar fallback, exhaustion returns null
- `NarrativeContext`: three-post cap, character trim, published-only selection

Feature:

- `agent:draft-scheduled` due/not-due across timezones — a 06:00 Kampala schedule must
  not fire at 06:00 UTC
- same-local-day idempotency, late firing after a missed tick, no cross-day backfill
- each skip guard: inactive, disconnected account, empty backlog and pillars
- `Bus::fake()` asserting the batch; `Notification::fake()` asserting recipients
- Autopilot authorisation, idea CRUD, reordering, kind correction locking
  `kind_source`, cross-team isolation matching the `abort_unless` pattern in
  `PostController::authorizeTeam()`

The classifier agent is faked in tests, following the precedent set by
`FakeBrowserDriver`.

**The regression test that matters most:** the scheduled path never produces a status
beyond `Draft` and never dispatches `PublishPostRun`. That property is the entire
safety story and must fail loudly if anyone wires drafting to approval.

Existing tests to update: any constructing `Composer::streamFor()` directly, and post
factories once `author_id` becomes nullable.

## Out of scope

- Auto-approval or auto-publishing of any kind. Approval stays human, always.
- Multiple schedules per team, or per-account schedules.
- Scheduling *when a post publishes* — approval still triggers publishing immediately.
- Analytics-driven topic selection.
