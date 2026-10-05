<?php
/**
 * Load channels from config + optional remote #EXTM3U catalog.
 */
function iptv_http_get(string $url, int $timeout = 8): ?string
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_USERAGENT => 'WiiMC-IPTV-Streamer/1.0',
        ]);
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($body !== false && $code >= 200 && $code < 400) {
            return $body;
        }
        return null;
    }

    $ctx = stream_context_create([
        'http' => [
            'timeout' => $timeout,
            'follow_location' => 1,
            'header' => "User-Agent: WiiMC-IPTV-Streamer/1.0\r\n",
        ],
    ]);
    $body = @file_get_contents($url, false, $ctx);
    return ($body === false) ? null : $body;
}

function iptv_resolve_url(string $maybeRelative, string $basePlaylistUrl): string
{
    $maybeRelative = trim($maybeRelative);
    if (preg_match('#^https?://#i', $maybeRelative)) {
        return $maybeRelative;
    }

    $parts = parse_url($basePlaylistUrl);
    if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
        return $maybeRelative;
    }

    $origin = $parts['scheme'] . '://' . $parts['host'];
    if (!empty($parts['port'])) {
        $origin .= ':' . $parts['port'];
    }

    if (str_starts_with($maybeRelative, '/')) {
        return $origin . $maybeRelative;
    }

    $path = $parts['path'] ?? '/';
    // If playlist path looks like a file (/iptv/list.m3u), resolve beside it;
    // if it's a bare route (/iptv), treat that directory as the base.
    if (str_ends_with($path, '/')) {
        $dir = rtrim($origin . $path, '/');
    } elseif (preg_match('#\.(m3u8?|pls)$#i', $path)) {
        $dir = $origin . str_replace('\\', '/', dirname($path));
        if (str_ends_with($dir, ':')) {
            $dir = $origin;
        }
    } else {
        $dir = $origin . $path;
    }

    return rtrim($dir, '/') . '/' . ltrim($maybeRelative, '/');
}

/**
 * Parse a standard M3U / HLS media playlist into title/url pairs.
 * Skips #EXT-X-* control lines; uses #EXTINF titles when present.
 */
function iptv_parse_m3u(string $body, string $playlistUrl): array
{
    $channels = [];
    $pendingTitle = 'Channel';
    $lines = preg_split("/\r\n|\n|\r/", $body);

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line === '#EXTM3U') {
            continue;
        }

        if (str_starts_with($line, '#EXTINF:')) {
            $title = 'Channel';
            if (preg_match('/,(.*)$/', $line, $m)) {
                $title = trim($m[1]);
            }
            // Prefer tvg-name="..." if present
            if (preg_match('/tvg-name="([^"]+)"/i', $line, $m)) {
                $title = trim($m[1]);
            }
            $pendingTitle = ($title !== '') ? $title : 'Channel';
            continue;
        }

        if (str_starts_with($line, '#')) {
            continue;
        }

        $url = iptv_resolve_url($line, $playlistUrl);
        if (!preg_match('#^https?://#i', $url)) {
            continue;
        }

        $channels[] = [
            'title' => $pendingTitle,
            'url' => $url,
        ];
        $pendingTitle = 'Channel';
    }

    return $channels;
}

function iptv_load_channels(array $config): array
{
    $channels = [];

    if (!empty($config['source_playlist']) && is_string($config['source_playlist'])) {
        $playlistUrl = $config['source_playlist'];
        $body = iptv_http_get($playlistUrl);
        if ($body !== null && stripos($body, '#EXTM3U') !== false) {
            $channels = array_merge($channels, iptv_parse_m3u($body, $playlistUrl));
        }
    }

    if (!empty($config['channels']) && is_array($config['channels'])) {
        foreach ($config['channels'] as $ch) {
            if (!empty($ch['title']) && !empty($ch['url'])) {
                $channels[] = $ch;
            }
        }
    }

    // Backward compatible: plain list of channels only
    if ($channels === [] && array_is_list($config)) {
        foreach ($config as $ch) {
            if (is_array($ch) && !empty($ch['title']) && !empty($ch['url'])) {
                $channels[] = $ch;
            }
        }
    }

    return $channels;
}
