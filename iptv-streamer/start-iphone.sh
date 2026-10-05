#!/usr/bin/env bash
# Single-client friendly launcher tuned for iPhone/iPad (iSH).
# Live transcode is CPU-heavy on iSH — keep iSH open and expect lower quality.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")" && pwd)"
PORT="${PORT:-8081}"
HOST="${HOST:-0.0.0.0}"

export VIDEO_SCALE="${VIDEO_SCALE:-480:270}"
export FFMPEG_PRESET="${FFMPEG_PRESET:-ultrafast}"
export VIDEO_FPS="${VIDEO_FPS:-24}"
export AUDIO_BITRATE="${AUDIO_BITRATE:-96k}"
export HOST PORT

if ! command -v ffmpeg >/dev/null 2>&1; then
  echo "ffmpeg missing. On iSH run: apk add ffmpeg"
  exit 1
fi

if ! command -v python3 >/dev/null 2>&1; then
  echo "python3 missing. On iSH run: apk add python3"
  exit 1
fi

if ! command -v php >/dev/null 2>&1; then
  echo "php missing (needed to read config.php). On iSH run: apk add php"
  exit 1
fi

echo "WiiMC IPTV streamer (iPhone / iSH single-client profile)"
echo "  scale=${VIDEO_SCALE} preset=${FFMPEG_PRESET} fps=${VIDEO_FPS}"
echo "  Keep iSH in the foreground (or use the location keep-alive trick)"
echo "  Listening on ${HOST}:${PORT}"
echo

cd "$ROOT"
# Prefer threaded server even for one client; falls back to PHP if needed.
if [[ -f server.py ]]; then
  exec python3 server.py
fi
exec php -S "${HOST}:${PORT}" router.php
