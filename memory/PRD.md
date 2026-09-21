# ApexNode — Game Server Control Panel

**Original problem statement**: Build a PHP-based game server installer panel (similar to Pterodactyl / MCSManager) with database integration, theme customizer, mobile app integration, Discord bot, that can be installed on Linux servers.

## User choices
- Web-based demo/preview panel (PHP + MariaDB + Redis)
- Games: Minecraft Java, Minecraft Bedrock, Counter-Strike 2, Rust
- Discord bot: server status + start/stop/restart commands
- Mobile: PWA shell
- Clean efficient coding, runs on small VPS → dedicated

## Architecture
- **Backend**: PHP 8.2 (built-in server on port 3000 for preview; Nginx + PHP-FPM in production)
- **DB**: MariaDB 10.11 (`apexnode` database, user `apexnode` / `apex_local_dev`)
- **Cache**: Redis 7 (session/cache-ready, not yet used for hot paths)
- **Discord bot**: Python 3 + discord.py, systemd unit (`apexnode-bot.service`)
- **Node daemon**: Python 3 heartbeat + Docker for game runners (`apexnode-daemon.service`)
- **PWA**: manifest + service worker; installs as an app on Android/iOS/Desktop

## Directory structure
```
/app/panel
├── public/             # web root
│   ├── router.php      # single entry point router
│   ├── assets/         # css, js, icons
│   └── favicon.svg
├── app/                # controllers, helpers, DB layer
│   ├── DB.php
│   ├── helpers.php
│   └── Controllers/    # Auth, Dashboard, Servers, Nodes, Users, Theme, Discord, Activity, Install, Pwa, Home
├── views/              # PHP views + layout partials
├── config/config.php
├── db/schema.sql       # 8 tables
├── db/seed.php
├── install/install.sh          # Linux one-liner installer
├── install/install-daemon.sh   # Node daemon installer
└── discord-bot/bot.py, requirements.txt
```

## Implemented (Jan 2026)
- Session-based auth (bcrypt) with first-run admin bootstrap
- Role-based access: admin / operator / viewer
- CSRF protection on all forms + JSON endpoints
- Dashboard: fleet stats (servers, online, nodes, players) + activity feed
- Server list with live status/CPU/RAM/players polling (`/json/servers` every 4s)
- Server detail page with live console (polling `/json/servers/logs`), start/stop/restart/kill/delete, resource meters, config
- Console command input → recorded to server_logs + faked responses (`say`, `list`, `help`)
- Server creation wizard (game, node, port, CPU, RAM, disk)
- Node management (register/delete)
- User management (invite/delete)
- Theme customizer: accent color, radius, density, font, dark/light — live CSS variable preview + per-user DB persistence + dynamic `/theme.css`
- Discord bot settings page + Discord API token verification (`GET /users/@me`)
- Activity log page
- PWA: manifest + service worker + install banner
- Install scripts served from panel (`/install.sh`, `/install-daemon.sh`)
- Seed data: 2 users, 3 nodes, 5 sample servers with initial logs

## Design language
Tactical Command Tower — dark background (#090A0F), electric-cyan accent (#00F0FF) with cyber-violet secondary, JetBrains Mono captions, Outfit / Plus Jakarta Sans body, bento grid layouts, glowing status pills, streaming terminal panel.

## Prioritized backlog
- **P0**: Real daemon protocol (WebSocket → Docker exec) replacing simulated state
- **P0**: Fine-grained RBAC (per-server ACLs)
- **P1**: Backups (S3/B2), scheduled tasks, subusers
- **P1**: File manager (SFTP or in-browser edit)
- **P1**: SSL setup step in `install.sh` (Certbot)
- **P1**: Redis-backed session store and rate limits on auth
- **P2**: Multi-language i18n
- **P2**: Marketplace of game egg templates
- **P2**: Native mobile wrapper (Capacitor build of PWA)

## Test credentials
See `/app/memory/test_credentials.md`
