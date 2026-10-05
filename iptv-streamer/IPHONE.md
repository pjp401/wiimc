# Running on iPhone / iPad (iSH)

This streamer can run on an iPhone using **[iSH Shell](https://ish.app)** (Alpine Linux userland).  
**Single client only is realistic.** Live ffmpeg transcode on a phone is slow; keep quality low.

## Limits (read first)

- Keep **iSH open** (screen on). iOS will suspend it in the background.
- Expect **stutter** vs a PC — iSH emulates Linux; ffmpeg is expensive.
- One Wii playing at a time.
- Same Wi‑Fi as the Wii; allow **Local Network** for iSH when prompted.

## 1. Install iSH

App Store → **iSH Shell**.

## 2. Install packages in iSH

```sh
apk update
apk add python3 php ffmpeg git curl
ffmpeg -version
python3 --version
php -v
```

## 3. Get this project onto the phone

### Option A — git (if network works in iSH)
```sh
cd ~
git clone -b cursor/iptv-streamer-setup-b985 --single-branch https://github.com/pjp401/wiimc.git
cd wiimc/iptv-streamer
```

### Option B — copy from PC
1. On PC, zip the `iptv-streamer` folder  
2. AirDrop / Files / iCloud into iSH’s file area  
3. In iSH: `cd` to that folder  

## 4. Configure

Edit `config.php` (same as on PC):

```php
'source_playlist' => 'http://10.75.39.12:9999/iptv',
'max_channels' => 500,
'group_filter' => '',
```

## 5. Keep iSH alive (important)

In a second iSH session / before starting the server:

```sh
cat /dev/location > /dev/null &
```

Then: **Settings → iSH → Location → Always**.

## 6. Start (iPhone profile)

```sh
cd ~/wiimc/iptv-streamer   # your path
chmod +x start-iphone.sh
./start-iphone.sh
```

Defaults for phone CPU: **480×270**, `ultrafast`, **24 fps**, AAC 96k.

## 7. Find the iPhone IP

**Settings → Wi‑Fi → (i) → IP Address**  
Example: `172.20.10.2` or `10.0.0.40`

## 8. Point WiiMC-SS at the phone

```xml
<link name="IPTV iPhone"
      addr="http://IPHONE_IP:8081/"
      type="playlist" />
```

Test from another device first:

```text
http://IPHONE_IP:8081/status.php
```

## If it’s too choppy

Lower further before starting:

```sh
export VIDEO_SCALE=320:180
export VIDEO_FPS=20
export AUDIO_BITRATE=64k
./start-iphone.sh
```

Or go back to the **PC/Android** host for smooth playback — phone hosting is a convenience tradeoff.

## Firewall / network

- iPhone and Wii on the **same Wi‑Fi** (not a client-isolated guest network)
- Accept iOS **Local Network** permission for iSH
- No Windows portproxy needed when the phone is the server
