<?php
/**
 * PLS playlist for WiiMC / WiiMC-SS Online Media.
 *
 * Based on Komfudo/WiiMC-IPTV-Streamer-and-Renderer (GPL), adapted for
 * config-driven channels and LAN use with WiiMC-SS.
 */
header('Content-Type: text/plain; charset=UTF-8');

$host = $_SERVER['HTTP_HOST'] ?? '127.0.0.1:8081';
$base = 'http://' . $host;
$channels = require __DIR__ . '/config.php';

echo "[playlist]\n";
echo 'NumberOfEntries=' . count($channels) . "\n";

$i = 1;
foreach ($channels as $channel) {
    if (empty($channel['title']) || empty($channel['url'])) {
        continue;
    }

    $render = $base . '/render/?site=iptv&q=' . rawurlencode($channel['url']);
    echo 'File' . $i . '=' . $render . "\n";
    echo 'Title' . $i . '=' . $channel['title'] . "\n";
    echo 'Length' . $i . "=9999999\n";
    $i++;
}
