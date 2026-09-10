<?php
// Thin read-through, same shape as api/signal.php -- all the actual work
// (listing recordings via production's own listQsoRecordings()/
// qsoRecordingInfo(), probing real durations with ffprobe) happens in
// lib/dashboard-v2/collect-qsolog.sh on a 30s timer. See that script for
// why: querying the recordings directory and shelling out to ffprobe per
// file isn't something to do synchronously on every panel poll.
header('Content-Type: application/json');

const STATE_FILE = '/var/cache/hotspot-image/dashboard-v2-qsolog.json';

if (!is_file(STATE_FILE)) {
    http_response_code(503);
    echo json_encode(['error' => 'No QSO log data yet -- collector may not have run its first cycle.']);
    exit;
}

echo file_get_contents(STATE_FILE);
