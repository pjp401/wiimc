<?php
/**
 * IPTV streamer config for WiiMC-SS.
 */
return [
    // Main HLS catalog from your LAN service
    'source_playlist' => 'http://10.75.39.12:9999/iptv',

    // Fetch/parse can be slow for huge catalogs
    'playlist_timeout' => 60,

    // Cache imported channels on disk (seconds). 0 = disable.
    // Clear cache after changing group_filter (delete the cache file or set 0 once).
    'cache_ttl' => 300,

    // 0 = no cap (safe after a tight group_filter)
    'max_channels' => 0,

    // Only keep entries whose group-title contains this text (case-insensitive).
    'group_filter' => 'ENGLISH | USA LOCAL',

    // Optional hard-coded extras
    'channels' => [],
];
