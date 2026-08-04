# Conversational Home — Design

**Date:** 2026-08-04
**Status:** Approved for planning
**Branch context:** `feature/linkedin-social-agent`

## Problem

bobbyai is an autonomous LinkedIn social agent, but its interface is form-and-list: a
metrics dashboard, a kanban board, a post detail page, a run log. The agent's
intelligence is invisible until a draft appears, fully formed, after a blocking request.

The product should read as an intelligence platform — a surface where you brief the
agent in your own words, watch it work, and approve the result, with the reasoning
visible throughout.

## Decisions

| Decision | Choice |
| --- | --- |
| Scope of change | Hybrid — new conversational Home; Studio, Runs, Accounts, Brand stay |
| Agent remit in the thread | Content work only: briefs, drafts, rewrites, tone, scheduling |
| Draft placement | Thread left, pinned live draft canvas right |
| Visual language | Existing warm paper-and-ink tokens, at the references' entry-point scale |
| Response feel | True token streaming over Reverb, with polling fallback |
| Suggestion source | Derived from stored data, no model call |
| Draft model | One streamed plain-text draft; three alternatives on demand |

Rejected: a full thread-first restructure (retires the kanban pipeline view), a
reference-faithful violet/glass restyle (discards the warm system built over the last
three commits and forces every page to be re-tokenised), and adding Home as a fourth
overlapping surface.

## Surfaces and routing

`{team}/dashboard` stops rendering `dashboard.tsx` and renders a new `home.tsx`.
`DashboardController` becomes `HomeController`. Navigation is otherwise unchanged.

The route's **URI and name stay `{current_team}/dashboard` / `dashboard`**. Only the
controller class and the Inertia page component change. This keeps every existing
Wayfinder import (`@/routes` → `dashboard`), breadcrumb, post-login redirect, and
bookmark valid; renaming would ripple through auth redirects and layouts for no user-
visible gain. `resources/js/pages/dashboard.tsx` is deleted once `home.tsx` replaces it.

### Home zones

1. **Greeting** — time-of-day salutation, user's first name, and a second line posing
   the actual question ("What should we post today?").
2. **Composer** — omni-input submitting to the existing `posts.store`.
3. **Suggestions** — three derived cards (see below).
4. **Status strip** — a single line replacing today's four metric tiles:
   "1 run in progress · 2 awaiting publish · 3 of 4 accounts connected", each fragment
   linking to Studio / Runs / Accounts respectively. The information survives; the real
   estate does not.

The pending-invitations modal carries over unchanged.

Home does not host the thread. Submitting a brief creates the `Post` and redirects to
`posts.show`.

### `posts.show` — thread and canvas

Already a two-column grid (`resources/js/pages/posts/show.tsx:75`) with the right data
model behind it: `Post` is the thread, `PostMessage` the turns, `PostDraft` the
versioned drafts via `variant` and `selected_at`.

The right column becomes a pinned canvas rather than a passive draft list:

- current draft body, streaming as it is written
- character count
- brand-voice provenance line
- **Approve & publish** button, on the canvas rather than below the thread
- **Show me other angles** — populates the canvas with the three alternatives and
  reveals arrows to move between them

### Draft model: one streamed draft, alternatives on demand

`CopywriterAgent` implements `HasStructuredOutput` and returns exactly three
alternatives (hook-led, story-led, question-led) in a single JSON response. Streaming
that produces partial-JSON deltas, which cannot be rendered as prose — there is nothing
to type into the canvas. `PostDraft.variant` holds A/B/C *alternatives*, not revisions
over time, so the canvas arrows are an alternative-picker, not a version history.

Therefore:

- A new **`ComposerAgent`** — plain text, no structured output — writes a *single* draft
  and is the one that streams. It inherits `CopywriterAgent`'s instructions minus the
  "return exactly three" clause. Its output is persisted as variant `A`.
- **`CopywriterAgent` is unchanged** and moves behind the explicit "Show me other
  angles" action, which generates variants `A`, `B`, `C` as it does today.
- Banned-term enforcement must apply to both paths. `Copywriter::bannedTermsIn()` is
  already public and stays the single source of that rule; the composer path reuses it
  with the same regenerate-once-on-violation behaviour.

The left column becomes purely conversational: user turns, agent prose, follow-up chips.
Run steps remain in their existing deferred panel.

**Folded-in refactor.** `posts/show.tsx` is 250 lines and is about to absorb streaming.
Split into `thread-column.tsx`, `draft-canvas.tsx`, and a `use-post-stream.ts` hook,
leaving the page file as composition only. This is the file the feature lands in, not
unrelated cleanup.

## Suggestion engine

A `SuggestionBuilder` in `app/Agent/` returning a typed array. Rules in priority order;
the first three that match win:

1. **Resume** — a `Post` in `draft` status touched within 7 days → "Finish: {brief}"
2. **Awaiting you** — a `Post` in `approved` status → "Approve and publish: {brief}"
3. **Pillar** — a `BrandProfile` content pillar unused in 14 days → "Write about {pillar}"
4. **Cadence** — modal weekday of the team's published posts → "Your weekly {Tuesday} update"
5. **Cold start** — for a team matching none of the above, two pillar-shaped prompts
   seeded from the brand profile; generic starters when the brand profile is empty

No model call, no cache layer, deterministic and assertable.

In-thread follow-up chips (*Shorten*, *Change tone*, *Add a hook*, *Approve & publish*)
are static per draft state and do not use the rules engine.

## Streaming architecture

### Dependencies added

- `laravel/reverb` (composer)
- `laravel-echo` + `pusher-js` (npm)
- `broadcasting.default` → `reverb` (currently `log`)
- A long-running `php artisan reverb:start` process alongside the queue worker

`docs/deployment.md` must be updated to cover both processes.

### Request path

`PostController@store` stops calling `Copywriter` inline
(`app/Http/Controllers/Agent/PostController.php:38`). It creates the `Post` and the
user's `PostMessage`, dispatches `DraftPostContent`, and redirects immediately — so the
thread page renders instantly with the user's turn on screen and the canvas in a writing
state. `PostMessageController@store` gets identical treatment for follow-up turns.

### Job path

`DraftPostContent` calls `ComposerAgent::stream()` and iterates the returned
`StreamableAgentResponse`, per the AI SDK's documented streaming API.

The SDK also offers `$event->broadcast(new Channel(...))` and an agent-level
`broadcastOnQueue()`, which would broadcast the SDK's own stream-event classes directly.
The job deliberately does **not** use those. It re-emits each text delta as the
application's own `DraftChunk` event (`ShouldBroadcastNow`, payload `{ delta: string }`)
on a private `posts.{id}` channel, because:

- the client contract stays ours — stable event names, no coupling to the SDK's internal
  streaming event classes across upgrades
- the job keeps control of persistence and failure handling around the loop
- `Event::fake()` assertions become straightforward

On completion the job persists the `PostDraft` (variant `A`) and the agent `PostMessage`,
then broadcasts `DraftCompleted`.

### Client path

`use-post-stream.ts` subscribes via Echo and accumulates chunks into local state, which
the canvas renders with a caret. On `DraftCompleted` it fires
`router.reload({ only: ['drafts', 'messages'] })` and server state becomes the source of
truth.

**Streaming is progressive enhancement only.** The job persists everything, so a page
whose socket never connects still receives its drafts — falling back to the `usePoll`
pattern already proven in `resources/js/pages/studio/index.tsx:42`. Correctness never
depends on the websocket.

### Authorization

A `posts.{post}` channel in `routes/channels.php`, authorizing on the same rule as
`PostController::authorizeTeam` — the post's `team_id` must match one of the user's
teams. This is the one place the feature can leak another team's content, so it gets a
dedicated test.

### Failure handling

Job `tries: 2`. On final failure the job persists an agent message of a new
`MessageKind::Error` and broadcasts `DraftFailed`; the canvas shows the reason and a
Retry that redispatches. A thread that fails mid-stream must never be left appearing to
still be thinking.

### Resolved during planning

The `laravel/ai` streaming surface was confirmed against the SDK docs: agents expose
`stream()` returning an iterable `StreamableAgentResponse` with a `then()` completion
callback, plus `broadcast()`/`broadcastNow()` on individual events and an agent-level
`broadcastOnQueue()`. The architecture above uses `stream()` and re-emits application
events, for the reasons given.

This also surfaced the structured-output conflict resolved in "Draft model" above.

## Visual system

Three token additions to `resources/css/app.css`. No new colours.

- `--text-display` — ~1.9rem, `-0.026em` tracking, weight 600, for the greeting.
  Today's ceiling is `--text-title` at 1.375rem, which is why the greeting otherwise
  reads as a page header rather than a front door.
- `--radius-xl: calc(var(--radius) + 6px)` — composer and suggestion cards.
- Composer elevation reuses the existing `--shadow-md`.

The generous scale is scoped to Home and the canvas header. Studio, Runs, and Accounts
stay dense; the contrast is deliberate.

## Testing

Feature tests (Pest):

- each suggestion rule in isolation, plus the cold-start path
- `posts.store` dispatches `DraftPostContent` and does not call the model
- `DraftPostContent` persists a variant `A` draft and broadcasts `DraftChunk` and
  `DraftCompleted` under `Event::fake()`, using `ComposerAgent::fake()`
- `DraftPostContent` regenerates once when the streamed draft contains a banned term,
  and persists the clean second attempt
- `DraftPostContent` failure persists an error message and broadcasts `DraftFailed`
- `posts.alternatives.store` generates variants A, B and C via the unchanged
  `Copywriter`, and is rejected for a foreign team
- `posts.{post}` channel authorization rejects a user from another team
- Home renders with correct status-strip counts

The existing `tests/Feature/Agent/PostPersistenceTest.php` and `PostLifecycleTest.php`
assert synchronous draft creation on `posts.store` and will need updating to assert
dispatch instead. `CopywriterTest.php` covers the unchanged three-variant path and
should keep passing untouched — if it breaks, the composer split has leaked.

Browser test: a Pest smoke on Home for JS errors, since it is the new landing route.

## Out of scope

- Account intelligence questions ("how did last week's posts do?") — the agent's remit
  is content work only
- Agent-initiated actions (connecting accounts, editing the brand profile, aborting runs)
- Retiring the Studio kanban board
- Re-tokenising the rest of the application to a new palette
