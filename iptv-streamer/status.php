<?php
require __DIR__ . '/lib.php';
header('Content-Type: text/plain; charset=UTF-8');

$config = require __DIR__ . '/config.php';
$loaded = iptv_load_channels($config);

echo "source_playlist=" . ($config['source_playlist'] ?? '') . "\n";
echo "channel_count=" . count($loaded['channels']) . "\n";
echo "from_cache=" . ($loaded['from_cache'] ? 'yes' : 'no') . "\n";
echo "max_channels=" . ($config['max_channels'] ?? '') . "\n";
echo "group_filter=" . ($config['group_filter'] ?? '') . "\n";
echo "cache_file=" . iptv_cache_path() . "\n";
echo "error=" . ($loaded['error'] ?? '') . "\n";

if (!empty($loaded['channels'][0])) {
    echo "first_title=" . $loaded['channels'][0]['title'] . "\n";
    echo "first_url=" . $loaded['channels'][0]['url'] . "\n";
}
