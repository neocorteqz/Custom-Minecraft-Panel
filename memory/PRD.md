# ApexNode — Game Server Control Panel

**Original problem statement**: PHP-based game server installer panel (Pterodactyl / MCSManager style) with DB, theme customizer, mobile app integration, Discord bot, installable on Linux.

## Architecture
- PHP 8.2 panel on port 3000 (supervisor `php-panel`)
- MariaDB 10.11 (`apexnode / apex_local_dev`), Redis 7, OpenJDK 17
- `apex-daemon` FastAPI on 127.0.0.1:8001 — real process manager, modpack resolver, job queue, cancellation
- `apex-backup` PHP CLI backup runner
- Python discord.py bot

## Iteration 5 — Shipped
- **Existing-web-panel compatibility**: `install.sh --port N --coexist standalone|cpanel|plesk|directadmin|nginx`. Loopback-bound nginx in coexist modes. `/install` page is a live builder — pick host layout + port and the install one-liner + reverse-proxy snippet regenerate on the fly. Snippets: cPanel WHM Pre-VirtualHost, Plesk "Additional nginx directives", DirectAdmin CustomBuild `custom_httpd.conf`, generic Nginx. New `settings` rows: `panel_port`, `panel_url_path`, `coexist_mode` (seeded via `schema_v5.sql`).
- **Job cancellation**: `POST /api/daemon/jobs/{id}/cancel` flips `jobs.cancel_requested=1` and signals a per-job `CancelToken` that the resolver polls between file downloads (chunk-level too). PHP proxy at `POST /jobs/{id}/cancel` (role: operator). Live `/jobs` page hides the cancel button when the status becomes terminal. Idempotent: cancel-on-finished returns `already_finished=true`.
- **Concurrency guard**: `install-async` checks for an existing `queued|running` modpack_install for the same server and returns the existing job_id with `already_running=true` instead of enqueueing a second.
- **Real loader runtimes**: `daemon/runtime.py` — `resolve(server, loader, log)` returns the actual shell command. Minecraft Java (Vanilla/Paper/Purpur, plus modpacks/Forge/Fabric that fall back to Paper as JVM host) downloads a real Paper JAR via the PaperMC v3 fill API (with UA header + build-walk fallback), writes `eula.txt`, and boots the JVM with `-Xms256M -Xmx{ram-256}M`. Non-Java games (CS2/Rust/Bedrock) remain on `fake_game.py` — those need SteamCMD + multi-GB downloads and don't fit a demo panel; production hosts can wire a real command via the loader's `start_command` DB row.

## Test outcomes
- Iter 1: 34/34 backend + all critical frontend
- Iter 2 (Modpack Resolver): 14/14 backend + Playwright
- Iter 3 (regressions): 8/8
- Iter 4 (Job Queue): 13/13 + Playwright, enqueue ~570ms
- **Iter 5**: 18/18 backend + Playwright /install & /jobs, zero functional bugs, 11 hardening notes (2 UX polishes applied)

## Implemented (all iterations)
Auth (bcrypt, roles, CSRF), Dashboard, Servers CRUD, real daemon start/stop/restart/kill + console stdin, Nodes, Users, Theme customizer, Discord bot, Activity log, Install docs + scripts, File Manager, Egg Marketplace (9), Installation chooser (20 loaders), Scheduled Backups, Live Modpack Resolver (Modrinth + CurseForge + cfwidget slug lookup), Background Job Queue, Job Cancellation, Concurrency Guard, Real Java Runtime (Paper), Coexistence installer, PWA.

## Prioritized backlog
- **P1**: Verify Paper JAR SHA256 checksum (Paper API returns it)
- **P1**: Surface runtime bootstrap failure as `status=crashed` instead of silent fake_game fallback
- **P1**: Shared-secret header on daemon endpoints
- **P1**: Per-server Minecraft version picker (currently pinned to 1.20.4)
- **P1**: Dedicated CSS class for `status-cancelled` (currently reuses `status-offline`)
- **P2**: TOCTOU-safe concurrency guard (transactional SELECT FOR UPDATE)
- **P2**: SteamCMD-backed real CS2 / Rust / Bedrock runtimes for production hosts

## Credentials
See `/app/memory/test_credentials.md`. Preview: https://69a53a13-fcf8-447b-9ea3-aa05080c4689.preview.emergentagent.com/
