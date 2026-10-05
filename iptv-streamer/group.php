<?php
/**
 * Per-group channel playlist for WiiMC.
 * Example: /group.php?name=ENGLISH%20%7C%20USA%20LOCAL
 */
require __DIR__ . '/lib.php';

$host = $_SERVER['HTTP_HOST'] ?? '127.0.0.1:8081';
$base = 'http://' . $host;
$config = require __DIR__ . '/config.php';
$groupName = trim((string)($_GET['name'] ?? ''));

if ($groupName === '') {
    iptv_emit_pls([[
        'file' => $base . '/',
        'title' => 'Missing group name',
        'length' => 0,
    ]]);
    exit;
}

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

$channels = iptv_channels_in_group($loaded['channels'], $groupName);
$max = (int)($config['max_channels'] ?? 0);
if ($max > 0 && count($channels) > $max) {
    $channels = array_slice($channels, 0, $max);
}

if ($channels === []) {
    iptv_emit_pls([[
        'file' => $base . '/',
        'title' => 'No channels in group',
        'length' => 0,
    ]]);
    exit;
}

$entries = [];
foreach ($channels as $channel) {
    $entries[] = [
        'file' => $base . '/render/?site=iptv&q=' . rawurlencode($channel['url']),
        'title' => $channel['title'],
        'length' => 9999999,
    ];
}

iptv_emit_pls($entries);
