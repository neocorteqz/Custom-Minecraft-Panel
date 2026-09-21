# ApexNode — Game Server Control Panel

**Original problem statement**: Build a PHP-based game server installer panel (Pterodactyl / MCSManager style) with database integration, theme customizer, mobile app integration, Discord bot, installable on Linux.

## User choices & subsequent requests
- PHP + MariaDB + Redis panel; Minecraft Java/Bedrock, CS2, Rust; Discord bot with status + start/stop/restart; PWA mobile shell.
- Iter 2 additions: File Manager, Real Daemon Bridge, Egg Marketplace, Scheduled Backups, Server Installation chooser.
- Iter 3 (current): **Live Modpack Resolver** — real Modrinth + CurseForge integration. User supplied a CurseForge Core API key (stored in `settings.curseforge_api_key`).

## Architecture
- PHP 8.2 panel on port 3000 (supervisor `php-panel`)
- MariaDB 10.11 (`apexnode / apex_local_dev`)
- Redis 7
- `apex-daemon` — FastAPI/uvicorn on 127.0.0.1:8001 — real process manager + modpack resolver
- `apex-backup` — PHP CLI backup runner
- Python discord.py bot

## Modpack Resolver (this iteration)
- **Modrinth**: public v2 API, no key. Resolves slug → project → latest version → downloads primary `.mrpack`, parses `modrinth.index.json`, downloads all listed files, extracts `overrides/` into server root.
- **CurseForge**: authenticated v1 API using the user-supplied key. Because that key's `/mods/search` scope returns 403, the resolver falls back to public `api.cfwidget.com` **only for slug → numeric ID resolution**. Every subsequent call (`/mods/{id}`, `/mods/{id}/files`, `/mods/{id}/files/{fid}/download-url`, actual downloads from CurseForge CDN) uses the real key. FTB packs route through the CurseForge path since FTB hosts on CurseForge.
- Resolver triggers on daemon `start/{id}` for pending servers, plus a manual `POST /servers/{id}/modpack/install` button on the server detail page.
- Live preview endpoint `/json/modpack/preview?source=…&ref=…` powers instant validation in the deploy wizard.
- Path safety: ZIP override extraction rejects `..` components and enforces `target.resolve().relative_to(work_root)` (fixed in iter 3).
- Status flow: `pending → installing → installed` (or `failed` on error). Server operational status is snapshotted before install and restored after success; on failure it becomes `crashed` (fixed in iter 3).

## Implemented (Jan 2026)
- Auth (bcrypt, roles), CSRF, first-run admin bootstrap
- Dashboard, Servers CRUD, Server Detail with real daemon start/stop/restart/kill, live console (stdout + stdin), Nodes, Users, Theme customizer (dynamic `/theme.css`), Discord bot page, Activity log, Install docs + scripts
- **File Manager** at `/servers/{id}/files` (sandboxed under `/var/lib/apexnode/servers/{id}`)
- **Egg Marketplace**: 9 templates
- **Server Installation chooser (`/mods`)**: 20 loaders — Vanilla, Paper, Purpur, Forge, NeoForge, Fabric, Quilt, CurseForge, Modrinth, FTB, PocketMine, Metamod:Source, CounterStrikeSharp, MatchZy, Workshop, Oxide, Carbon
- **Scheduled Backups**: interval, retention, local + S3-compatible (Backblaze/Wasabi), manual "Backup Now", one-click restore, download
- **Modpack Resolver**: Modrinth + CurseForge live preview and real download

## Testing
- Iter 1: 34/34 backend + all critical frontend
- Iter 2 (Modpack Resolver): 14/14 backend + Playwright live-preview UI
- Iter 3 (fixes): 8/8 backend, both regressions closed (status-stuck-installing, ZIP path traversal), plus failure-path verified

## Prioritized backlog
- **P0**: Real Docker/OS shims per loader (Paper JAR, Forge installer)
- **P0**: Background job queue so 100+ MB CurseForge packs don't hold a PHP-FPM worker
- **P1**: WebSocket console + metrics
- **P1**: Nginx + SSL via Certbot in install.sh, Redis-backed sessions & rate limits
- **P1**: Reject symlinks in ZIP overrides (defense-in-depth on top of current path check)
- **P2**: Per-server ACLs / subusers, i18n, Capacitor mobile wrapper

## Credentials & preview
See `/app/memory/test_credentials.md`. Preview URL: https://69a53a13-fcf8-447b-9ea3-aa05080c4689.preview.emergentagent.com/
