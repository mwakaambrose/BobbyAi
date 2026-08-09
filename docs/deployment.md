# Production Deployment

Single Oracle Cloud host running everything: nginx, PHP-FPM, the queue worker,
the Chromium sidecar, Reverb, and Ollama. 11 GB RAM, no GPU.

Read the [resource budget](#the-resource-budget) before anything else. It is the
constraint that decides most of the rest of this document.

## Why one host

The app is stateful in ways that rule out serverless and most managed PaaS
targets:

- Each connected account keeps a persistent Chromium profile on local disk. That
  profile *is* the LinkedIn session. It cannot live on ephemeral storage, and it
  cannot be shared across nodes.
- A publishing run is a single queued job that may take up to 30 minutes
  (`AGENT_RUN_TIMEOUT`), well past typical request and job ceilings.
- Inference is local to Ollama.

One box, one worker, local disk. Scaling out means sharding accounts across
hosts, not adding nodes behind a load balancer.

## The resource budget

This is the part that needs planning rather than copy-paste. Everything shares
11 GB:

| Component | Resident | Notes |
| --- | --- | --- |
| OS, nginx, PHP-FPM, worker | ~1.5–2 GB | Modest, but not nothing |
| Reverb | ~50–100 MB | One more idle PHP process; it holds sockets, not work |
| Chromium, per active run | ~0.5–1 GB | A persistent context with a real page loaded |
| Ollama model | **whatever is left** | ~7–8 GB ceiling, and that is too tight |

Your development model, `gemma4:12b`, is 7.6 GB on disk and needs roughly that
much resident. Against a 2 GB baseline plus Chromium, that leaves no headroom —
the box will swap mid-run, and swapping during CPU inference is effectively a
hang.

**Use a smaller model in production.** A 4B-class model at ~3–4 GB leaves room
for Chromium and the web tier at the same time. Set it explicitly:

```dotenv
AGENT_MODEL=gemma3:4b
```

There is no GPU, so this is also a speed decision, not only a memory one. For
calibration, `config/agent.php` records ~40 s for a planning call and ~180 s for
a three-draft copywriting call against `gemma4:12b` on Apple silicon with GPU
acceleration. Ordinary drafting is now a single streamed post rather than three
at once, so it lands well under that figure; the three-draft cost is only paid
when someone asks for other angles. On CPU-only ARM cores, expect a 4B model to land in a broadly
similar range and a 12B model to be several times worse. **These are estimates —
measure on the actual box before committing to timeouts** by timing one real run.

Two mitigations that matter more here than in development:

```dotenv
AGENT_PERCEPTION=text
```

Vision prefill is expensive on CPU, and `text` perception sends the element list
without the annotated screenshot. Screenshots are still captured and retained for
the audit trail either way, so you keep run replay. Start here and only move to
`marks` if action quality is unacceptable.

Constrain Ollama so it never holds more than one model copy:

```dotenv
OLLAMA_MAX_LOADED_MODELS=1
OLLAMA_NUM_PARALLEL=1
OLLAMA_KEEP_ALIVE=5m
```

`OLLAMA_KEEP_ALIVE` is a genuine trade-off: keeping the model resident avoids a
reload on every run, but holds several GB while Chromium wants memory. Five
minutes keeps it warm across a burst of runs and releases it when idle.

### Timeouts follow the hardware

If measured runs are slower than the defaults allow, raise the timeouts together —
they are coupled, and breaking the coupling causes duplicate runs:

```dotenv
AGENT_INFERENCE_TIMEOUT=600     # a single model call
AGENT_RUN_TIMEOUT=3600          # a whole run
DB_QUEUE_RETRY_AFTER=3660       # MUST stay above AGENT_RUN_TIMEOUT
```

`DB_QUEUE_RETRY_AFTER` above `AGENT_RUN_TIMEOUT` is not optional. If a run can
outlive `retry_after`, a second worker picks up a job that is still executing and
you get two browsers driving one account.

### Swap

Oracle images often ship with little or no swap. Add a few GB as a safety net so
a memory spike fails slowly instead of OOM-killing PHP-FPM. Treat it strictly as
a net — if normal runs touch swap, the model is too big.

### Architecture check

Oracle's 11 GB shapes are usually Ampere A1, which is ARM64. Confirm before
provisioning, because Playwright ships different Chromium builds per
architecture:

```bash
uname -m     # aarch64 on Ampere A1, x86_64 on AMD shapes
```

Playwright's browser binaries must be installed for the same service user that
launches the sidecar. Keep them in an explicit shared cache so a systemd
restart, a non-login shell, or a Playwright upgrade cannot make the installer
and the sidecar look in different locations:

```bash
sudo install -d -o www-data -g www-data /var/www/.cache/ms-playwright
```

## Provisioning

Ubuntu 24.04 assumed. Adjust package names to taste.

```bash

# PHP
sudo apt update
sudo apt install -y software-properties-common ca-certificates lsb-release apt-transport-https
sudo add-apt-repository ppa:ondrej/php

sudo apt update
sudo apt install -y nginx php8.4-fpm php8.4-cli php8.4-sqlite3 php8.4-mbstring \
    php8.4-xml php8.4-curl php8.4-zip php8.4-intl unzip git sqlite3

# MariaDB
sudo apt update
sudo apt install mariadb-server mariadb-client -y
sudo systemctl enable --now mariadb
sudo systemctl status mariadb

sudo mariadb-secure-installation

# Node 22
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt install -y nodejs

# Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Ollama
curl -fsSL https://ollama.com/install.sh | sh
ollama pull gemma3:4b
```

Open only 80 and 443 in the Oracle security list. Ollama binds to `127.0.0.1:11434`
by default and **must stay there** — it has no authentication whatsoever. Nothing
in this deployment needs it reachable from outside the host, so do not proxy it
through nginx.

## Application setup

Deploy to `/var/www/bobbyai`, owned by `www-data`. If the checkout lives at a
different path (for example `/var/www/html/BobbyAi`), substitute that path in
every command below.

```bash
sudo mkdir -p /var/www/bobbyai
sudo chown -R www-data:www-data /var/www/bobbyai
sudo -u www-data git clone <repo> /var/www/bobbyai
cd /var/www/bobbyai

sudo -u www-data composer install --no-dev --optimize-autoloader
sudo -u www-data npm ci && sudo -u www-data npm run build
sudo chown -R www-data:www-data /var/www/bobbyai/browser
sudo install -d -o www-data -g www-data /var/www/.npm
sudo install -d -o www-data -g www-data /var/www/.cache/ms-playwright
sudo -u www-data env HOME=/var/www PLAYWRIGHT_BROWSERS_PATH=/var/www/.cache/ms-playwright \
    bash -c 'cd /var/www/bobbyai/browser && npm ci && npx playwright install chromium'
sudo env HOME=/var/www PLAYWRIGHT_BROWSERS_PATH=/var/www/.cache/ms-playwright \
    /var/www/bobbyai/browser/node_modules/.bin/playwright install-deps chromium

sudo -u www-data cp .env.example .env
sudo -u www-data php artisan key:generate
sudo -u www-data touch database/database.sqlite
sudo -u www-data php artisan migrate --force
```

The `install-deps` command must run as root because it installs Linux libraries;
the browser install itself runs as `www-data` so the sidecar can read the cache.
The same two commands are repeated during [deploying changes](#deploying-changes)
because Playwright may require a new browser revision after `npm ci`.

`npm run build` on a small ARM box is the heaviest step of a deploy. If it runs
out of memory, build elsewhere and ship `public/build`.

### Environment

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain

DB_CONNECTION=sqlite
SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database

AI_PROVIDER=ollama
OLLAMA_URL=http://localhost:11434
AGENT_MODEL=gemma3:4b
AGENT_PERCEPTION=text
BROWSER_SIDECAR_URL=http://127.0.0.1:4571

BROADCAST_CONNECTION=reverb
REVERB_APP_ID=
REVERB_APP_KEY=
REVERB_APP_SECRET=
REVERB_HOST=your-domain
REVERB_PORT=443
REVERB_SCHEME=https

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

The `REVERB_*` values must be generated once and kept — regenerating the key
invalidates every browser currently connected. The `VITE_*` mirrors are compiled
into the bundle, so **changing them requires `npm run build`**, not just a
service restart. `REVERB_HOST` and `REVERB_PORT` here describe the address the
*browser* dials, which is nginx on 443, not the port Reverb itself binds.

`OLLAMA_URL` is read by `config/ai.php`, not `config/agent.php` — a common place
to look in the wrong file. Note also that `config/ai.php` keys off `AI_PROVIDER`
while `config/agent.php` keys off `AGENT_PROVIDER`; both default to `ollama`.

### SQLite

Enable WAL so the web tier and a long-running job stop blocking each other:

```bash
sudo -u www-data sqlite3 database/database.sqlite 'PRAGMA journal_mode=WAL;'
```

The **directory** must be writable by `www-data`, not just the file — SQLite
writes sibling `-wal` and `-shm` files.

## Services

Four long-running things: PHP-FPM (packaged), the sidecar, the worker, and Reverb.

The service units below assume the checkout is `/var/www/bobbyai`. If the actual
checkout is elsewhere, update every `WorkingDirectory` and application path in
the units to match it; systemd does not follow the shell's current directory.

Run the sidecar as `www-data`. It creates browser profile directories under the
app's storage path, so it needs the same ownership as the app.

`/etc/systemd/system/bobbyai-sidecar.service`:

```ini
[Unit]
Description=BobbyAI browser sidecar
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/bobbyai/browser
ExecStart=/usr/bin/node server.mjs
Restart=always
RestartSec=5
Environment=SIDECAR_PORT=4571
Environment=HOME=/var/www
Environment=PLAYWRIGHT_BROWSERS_PATH=/var/www/.cache/ms-playwright

[Install]
WantedBy=multi-user.target
```

`/etc/systemd/system/bobbyai-worker.service`:

```ini
[Unit]
Description=BobbyAI queue worker
After=network.target bobbyai-sidecar.service

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/bobbyai
ExecStart=/usr/bin/php artisan queue:work --tries=1 --timeout=3660 --sleep=3
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

**Run exactly one worker.** `PublishPostRun` already applies `WithoutOverlapping`
per account, but that does not stop two *different* accounts running at once —
which on this box means two Chromiums and two concurrent inference calls against
11 GB. One worker serialises everything.

The `--timeout` must exceed `AGENT_RUN_TIMEOUT`; the job itself reports its
timeout from `config('agent.run_timeout')`.

`/etc/systemd/system/bobbyai-reverb.service`:

```ini
[Unit]
Description=BobbyAI Reverb websocket server
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/bobbyai
ExecStart=/usr/bin/php artisan reverb:start --host=127.0.0.1 --port=8080
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

Reverb binds to loopback and is reached only through nginx, which terminates TLS
for it. Do not open 8080 in the Oracle security list.

**Drafting does not depend on this service.** The queue worker writes every draft
to the database regardless; Reverb only decides whether the user watches it
appear word by word or waits a few seconds for the client's fallback reload. If
Reverb is down, drafting still works and the page still fills in — worth knowing
before treating a websocket outage as an incident.

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now bobbyai-sidecar bobbyai-worker bobbyai-reverb
```

### Scheduler

`routes/console.php` schedules expired-invitation cleanup and
`agent:prune-screenshots` at 03:00. Without cron, screenshots accumulate until
the disk fills.

```bash
sudo -u www-data crontab -e
```

```cron
* * * * * cd /var/www/bobbyai && php artisan schedule:run >> /dev/null 2>&1
```

### nginx

Standard Laravel vhost against `/var/www/bobbyai/public`, PHP-FPM over the
`php8.4-fpm` socket, plus a websocket upgrade for Reverb:

```nginx
location /app {
    proxy_pass http://127.0.0.1:8080;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_set_header Host $host;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_read_timeout 3600s;
}
```

`/app` is Pusher-protocol's fixed path, which Echo derives from the app key — it
is not a route in this application. The long `proxy_read_timeout` stops nginx
closing idle sockets between drafts.

Then TLS:

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d your-domain
```

Screenshots are served through the app, not by nginx directly — they live on the
private disk and are authorised per run by
`runs.screenshot`. Do not expose `storage/app/private` as a static root.

## Deploying changes

```bash
cd /var/www/bobbyai
sudo -u www-data git pull
sudo -u www-data composer install --no-dev --optimize-autoloader
sudo -u www-data npm ci && sudo -u www-data npm run build
sudo chown -R www-data:www-data /var/www/bobbyai/browser
sudo install -d -o www-data -g www-data /var/www/.npm
sudo install -d -o www-data -g www-data /var/www/.cache/ms-playwright
sudo -u www-data env HOME=/var/www PLAYWRIGHT_BROWSERS_PATH=/var/www/.cache/ms-playwright \
    bash -c 'cd /var/www/bobbyai/browser && npm ci && npx playwright install chromium'
sudo env HOME=/var/www PLAYWRIGHT_BROWSERS_PATH=/var/www/.cache/ms-playwright \
    /var/www/bobbyai/browser/node_modules/.bin/playwright install-deps chromium
sudo -u www-data php artisan migrate --force
sudo -u www-data php artisan optimize      # config, route and view caches
sudo systemctl restart bobbyai-worker bobbyai-sidecar bobbyai-reverb php8.4-fpm
```

Restarting the worker matters: a running worker holds stale code in memory.
Restarting the sidecar closes live browser sessions but leaves profiles on disk,
so accounts stay logged in.

After `artisan optimize`, `env()` outside of config files returns null. Read
configuration through `config()` only.

Verify:

```bash
sudo -u www-data php artisan agent:doctor
sudo -u www-data env HOME=/var/www PLAYWRIGHT_BROWSERS_PATH=/var/www/.cache/ms-playwright \
    /var/www/bobbyai/browser/node_modules/.bin/playwright install --list
```

## Backups

Two pieces of state, and the second one is easy to forget:

```bash
# The database
sudo -u www-data sqlite3 /var/www/bobbyai/database/database.sqlite \
    ".backup '/var/backups/bobbyai-$(date +%F).sqlite'"

# The browser profiles — these ARE the LinkedIn sessions
sudo tar czf /var/backups/profiles-$(date +%F).tar.gz \
    -C /var/www/bobbyai/storage/app/private browser-profiles
```

Use `.backup` rather than copying the file, which is unsafe under WAL. Losing
`browser-profiles` does not lose data, but every account must be reconnected by
hand, so back it up and treat it as a credential: it grants LinkedIn access
without a password.

## Troubleshooting

| Symptom | Cause |
| --- | --- |
| `cURL error 7 … port 4571` | Sidecar down. `systemctl status bobbyai-sidecar` |
| Approved posts never publish | Worker down, or cron missing |
| Runs die around the timeout | CPU inference exceeding budget — shrink the model or raise the timeouts as a set |
| OOM kills mid-run | Model too large alongside Chromium. Shrink it; do not solve with swap |
| Runs fail with unusable actions | Model misreading the page. `AGENT_PERCEPTION=text` if not already |
| Account keeps needing reconnection | Profile directory unwritable by `www-data`, or being wiped by a deploy |
| Drafts appear all at once after a pause, never word by word | Reverb down or `/app` not proxied. Drafting is unaffected; this is the fallback reload doing its job |
| Browser console shows a websocket handshake failure | `VITE_REVERB_*` compiled from stale values — rebuild the bundle, do not just restart Reverb |
| `browserType.launchPersistentContext` says the Chromium executable is missing | The Playwright package was installed or upgraded without installing its matching browser binary, or the sidecar and installer use different caches. Run the browser install commands from [Application setup](#application-setup) as `www-data`, confirm the `PLAYWRIGHT_BROWSERS_PATH` systemd environment, then restart `bobbyai-sidecar` |

Application logs go to `storage/logs`. `agent:doctor` remains the fastest triage
for anything agent-related.

## What this setup does not give you

Stated plainly so it is a decision rather than a surprise:

- **No redundancy.** One host, one worker. Losing the box loses the service until
  it is rebuilt, and browser profiles come back only from backup.
- **No horizontal scale.** Throughput is bounded by one CPU-bound model call at a
  time.
- **Web and inference contend.** A run in progress makes the studio slower. If
  that becomes unacceptable, the first change to make is moving Ollama to its own
  host and pointing `OLLAMA_URL` at it over a private network — the app needs no
  code change for that.
