<?php
// QSO Log Settings module -- step 2 of the QSO Log work, after play/
// delete. Reuses production's own dashboard/qsolog/index.php settings
// form logic directly: getQsoRecorderSettings()/saveQsoRecorderSettings()
// and getLoadMonitorAutoPause() (dashboard/include/qso_recorder.php +
// inisync.php), same validation rules, same two-form split (recorder
// settings vs. the load-monitor auto-pause toggle) production's page
// already has -- not reimplemented here.
//
// GET (no action): current settings + the TG name list (for labeling the
// "record only these talkgroups" checkboxes).
//
// ?action=save: recorder settings (on/off, disk limit, QSO gap, max
// recordings, TG filter). On/off, TG filter, and max recordings apply
// immediately (DTMF + a live prune); disk limit and QSO gap are only
// read by SvxLink at startup, so those two need a restart to actually
// take effect -- same caveat production's own save message states,
// repeated in this endpoint's response.
//
// ?action=save_load_monitor: the auto-pause-under-load toggle, a single
// svxlink.conf key (LOAD_MONITOR_AUTO_PAUSE_QSO) lib/load-monitor/
// monitor.sh reads directly -- no restart needed, takes effect on that
// script's own next check cycle.
header('Content-Type: application/json');

require_once __DIR__ . '/../../dashboard/include/qso_recorder.php';
require_once __DIR__ . '/../../dashboard/include/tgdb_store.php';
require_once __DIR__ . '/../../dashboard/include/inisync.php';

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

if ($action === 'save') {
    $body = json_decode((string)file_get_contents('php://input'), true) ?: [];
    $active = !empty($body['active']);
    $maxDirsize = (string)($body['max_dirsize'] ?? '');
    $qsoTimeout = (string)($body['qso_timeout'] ?? '');
    $maxRecordings = (string)($body['max_recordings'] ?? '0');
    $recordOnly = array_values(array_filter((array)($body['record_only_tgs'] ?? []), fn($tg) => ctype_digit((string)$tg)));

    if (!ctype_digit($maxDirsize) || (int)$maxDirsize < 100) {
        http_response_code(400);
        echo json_encode(['error' => 'Disk limit must be a number of megabytes, at least 100.']);
        exit;
    }
    if (!ctype_digit($qsoTimeout) || (int)$qsoTimeout < 1) {
        http_response_code(400);
        echo json_encode(['error' => 'QSO gap must be a number of seconds, at least 1.']);
        exit;
    }
    if (!ctype_digit($maxRecordings)) {
        http_response_code(400);
        echo json_encode(['error' => 'Max recordings must be a number (0 for no limit).']);
        exit;
    }

    try {
        saveQsoRecorderSettings($active, (int)$maxDirsize, (int)$qsoTimeout, (int)$maxRecordings, $recordOnly);
        echo json_encode([
            'saved' => true,
            'message' => 'Saved. On/off, the talkgroup filter, and max recordings apply immediately -- the disk limit and QSO gap need a SvxLink restart to take effect.',
        ]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'save_load_monitor') {
    $body = json_decode((string)file_get_contents('php://input'), true) ?: [];
    iniSyncUpdateSection('/etc/svxlink/svxlink.conf', 'Dashboard', [
        'LOAD_MONITOR_AUTO_PAUSE_QSO' => !empty($body['auto_pause']) ? '1' : '0',
    ]);
    echo json_encode(['saved' => true]);
    exit;
}

if ($action === 'toggle') {
    // For the topbar indicator's click-to-toggle -- reads the current
    // settings itself and flips only 'active', saving every other field
    // straight back unchanged. Deliberately does NOT take a caller-
    // supplied disk limit/QSO gap/max recordings/TG filter: a topbar
    // chip has no business needing to know those to flip one switch, and
    // sending them back verbatim here means it never risks clobbering
    // them with stale values the chip happened to be caching.
    $current = getQsoRecorderSettings();
    $newActive = !$current['active'];
    try {
        saveQsoRecorderSettings($newActive, $current['max_dirsize'], $current['qso_timeout'], $current['max_recordings'], $current['record_only_tgs']);
        echo json_encode(['active' => $newActive]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

$settings = getQsoRecorderSettings();
$names = loadTgDb();
ksort($names, SORT_NUMERIC);

echo json_encode([
    'active' => $settings['active'],
    'max_dirsize' => $settings['max_dirsize'],
    'qso_timeout' => $settings['qso_timeout'],
    'max_recordings' => $settings['max_recordings'],
    'record_only_tgs' => $settings['record_only_tgs'],
    'load_monitor_auto_pause' => getLoadMonitorAutoPause(),
    'tg_names' => $names,
]);
