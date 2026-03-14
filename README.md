# Sal GTD

A calm, single-user Getting Things Done app. Capture tasks, clarify them into next actions, organize with projects and contexts, and review weekly — all from a clean interface built with Laravel, Vue, and SQLite.

Self-hosted on your own VPS with automatic HTTPS. Installable as a PWA on iOS and Android.

## Deploy (fresh VPS)

Point a domain's DNS to your Ubuntu 24.04 VPS, SSH in as root, and run:

```bash
bash <(curl -fsSL https://raw.githubusercontent.com/salagent44/salgtd/main/deploy.sh)
```

It handles everything — Docker, firewall, HTTPS, database, Caddy — and prompts you for domain, admin credentials, timezone, IP allowlist, and S3 backup config.

## Update

SSH into your VPS and run:

```bash
cd /opt/salgtd && git pull && docker compose up -d --build
```

## Redeploy (new VPS, same data)

1. Back up your SQLite database from the old server's Docker volume
2. Point your domain's DNS to the new VPS IP
3. Run the deploy script (see above) on the new VPS
4. Restore the SQLite file into the `gtd-data` Docker volume at `/data/gtd.sqlite`
5. Restart: `docker compose restart`

## Stack

- **Backend:** Laravel 12, SQLite, PHP 8.3
- **Frontend:** Vue 3, Inertia.js, Tailwind CSS, shadcn-vue
- **Infrastructure:** Docker, Caddy (auto HTTPS), Supervisor
- **Sync:** Lightweight version polling (3s) for cross-device sync
