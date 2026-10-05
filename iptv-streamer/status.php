<?php
require __DIR__ . '/lib.php';
header('Content-Type: text/plain; charset=UTF-8');

$config = require __DIR__ . '/config.php';
$loaded = iptv_load_all_channels($config);
$groups = iptv_filter_groups(
    iptv_group_map($loaded['channels']),
    (string)($config['group_filter'] ?? '')
);

echo "source_playlist=" . ($config['source_playlist'] ?? '') . "\n";
echo "channel_count=" . count($loaded['channels']) . "\n";
echo "group_count=" . count($groups) . "\n";
echo "from_cache=" . ($loaded['from_cache'] ? 'yes' : 'no') . "\n";
echo "max_channels_per_group=" . ($config['max_channels'] ?? '') . "\n";
echo "group_filter=" . ($config['group_filter'] ?? '') . "\n";
echo "cache_file=" . iptv_cache_path() . "\n";
echo "error=" . ($loaded['error'] ?? '') . "\n";
echo "\n";

$i = 0;
foreach ($groups as $name => $channels) {
    echo 'group[' . $i . ']=' . $name . ' (' . count($channels) . ")\n";
    $i++;
    if ($i >= 30) {
        echo "...\n";
        break;
    }
}
