# ApexNode — Game Server Control Panel

**Original problem statement**: Build a PHP-based game server installer panel (similar to Pterodactyl / MCSManager) with database integration, theme customizer, mobile app integration, Discord bot, that can be installed on Linux servers.

## User choices
- Web-based panel using PHP + MariaDB + Redis; games: Minecraft Java/Bedrock, CS2, Rust
- Discord bot with status + start/stop/restart
- PWA shell for mobile
- Later requests: File Manager, Real Daemon Bridge, Marketplace Eggs, Scheduled Backups, and Server Installation chooser (Paper, Forge, Fabric, CurseForge, Modrinth, CS2 Metamod/CSSharp, Rust Oxide/Carbon)

## Architecture
- **PHP 8.2** panel served by PHP built-in server on port 3000 (Nginx + PHP-FPM in production)
- **MariaDB 10.11** database `apexnode`, user `apexnode / apex_local_dev`
- **Redis 7** for caching / sessions (installed, not yet a hot path)
- **Real ApexNode Daemon**: FastAPI/uvicorn on port 8001. Spawns real subprocesses for each server (currently `fake_game.py` demo binary), streams stdout to `server_logs`, exposes `/api/daemon/start/stop/restart/status/console/health`
- **Backup runner**: PHP CLI script under supervisor, tars `/var/lib/apexnode/servers/{id}/` on interval, optional AWS/B2 S3 upload, retention enforcement
- **Discord bot**: Python (discord.py) with slash commands
- **PWA**: manifest + service worker + install banner

## Implemented (Jan 2026)
- Session-based auth (bcrypt), roles admin/operator/viewer, CSRF everywhere, first-run admin bootstrap
- Dashboard, Servers, Server Detail, Nodes, Users, Theme customizer (accent/radius/density/font/mode with dynamic `/theme.css`), Discord Bot integration + token verification, Activity log, Install docs (`/install.sh`, `/install-daemon.sh`)
- **Real daemon lifecycle**: start/stop/restart/kill dispatch to `apex-daemon` service, live stdout streaming, real `console` stdin (send `say`, `list`, `stop`, `help` to running process)
- **File Manager**: real filesystem-backed browser at `/servers/{id}/files`, folder navigation, edit files ≤512KB inline (Ctrl+S save), new file, new folder, upload, delete, download; all path operations sandboxed via `realpath` inside `/var/lib/apexnode/servers/{id}/`
- **Egg Marketplace**: 9 templates (Vanilla/Paper/Forge Minecraft, Bedrock, CS2 Competitive/DM/Retakes, Rust Vanilla/PvE), one-tap deploy, download counter, per-egg files seeded on first daemon start
- **Server Installation chooser** (`/mods`): 20 curated loaders/modpack sources — Vanilla, Paper, Purpur, Forge, NeoForge, Fabric, Quilt, CurseForge, Modrinth, FTB for MC Java; PocketMine-MP for Bedrock; Metamod:Source, CounterStrikeSharp, MatchZy, Workshop bundle for CS2; Oxide/uMod and Carbon for Rust; wizard live-swaps per selected game; modpack-source loaders require a slug/ID (CurseForge, Modrinth, FTB, Workshop)
- **Scheduled Backups**: per-server schedule form (interval min, retention count, local disk or S3-compatible remote with endpoint/bucket/keys), manual "Backup Now", one-click restore (tar-extract into work_dir), download, delete
- Seed data: 5 sample servers, 3 nodes, 20 loaders, 9 eggs, 2 users

## Directory
```
/app/panel
├── public/router.php + assets
├── app/DB.php, helpers.php, Controllers/{Auth,Home,Dashboard,Servers,Nodes,Users,Theme,Discord,Activity,Install,Pwa,Eggs,Files,Backups,Mods}.php
├── views/{auth,dashboard,servers,nodes,users,theme,discord,activity,install,errors,eggs,files,backups,mods}
├── db/schema.sql, schema_v2.sql, schema_v3.sql, seed.php, seed_v2.php, seed_v3.php
├── daemon/daemon.py, fake_game.py, requirements.txt      # real process manager
├── scripts/backup_runner.php                             # scheduled backups
├── discord-bot/bot.py, requirements.txt
└── install/install.sh, install-daemon.sh
```

## Prioritized backlog (P0/P1/P2)
- **P0**: Real Docker/OS process shims per loader (Paper JAR, Forge installer, Docker images from eggs)
- **P0**: Live CurseForge / Modrinth manifest resolvers to actually download modpacks
- **P1**: WebSocket for console + metrics (replace 1.5s poll)
- **P1**: Nginx + SSL via Certbot in `install.sh`, Redis-backed sessions & rate limits
- **P1**: Per-server ACLs / subusers
- **P2**: i18n, marketplace publishing flow, Capacitor mobile wrapper of PWA

## Credentials & preview
See `/app/memory/test_credentials.md`. Preview URL: https://69a53a13-fcf8-447b-9ea3-aa05080c4689.preview.emergentagent.com/
