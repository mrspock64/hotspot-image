<?php
// DTMF Dialer module -- combines production's two separate raw-DTMF UIs
// into one compact module: dashboard/dtmf/index.php's numeric keypad
// (one digit per button press) and dashboard/include/buttons.php's
// free-text "DTMF command" box (send a typed string as-is). No
// persistence, no reviewable list -- deliberately separate from the
// Quick Buttons module, which is exactly that. This is the raw,
// type-anything escape hatch, same as production's two dialer UIs were.
//
// Both actions are a single shell_exec, never doubled -- same
// "arbitrary/toggle command sent twice could have a real, wrong second
// effect" reasoning production's own buttons.php documents for its
// free-text box (unlike TG-select's deliberate double-send).
header('Content-Type: application/json');

const DTMF_CHARS = '0123456789*#ABCD';

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

if ($action === 'key') {
    $digit = strtoupper((string)($_GET['digit'] ?? ($_POST['digit'] ?? '')));
    if (strlen($digit) !== 1 || strpos(DTMF_CHARS, $digit) === false) {
        http_response_code(400);
        echo json_encode(['error' => 'digit must be one of 0-9 * # A-D']);
        exit;
    }
    shell_exec('/usr/sbin/hotspot_dtmf ' . escapeshellarg($digit));
    echo json_encode(['sent' => $digit]);
    exit;
}

if ($action === 'send') {
    $code = strtoupper(trim((string)($_GET['code'] ?? ($_POST['code'] ?? ''))));
    if ($code === '' || strspn($code, DTMF_CHARS) !== strlen($code)) {
        http_response_code(400);
        echo json_encode(['error' => 'code must only contain 0-9 * # A-D']);
        exit;
    }
    shell_exec('/usr/sbin/hotspot_dtmf ' . escapeshellarg($code));
    echo json_encode(['sent' => $code]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'action must be key or send']);
