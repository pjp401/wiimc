# WiiMC IPTV Streamer (WiiMC-SS helper)

Companion HTTP service for **WiiMC / WiiMC-SS** Online Media. It serves a PLS playlist and live-transcodes upstream HTTP/HLS streams to **MPEG-TS** (Baseline H.264 + AAC @ 48 kHz), which matches what worked over LAN with WiiMC-SS.

Derived from [Komfudo/WiiMC-IPTV-Streamer-and-Renderer](https://github.com/Komfudo/WiiMC-IPTV-Streamer-and-Renderer) (GPL). Keep this on your LAN only — the render endpoint proxies URLs through ffmpeg.

## Requirements

- PHP CLI (`php-cli`) — used to read `config.php`
- Python 3 — concurrent server
- ffmpeg
- Same Wi‑Fi/LAN as the Wii

## Quick start (Linux / WSL)

### Concurrent mode (recommended — multiple Wiis / Chrome / phone)
```bash
cd iptv-streamer
chmod +x start-concurrent.sh
./start-concurrent.sh
```

One playing stream will **not** block other requests.

### Single-thread mode (legacy)
```bash
chmod +x start.sh
./start.sh
```

`php -S` handles **one request at a time**. While a channel is playing, Chrome/phone/another Wii will hang until that stream stops.

Default listen address: `0.0.0.0:8081`.

### WSL2 note

PHP inside WSL2 is not always reachable from the Wii via the Windows LAN IP. Either:

- run this on native Linux, or
- port-forward from Windows (Admin PowerShell):

```powershell
$wslIp = (wsl hostname -I).Trim().Split(" ")[0]
netsh interface portproxy add v4tov4 listenport=8081 listenaddress=0.0.0.0 connectport=8081 connectaddress=$wslIp
New-NetFirewallRule -DisplayName "WiiMC IPTV 8081" -Direction Inbound -Protocol TCP -LocalPort 8081 -Action Allow
```

## Configure channels

If your HLS service publishes a root `#EXTM3U` catalog (e.g. `http://10.75.39.12:9999/iptv`), set that in `config.php`:

```php
return [
    'source_playlist' => 'http://10.75.39.12:9999/iptv',
    'playlist_timeout' => 60,
    'cache_ttl' => 300,
    'max_channels' => 500,     // per group
    'group_filter' => '',      // optional: only list matching group names
    'channels' => [],
];
```

Behavior:
1. `/` lists each `group-title` as a playlist entry (folder-like in WiiMC)
2. `/group.php?name=ENGLISH%20%7C%20USA%20LOCAL` lists up to `max_channels` streams in that group
3. each stream goes through `/render/` for live MPEG-TS transcode

Debug:
```bash
curl -s http://10.0.0.83:8081/status.php
curl -s http://10.0.0.83:8081/ | head
curl -s "http://10.0.0.83:8081/group.php?name=ENGLISH%20%7C%20USA%20LOCAL" | head
```

## WiiMC-SS

Merge `examples/onlinemedia.xml` into `SD:/apps/wiimc/onlinemedia.xml`:

```xml
<link name="IPTV Catalog"
      addr="http://10.0.0.83:8081/"
      type="playlist" />
```

Then: Online Media → IPTV Catalog → pick a channel.

## Test from a PC

```bash
curl -s "http://10.0.0.83:8081/"
curl -sI "http://10.0.0.83:8081/render/?site=iptv&q=http%3A//example.com/stream.m3u8"
```

The playlist should look like:

```text
[playlist]
NumberOfEntries=1
File1=http://10.0.0.83:8081/render/?site=iptv&q=...
Title1=Test Channel
Length1=9999999
```

## Tuning

Default encode in `render/index.php`: `640x360`, baseline, 30 fps, AAC 128k/48kHz, MPEG-TS.

If the Wii stutters, lower further (e.g. `480x270`) or raise `-preset` speed. If you need sharper video on a strong LAN, try `854x480` (keep even dimensions).
