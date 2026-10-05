#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")" && pwd)"
PORT="${PORT:-8081}"
HOST="${HOST:-0.0.0.0}"

if ! command -v php >/dev/null 2>&1; then
  echo "php is required (used to read config.php)."
  exit 1
fi

if ! command -v ffmpeg >/dev/null 2>&1; then
  echo "ffmpeg is required."
  exit 1
fi

if ! command -v python3 >/dev/null 2>&1; then
  echo "python3 is required for concurrent mode."
  exit 1
fi

echo "WiiMC IPTV streamer (concurrent / threaded)"
echo "  Playlist: http://<this-pc-ip>:${PORT}/"
echo "  This allows multiple Wiis / browsers at once"
echo "  Ctrl+C to stop"
echo

cd "$ROOT"
export HOST PORT
exec python3 server.py
