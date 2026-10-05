<?php
/**
 * IPTV streamer config for WiiMC-SS.
 *
 * source_playlist: remote #EXTM3U catalog (your HLS service root).
 * Each entry is wrapped through /render/ so WiiMC gets MPEG-TS.
 *
 * channels: optional extra/manual entries merged after the playlist.
 */
return [
    // Main HLS catalog from your LAN service
    'source_playlist' => 'http://10.75.39.12:9999/iptv',

    // Optional hard-coded extras (leave empty if the playlist has everything)
    'channels' => [
        // [
        //     'title' => 'Manual Channel',
        //     'url' => 'http://10.75.39.12:9999/iptv/SOME%20CHANNEL',
        // ],
    ],
];
