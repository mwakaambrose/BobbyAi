# Collaborative drafting and the run theatre

Date: 2026-08-04
Status: approved

## 1. Why

Two halves of the product feel transactional where they should feel worked-on
together.

**Correcting a draft is a request, not a collaboration.** The canvas is a plain
textarea. The only way to change a sentence is to send a revision request
through the thread and have the whole post rewritten. Nothing lets you point at
the one line that is wrong.

**Publishing is a table refresh.** The original design (`2026-07-30`) described
the workspace's right panel as a theatre: the annotated screenshot with the
agent's observation in prose beneath it. What shipped is a bare list of step
rows. The model's reasoning is recorded on every step and only visible if you
click a row open, and steps arrive by polling while drafting streams over
Reverb. So the drafting half of the app feels alive and the publishing half
does not.

This design closes both gaps. It adds no dependencies.

## 2. Scope

In:

- Span-level correction of a draft: select, ask, inline diff, accept or reject.
- Accepted corrections recorded as thread turns.
- A live run theatre: streamed steps, screenshot, narrated reasoning.
- The run replay narrated from the same source.

Out, each its own brainstorm:

- Mid-run intervention (pause, correct, resume).
- Draft version history and rollback.
- Margin notes and batch revision.

## 3. The correction loop

### Interaction

Select a span of the draft. A prompt bar appears anchored to the selection; you
say what is wrong and submit. The box switches to a read-only proposal showing
`[before][old struck][new][after]`, the replacement streaming in. The text
around the span does not move. Then Accept or Reject.

Reject is purely local — nothing was persisted, so nothing happened.

Accept splices the string back into the buffer, returns the box to editing, and
records the exchange in the thread.

### Request

`POST /{team}/posts/{post}/refinements` — `PostRefinementController@store`.

| Field             | Rule                                            |
| ----------------- | ----------------------------------------------- |
| `body`            | required, string — the current editor buffer    |
| `selection_start` | required, integer, `>= 0`, `< strlen(body)`     |
| `selection_end`   | required, integer, `> selection_start`, `<= strlen(body)` |
| `instruction`     | required, string, max 500                       |

`body` is sent rather than read from the draft because the buffer may hold the
user's own typing, and the offsets are meaningless against anything else.
Offsets are validated server-side against the submitted body and 422 when out of
range.

The controller queues `RefineDraftSpan` and returns. Team ownership is asserted
as in `PostMessageController`.

### Job and agent

`RefineDraftSpan` runs `App\Agent\Reviser` over a new `App\Ai\Agents\EditorAgent`.
The agent receives the brand profile, the full body as context, the selected
span, and the instruction, and is constrained to return **only the replacement
text for the span** — no framing, no surrounding sentences.

Deltas stream through three events on the existing private `posts.{id}` channel,
mirroring `DraftChunk` and broadcast with `ShouldBroadcastNow`:

- `refine.chunk` — `{delta}`
- `refine.completed` — `{replacement}`
- `refine.failed` — `{reason}`

No new channel and no new authorization: the workspace is already subscribed to
`posts.{id}`.

### Accepting

`POST /{team}/posts/{post}/refinements/accept` — `PostRefinementController@accept`,
carrying the instruction, the replaced text, the replacement, and the offset.
It writes two `PostMessage` rows:

- `role: user`, `kind: text`, body = the instruction.
- `role: agent`, `kind: edit`, `payload: {before, after, start}`.

`MessageKind::Edit` is new. `ThreadMessage` grows an `edit` case rendering the
before/after pair compactly.

This is what makes the loop collaborative rather than transactional: Bobby's own
edit history is in the thread, so a later "now shorten the close" arrives with
context.

### What it does not touch

Nothing writes `PostDraft`. The angle carousel keeps meaning "angles Bobby
wrote". Buffer edits are snapshotted into `post.approved_body` on approval,
exactly as today.

### Frontend units

| File                            | Responsibility                                       |
| ------------------------------- | ---------------------------------------------------- |
| `draft-editor.tsx`              | Textarea, selection capture, anchored prompt bar      |
| `span-proposal.tsx`             | Read-only diff view, Accept / Reject                  |
| `use-span-refinement.ts`        | Echo subscription for `refine.*`, timeout             |
| `draft-canvas.tsx` (existing)   | Shell only: angles, char count, approve               |

Selection is captured as `selectionStart`/`selectionEnd` integers — plain
offsets, no DOM parsing. `DraftCanvas` gets smaller, which it needs at 167
lines.

### Edges

| Case                          | Behaviour                                             |
| ----------------------------- | ----------------------------------------------------- |
| Second request while pending  | Blocked; the editor is read-only during a proposal    |
| Offsets out of range          | 422 before the job is queued                          |
| Job fails                     | `refine.failed` renders inline, editor restored       |
| Socket never connects         | 30s timeout, inline retry — nothing was persisted     |
| Draft still streaming         | The prompt bar is unavailable while `isDrafting`      |

The lost work in the timeout case is one span edit and one retry, not a draft,
so no server-side recovery is warranted.

### Tests

| Kind    | Covers                                                            |
| ------- | ----------------------------------------------------------------- |
| Feature | Validation, team authorization, `RefineDraftSpan` dispatched       |
| Feature | Out-of-range offsets return 422                                    |
| Feature | Accept writes both messages with the `edit` payload                |
| Unit    | `Reviser` returns only the span under `Ai::fake()`                 |

## 4. The run theatre

### Streaming the steps

`PublishRunner::recordStep()` is the single place every step is persisted, so it
is the single place to broadcast from. A new `RunStepRecorded` event
(`ShouldBroadcastNow`) goes out on the same private `posts.{postId}` channel.

Payload: run id and status, and the step's index, phase, action, reasoning, gate
decision and reason, latency, error, and screenshot URL.

`use-run-stream.ts` replaces the 1.5s `usePoll` in `posts/show.tsx`. Because
steps are persisted, it keeps the 8s reload fallback drafting uses — a dead
socket degrades to today's behaviour rather than an empty panel.

### Narration

`App\Agent\StepNarrator` turns an `AgentStep` into one prose line from its
`ActionType` and element ref:

| Action  | Line                             |
| ------- | -------------------------------- |
| `click` | `Clicked "Start a post"`         |
| `type`  | `Typed the approved text`        |
| `press` | `Pressed Enter`                  |
| `scroll`| `Scrolled the feed`              |
| `wait`  | `Waited for the composer`        |
| `finish`| `Finished`                       |
| `abort` | `Stopped`                        |

An action with no resolvable element falls back to the bare verb. A denied step
is narrated as the attempt plus the gate reason.

The narrator is backend, not frontend, so the live theatre and the replay
produce identical text from one tested function.

Beneath each line sits the model's own `reasoning`, quoted. That is the thoughts
stream — already in the database, merely buried behind a click today. No second
model call is made to write narration: it would double calls on the publish
path and add latency to every step to restate what the reasoning already says.

### The two surfaces

**Theatre** — `run-theatre.tsx`, replacing `RunTimeline` in the workspace's
right panel. The current screenshot large at the top; narration lines
accumulating below as they arrive, the active one marked. Gate denials and
errors stay visually distinct: a blocked step is the most important thing on the
panel. "Stop the agent" stays where it is.

**Replay** — `runs/show` renders the same `StepNarration` component, so it reads
as prose rather than `click #12 · 340ms`. Hover-to-scrub of the screenshot
stays; that interaction is good. The audit details the page exists for — latency,
gate reason, raw action — demote to secondary text on each line rather than
being the line.

`RunTimeline` is superseded by `run-theatre.tsx` and removed.

### Tests

| Kind    | Covers                                                              |
| ------- | ------------------------------------------------------------------- |
| Unit    | `StepNarrator` over every `ActionType`, a denied step, a step with no element |
| Feature | A `FakeBrowserDriver` run broadcasts `RunStepRecorded` per step      |
| Feature | The replay page renders narrated steps                              |

## 5. Decisions and rejected alternatives

| Decision                                   | Rejected                                                        | Why                                                                  |
| ------------------------------------------ | --------------------------------------------------------------- | -------------------------------------------------------------------- |
| Textarea plus read-only proposal mode      | Contenteditable segment renderer; Tiptap/ProseMirror             | Caret, IME and undo bugs for plain text; a library needs approval and there is no rich formatting to justify it |
| Templated narration plus stored reasoning  | A second model call per step to write prose                      | Doubles calls on the publish path and adds latency per step           |
| Accepted edits become thread turns         | Local-only until approve; a new `PostDraft` per accept           | Local-only leaves Bobby blind to its own edits; a draft per accept mixes edit history into the angle carousel |
| Broadcast on the existing `posts.{id}`     | A `runs.{id}` channel                                            | The workspace is already subscribed and authorized                    |
| Queued refinement over Reverb              | Synchronous HTTP returning the replacement                       | Matches the drafting path and keeps the request fast                  |
