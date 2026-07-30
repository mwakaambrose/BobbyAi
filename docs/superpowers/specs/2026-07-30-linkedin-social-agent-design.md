# LinkedIn Social Agent — Design

Date: 2026-07-30
Status: Approved (design); implementation plan pending

## 1. Purpose

Let a local LLM draft LinkedIn posts in the team's brand voice, and — after a human
approves the exact text — publish them by driving a real browser, one observed
action at a time, with every step recorded and replayable.

The point is not to avoid LinkedIn's API. It is to build a publishing agent whose
perception is semantic (what the page *says*) rather than structural (CSS
selectors), so that platform UI changes degrade the agent's confidence instead of
breaking the integration outright.

### Non-goals for this slice

- Platforms other than LinkedIn.
- Image or video attachments.
- Analytics ingestion, engagement learning, autonomous scheduling.
- Multi-agent strategist/planner pipelines.
- Real-time websockets (Reverb) — polling is sufficient.

### Accepted risk

Driving LinkedIn with an automated browser is contrary to LinkedIn's User
Agreement and can result in account restriction. The owner of the account has
accepted this risk. The design mitigates it by using a real interactive login on a
persistent profile, requiring human approval of content, taking one deliberate
human-paced action at a time, and structurally forbidding any interaction beyond
the composer flow.

## 2. Architecture

Three cooperating processes, all local:

```
Laravel (Inertia v3 + React 19)         browser/ sidecar (Node)          Ollama
  Studio board, post workspace     ──HTTP──▶  Playwright + Chromium       gemma
  queue worker: PublishPostRun               persistent profiles/          vision
  PublishPolicy (the gate)          ◀──────  observe / act
            │
            └──────────── laravel/ai (Ollama provider) ─────────────────────┘
```

Laravel is the control plane and the only place decisions are made. The sidecar is
a mechanical effector: it can observe and it can act, but it never chooses. Ollama
is consulted for two things only — writing drafts, and choosing the next browser
action.

`composer run dev` runs vite, the queue worker, and the sidecar together.

### Why a long-running sidecar

Chromium must stay hot between steps: the observe → act loop runs many times per
run, persistent login state must survive, and relaunching per step would make the
loop unusably slow. The sidecar owns one `launchPersistentContext` per connected
account under `storage/app/private/browser-profiles/{account_id}/`.

The sidecar has its own `browser/package.json` depending on `playwright` and
nothing else (HTTP served by `node:http`). The application's root `package.json`
is untouched.

## 3. Perception: set-of-marks

Every observation is produced by the sidecar in one pass:

1. Walk the accessibility tree, keeping actionable and text-bearing nodes.
2. Assign each an integer `ref`, held in an in-memory map `ref → ElementHandle`
   for the lifetime of that observation only.
3. Inject absolutely-positioned overlay boxes labelled with those integers.
4. Screenshot the viewport.
5. Remove the overlay.

The observation returned to Laravel:

```json
{
  "url": "https://www.linkedin.com/feed/",
  "title": "Feed | LinkedIn",
  "screenshot": "<base64 png>",
  "elements": [
    { "ref": 3,  "role": "link",    "name": "Home",    "state": {} },
    { "ref": 7,  "role": "button",  "name": "Start a post", "state": {} },
    { "ref": 12, "role": "textbox", "name": "What do you want to talk about?", "state": {"focused": false} },
    { "ref": 19, "role": "button",  "name": "Post",    "state": {"disabled": true} }
  ]
}
```

The model receives the annotated screenshot as an attachment plus the element list
as text, and answers with a `ref`. It never produces coordinates, so an action can
always be validated against a known element before it executes, and a stale `ref`
is a hard error rather than a misplaced click.

### Degraded mode

If the configured model cannot handle images reliably, `AGENT_PERCEPTION=text`
omits the screenshot attachment and sends only the element list. The rest of the
system is unchanged. Screenshots are still captured and stored for the audit
trail either way.

## 4. The loop

`App\Agent\PublishRunner` is an explicit state machine executing at most one
action per iteration:

| Phase   | Owner           | Output                                              |
|---------|-----------------|-----------------------------------------------------|
| observe | sidecar         | `Observation` (url, title, screenshot, elements)    |
| think   | `ActionPlanner` | `Action` via laravel/ai structured output           |
| gate    | `PublishPolicy` | `GateDecision` (allowed / denied + reason)          |
| act     | sidecar         | `ActionResult` (ok / error)                         |
| record  | Laravel         | one `agent_steps` row                               |

The action schema given to the model:

```json
{
  "action": "click | type | press | scroll | wait | finish | abort",
  "ref":    "integer, required for click and type",
  "text":   "string, required for type",
  "key":    "string, required for press",
  "why":    "one sentence explaining the choice"
}
```

Termination: `finish` followed by a verification observation that the post is
live; or `abort`; or the step cap (18); or the run timeout (5 minutes); or two
consecutive gate denials of the same action.

Per-step timeout is 20 seconds for browser work and 60 seconds for model
inference (local inference on a large vision model is slow).

Exactly one run may be active per social account, enforced by an atomic lock.

## 5. The gate

`App\Agent\PublishPolicy` receives every proposed action together with the run's
`RunContract` — built once at approval time and immutable thereafter:

```
RunContract {
  allowed_host:   "www.linkedin.com"
  approved_body:  "<exact text the human approved>"
  allowed_roles:  [button, textbox, link]
  step_budget:    18
}
```

Rules:

- **Host lock.** Any action while the page is off `allowed_host` is denied.
- **Text equality.** A `type` action is denied unless its text is byte-for-byte
  identical to `approved_body`. The model cannot edit, extend, or append to what
  was approved.
- **Element allow-list.** A `click` is denied unless the target `ref` exists in the
  current observation and its role is in `allowed_roles`.
- **Interaction ban.** Any element whose accessible name matches the social-graph
  ban list (like, react, follow, connect, comment, reply, message, send, share,
  repost) is denied regardless of role.
- **Budget.** Actions beyond `step_budget` are denied.

A denial is not an exception. It is recorded as a step and fed back to the model
as an observation ("denied: …"), giving it a chance to choose differently. Two
consecutive denials of the same action abort the run — the model is confused and
should not keep guessing at a live account.

This is the reason the gate lives in Laravel rather than in the prompt: a prompt
can be talked out of a rule, a policy class cannot.

## 6. Content generation

`App\Agent\Copywriter` is a laravel/ai agent with structured output returning
three candidate drafts, each with a short rationale and a character count. It is
conditioned on:

- the team's `brand_profile` (voice, audience, topics, banned terms, example posts),
- the user's brief,
- LinkedIn's practical constraints (length, no markdown, hashtag conventions).

Banned terms are checked deterministically in PHP after generation, not left to
the model. A draft containing one is regenerated once, then surfaced with the
offending term highlighted.

The user may edit any draft freely. What is approved is what was on screen — the
approved body is snapshotted onto the post at approval time and the `RunContract`
is built from that snapshot.

## 7. Data model

All tables are team-scoped and follow the existing `{current_team}` route prefix
and `EnsureTeamMembership` middleware.

- `brand_profiles` — team_id, voice, audience, topics, banned_terms (json),
  examples (json)
- `social_accounts` — team_id, platform (enum), handle, profile_path, status
  (enum: disconnected, connected, expired), last_verified_at
- `posts` — team_id, social_account_id, author_id, brief, status (enum: brief,
  draft, approved, publishing, published, failed), approved_body, approved_at,
  approved_by, published_url, published_at
- `post_drafts` — post_id, variant, body, rationale, character_count, model,
  selected_at
- `post_messages` — post_id, role (user, agent), kind (text, drafts, run), body,
  payload (json) — backs the workspace thread
- `agent_runs` — post_id, social_account_id, status, mode (autonomous, step),
  contract (json), started_at, finished_at, failure_reason, step_count
- `agent_steps` — agent_run_id, index, phase, action (json), reasoning, ref,
  screenshot_path, gate_decision, gate_reason, latency_ms, error

Screenshots are stored on the private disk, referenced by path, and pruned by a
retention command (default 30 days).

## 8. Interfaces and isolation

```
App\Agent\Contracts\BrowserDriver
  openSession(SocialAccount): SessionHandle
  observe(SessionHandle): Observation
  act(SessionHandle, Action): ActionResult
  closeSession(SessionHandle): void
```

Two implementations: `SidecarBrowserDriver` (HTTP to the Node service) and
`FakeBrowserDriver` (a scripted list of observations, asserting the actions it
receives). Because the runner depends only on the interface, the whole publish
loop is testable with no browser and no model.

Each unit answers the three questions cleanly:

| Unit | Does | Depends on |
|------|------|-----------|
| `PublishRunner` | drives the state machine, records steps | BrowserDriver, ActionPlanner, PublishPolicy |
| `ActionPlanner` | turns an observation into a proposed action | laravel/ai |
| `PublishPolicy` | allows or denies an action | nothing (pure) |
| `Copywriter` | brief + brand → three drafts | laravel/ai |
| `SidecarBrowserDriver` | speaks HTTP to the sidecar | Http client |
| sidecar | observes and acts on Chromium | Playwright |

`PublishPolicy` being pure and dependency-free is deliberate: it is the component
whose correctness matters most, so it must be the easiest to test exhaustively.

## 9. User interface

Built on the existing starter kit — Inertia v3, React 19, Tailwind v4,
shadcn/ui, Wayfinder, existing sidebar shell and dark mode tokens.

Sidebar navigation: **Studio** (the board, and the app's landing page after
login), **Runs**, **Accounts**, **Brand**. The existing Dashboard, Teams, and
Settings items remain untouched.

### Studio board — `/{team}/studio`

Read-only status surface. Columns: Brief, Draft, Approved, Publishing, Published,
Failed. Cards are **not draggable**; state changes only from human approval or
agent execution. Because it reports rather than controls, it is where the UI can
be visually expressive:

- a step-progress ring on the card currently running,
- a slow pulse on the Publishing column while any run is live,
- animated count chips per column,
- a quiet flip as a card lands in Published,
- failures muted rather than alarming, with the failing step one click away.

Clicking a card opens the workspace. That is the board's only interaction.

### Post workspace — `/{team}/posts/{post}`

Two panels. Left is the thread: the brief, the agent's draft cards, revision
requests, and the approve action. Right has three states in the same space —
draft preview (LinkedIn-style card) before a run, live theatre during, replay
after:

```
┌─ thread ─────────────────────┬─ theatre ──────────────────────┐
│ you  › announce Growth       │  Run #24 · publishing · 3/5    │
│        Savings, warm tone    │                                │
│ bobby  3 drafts              │  ✓ open feed            1.2s   │
│   ┌ A ┐ ┌━B━┐ ┌ C ┐          │  ✓ click "Start a post"  0.6s  │
│ you  › B but shorter         │  ◐ type approved text          │
│ bobby  updated · 142 chars   │  · click "Post"        gated   │
│        [ Approve & publish ] │  ┌──────────────────────────┐  │
│                              │  │  [ annotated screenshot ]│  │
│                              │  └──────────────────────────┘  │
│                              │  "Composer is open; textbox    │
│                              │   [12] is focused."            │
└──────────────────────────────┴────────────────────────────────┘
```

Live updates via Inertia polling while `agent_runs.status` is active; polling
stops on terminal status. Steps arrive as deferred props with a pulsing skeleton
before first paint.

Each step row shows phase, action, latency, gate verdict, and expands to the
model's reasoning and the screenshot at that moment. A `step` mode toggle makes
the agent request confirmation per action — the debugging affordance.

### Accounts — `/{team}/accounts`

"Open browser & sign in" launches headed Chromium on the account's persistent
profile. The user logs in and clears MFA themselves; the app then verifies by
observing the feed and marks the account connected. No credentials are stored by
the application. A "Re-verify" action re-checks a session that may have expired.

### Brand — `/{team}/brand`

The brand profile form.

### Runs — `/{team}/runs` and `/{team}/runs/{run}`

Run history and full step-by-step replay with screenshots.

### Design language

Inherits the starter kit's shadcn tokens and dark mode. Additions: one accent
colour reserved for agent activity, a monospace treatment for the step timeline
so it reads as machine output, and motion used only to signal state change.
Every list has a designed empty state.

## 10. Error handling

| Failure | Handling |
|---------|----------|
| Sidecar unreachable | run fails immediately with a clear message; no partial browser state |
| Chromium/profile missing | caught at `agent:doctor`, surfaced on the Accounts page |
| Session expired (login wall observed) | run aborts, account marked `expired`, user prompted to re-verify |
| Model returns unparseable output | one retry with a schema reminder, then abort |
| Model proposes a denied action | recorded, fed back, retried; two identical denials abort |
| Stale `ref` | act returns an error, treated as a fresh observation, retried within the step budget |
| Step cap or timeout reached | run fails as `step_budget_exhausted` / `timed_out`, all steps retained |
| Publish verification fails | run fails as `unverified` — the post may or may not exist; the UI says exactly that and links the screenshot |

Nothing is silently retried. Every failure leaves a complete step trail.

## 11. Testing

| Layer | Coverage |
|-------|----------|
| Unit | `PublishPolicy` allow/deny matrix — host, text equality, role, ban list, budget; element-list builder; `Action` schema mapping |
| Feature | post lifecycle brief → drafts → approve → run queued; team authorization on every route; approval snapshots the body; run replay renders |
| Feature (full loop) | `FakeBrowserDriver` + `Ai::fake()` — run reaches `published`, every step recorded, an injected off-contract action is blocked |
| Node | one `node:test` on the sidecar's ref ↔ element mapping and overlay teardown |
| Manual | real LinkedIn, headed, by the account owner |

Real LinkedIn is never touched in CI.

## 12. Operations

- `php artisan agent:doctor` — Ollama reachable, model pulled, vision round-trips a
  test image, sidecar up, Chromium installed, profiles writable. Prints what to fix.
- `php artisan agent:prune-screenshots` — retention.
- Config: `AGENT_MODEL`, `AGENT_PERCEPTION` (marks|text), `AGENT_STEP_CAP`,
  `AGENT_RUN_TIMEOUT`, `BROWSER_SIDECAR_URL`.

## 13. Dependencies (approved)

- `laravel/ai` `^0.10` — official AI SDK; first-class Ollama provider with image
  attachments, tool calling, and JSON-schema structured output.
- `browser/package.json` (new, isolated) — `playwright` only.
- Root `package.json` unchanged.

## 14. Open items for the implementation plan

- Confirm the exact local Ollama model tag and whether it supports image input;
  `agent:doctor` answers this before any run is attempted.
- Whether `pestphp/pest-plugin-evals` is worth adding to score draft quality —
  deferred, not needed for this slice.
