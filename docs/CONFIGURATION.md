# Configuration — environment variables

Every setting is read from the environment (real env vars win over `.env` — see
`src/Support/Env`). Nothing is hardcoded; defaults live in
[config/config.php](../config/config.php). Copy `deploy/env.production.example`
(Docker) or `.env.example` (local) and fill in the values below.

> **Never commit `.env`.** It is git-ignored. Secrets (DB password, SMTP password)
> belong only in the server's `.env` / the Coolify secret store.

## Application

| Variable | Default | Notes |
|----------|---------|-------|
| `APP_NAME` | `Gestionale Muratori` | Shown in the UI title and used as the mail "from" name / company name fallback. |
| `APP_ENV` | `local` | Set to `production` in prod. Hides the demo credentials on the login page. |
| `APP_URL` | `http://localhost` | Public URL. Drives the `Secure` cookie flag when `SESSION_SECURE` is unset, and the SMTP EHLO host. |
| `APP_DEBUG` | `false` | **Keep `false` in production** (stack traces are logged, never shown). `true` only for local dev. |
| `APP_TIMEZONE` | `Europe/Rome` | Timezone for scheduling ("today" lists) and timestamps. |

## Database

| Variable | Default | Notes |
|----------|---------|-------|
| `DB_HOST` | `127.0.0.1` | `db` inside the Docker stack. |
| `DB_PORT` | `3306` | |
| `DB_NAME` | `gestionale_muratori` | |
| `DB_USER` | `root` | Use a dedicated, least-privilege user in production. |
| `DB_PASS` | *(empty)* | **Required in production.** |
| `DB_ROOT_PASS` | — | Docker-compose only (MySQL root password for the container). |

## Session & authentication

| Variable | Default | Notes |
|----------|---------|-------|
| `SESSION_SECURE` | auto (from `APP_URL` scheme) | Force the `Secure` cookie flag. Set `true` behind HTTPS, `false` for a plain-HTTP test. |
| `SESSION_IDLE_TIMEOUT` | `28800` | Idle logout, seconds (8 h). |
| `LOGIN_MAX_ATTEMPTS` | `5` | Failed logins per email before a temporary block. |
| `LOGIN_MAX_ATTEMPTS_IP` | `20` | Failed logins per IP before a block. |
| `LOGIN_WINDOW_MINUTES` | `15` | Sliding window for the two limits above. |

## Storage & inventory

| Variable | Default | Notes |
|----------|---------|-------|
| `UPLOADS_PATH` | `storage/uploads` | Where photos/signatures/documents are written. Mount a **persistent** volume here in production. |
| `PDF_TEMP_PATH` | `storage/tmp/mpdf` | mPDF scratch space (font cache + image temp). Must be writable by the web-server user. Created automatically; safe to be ephemeral. Only override if `storage/` isn't writable. |
| `ALLOW_NEGATIVE_STOCK` | `false` | When `true`, reservations/transfers may drive stock negative (off by default). |

## Observability & alerting

Uncaught 500s are always written to the PHP error log as one structured JSON line
prefixed `gm ` (production logs to container stderr, so `docker logs`/Coolify show
them). Each request carries a short reference id shown to the user on the error
page and logged, so a user report maps straight to a log line.

| Variable | Default | Notes |
|----------|---------|-------|
| `ALERT_WEBHOOK_URL` | *(empty)* | When set, uncaught 500s best-effort POST a compact `{ "text": … }` payload (Slack/Discord/Teams-compatible incoming webhook). Off by default; structured logging happens regardless. |
| `ALERT_MIN_INTERVAL` | `300` | Per-error-signature throttle (seconds) so one recurring fault can't flood the alert channel. `0` disables throttling. |

## Company identity (PDF headers) — *new*

Printed on invoice / quote / S.A.L. PDFs. Previously blank; now env-driven.

| Variable | Default | Notes |
|----------|---------|-------|
| `COMPANY_NAME` | `APP_NAME` | Contractor legal name. |
| `COMPANY_ADDRESS` | *(empty)* | Street, city. |
| `COMPANY_VAT` | *(empty)* | P.IVA / Codice Fiscale. |
| `COMPANY_PHONE` | *(empty)* | |
| `COMPANY_EMAIL` | *(empty)* | |

## Scheduled automation — *new*

Run `php scripts/scheduler.php` daily (cron). See [DEPLOYMENT.md](DEPLOYMENT.md) §Scheduler.

| Variable | Default | Notes |
|----------|---------|-------|
| `SCHEDULER_COMPLIANCE_DAYS` | `30` | Alert on compliance documents expiring within this many days (or already expired). |

## E-mail (alert digests) — *new, disabled by default*

The platform ships without an SMTP account. Leave `MAIL_ENABLED=false` and the
in-app notification bell still works; set the variables below to also e-mail admins
a digest of new alerts when the scheduler runs.

| Variable | Default | Notes |
|----------|---------|-------|
| `MAIL_ENABLED` | `false` | Master switch. |
| `MAIL_TRANSPORT` | `smtp` | `smtp` (built-in STARTTLS/SSL client) or `mail` (PHP `mail()`, needs a host MTA). |
| `MAIL_HOST` | *(empty)* | SMTP server (e.g. `smtp.example.com`). |
| `MAIL_PORT` | `587` | `587` for STARTTLS, `465` for implicit SSL. |
| `MAIL_USERNAME` | *(empty)* | Omit for an unauthenticated relay. |
| `MAIL_PASSWORD` | *(empty)* | **Secret.** |
| `MAIL_ENCRYPTION` | `tls` | `tls` (STARTTLS), `ssl` (implicit), or empty (none). |
| `MAIL_FROM_ADDRESS` | `no-reply@localhost` | Envelope/from address. |
| `MAIL_FROM_NAME` | `APP_NAME` | Display name. |
| `MAIL_TIMEOUT` | `10` | Socket timeout, seconds. |
| `MAIL_DIGEST_RECIPIENTS` | *(empty)* | Comma-separated override; empty = every active admin's address. |

## Weather auto-fill (Giornale dei Lavori)

| Variable | Default | Notes |
|----------|---------|-------|
| `WEATHER_ENABLED` | `true` | Open-Meteo lookup on daily-log creation. Set `false` for air-gapped installs (and in tests). |
| `WEATHER_ENDPOINT` | Open-Meteo forecast URL | Override for a proxy. |
| `WEATHER_TIMEOUT` | `5` | Seconds. |

## Deployment (Docker/Coolify only)

| Variable | Notes |
|----------|-------|
| `APP_DOMAIN` | Caddy site address. A bare `:80` behind Coolify's proxy, or `example.com` for the standalone stack (automatic HTTPS). |

## Minimal production example

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://muratori.example.com
APP_DOMAIN=muratori.example.com
SESSION_SECURE=true

DB_HOST=db
DB_NAME=gestionale_muratori
DB_USER=gestionale
DB_PASS=<openssl rand -base64 24>
DB_ROOT_PASS=<openssl rand -base64 24>

COMPANY_NAME=Impresa Edile Rossi S.r.l.
COMPANY_VAT=IT01234567890
COMPANY_ADDRESS=Via Roma 1, Ancona

# Optional: turn on e-mail alert digests
MAIL_ENABLED=true
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=alerts@example.com
MAIL_PASSWORD=<smtp secret>
MAIL_FROM_ADDRESS=alerts@example.com
```
