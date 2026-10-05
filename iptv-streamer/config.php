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
    'cache_ttl' => 300,

    // WiiMC struggles with 10k+ entries. Start small; raise later.
    'max_channels' => 100,

    // Optional: only keep entries whose group-title contains this text (case-insensitive).
    // Example: 'FOR ADULTS' or 'something'
    'group_filter' => '',

    // Optional hard-coded extras
    'channels' => [],
];
