# Sal GTD

A calm, single-user Getting Things Done app. Capture tasks, clarify them into next actions, organize with projects and contexts, and review weekly — all from a clean interface built with Laravel, Vue, and SQLite.

Self-hosted on your own VPS with automatic HTTPS.

## Deploy

Point a domain to your Ubuntu 24.04 VPS, SSH in as root, and run:

```bash
bash <(curl -fsSL https://raw.githubusercontent.com/salagent44/salgtd/main/deploy.sh)
```

It handles everything — Docker, firewall, HTTPS, database — and prompts you for domain, admin credentials, and optional settings.

To update or change any settings, just run the script again.
