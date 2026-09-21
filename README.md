# ApexNode — Game Server Control Panel

PHP 8.2 + MariaDB + Redis panel for managing Minecraft (Java/Bedrock), Counter-Strike 2, and Rust game servers on Linux hosts. Inspired by Pterodactyl and MCSManager, tuned for tiny VPSs.

## Highlights
- Tactical Command Tower dark UI, live-polling server console, bento dashboards
- Per-user theme customizer (accent color, radius, density, font) with instant CSS variable preview
- Discord bot with `/status /start /stop /restart` slash commands
- PWA — installable on Android, iOS, and desktop
- One-line Linux installer (`install.sh`) provisioning Nginx + PHP-FPM + MariaDB + Redis + systemd units

## Install on a Linux server (Ubuntu 22.04+ / Debian 12+)
```
curl -fsSL https://YOUR.HOST/install.sh | sudo bash
```
Full documentation lives in `/app/memory/PRD.md`.

## Local dev preview
Panel served by PHP built-in server via supervisor:
```
sudo supervisorctl restart php-panel
```
Login with `admin / admin123` (see `/app/memory/test_credentials.md`).
