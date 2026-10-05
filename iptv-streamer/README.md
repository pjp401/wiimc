# WiiMC IPTV Streamer (WiiMC-SS helper)

Companion HTTP service for **WiiMC / WiiMC-SS** Online Media. It serves a PLS playlist and live-transcodes upstream HTTP/HLS streams to **MPEG-TS** (Baseline H.264 + AAC @ 48 kHz), which matches what worked over LAN with WiiMC-SS.

Derived from [Komfudo/WiiMC-IPTV-Streamer-and-Renderer](https://github.com/Komfudo/WiiMC-IPTV-Streamer-and-Renderer) (GPL). Keep this on your LAN only — the render endpoint proxies URLs through ffmpeg.

## Requirements

- PHP CLI (`php-cli`)
- ffmpeg
- Same Wi‑Fi/LAN as the Wii

## Quick start (Linux / WSL)

```bash
cd iptv-streamer
# edit config.php with your stream URLs
chmod +x start.sh
./start.sh
```

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

Edit `config.php`:

```php
return [
    [
        'title' => 'Test Channel',
        'url' => 'http://10.75.39.12:9999/iptv/TEST%20CHANNEL',
    ],
];
```

Use streams you are allowed to access. HLS (`.m3u8` / `application/vnd.apple.mpegurl`) is the main target.

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
