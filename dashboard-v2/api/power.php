<?php
// Power module -- SvxLink service control, performance mode (Turbo/
// Guru), high-temperature protection settings, and device-level power
// actions. Reuses production's own dashboard/power/index.php logic
// directly: perf_mode.php's getPerfMode()/setPerfMode()/isPiZero2W(),
// inisync.php's getLoadMonitorTempThreshold()/
// getLoadMonitorAutoStopSvxlink()/getLoadMonitorAutoAlertTx() + a direct
// iniSyncUpdateSection() call to save them (production's own page does
// the same -- no dedicated setter wraps just these three keys there
// either, same as QSO Log Settings' own save_load_monitor action).
//
// Confirmation dialogs live client-side only on ?action=restart_device
// and ?action=poweroff -- matches production's own risk calibration
// (dashboard/power/index.php's only two confirm() dialogs). Service
// start/stop/restart, perf mode, and the temperature settings save fire
// immediately, same as production.
header('Content-Type: application/json');

require_once __DIR__ . '/../../dashboard/include/perf_mode.php';
require_once __DIR__ . '/../../dashboard/include/inisync.php';

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

if ($action === 'service') {
    $op = $_GET['op'] ?? ($_POST['op'] ?? '');
    if (!in_array($op, ['start', 'stop', 'restart'], true)) {
        http_response_code(400);
        echo json_encode(['error' => 'op must be start, stop, or restart']);
        exit;
    }
    // Backgrounded, same as production -- PHP responds immediately, the
    // caller re-polls the default (no-action) response to see the real
    // state land a moment later.
    exec('sudo service svxlink ' . escapeshellarg($op) . ' > /dev/null 2>&1 &');
    echo json_encode(['requested' => $op]);
    exit;
}

if ($action === 'perf') {
    $mode = (string)($_GET['mode'] ?? ($_POST['mode'] ?? ''));
    try {
        setPerfMode($mode);
        echo json_encode(['perf_mode' => $mode]);
    } catch (Throwable $e) {
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'save_temp') {
    $body = json_decode((string)file_get_contents('php://input'), true) ?: [];
    $threshold = (string)($body['threshold'] ?? '');
    if (!ctype_digit($threshold) || (int)$threshold < 40 || (int)$threshold > 85) {
        http_response_code(400);
        echo json_encode(['error' => 'Threshold must be a number of degrees C between 40 and 85.']);
        exit;
    }
    iniSyncUpdateSection('/etc/svxlink/svxlink.conf', 'Dashboard', [
        'LOAD_MONITOR_TEMP_THRESHOLD_C' => $threshold,
        'LOAD_MONITOR_AUTO_STOP_SVXLINK' => !empty($body['auto_stop']) ? '1' : '0',
        'LOAD_MONITOR_AUTO_ALERT_TX' => !empty($body['auto_alert']) ? '1' : '0',
    ]);
    echo json_encode(['saved' => true]);
    exit;
}

if ($action === 'restart_device') {
    exec('sudo shutdown -r now > /dev/null 2>&1 &');
    echo json_encode(['requested' => 'restart_device']);
    exit;
}

if ($action === 'poweroff') {
    exec('sudo shutdown -h now > /dev/null 2>&1 &');
    echo json_encode(['requested' => 'poweroff']);
    exit;
}

$svxlinkActive = trim((string)@shell_exec('systemctl is-active svxlink 2>/dev/null')) === 'active';

// Same /sys/class/thermal read api/node.php already does -- not required
// from there since node.php does a lot else besides temp and this is
// only three lines, same class of one-liner the svxlink_active check
// above is already duplicated as in several other api/*.php files.
$tempC = null;
if (is_file('/sys/class/thermal/thermal_zone0/temp')) {
    $raw = trim((string)@file_get_contents('/sys/class/thermal/thermal_zone0/temp'));
    if ($raw !== '') {
        $tempC = round(abs((float)$raw) / 1000);
    }
}

echo json_encode([
    'svxlink_active' => $svxlinkActive,
    'temp_c' => $tempC,
    'perf_mode' => getPerfMode(),
    'is_pi_zero_2w' => isPiZero2W(),
    'temp_threshold' => getLoadMonitorTempThreshold(),
    'auto_stop' => getLoadMonitorAutoStopSvxlink(),
    'auto_alert' => getLoadMonitorAutoAlertTx(),
]);
