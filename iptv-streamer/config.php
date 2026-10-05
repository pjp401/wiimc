<?php
/**
 * Channel list for the WiiMC IPTV streamer.
 *
 * Each entry needs:
 * - title: label shown in WiiMC
 * - url: source stream URL (HLS .m3u8 or other ffmpeg-readable HTTP stream)
 *
 * Examples use placeholders — replace with your own allowed streams.
 */
return [
    [
        'title' => 'Example HLS Channel',
        'url' => 'http://example.com/stream/index.m3u8',
    ],
    // [
    //     'title' => 'Portal Test Channel',
    //     'url' => 'http://10.75.39.12:9999/iptv/TEST%20CHANNEL',
    // ],
];
