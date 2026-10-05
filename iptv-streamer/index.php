<?php
/**
 * PLS playlist for WiiMC / WiiMC-SS Online Media.
 */
require __DIR__ . '/lib.php';

header('Content-Type: text/plain; charset=UTF-8');

$host = $_SERVER['HTTP_HOST'] ?? '127.0.0.1:8081';
$base = 'http://' . $host;
$config = require __DIR__ . '/config.php';
$loaded = iptv_load_channels($config);
$channels = $loaded['channels'];

echo "[playlist]\n";
echo 'NumberOfEntries=' . count($channels) . "\n";

if ($channels === []) {
    $msg = $loaded['error'] ? $loaded['error'] : 'No channels found';
    // Keep it short for the Wii UI
    $msg = substr(str_replace(["\r", "\n", '='], ' ', $msg), 0, 80);
    echo "File1=" . $base . "/status.php\n";
    echo "Title1=" . $msg . "\n";
    echo "Length1=0\n";
    exit;
}

$i = 1;
foreach ($channels as $channel) {
    $render = $base . '/render/?site=iptv&q=' . rawurlencode($channel['url']);
    echo 'File' . $i . '=' . $render . "\n";
    echo 'Title' . $i . '=' . str_replace(["\r", "\n", '='], ' ', $channel['title']) . "\n";
    echo 'Length' . $i . "=9999999\n";
    $i++;
}
