<?php
/**
 * Top-level PLS: one entry per group-title (acts like folders in WiiMC).
 * Opening an entry loads /group.php?name=... which lists that group's channels.
 */
require __DIR__ . '/lib.php';

$host = $_SERVER['HTTP_HOST'] ?? '127.0.0.1:8081';
$base = 'http://' . $host;
$config = require __DIR__ . '/config.php';
$loaded = iptv_load_all_channels($config);

if ($loaded['channels'] === []) {
    $msg = $loaded['error'] ? $loaded['error'] : 'No channels found';
    $msg = substr(str_replace(["\r", "\n", '='], ' ', $msg), 0, 80);
    iptv_emit_pls([[
        'file' => $base . '/status.php',
        'title' => $msg,
        'length' => 0,
    ]]);
    exit;
}

$groups = iptv_filter_groups(
    iptv_group_map($loaded['channels']),
    (string)($config['group_filter'] ?? '')
);

if ($groups === []) {
    iptv_emit_pls([[
        'file' => $base . '/status.php',
        'title' => 'No groups matched group_filter',
        'length' => 0,
    ]]);
    exit;
}

$entries = [];
foreach ($groups as $name => $channels) {
    $count = count($channels);
    $entries[] = [
        'file' => $base . '/group.php?name=' . rawurlencode($name),
        'title' => $name . ' (' . $count . ')',
        'length' => 9999999,
    ];
}

iptv_emit_pls($entries);
