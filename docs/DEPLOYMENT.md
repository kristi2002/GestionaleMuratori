# Deployment guide — Hetzner Cloud

Target: a single Hetzner Cloud server running the Docker stack in
[docker-compose.yml](../docker-compose.yml): **Caddy** (automatic HTTPS) →
**PHP-FPM 8.2** app → **MySQL 8**, with named volumes for the database, the
uploaded photos/signatures, and TLS material.

A CX22 (2 vCPU / 4 GB) is more than enough for this workload; CX32 gives headroom.

## 1. Create the server

1. Hetzner Cloud console → *Add Server*:
   - Image: **Ubuntu 24.04**
   - Type: CX22 (shared) or better
   - Networking: IPv4 + IPv6
   - SSH key: add yours (avoid password login)
   - Backups: enable Hetzner's server backups too if budget allows (belt & suspenders)
2. Note the public IP. If you have a domain, create an **A record**
   (e.g. `gestionale.example.com → <server-ip>`) *before* the first start so
   Let's Encrypt validation succeeds immediately.

## 2. Harden SSH + firewall

```bash
ssh root@<server-ip>

# System updates
apt update && apt -y upgrade

# Firewall: SSH + HTTP/HTTPS only
apt -y install ufw
ufw default deny incoming
ufw default allow outgoing
ufw allow OpenSSH
ufw allow 80/tcp
ufw allow 443/tcp
ufw enable

# Optional but recommended: disable password SSH auth
sed -i 's/^#\?PasswordAuthentication.*/PasswordAuthentication no/' /etc/ssh/sshd_config
systemctl restart ssh
```

You can also add a Hetzner Cloud **Firewall** with the same rules at the
project level (defense in depth).

## 3. Install Docker

```bash
curl -fsSL https://get.docker.com | sh
docker --version && docker compose version
```

## 4. Get the code and configure

```bash
apt -y install git
mkdir -p /opt && cd /opt
git clone <your-repo-url> gestionale
cd gestionale

cp deploy/env.production.example .env
nano .env
```

In `.env` set:

- `APP_DOMAIN` — your domain (automatic HTTPS), or `http://<server-ip>` for a
  test run without TLS (then also set `SESSION_SECURE=false`).
- `APP_URL` — `https://<domain>` (must match).
- `DB_PASS` / `DB_ROOT_PASS` — strong random values (`openssl rand -base64 24`).

## 5. First start

```bash
docker compose up -d --build
docker compose ps                     # wait until db is healthy

# Apply the schema
docker compose exec app php database/migrate.php

# Option A (demo/dev data): seed users, projects, warehouse
docker compose exec app php database/seed.php

# Option B (production): create ONLY the first admin user
docker compose exec app php scripts/create-admin.php "Nome Admin" admin@example.com 'STRONG-password'
```

Open `https://<domain>/` → login page. `https://<domain>/health` must return
`{"ok":true,...}`.

> With the seed data, change every account's password immediately
> (admin → *Utenti* → edit user → new password), or use option B.

## 6. Backups

Nightly dump of the DB + uploads with 14-day rotation:

```bash
crontab -e
# ┌ min  ┌ hour
  30     2  * * *  cd /opt/gestionale && ./scripts/backup.sh >> /var/log/gestionale-backup.log 2>&1
```

Backups land in `/var/backups/gestionale/`. **Copy them off the machine** (e.g.
Hetzner Storage Box via `rclone`/`scp`, or object storage) — a backup on the
same disk is not a backup.

### Restore procedure (tested commands)

```bash
cd /opt/gestionale
# 1. database
gunzip -c /var/backups/gestionale/db-<STAMP>.sql.gz \
  | docker compose exec -T db mysql -u"$DB_USER" -p"$DB_PASS" "$DB_NAME"
# 2. uploads
docker compose exec -T app rm -rf /var/www/app/storage/uploads
cat /var/backups/gestionale/uploads-<STAMP>.tar.gz \
  | docker compose exec -T app tar -C /var/www/app/storage -xzf -
```

## 7. Updates / new releases

```bash
cd /opt/gestionale
git pull
docker compose up -d --build          # rebuilds the app image
docker compose exec app php database/migrate.php   # applies any new migrations
```

Migrations are additive and idempotent (each `database/migrations/*.sql` runs
once, recorded in the `migrations` table).

## 8. Monitoring & logs

- Readiness: `GET /health` → `{"ok":true}` (checks the DB). Point any uptime
  monitor (UptimeRobot, Hetzner's own, …) at it.
- Logs: `docker compose logs -f app` (PHP errors land on stderr),
  `docker compose logs -f web` (access logs), `docker compose logs -f db`.
- Disk usage: photos accumulate in the `uploads` volume —
  `docker system df -v` and the backup sizes tell you when to grow the server.

## 9. PHP limits (already configured)

`deploy/php.ini` sets `upload_max_filesize=12M` / `post_max_size=16M`
(the app itself caps photos at 8 MB and compresses client-side), timezone
`Europe/Rome`, opcache, and stderr logging. Caddy caps request bodies at 16 MB.

## Alternative: bare-metal (no Docker)

Install `php8.2-fpm php8.2-mysql php8.2-gd php8.2-mbstring php8.2-zip`,
MySQL 8, nginx (root = `public/`, front-controller rewrite to `index.php`,
`client_max_body_size 16m`), `composer install --no-dev`, copy
`.env` (`APP_DEBUG=false`, `SESSION_SECURE=true`), run `php database/migrate.php`,
point a certbot-managed vhost at it. The Docker path above is recommended:
it is what the test suite and this guide validate.

## Security checklist before go-live

- [ ] `APP_DEBUG=false`, `SESSION_SECURE=true`, HTTPS working (padlock, HSTS)
- [ ] Strong, unique `DB_PASS` / `DB_ROOT_PASS`
- [ ] Seed passwords changed or seed skipped (create-admin script)
- [ ] `ufw` (and/or Hetzner firewall) active: 22/80/443 only
- [ ] Nightly cron backup + off-site copy verified with a test **restore**
- [ ] `/health` monitored
