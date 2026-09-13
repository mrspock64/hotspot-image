<?php
// Quick Buttons module -- production's front-page macro buttons
// (dashboard/include/buttons.php's button_0..N + the Buttons admin page,
// dashboard/include/buttons_store.php's loadButtons()/saveButtons()),
// reused directly here rather than duplicated: each button is a plain
// {label, dtmf, color}, stored as JSON, no restart involved anywhere in
// this module -- pressing a button and editing the list are both just a
// DTMF shell_exec / a JSON file write.
//
// Deliberately does NOT include production's free-text "DTMF command"
// box -- that's a different module (arbitrary input, not a saved,
// reviewable list of buttons), out of scope here.
//
// ?action=press&index=<i>: fires the DTMF for buttons[i] -- looked up
// server-side from the saved list, never a client-supplied DTMF string,
// so a press can only ever trigger a code that's already in the
// reviewable button list. Single send, NOT the TG-select double-send --
// matches production's own buttons.php comment: "NOT applied to
// dtmfsvx/macros ... an arbitrary or toggle-style command sent twice
// could have a real (and wrong) second effect."
//
// ?action=save: replaces the whole button list (add/edit/remove/reorder
// all go through this) via saveButtons() -- no restart, so every edit in
// the panel's edit mode saves immediately, unlike Monitored Talkgroups'
// batched monitor-list save.
// Colors are stored using production's own vocabulary (BUTTON_COLORS in
// buttons_store.php: green/blue/red/orange/purple), not a new one --
// this JSON file is shared with production's Buttons admin page (both
// read/write the exact same /etc/svxlink/dashboard_buttons.json via the
// exact same loadButtons()/saveButtons()), and saving a color name
// production's own BUTTON_COLORS check doesn't recognize would break its
// rendering there. The remap to this theme's actual palette (copper/
// cyan/ok/warn/crit) happens client-side in panel.js, display-only.
header('Content-Type: application/json');

require_once __DIR__ . '/../../dashboard/include/buttons_store.php';

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

if ($action === 'press') {
    $buttons = loadButtons();
    $index = $_GET['index'] ?? ($_POST['index'] ?? '');
    if (!ctype_digit((string)$index) || !isset($buttons[(int)$index])) {
        http_response_code(400);
        echo json_encode(['error' => 'index must refer to an existing button']);
        exit;
    }
    shell_exec('/usr/sbin/hotspot_dtmf ' . escapeshellarg($buttons[(int)$index]['dtmf']));
    echo json_encode(['pressed' => (int)$index]);
    exit;
}

if ($action === 'save') {
    $body = json_decode((string)file_get_contents('php://input'), true);
    $incoming = is_array($body) ? ($body['buttons'] ?? null) : null;
    if (!is_array($incoming)) {
        http_response_code(400);
        echo json_encode(['error' => 'body must be {"buttons": [{"label","dtmf","color"}, ...]}']);
        exit;
    }
    $clean = [];
    foreach ($incoming as $btn) {
        $label = trim((string)($btn['label'] ?? ''));
        $dtmf = trim((string)($btn['dtmf'] ?? ''));
        $color = (string)($btn['color'] ?? 'copper');
        if ($label === '' || $dtmf === '') {
            continue;
        }
        $clean[] = [
            'label' => $label,
            'dtmf' => $dtmf,
            'color' => in_array($color, BUTTON_COLORS, true) ? $color : 'green',
        ];
    }
    try {
        saveButtons($clean);
        echo json_encode(['buttons' => $clean]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['buttons' => loadButtons()]);
