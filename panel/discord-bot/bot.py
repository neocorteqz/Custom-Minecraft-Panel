"""
ApexNode Discord Bot
Slash commands: /status /start /stop /restart
Reads token + panel API endpoint from environment or panel settings via DB.
"""
import os
import sys
import asyncio
from typing import Optional

try:
    import discord
    from discord import app_commands
except ImportError:
    print("Install: pip install -r requirements.txt", file=sys.stderr)
    sys.exit(1)

import pymysql

DB_HOST = os.environ.get("DB_HOST", "127.0.0.1")
DB_USER = os.environ.get("DB_USER", "root")
DB_PASS = os.environ.get("DB_PASS", "")
DB_NAME = os.environ.get("DB_NAME", "apexnode")
TOKEN   = os.environ.get("DISCORD_BOT_TOKEN")


def db():
    return pymysql.connect(host=DB_HOST, user=DB_USER, password=DB_PASS,
                           database=DB_NAME, cursorclass=pymysql.cursors.DictCursor,
                           autocommit=True)


def load_settings():
    global TOKEN
    try:
        with db().cursor() as c:
            c.execute("SELECT k, v FROM settings WHERE k IN ('discord_token','discord_channel')")
            for row in c.fetchall():
                if row["k"] == "discord_token" and not TOKEN:
                    TOKEN = row["v"]
    except Exception as e:
        print("settings load error:", e)


def find_server(name: str):
    with db().cursor() as c:
        c.execute("SELECT * FROM servers WHERE name=%s LIMIT 1", (name,))
        return c.fetchone()


def set_status(server_id: int, status: str, reason: str):
    with db().cursor() as c:
        c.execute("UPDATE servers SET status=%s WHERE id=%s", (status, server_id))
        c.execute("INSERT INTO server_logs (server_id, line, level) VALUES (%s,%s,'system')",
                  (server_id, f"[discord] {reason}"))


intents = discord.Intents.default()
bot = discord.Client(intents=intents)
tree = app_commands.CommandTree(bot)


@tree.command(name="status", description="Show status of a game server")
@app_commands.describe(server="Server name")
async def status(interaction: discord.Interaction, server: str):
    s = find_server(server)
    if not s:
        await interaction.response.send_message(f"❌ Server `{server}` not found.", ephemeral=True); return
    e = discord.Embed(title=f"▶ {s['name']}", color=0x00F0FF)
    e.add_field(name="Game", value=s['game'])
    e.add_field(name="Status", value=s['status'].upper())
    e.add_field(name="Players", value=f"{s['players_online']}/{s['players_max']}")
    e.add_field(name="CPU", value=f"{round(float(s['cpu_usage']))}%")
    e.add_field(name="RAM", value=f"{s['ram_usage_mb']} / {s['ram_mb']} MB")
    e.add_field(name="Port", value=str(s['port']))
    await interaction.response.send_message(embed=e)


async def lifecycle(interaction: discord.Interaction, server: str, action: str, target_status: str):
    s = find_server(server)
    if not s:
        await interaction.response.send_message(f"❌ Server `{server}` not found.", ephemeral=True); return
    set_status(s['id'], target_status, f"{action} issued from Discord by {interaction.user}")
    await interaction.response.send_message(f"✅ `{action}` dispatched to **{s['name']}** — now {target_status.upper()}")


@tree.command(name="start", description="Start a game server")
async def start_cmd(interaction: discord.Interaction, server: str):
    await lifecycle(interaction, server, "start", "online")


@tree.command(name="stop", description="Stop a game server")
async def stop_cmd(interaction: discord.Interaction, server: str):
    await lifecycle(interaction, server, "stop", "offline")


@tree.command(name="restart", description="Restart a game server")
async def restart_cmd(interaction: discord.Interaction, server: str):
    await lifecycle(interaction, server, "restart", "starting")


@bot.event
async def on_ready():
    await tree.sync()
    print(f"◈ ApexNode bot logged in as {bot.user}")


def main():
    load_settings()
    if not TOKEN:
        print("No DISCORD_BOT_TOKEN set (env or settings table). Exiting.")
        sys.exit(0)
    bot.run(TOKEN)


if __name__ == "__main__":
    main()
