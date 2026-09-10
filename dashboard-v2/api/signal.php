<?php
// Thin read-through, per docs/dashboard-v2-brief.md's module contract --
// all the actual work (parsing /proc/net/wireless, the gateway ping, the
// rolling history/loss-ring bookkeeping) happens in
// lib/dashboard-v2/collect-signal.sh on a 60s systemd timer, same division
// of labour as e.g. the production dashboard's rx_level.php reading a file
// lib/rx-monitor/tail_qso_recorder.py maintains rather than doing any of
// that work inline on each request. There's no existing dashboard/include/
// PHP module to wrap here (unlike, say, qso_recorder.php) -- WiFi signal
// is genuinely new -- so "thin wrapper" means thin over the collector's
// state file instead.
header('Content-Type: application/json');

const STATE_FILE = '/var/cache/hotspot-image/dashboard-v2-signal.json';

if (!is_file(STATE_FILE)) {
    http_response_code(503);
    echo json_encode(['error' => 'No signal data yet -- collector may not have run its first cycle.']);
    exit;
}

// Pass the collector's JSON straight through rather than decode+re-encode:
// this file's whole job is staying a thin wrapper, and re-encoding would
// only risk silently reshaping a field someday without either side
// noticing.
echo file_get_contents(STATE_FILE);
