<?php
/**
 * PLS playlist for WiiMC / WiiMC-SS Online Media.
 *
 * Imports channels from config source_playlist (e.g. http://host:9999/iptv)
 * and wraps each URL through /render/ for live MPEG-TS transcode.
 */
require __DIR__ . '/lib.php';

header('Content-Type: text/plain; charset=UTF-8');

$host = $_SERVER['HTTP_HOST'] ?? '127.0.0.1:8081';
$base = 'http://' . $host;
$config = require __DIR__ . '/config.php';
$channels = iptv_load_channels($config);

echo "[playlist]\n";
echo 'NumberOfEntries=' . count($channels) . "\n";

if ($channels === []) {
    echo "Title1=No channels found\n";
    echo "File1=" . $base . "/\n";
    echo "Length1=0\n";
    exit;
}

$i = 1;
foreach ($channels as $channel) {
    $render = $base . '/render/?site=iptv&q=' . rawurlencode($channel['url']);
    echo 'File' . $i . '=' . $render . "\n";
    echo 'Title' . $i . '=' . str_replace(["\r", "\n"], ' ', $channel['title']) . "\n";
    echo 'Length' . $i . "=9999999\n";
    $i++;
}
