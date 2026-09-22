# ApexNode — Game Server Control Panel

**Original problem statement**: PHP-based game server installer panel (Pterodactyl / MCSManager style) with DB, theme customizer, mobile app integration, Discord bot, installable on Linux.

## User choices
- PHP + MariaDB + Redis; Minecraft Java/Bedrock, CS2, Rust
- Discord bot: status + start/stop/restart; PWA mobile shell
- Later iterations: File Manager, Real Daemon Bridge, Egg Marketplace, Scheduled Backups, Server Installation chooser, Live Modpack Resolver (Modrinth + CurseForge), **Background Job Queue** (this iteration)

## Architecture
- PHP 8.2 panel on port 3000 (supervisor `php-panel`, PHP built-in server)
- MariaDB 10.11 (`apexnode / apex_local_dev`), Redis 7
- `apex-daemon` FastAPI on 127.0.0.1:8001 — real process manager + modpack resolver + job queue
- `apex-backup` PHP CLI backup runner
- Python discord.py bot (`apexnode-bot`)

## Job Queue (this iteration)
- `jobs` table: `id, kind, target_kind, target_id, status(queued/running/completed/failed/cancelled), progress, total, message, error, payload, timestamps`
- Daemon endpoints: `POST /api/daemon/modpack/install-async/{sid}` (queues + returns `{ok, job_id}` in <1s), `GET /api/daemon/jobs/{id}`, plus the legacy synchronous install endpoint kept for regression tests
- Background worker: `asyncio.create_task(_run_modpack_job)` runs the resolver off the request path; `pack_resolver.install()` accepts a `progress(done, total, message)` callback that updates the job row in real time
- Panel: `/jobs` list page (auto-refresh 2s), `/json/jobs/{id}` and `/json/servers/{sid}/jobs` polling endpoints, live progress panel on server detail page (data-testid `server-jobs-panel`) that auto-reloads the page once a modpack install completes so the "Pack status" pill picks up the new state without manual refresh
- Idempotency: install-async short-circuits with `already_installed=true` for servers whose `modpack_status='installed'`
- Failure path: sets `jobs.status='failed'` with `error`, `servers.status='crashed'`, `servers.modpack_status='failed'`, log line `[modpack] ✗ FAILED`
- Connection hygiene: `_job_exec()` helper wraps every daemon-side write in `try/finally: conn.close()` so long installs no longer leak MySQL connections per progress tick

## Iteration test outcomes
- Iter 1 (v1 features): 34/34 backend
- Iter 2 (Modpack Resolver): 14/14 backend + Playwright live preview
- Iter 3 (bug fixes): 8/8 (status-stuck-installing, ZIP path traversal both closed)
- Iter 4 (Job Queue): 13/13 backend + Playwright E2E, enqueue latency ~570ms

## Implemented across all iterations
Auth (bcrypt, roles, CSRF), Dashboard, Servers CRUD, real daemon start/stop/restart/kill, live console with stdin, Nodes, Users, Theme customizer, Discord bot, Activity log, Install docs + scripts, File Manager, Egg Marketplace (9 eggs), Server Installation chooser (20 loaders), Scheduled Backups (local + S3), **Live Modpack Resolver (Modrinth public API + CurseForge with user's key, cfwidget slug→id fallback) with async job queue**, PWA (manifest, service worker, install banner).

## Prioritized backlog
- **P0**: Real loader runtimes (Paper JAR, Forge installer, Docker) — swap `fake_game.py` for real game processes
- **P1**: Concurrency guard on install-async (reject queueing if a running job already exists for this server)
- **P1**: Symlink rejection in ZIP override extraction (defense-in-depth over current path check)
- **P1**: WebSocket console + metrics (replace 1.5s polling)
- **P1**: Nginx + SSL via Certbot in install.sh, Redis-backed sessions
- **P2**: Per-server ACLs, i18n, Capacitor mobile wrapper

## Credentials
See `/app/memory/test_credentials.md`. Preview: https://69a53a13-fcf8-447b-9ea3-aa05080c4689.preview.emergentagent.com/
