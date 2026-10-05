<?php
/**
 * IPTV streamer config for WiiMC-SS.
 */
return [
    // Main HLS catalog from your LAN service
    'source_playlist' => 'http://10.75.39.12:9999/iptv',

    'playlist_timeout' => 60,
    'cache_ttl' => 300,

    // Per-group channel cap (WiiMC memory). 0 = unlimited.
    'max_channels' => 500,

    // Top-level index lists each group-title as a folder/playlist.
    // Optional: only show groups whose name contains this text.
    // Leave empty to show all groups.
    'group_filter' => '',

    'channels' => [],
];
