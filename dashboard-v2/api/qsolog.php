<?php
// Thin read-through, same shape as api/signal.php -- all the actual work
// (listing recordings via production's own listQsoRecordings()/
// qsoRecordingInfo(), probing real durations with ffprobe) happens in
// lib/dashboard-v2/collect-qsolog.sh on a 30s timer. See that script for
// why: querying the recordings directory and shelling out to ffprobe per
// file isn't something to do synchronously on every panel poll.
//
// ?action=delete&file=<name> / ?action=delete_all: reuse production's own
// deleteQsoRecording()/deleteAllQsoRecordings() (dashboard/include/
// qso_recorder.php) -- same filename validation and sudo-rm path that
// file's own QSO Log page already uses, not reimplemented here. Neither
// action needs the collector to re-run before the *next poll* reflects
// the change -- the deleted file is just gone from disk, and the
// collector's own 30s timer picks that up on its own next cycle same as
// any other recording change.
header('Content-Type: application/json');

require_once __DIR__ . '/../../dashboard/include/qso_recorder.php';

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

if ($action === 'delete') {
    $file = $_GET['file'] ?? ($_POST['file'] ?? '');
    try {
        deleteQsoRecording((string)$file);
        echo json_encode(['deleted' => $file]);
    } catch (Throwable $e) {
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'delete_all') {
    try {
        $n = deleteAllQsoRecordings();
        echo json_encode(['deleted_count' => $n]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

const STATE_FILE = '/var/cache/hotspot-image/dashboard-v2-qsolog.json';

if (!is_file(STATE_FILE)) {
    http_response_code(503);
    echo json_encode(['error' => 'No QSO log data yet -- collector may not have run its first cycle.']);
    exit;
}

echo file_get_contents(STATE_FILE);
