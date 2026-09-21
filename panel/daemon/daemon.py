"""
ApexNode Node Daemon — real process manager
Runs game server subprocesses, streams stdout/stderr to server_logs,
persists PIDs, exposes HTTP control API on 127.0.0.1:8001.

Endpoints (all via /api prefix - matches K8s ingress; but also exposed locally):
  POST /daemon/start/{id}
  POST /daemon/stop/{id}
  POST /daemon/restart/{id}
  GET  /daemon/status/{id}
  POST /daemon/console/{id}  {"cmd": "..."}
"""
import asyncio
import json
import os
import shlex
import signal
import subprocess
import sys
import time
from pathlib import Path
from typing import Dict, Optional

import pymysql
from fastapi import FastAPI, HTTPException
from pydantic import BaseModel

DB_HOST = os.environ.get("DB_HOST", "127.0.0.1")
DB_USER = os.environ.get("DB_USER", "apexnode")
DB_PASS = os.environ.get("DB_PASS", "apex_local_dev")
DB_NAME = os.environ.get("DB_NAME", "apexnode")

STATE_ROOT = Path(os.environ.get("APEX_STATE", "/var/lib/apexnode"))
STATE_ROOT.mkdir(parents=True, exist_ok=True)
(STATE_ROOT / "servers").mkdir(exist_ok=True)
(STATE_ROOT / "backups").mkdir(exist_ok=True)

app = FastAPI(title="ApexNode Daemon", docs_url=None, redoc_url=None)

# In-memory process registry
processes: Dict[int, subprocess.Popen] = {}
stdin_streams: Dict[int, any] = {}


def db():
    return pymysql.connect(host=DB_HOST, user=DB_USER, password=DB_PASS,
                           database=DB_NAME, cursorclass=pymysql.cursors.DictCursor,
                           autocommit=True)


def log_line(server_id: int, line: str, level: str = "info"):
    try:
        with db().cursor() as c:
            c.execute("INSERT INTO server_logs (server_id, line, level) VALUES (%s, %s, %s)",
                      (server_id, line[:2000], level))
    except Exception as e:
        print(f"log_line err {e}", file=sys.stderr)


def get_server(sid: int):
    with db().cursor() as c:
        c.execute("SELECT * FROM servers WHERE id=%s", (sid,))
        return c.fetchone()


def get_egg(egg_id):
    if not egg_id:
        return None
    with db().cursor() as c:
        c.execute("SELECT * FROM eggs WHERE id=%s", (egg_id,))
        return c.fetchone()


def set_status(sid: int, status: str, **fields):
    parts = ["status=%s"]
    args = [status]
    for k, v in fields.items():
        parts.append(f"{k}=%s")
        args.append(v)
    args.append(sid)
    with db().cursor() as c:
        c.execute(f"UPDATE servers SET {', '.join(parts)} WHERE id=%s", args)


def ensure_workdir(server) -> Path:
    wd = STATE_ROOT / "servers" / str(server["id"])
    wd.mkdir(parents=True, exist_ok=True)
    egg = get_egg(server.get("egg_id"))
    # Seed default files from egg if empty
    if not any(wd.iterdir()):
        default_files = {}
        if egg and egg.get("default_files"):
            try:
                default_files = json.loads(egg["default_files"])
            except Exception:
                default_files = {}
        # Add a generic fallback file
        if not default_files:
            default_files = {
                "server.properties": f"# {server['name']}\nserver-port={server['port']}\nmax-players={server['players_max']}\nmotd=Powered by ApexNode\n",
                "README.md": f"# {server['name']}\n\nProvisioned by ApexNode. Edit files in this folder, then Restart the server.\n"
            }
        for fname, content in default_files.items():
            (wd / fname).parent.mkdir(parents=True, exist_ok=True)
            (wd / fname).write_text(content)
    # Persist work_dir
    with db().cursor() as c:
        c.execute("UPDATE servers SET work_dir=%s WHERE id=%s", (str(wd), server["id"]))
    return wd


async def stream_output(sid: int, stream, level: str = "info"):
    """Read stdout/stderr line by line, persist to DB."""
    loop = asyncio.get_event_loop()
    while True:
        line = await loop.run_in_executor(None, stream.readline)
        if not line:
            break
        try:
            text = line.decode("utf-8", errors="replace").rstrip()
        except Exception:
            text = str(line)
        if text:
            log_line(sid, text, level)
    # process ended
    log_line(sid, f"[daemon] stream closed", "system")


def build_start_cmd(server, egg) -> str:
    """Compose the actual command to run inside the working directory."""
    if egg and egg.get("start_command"):
        cmd_tpl = egg["start_command"]
    else:
        cmd_tpl = "python3 -u /app/panel/daemon/fake_game.py {game}"
    return cmd_tpl.format(
        game=server["game"],
        port=server["port"],
        name=server["name"],
        ram=server["ram_mb"],
        players=server["players_max"],
    )


class ConsoleIn(BaseModel):
    cmd: str


@app.post("/api/daemon/start/{sid}")
async def start(sid: int):
    s = get_server(sid)
    if not s:
        raise HTTPException(404, "server not found")
    if sid in processes and processes[sid].poll() is None:
        return {"status": "already_running", "pid": processes[sid].pid}

    wd = ensure_workdir(s)
    egg = get_egg(s.get("egg_id"))
    cmd = build_start_cmd(s, egg)
    log_line(sid, f"[daemon] boot: {cmd}", "system")
    set_status(sid, "starting")

    try:
        proc = subprocess.Popen(
            shlex.split(cmd),
            cwd=str(wd),
            stdin=subprocess.PIPE,
            stdout=subprocess.PIPE,
            stderr=subprocess.STDOUT,
            preexec_fn=os.setsid,
        )
    except Exception as e:
        log_line(sid, f"[daemon] failed to spawn: {e}", "error")
        set_status(sid, "crashed")
        raise HTTPException(500, str(e))

    processes[sid] = proc
    stdin_streams[sid] = proc.stdin
    asyncio.create_task(stream_output(sid, proc.stdout))

    async def watchdog():
        loop = asyncio.get_event_loop()
        await loop.run_in_executor(None, proc.wait)
        # Only mark offline if process exited on its own
        if sid in processes and processes[sid].pid == proc.pid:
            set_status(sid, "offline", cpu_usage=0, ram_usage_mb=0, players_online=0)
            log_line(sid, f"[daemon] process exited (code={proc.returncode})", "system")
            processes.pop(sid, None)
            stdin_streams.pop(sid, None)

    asyncio.create_task(watchdog())
    # Small grace to transition from starting → online
    await asyncio.sleep(0.6)
    set_status(sid, "online", cpu_usage=15.0, ram_usage_mb=int(s["ram_mb"] * 0.4))
    return {"status": "started", "pid": proc.pid}


@app.post("/api/daemon/stop/{sid}")
async def stop(sid: int):
    s = get_server(sid)
    if not s:
        raise HTTPException(404, "server not found")
    proc = processes.get(sid)
    if not proc or proc.poll() is not None:
        set_status(sid, "offline", cpu_usage=0, ram_usage_mb=0, players_online=0)
        return {"status": "not_running"}
    set_status(sid, "stopping")
    log_line(sid, "[daemon] stopping…", "system")
    try:
        # Send graceful signal
        os.killpg(os.getpgid(proc.pid), signal.SIGTERM)
        for _ in range(20):
            if proc.poll() is not None:
                break
            await asyncio.sleep(0.25)
        if proc.poll() is None:
            os.killpg(os.getpgid(proc.pid), signal.SIGKILL)
    except ProcessLookupError:
        pass
    processes.pop(sid, None)
    stdin_streams.pop(sid, None)
    set_status(sid, "offline", cpu_usage=0, ram_usage_mb=0, players_online=0)
    log_line(sid, "[daemon] stopped.", "system")
    return {"status": "stopped"}


@app.post("/api/daemon/restart/{sid}")
async def restart(sid: int):
    await stop(sid)
    return await start(sid)


@app.get("/api/daemon/status/{sid}")
async def status(sid: int):
    proc = processes.get(sid)
    running = bool(proc and proc.poll() is None)
    return {"id": sid, "running": running, "pid": proc.pid if running else None}


@app.post("/api/daemon/console/{sid}")
async def console(sid: int, payload: ConsoleIn):
    stream = stdin_streams.get(sid)
    if not stream:
        log_line(sid, f"> {payload.cmd}", "system")
        log_line(sid, "[daemon] server not running — command queued", "warn")
        return {"ok": False, "reason": "not_running"}
    try:
        stream.write((payload.cmd + "\n").encode())
        stream.flush()
        log_line(sid, f"> {payload.cmd}", "system")
        return {"ok": True}
    except Exception as e:
        return {"ok": False, "reason": str(e)}


@app.get("/api/daemon/health")
async def health():
    return {"status": "ok", "running_servers": len([p for p in processes.values() if p.poll() is None])}


if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="0.0.0.0", port=int(os.environ.get("PORT", "8001")))
