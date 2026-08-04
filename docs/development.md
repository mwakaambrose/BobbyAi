# Development

How to get the studio running locally and iterate on posts.

## Requirements

| Tool | Version | Notes |
| --- | --- | --- |
| PHP | 8.4 | CI runs 8.3, so avoid 8.4-only syntax |
| Node | 22 | Used by both the frontend build and the browser sidecar |
| Composer | 2 | |
| Ollama | any recent | Supplies both copywriting and browser action planning |

The database is SQLite, so there is no database server to install. Sessions, cache
and the queue all live in that same file.

## First-time setup

```bash
composer setup
```

That installs PHP and Node dependencies, copies `.env.example` to `.env` if you do
not already have one, generates an app key, migrates the database, and builds the
frontend once.

Two things `composer setup` does not do:

```bash
# Chromium for the browser sidecar
cd browser && npm install && npx playwright install chromium

# The model the agent plans and writes with
ollama pull gemma3:12b
```

`AGENT_MODEL` in `.env` decides which model is required. `config/agent.php`
defaults to `gemma3:12b`; pull whichever tag you set.

## Running

The studio needs two terminals, plus Ollama running somewhere.

**Terminal 1 — the application**

```bash
composer dev
```

This is `php artisan dev`, which fans out with `concurrently` into five processes:

| Process | Command | Why it matters |
| --- | --- | --- |
| server | `artisan serve --host=localhost` | Serves the app on http://localhost:8000 |
| queue | `artisan queue:listen --tries=1 --timeout=0` | Publishing runs are queued jobs. Without this, approving a post does nothing visible |
| logs | `artisan pail` | Live application log |
| vite | `npm run dev` | HMR for the React studio |
| reverb | `artisan reverb:start` | Streams drafts into the canvas as the model writes them |

Reverb is optional in the sense that nothing breaks without it: the queue worker
persists every draft either way, and the page falls back to reloading a few
seconds later. What you lose is watching the words appear. If you are debugging
that fallback, stop the reverb process and the app should still fill the canvas.

**Terminal 2 — the browser sidecar**

```bash
composer browser
```

A Node service on `http://127.0.0.1:4571` that owns Chromium. It is deliberately
not part of `composer dev`: writing and iterating on copy never touches it. You
only need it when a run drives a real browser, which means connecting an account
or publishing.

**Ollama**

Usually already running as a background service. If not, `ollama serve`.

## Verifying the environment

```bash
php artisan agent:doctor
```

Checks the three things that break most often — Ollama is reachable, the
configured model is actually pulled, and the sidecar is up. Run this first
whenever something misbehaves, before reading any code.

## The browser sidecar

Source lives in [`browser/`](../browser). It speaks a small HTTP contract that
[`SidecarBrowserDriver`](../app/Agent/Browser/SidecarBrowserDriver.php) calls:

| Route | Purpose |
| --- | --- |
| `GET /health` | Liveness, used by `agent:doctor` |
| `POST /sessions` | Launch a persistent Chromium context for an account |
| `GET /sessions/{id}/observation` | Element list, plus an annotated screenshot under `marks` perception |
| `POST /sessions/{id}/actions` | Click, type, and so on |
| `POST /sessions/{id}/navigate` | Go to a URL |
| `DELETE /sessions/{id}` | Close the session, leaving the profile on disk |

Each connected account gets its own persistent Chromium profile under
`storage/app/private/browser-profiles/{account-id}`. That is what lets a LinkedIn
session survive between runs — the login is in the profile, not in the database.
Deleting a profile directory logs that account out.

Override the port with `SIDECAR_PORT`, and point the app elsewhere with
`BROWSER_SIDECAR_URL`.

## Tests and quality gates

```bash
composer test        # config:clear, Pint, PHPStan, then the test suite
composer ci:check    # everything above plus ESLint, Prettier and tsc
```

While iterating, run the narrow thing instead:

```bash
php artisan test --compact --filter=PublishRunner
vendor/bin/pint --dirty --format agent
```

Sidecar tests are separate, since they are Node:

```bash
cd browser && npm test
```

## Configuration worth knowing

All of this lives in [`config/agent.php`](../config/agent.php), which documents
the reasoning behind each value in more depth.

| Variable | Default | Meaning |
| --- | --- | --- |
| `AGENT_MODEL` | `gemma3:12b` | Model for planning and copywriting |
| `AGENT_PERCEPTION` | `marks` | `marks` sends an annotated screenshot; `text` sends the element list only |
| `AGENT_STEP_CAP` | `18` | Maximum actions in a single run |
| `AGENT_RUN_TIMEOUT` | `1800` | Seconds a whole run may take |
| `AGENT_INFERENCE_TIMEOUT` | `300` | Seconds any single model call may take |
| `BROWSER_SIDECAR_URL` | `http://127.0.0.1:4571` | Where the app finds Chromium |
| `OLLAMA_URL` | `http://localhost:11434` | Read from `config/ai.php`, not `config/agent.php` |

Two coupling constraints that are easy to break:

- `AGENT_PERCEPTION=marks` requires a model that accepts image attachments. On a
  text-only model, switch to `text` or every planning call fails.
- `DB_QUEUE_RETRY_AFTER` (1860) must stay above `AGENT_RUN_TIMEOUT` (1800). If a
  run can outlive `retry_after`, a second worker picks up a job that is still
  running. Raise one, raise the other.

## Common problems

**"Could not open the browser: cURL error 7 … port 4571"**
The sidecar is not running. Start `composer browser`. This is the single most
common local failure, because the sidecar is a separate terminal from `composer dev`.

**Approving a post appears to do nothing**
The queue worker is not running. It ships inside `composer dev`; if you started
the server by hand with `artisan serve`, you also need `artisan queue:listen`.

**"Unable to locate file in Vite manifest"**
Either `npm run dev` is not running or you have never built. Run `composer dev`,
or `npm run build` for a one-off.

**`agent:doctor` says the model is missing**
`ollama pull <the tag in AGENT_MODEL>`. The name in `.env` and the name in Ollama
have to match exactly, tag included.

**Runs fail with unusable actions**
The model is not reading the annotated screenshot well. Set `AGENT_PERCEPTION=text`
to fall back to the element list. `agent:doctor` prints this hint too.

## Safety rails

The agent is only ever permitted to act on `www.linkedin.com`, pinned in
`config/agent.php`. Every observation the agent acted on is persisted so a run
can be replayed from the studio. Screenshots are pruned after 30 days by
`agent:prune-screenshots`; the step records themselves are kept indefinitely.
