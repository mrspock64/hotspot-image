<?php
// RX Monitor module's status/control endpoint. Deliberately no
// collector/cache file like the other three modules -- everything here is
// already cheap enough to do live per request: a short-timeout TCP probe
// of the existing production audio proxy (:8080, lib/rx-monitor/proxy.js
// -- this module adds no new backend of its own, it's a client for that
// already-running service) and qso_recorder.php's own
// listQsoRecordings(), a plain glob() over the recordings directory.
//
// The actual waterfall is driven client-side straight off that same
// ws://host:8080 stream (see modules/rxmonitor/panel.js) -- the default
// (no action) response here only answers "is there anything to connect
// to, and is a QSO in progress right now" so the panel can show a sane
// state before/without a successful WebSocket handshake.
//
// ?action=start / ?action=stop: the "Lyssna" button's monitor-only
// control, proxied straight through to production's own
// dashboard/include/rx_monitor_toggle.php rather than reimplemented here
// -- same /dev/shm flag file, same DTMF codes, same delayed-stop script.
// There's only one real SvxLink instance and QSO Recorder underneath
// either UI (this preview or the production dashboard), so whichever one
// asks for monitor-only mode is asking the same underlying thing.
header('Content-Type: application/json');

$action = $_GET['action'] ?? ($_POST['action'] ?? '');
if ($action === 'start' || $action === 'stop') {
    require __DIR__ . '/../../dashboard/include/rx_monitor_toggle.php';
    exit;
}

require_once __DIR__ . '/../../dashboard/include/qso_recorder.php';

function proxyReachable(int $port, float $timeoutSec): bool
{
    $conn = @fsockopen('127.0.0.1', $port, $errno, $errstr, $timeoutSec);
    if ($conn === false) {
        return false;
    }
    fclose($conn);
    return true;
}

$proxyUp = proxyReachable(8080, 0.3);
$recordings = listQsoRecordings();

echo json_encode([
    'proxy_reachable' => $proxyUp,
    'ws_port' => 8080,
    'recording' => $recordings['inProgress'] !== null,
    'updated_at' => date('c'),
]);
