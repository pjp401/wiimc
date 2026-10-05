<?php
/**
 * Load channels from config + optional remote #EXTM3U catalog.
 */

function iptv_cache_path(): string
{
    return sys_get_temp_dir() . '/wiimc-iptv-channels.json';
}

function iptv_http_get(string $url, int $timeout = 60): array
{
    $error = null;
    $body = null;
    $code = 0;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => min(15, $timeout),
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_USERAGENT => 'WiiMC-IPTV-Streamer/1.0',
        ]);
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($body === false) {
            $error = curl_error($ch) ?: 'curl failed';
            $body = null;
        } elseif ($code < 200 || $code >= 400) {
            $error = "HTTP $code";
            $body = null;
        }
        curl_close($ch);
        return ['body' => $body, 'error' => $error, 'code' => $code];
    }

    $ctx = stream_context_create([
        'http' => [
            'timeout' => $timeout,
            'follow_location' => 1,
            'header' => "User-Agent: WiiMC-IPTV-Streamer/1.0\r\n",
        ],
    ]);
    $body = @file_get_contents($url, false, $ctx);
    if ($body === false) {
        return ['body' => null, 'error' => 'file_get_contents failed', 'code' => 0];
    }
    return ['body' => $body, 'error' => null, 'code' => 200];
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

function iptv_parse_m3u(string $body, string $playlistUrl): array
{
    $channels = [];
    $pendingTitle = 'Channel';
    $pendingGroup = '';
    $lines = preg_split("/\r\n|\n|\r/", $body);

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line === '#EXTM3U') {
            continue;
        }

        if (str_starts_with($line, '#EXTINF:')) {
            $title = 'Channel';
            $pendingGroup = '';
            if (preg_match('/,(.*)$/', $line, $m)) {
                $title = trim($m[1]);
            }
            if (preg_match('/tvg-name="([^"]+)"/i', $line, $m)) {
                $title = trim($m[1]);
            }
            if (preg_match('/group-title="([^"]+)"/i', $line, $m)) {
                $pendingGroup = trim($m[1]);
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
            'group' => ($pendingGroup !== '') ? $pendingGroup : 'Ungrouped',
        ];
        $pendingTitle = 'Channel';
        $pendingGroup = '';
    }

    return $channels;
}

function iptv_load_all_channels(array $config): array
{
    $result = [
        'channels' => [],
        'error' => null,
        'from_cache' => false,
    ];

    $timeout = (int)($config['playlist_timeout'] ?? 60);
    $ttl = (int)($config['cache_ttl'] ?? 300);
    $cacheFile = iptv_cache_path();

    if (empty($config['source_playlist']) || !is_string($config['source_playlist'])) {
        // Manual-only mode
        if (!empty($config['channels']) && is_array($config['channels'])) {
            foreach ($config['channels'] as $ch) {
                if (!empty($ch['title']) && !empty($ch['url'])) {
                    if (empty($ch['group'])) {
                        $ch['group'] = 'Ungrouped';
                    }
                    $result['channels'][] = $ch;
                }
            }
        }
        return $result;
    }

    $playlistUrl = $config['source_playlist'];
    $all = [];

    if ($ttl > 0 && is_file($cacheFile) && (time() - filemtime($cacheFile)) < $ttl) {
        $cached = json_decode((string)file_get_contents($cacheFile), true);
        if (is_array($cached)
            && ($cached['source'] ?? '') === $playlistUrl
            && !empty($cached['channels'])
            && is_array($cached['channels'])) {
            $all = $cached['channels'];
            $result['from_cache'] = true;
        }
    }

    if ($all === []) {
        $fetch = iptv_http_get($playlistUrl, max(5, $timeout));
        if ($fetch['body'] === null) {
            $result['error'] = 'Fetch failed: ' . ($fetch['error'] ?? 'unknown');
            return $result;
        }
        if (stripos($fetch['body'], '#EXTM3U') === false) {
            $result['error'] = 'Source did not look like #EXTM3U';
            return $result;
        }

        $all = iptv_parse_m3u($fetch['body'], $playlistUrl);
        if ($all === []) {
            $result['error'] = 'Parsed 0 channels from source playlist';
            return $result;
        }
        if ($ttl > 0) {
            @file_put_contents($cacheFile, json_encode([
                'fetched_at' => time(),
                'source' => $playlistUrl,
                'channels' => $all,
            ]));
        }
    }

    if (!empty($config['channels']) && is_array($config['channels'])) {
        foreach ($config['channels'] as $ch) {
            if (!empty($ch['title']) && !empty($ch['url'])) {
                if (empty($ch['group'])) {
                    $ch['group'] = 'Ungrouped';
                }
                $all[] = $ch;
            }
        }
    }

    $result['channels'] = $all;
    return $result;
}

function iptv_group_map(array $channels): array
{
    $groups = [];
    foreach ($channels as $ch) {
        $group = trim((string)($ch['group'] ?? 'Ungrouped'));
        if ($group === '') {
            $group = 'Ungrouped';
        }
        if (!isset($groups[$group])) {
            $groups[$group] = [];
        }
        $groups[$group][] = $ch;
    }
    ksort($groups, SORT_NATURAL | SORT_FLAG_CASE);
    return $groups;
}

function iptv_filter_groups(array $groups, string $groupFilter): array
{
    $filter = trim($groupFilter);
    if ($filter === '') {
        return $groups;
    }

    $out = [];
    foreach ($groups as $name => $channels) {
        if (stripos($name, $filter) !== false) {
            $out[$name] = $channels;
        }
    }
    return $out;
}

function iptv_channels_in_group(array $channels, string $groupName): array
{
    $want = trim($groupName);
    $out = [];
    foreach ($channels as $ch) {
        $group = trim((string)($ch['group'] ?? 'Ungrouped'));
        if ($group === '') {
            $group = 'Ungrouped';
        }
        if (strcasecmp($group, $want) === 0) {
            $out[] = $ch;
        }
    }
    return $out;
}

function iptv_emit_pls(array $entries): void
{
    header('Content-Type: text/plain; charset=UTF-8');
    echo "[playlist]\n";
    echo 'NumberOfEntries=' . count($entries) . "\n";

    $i = 1;
    foreach ($entries as $entry) {
        echo 'File' . $i . '=' . $entry['file'] . "\n";
        echo 'Title' . $i . '=' . str_replace(["\r", "\n", '='], ' ', $entry['title']) . "\n";
        echo 'Length' . $i . '=' . ($entry['length'] ?? 9999999) . "\n";
        $i++;
    }
}

/** @deprecated kept for older status helpers */
function iptv_load_channels(array $config): array
{
    $loaded = iptv_load_all_channels($config);
    $groupFilter = (string)($config['group_filter'] ?? '');
    $max = (int)($config['max_channels'] ?? 0);

    if ($groupFilter !== '') {
        $filtered = [];
        foreach ($loaded['channels'] as $ch) {
            $group = (string)($ch['group'] ?? '');
            if (stripos($group, $groupFilter) !== false) {
                $filtered[] = $ch;
            }
        }
        $loaded['channels'] = $filtered;
        if ($filtered === [] && $loaded['error'] === null) {
            $loaded['error'] = 'No channels matched group_filter';
        }
    }

    if ($max > 0 && count($loaded['channels']) > $max) {
        $loaded['channels'] = array_slice($loaded['channels'], 0, $max);
    }

    return $loaded;
}
