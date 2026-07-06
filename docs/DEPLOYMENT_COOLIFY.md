# Deployment on Coolify (Hetzner)

Deploy Gestionale Muratori as a Docker-Compose application on a
[Coolify](https://coolify.io) instance (typically a Hetzner VPS). Coolify's
built-in Traefik proxy terminates TLS for your domain and forwards traffic to the
app's Caddy container; you only manage environment variables and the git push.

For the plain Docker (no Coolify) path, see [DEPLOYMENT.md](DEPLOYMENT.md).

## What runs

The compose file [`docker-compose.coolify.yml`](../docker-compose.coolify.yml)
defines three services:

| Service | Image / build | Role |
|---------|---------------|------|
| `db`  | `mysql:8.0` | Database (named volume `db_data`) |
| `app` | `deploy/Dockerfile` (PHP 8.2-FPM + gd/pdo_mysql/zip/curl) | Application (uploads on volume `uploads`) |
| `web` | `deploy/Dockerfile.web` (Caddy, `Caddyfile` + `public/` baked in) | Static files + reverse proxy to PHP-FPM |

Persistent state lives on named volumes (`db_data`, `uploads`, `caddy_data`,
`caddy_config`) so redeploys never lose the database or uploaded photos/signatures/S.A.L. PDFs.

## Prerequisites

- A running Coolify instance on a server with a public IP.
- A domain (or subdomain) with an `A` record pointing at that server, e.g.
  `muratori.example.com`.
- This repository reachable by Coolify (GitHub/GitLab app, or a deploy key).

## 1. Create the resource

1. In Coolify: **+ New → Resource → Docker Compose** (Build Pack: *Docker Compose*).
2. Connect this repository and pick the branch to deploy.
3. Set the **Compose file path** to `/docker-compose.coolify.yml`.

## 2. Environment variables

Add these under the resource's **Environment Variables** (mark the passwords as
*secret*). They feed both compose and the application:

```
APP_URL=https://muratori.example.com     # must match the attached domain, https
APP_NAME=Gestionale Muratori
APP_TIMEZONE=Europe/Rome

DB_NAME=gestionale_muratori
DB_USER=gestionale
DB_PASS=<openssl rand -base64 24>
DB_ROOT_PASS=<openssl rand -base64 24>

# Optional (sensible defaults shown)
ALLOW_NEGATIVE_STOCK=false
WEATHER_ENABLED=true          # Giornale dei Lavori weather auto-fill (Open-Meteo)
WEATHER_TIMEOUT=5
```

`APP_DEBUG` is forced to `false` and `SESSION_SECURE` to `true` by the compose
file — do not override them in production.

> **Outbound HTTPS**: the Giornale dei Lavori auto-fills weather from
> `api.open-meteo.com`. The app container ships with `ca-certificates`, so no extra
> setup is needed. If the server blocks egress, set `WEATHER_ENABLED=false` and the
> daily log falls back to manual weather entry.

## 3. Attach the domain

Attach `https://muratori.example.com` to the **`web`** service (port 80). Coolify
provisions the certificate and routes the domain to Caddy. The `web` service
publishes **no** host ports on purpose — Coolify's proxy owns 80/443.

## 4. Deploy

Click **Deploy**. Coolify builds the `app` and `web` images and starts the stack.
Watch the logs until `db` is healthy and `app`/`web` are running.

## 5. First-run: migrate and create an admin

Open a shell into the `app` container (Coolify: **Terminal / Execute Command**) and run:

```bash
php database/migrate.php
php scripts/create-admin.php "Nome Cognome" admin@example.com 'strong-password'
```

`migrate.php` is idempotent — it applies migrations `001`–`009` (all the v2 tables:
stock locations, subcontractors, attendance, daily logs, S.A.L., compliance, photo
geo) and is safe to re-run on every deploy. **Do not run `database/seed.php` in
production** — it truncates all tables and loads demo data.

Then open `https://muratori.example.com` and log in.

## 6. Updates

Push to the deployed branch (or click **Redeploy**). Coolify rebuilds and restarts;
volumes persist. After a deploy that adds migrations, run `php database/migrate.php`
in the `app` container again (new files only are applied).

## 7. Backups

The database and uploads are the only stateful pieces. Back them up from the host:

```bash
# database
docker compose exec -T db mysqldump -u root -p"$DB_ROOT_PASS" "$DB_NAME" > backup-$(date +%F).sql
# uploads (photos, signatures, S.A.L. PDFs)
docker run --rm -v <project>_uploads:/data -v "$PWD":/out alpine \
    tar czf /out/uploads-$(date +%F).tar.gz -C /data .
```

See [`scripts/backup.sh`](../scripts/backup.sh) for an automatable version and
[DEPLOYMENT.md](DEPLOYMENT.md) for the restore procedure.

## Health check

`GET /health` returns `{"ok":true,"data":{"status":"ok"}}` when the app and DB are
up — point Coolify's health check (or an external monitor) at it.
