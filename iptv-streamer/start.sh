#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")" && pwd)"
PORT="${PORT:-8081}"
HOST="${HOST:-0.0.0.0}"

if ! command -v php >/dev/null 2>&1; then
  echo "php is required (php-cli)."
  exit 1
fi

if ! command -v ffmpeg >/dev/null 2>&1; then
  echo "ffmpeg is required."
  exit 1
fi

echo "WiiMC IPTV streamer"
echo "  Playlist: http://<this-pc-ip>:${PORT}/"
echo "  Edit channels in: ${ROOT}/config.php"
echo "  Ctrl+C to stop"
echo

cd "$ROOT"
exec php -S "${HOST}:${PORT}" router.php
