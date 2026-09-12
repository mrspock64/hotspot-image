<?php
// Lets the RX Monitor button work even when QSO logging is off, by
// temporarily turning the QSO Recorder on just to feed RX Monitor's own
// audio tap (lib/rx-monitor/tail_qso_recorder.py) -- without actually
// keeping any recordings. See docs comment in top_menu.php's script for
// the button side; this is the two actions it calls.
//
// MONITOR_ONLY_FLAG in /dev/shm (RAM, not the SD card -- same reasoning
// as tail_qso_recorder.py's own LEVEL_FILE) holds a random token, not
// just a boolean: lib/rx-monitor/tag_and_encode.py (ENCODER_CMD) checks
// for its mere existence to skip encoding/tagging and just delete the
// finished recording instead of keeping it, but the *token* is what lets
// stop_monitor_only.sh (started here, backgrounded with a grace delay)
// tell "is this still the same monitor-only session I was asked to end,
// or did a new start already replace it" -- a plain flag file alone
// can't distinguish a fresh restart from the session it was told to
// clean up, which would otherwise let a quick stop-then-start race turn
// the recorder back off right after a new session turned it on.
header('Content-Type: application/json');

require_once __DIR__ . '/qso_recorder.php';

const MONITOR_ONLY_FLAG = '/dev/shm/hotspot_rx_monitor_only';
const STOP_GRACE_SECONDS = 8;

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

if ($action === 'start') {
    $settings = getQsoRecorderSettings();
    if ($settings['active']) {
        // Already really on (the user's own saved setting) -- nothing to
        // do, and definitely not something a "stop" later should turn off.
        echo json_encode(['mode' => 'already-active']);
        exit;
    }
    $token = bin2hex(random_bytes(8));
    file_put_contents(MONITOR_ONLY_FLAG, $token);
    shell_exec('/usr/sbin/hotspot_dtmf ' . escapeshellarg(QSO_RECORDER_DTMF_CMD . '1#'));
    echo json_encode(['mode' => 'monitor-only']);
    exit;
}

if ($action === 'stop') {
    if (!is_file(MONITOR_ONLY_FLAG)) {
        // Either it was already really on (nothing for us to turn off)
        // or there was never a monitor-only session to end.
        echo json_encode(['scheduled' => false]);
        exit;
    }
    $token = trim((string)@file_get_contents(MONITOR_ONLY_FLAG));
    // /opt/rx-monitor/, not a path relative to this file -- this file is
    // served from /var/www/html (a *copy* of dashboard/, see lib/install-
    // dashboard.sh), not the git checkout, so a __DIR__-relative path
    // into lib/ would resolve outside /var/www/html entirely and 404.
    // /opt/rx-monitor/ is the same fixed runtime install path
    // tag_and_encode.py itself already lives at (lib/install-rx-
    // monitor.sh installs both there).
    exec('bash /opt/rx-monitor/stop_monitor_only.sh ' . escapeshellarg($token) . ' ' . STOP_GRACE_SECONDS . ' > /dev/null 2>&1 &');
    echo json_encode(['scheduled' => true]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'action must be start or stop']);
