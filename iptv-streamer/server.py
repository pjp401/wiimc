#!/usr/bin/env python3
"""
Concurrent LAN server for the WiiMC IPTV streamer.

PHP's built-in server (php -S) is single-threaded, so one /render/ stream
blocks Chrome/phone/other Wiis. This threaded server allows multiple clients.
"""

from __future__ import annotations

import json
import os
import re
import subprocess
import sys
import tempfile
import threading
import time
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from urllib.error import URLError, HTTPError
from urllib.parse import parse_qs, unquote, urlparse
from urllib.request import Request, urlopen

ROOT = Path(__file__).resolve().parent
CACHE_FILE = Path(tempfile.gettempdir()) / "wiimc-iptv-channels.json"
CACHE_LOCK = threading.Lock()


def load_config() -> dict:
    out = subprocess.check_output(
        ["php", "-r", "echo json_encode(require 'config.php');"],
        cwd=str(ROOT),
        text=True,
    )
    cfg = json.loads(out)
    if not isinstance(cfg, dict):
        raise RuntimeError("config.php must return an object/array")
    return cfg


def http_get(url: str, timeout: int = 60) -> str:
    req = Request(url, headers={"User-Agent": "WiiMC-IPTV-Streamer/1.0"})
    with urlopen(req, timeout=timeout) as resp:
        return resp.read().decode("utf-8", errors="replace")


def resolve_url(maybe_relative: str, base_playlist_url: str) -> str:
    maybe_relative = maybe_relative.strip()
    if re.match(r"^https?://", maybe_relative, re.I):
        return maybe_relative

    parts = urlparse(base_playlist_url)
    origin = f"{parts.scheme}://{parts.hostname}"
    if parts.port:
        origin += f":{parts.port}"

    if maybe_relative.startswith("/"):
        return origin + maybe_relative

    path = parts.path or "/"
    if path.endswith("/"):
        directory = (origin + path).rstrip("/")
    elif re.search(r"\.(m3u8?|pls)$", path, re.I):
        directory = origin + str(Path(path).parent).replace("\\", "/")
        if directory.endswith(":"):
            directory = origin
    else:
        directory = origin + path

    return directory.rstrip("/") + "/" + maybe_relative.lstrip("/")


def parse_m3u(body: str, playlist_url: str) -> list[dict]:
    channels: list[dict] = []
    pending_title = "Channel"
    pending_group = ""

    for raw in re.split(r"\r\n|\n|\r", body):
        line = raw.strip()
        if not line or line == "#EXTM3U":
            continue

        if line.startswith("#EXTINF:"):
            title = "Channel"
            pending_group = ""
            m = re.search(r",(.*)$", line)
            if m:
                title = m.group(1).strip()
            m = re.search(r'tvg-name="([^"]+)"', line, re.I)
            if m:
                title = m.group(1).strip()
            m = re.search(r'group-title="([^"]+)"', line, re.I)
            if m:
                pending_group = m.group(1).strip()
            pending_title = title or "Channel"
            continue

        if line.startswith("#"):
            continue

        url = resolve_url(line, playlist_url)
        if not re.match(r"^https?://", url, re.I):
            continue

        channels.append(
            {
                "title": pending_title,
                "url": url,
                "group": pending_group or "Ungrouped",
            }
        )
        pending_title = "Channel"
        pending_group = ""

    return channels


def load_all_channels(cfg: dict) -> tuple[list[dict], str | None, bool]:
    source = cfg.get("source_playlist") or ""
    timeout = int(cfg.get("playlist_timeout") or 60)
    ttl = int(cfg.get("cache_ttl") or 300)
    extras = cfg.get("channels") or []

    if not source:
        channels = []
        for ch in extras:
            if ch.get("title") and ch.get("url"):
                ch = dict(ch)
                ch.setdefault("group", "Ungrouped")
                channels.append(ch)
        return channels, None, False

    with CACHE_LOCK:
        if ttl > 0 and CACHE_FILE.is_file() and (time.time() - CACHE_FILE.stat().st_mtime) < ttl:
            try:
                cached = json.loads(CACHE_FILE.read_text(encoding="utf-8"))
                if cached.get("source") == source and cached.get("channels"):
                    all_channels = cached["channels"]
                    from_cache = True
                else:
                    all_channels = []
                    from_cache = False
            except Exception:
                all_channels = []
                from_cache = False
        else:
            all_channels = []
            from_cache = False

        if not all_channels:
            try:
                body = http_get(source, timeout=max(5, timeout))
            except Exception as exc:
                return [], f"Fetch failed: {exc}", False
            if "#EXTM3U" not in body.upper():
                return [], "Source did not look like #EXTM3U", False
            all_channels = parse_m3u(body, source)
            if not all_channels:
                return [], "Parsed 0 channels from source playlist", False
            if ttl > 0:
                CACHE_FILE.write_text(
                    json.dumps(
                        {
                            "fetched_at": int(time.time()),
                            "source": source,
                            "channels": all_channels,
                        }
                    ),
                    encoding="utf-8",
                )
            from_cache = False

    for ch in extras:
        if ch.get("title") and ch.get("url"):
            ch = dict(ch)
            ch.setdefault("group", "Ungrouped")
            all_channels.append(ch)

    return all_channels, None, from_cache


def group_map(channels: list[dict]) -> dict[str, list[dict]]:
    groups: dict[str, list[dict]] = {}
    for ch in channels:
        name = (ch.get("group") or "Ungrouped").strip() or "Ungrouped"
        groups.setdefault(name, []).append(ch)
    return dict(sorted(groups.items(), key=lambda kv: kv[0].lower()))


def filter_groups(groups: dict[str, list[dict]], group_filter: str) -> dict[str, list[dict]]:
    needle = (group_filter or "").strip()
    if not needle:
        return groups
    return {k: v for k, v in groups.items() if needle.lower() in k.lower()}


def channels_in_group(channels: list[dict], group_name: str) -> list[dict]:
    want = group_name.strip()
    out = []
    for ch in channels:
        group = (ch.get("group") or "Ungrouped").strip() or "Ungrouped"
        if group.lower() == want.lower():
            out.append(ch)
    return out


def emit_pls(entries: list[dict]) -> bytes:
    lines = ["[playlist]", f"NumberOfEntries={len(entries)}"]
    for i, entry in enumerate(entries, start=1):
        title = str(entry["title"]).replace("\r", " ").replace("\n", " ").replace("=", " ")
        length = entry.get("length", 9999999)
        lines.append(f"File{i}={entry['file']}")
        lines.append(f"Title{i}={title}")
        lines.append(f"Length{i}={length}")
    return ("\n".join(lines) + "\n").encode("utf-8")


class Handler(BaseHTTPRequestHandler):
    server_version = "WiiMC-IPTV-Streamer/1.0"
    cfg: dict = {}

    def log_message(self, fmt: str, *args) -> None:
        sys.stderr.write("%s - %s\n" % (self.address_string(), fmt % args))

    def _send(self, code: int, body: bytes, content_type: str) -> None:
        self.send_response(code)
        self.send_header("Content-Type", content_type)
        self.send_header("Content-Length", str(len(body)))
        self.send_header("Connection", "close")
        self.send_header("Cache-Control", "no-cache")
        self.end_headers()
        self.wfile.write(body)

    def _base(self) -> str:
        host = self.headers.get("Host") or f"127.0.0.1:{self.server.server_address[1]}"
        return f"http://{host}"

    def do_GET(self) -> None:
        parsed = urlparse(self.path)
        path = unquote(parsed.path)
        qs = parse_qs(parsed.query)
        base = self._base()

        try:
            if path in ("/", "/index.php"):
                self.handle_index(base)
            elif path in ("/group", "/group.php"):
                self.handle_group(base, qs)
            elif path in ("/status", "/status.php"):
                self.handle_status()
            elif path in ("/render", "/render/", "/render/index.php"):
                self.handle_render(qs)
            else:
                self._send(404, b"Not found\n", "text/plain; charset=UTF-8")
        except BrokenPipeError:
            return
        except Exception as exc:
            msg = f"Server error: {exc}\n".encode("utf-8")
            try:
                self._send(500, msg, "text/plain; charset=UTF-8")
            except Exception:
                pass

    def handle_index(self, base: str) -> None:
        channels, err, _ = load_all_channels(self.cfg)
        if not channels:
            body = emit_pls(
                [{"file": f"{base}/status.php", "title": (err or "No channels found")[:80], "length": 0}]
            )
            self._send(200, body, "text/plain; charset=UTF-8")
            return

        groups = filter_groups(group_map(channels), str(self.cfg.get("group_filter") or ""))
        if not groups:
            body = emit_pls(
                [{"file": f"{base}/status.php", "title": "No groups matched group_filter", "length": 0}]
            )
            self._send(200, body, "text/plain; charset=UTF-8")
            return

        entries = []
        for name, chans in groups.items():
            entries.append(
                {
                    "file": f"{base}/group.php?name={quote(name)}",
                    "title": f"{name} ({len(chans)})",
                }
            )
        self._send(200, emit_pls(entries), "text/plain; charset=UTF-8")

    def handle_group(self, base: str, qs: dict) -> None:
        name = (qs.get("name") or [""])[0].strip()
        if not name:
            body = emit_pls([{"file": f"{base}/", "title": "Missing group name", "length": 0}])
            self._send(200, body, "text/plain; charset=UTF-8")
            return

        channels, err, _ = load_all_channels(self.cfg)
        if not channels:
            body = emit_pls(
                [{"file": f"{base}/status.php", "title": (err or "No channels found")[:80], "length": 0}]
            )
            self._send(200, body, "text/plain; charset=UTF-8")
            return

        group_channels = channels_in_group(channels, name)
        max_channels = int(self.cfg.get("max_channels") or 0)
        if max_channels > 0:
            group_channels = group_channels[:max_channels]

        if not group_channels:
            body = emit_pls([{"file": f"{base}/", "title": "No channels in group", "length": 0}])
            self._send(200, body, "text/plain; charset=UTF-8")
            return

        entries = [
            {
                "file": f"{base}/render/?site=iptv&q={quote(ch['url'])}",
                "title": ch["title"],
            }
            for ch in group_channels
        ]
        self._send(200, emit_pls(entries), "text/plain; charset=UTF-8")

    def handle_status(self) -> None:
        channels, err, from_cache = load_all_channels(self.cfg)
        groups = filter_groups(group_map(channels), str(self.cfg.get("group_filter") or ""))
        lines = [
            f"source_playlist={self.cfg.get('source_playlist', '')}",
            f"channel_count={len(channels)}",
            f"group_count={len(groups)}",
            f"from_cache={'yes' if from_cache else 'no'}",
            f"max_channels_per_group={self.cfg.get('max_channels', '')}",
            f"group_filter={self.cfg.get('group_filter', '')}",
            f"cache_file={CACHE_FILE}",
            f"error={err or ''}",
            f"server=python-threaded",
            "",
        ]
        for i, (name, chans) in enumerate(groups.items()):
            if i >= 30:
                lines.append("...")
                break
            lines.append(f"group[{i}]={name} ({len(chans)})")
        self._send(200, ("\n".join(lines) + "\n").encode("utf-8"), "text/plain; charset=UTF-8")

    def handle_render(self, qs: dict) -> None:
        site = (qs.get("site") or [""])[0]
        video_url = unquote((qs.get("q") or [""])[0])
        if site != "iptv" or not re.match(r"^https?://", video_url, re.I):
            self._send(400, b"Invalid request.\n", "text/plain; charset=UTF-8")
            return

        ffmpeg = os.environ.get("FFMPEG_BIN", "ffmpeg")
        cmd = [
            ffmpeg,
            "-hide_banner",
            "-loglevel",
            "error",
            "-re",
            "-i",
            video_url,
            "-c:v",
            "libx264",
            "-profile:v",
            "baseline",
            "-level",
            "3.0",
            "-preset",
            "veryfast",
            "-tune",
            "zerolatency",
            "-pix_fmt",
            "yuv420p",
            "-vf",
            "scale=640:360",
            "-r",
            "30",
            "-c:a",
            "aac",
            "-b:a",
            "128k",
            "-ac",
            "2",
            "-ar",
            "48000",
            "-f",
            "mpegts",
            "-",
        ]

        self.send_response(200)
        self.send_header("Content-Type", "application/octet-stream")
        self.send_header("Cache-Control", "no-cache")
        self.send_header("Connection", "close")
        self.end_headers()

        proc = subprocess.Popen(cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
        assert proc.stdout is not None
        try:
            while True:
                chunk = proc.stdout.read(8192)
                if not chunk:
                    break
                self.wfile.write(chunk)
        except (BrokenPipeError, ConnectionResetError):
            pass
        finally:
            proc.kill()
            try:
                proc.wait(timeout=2)
            except Exception:
                pass


def quote(value: str) -> str:
    from urllib.parse import quote as _quote

    return _quote(value, safe="")


def main() -> int:
    host = os.environ.get("HOST", "0.0.0.0")
    port = int(os.environ.get("PORT", "8081"))

    try:
        cfg = load_config()
    except Exception as exc:
        print(f"Failed to load config.php via php: {exc}", file=sys.stderr)
        return 1

    Handler.cfg = cfg
    server = ThreadingHTTPServer((host, port), Handler)
    print("WiiMC IPTV streamer (threaded)")
    print(f"  Listening: http://{host}:{port}/")
    print("  Multiple Wiis/Chrome/phone can connect at once")
    print("  Ctrl+C to stop")
    try:
        server.serve_forever()
    except KeyboardInterrupt:
        print("\nStopping...")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
