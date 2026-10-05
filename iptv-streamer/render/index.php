<?php
/**
 * Live transcoder endpoint for WiiMC-SS.
 *
 * Pulls an upstream HTTP/HLS URL and remuxes/transcodes to MPEG-TS with
 * Baseline H.264 + AAC @ 48 kHz — settings that worked with WiiMC-SS over LAN.
 *
 * LAN-only recommended: this proxies arbitrary URLs through ffmpeg.
 */
header('Content-Type: application/octet-stream');
header('Cache-Control: no-cache');
header('Connection: close');

$site = $_REQUEST['site'] ?? '';
$video_url = ($site === 'iptv') ? urldecode((string)($_REQUEST['q'] ?? '')) : '';

if ($video_url === '' || !preg_match('#^https?://#i', $video_url)) {
    http_response_code(400);
    echo "Invalid request.\n";
    exit;
}

// Disallow obviously local metadata schemes; keep plain http(s) streams only.
if (preg_match('#^(file|ftp|rtmp|udp|rtsp):#i', $video_url)) {
    http_response_code(400);
    echo "Unsupported URL scheme.\n";
    exit;
}

$ffmpeg = getenv('FFMPEG_BIN') ?: 'ffmpeg';
$scale = getenv('VIDEO_SCALE') ?: '640:360';
$preset = getenv('FFMPEG_PRESET') ?: 'veryfast';
$fps = getenv('VIDEO_FPS') ?: '30';
$audioBr = getenv('AUDIO_BITRATE') ?: '128k';

// Wii-friendly progressive MPEG-TS (same approach as the working .ts catalog).
$cmd = sprintf(
    '%s -hide_banner -loglevel error -re -i %s ' .
    '-c:v libx264 -profile:v baseline -level 3.0 -preset %s -tune zerolatency ' .
    '-pix_fmt yuv420p -vf scale=%s -r %s ' .
    '-c:a aac -b:a %s -ac 2 -ar 48000 ' .
    '-f mpegts -',
    escapeshellcmd($ffmpeg),
    escapeshellarg($video_url),
    escapeshellarg($preset),
    escapeshellarg($scale),
    escapeshellarg($fps),
    escapeshellarg($audioBr)
);

$descriptors = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
];

$process = proc_open($cmd, $descriptors, $pipes);
if (!is_resource($process)) {
    http_response_code(500);
    echo "Failed to start ffmpeg.\n";
    exit;
}

fclose($pipes[0]);
stream_set_blocking($pipes[1], true);

while (!feof($pipes[1])) {
    $chunk = fread($pipes[1], 8192);
    if ($chunk === false || $chunk === '') {
        break;
    }
    echo $chunk;
    if (function_exists('fastcgi_finish_request') === false) {
        flush();
    }
}

fclose($pipes[1]);
fclose($pipes[2]);
proc_close($process);
