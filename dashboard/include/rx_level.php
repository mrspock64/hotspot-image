<?php
// Tiny JSON endpoint for the header's live RX level meter. Reads the peak
// level lib/rx-monitor/tail_qso_recorder.py writes to /dev/shm while
// SvxLink's QSO Recorder has a recording open (same mechanism RX Monitor's
// own audio stream uses -- see that script for why). The recorder is off
// by default (QSO Log page toggle), so with it off this always reports 0,
// same as RX Monitor itself being silent.
header('Content-Type: application/json');

const RX_LEVEL_FILE = '/dev/shm/hotspot_rx_level';
// filemtime() truncates to whole seconds while microtime(true) is
// fractional, so a file written 0.05s ago can already look ~1s old
// depending where in the current second the write landed -- confirmed
// live, a level written moments earlier was reported stale immediately.
// 1.9s absorbs that truncation error (up to ~1s) while still dropping the
// meter to 0 well within a second of the recorder actually going idle
// (which rewrites this file every 0.1s while active).
const RX_LEVEL_MAX_AGE = 1.9; // seconds -- older than this means "not currently receiving"

$level = 0;
if (is_file(RX_LEVEL_FILE) && (microtime(true) - filemtime(RX_LEVEL_FILE)) < RX_LEVEL_MAX_AGE) {
    $level = (int)trim((string)@file_get_contents(RX_LEVEL_FILE));
    $level = max(0, min(100, $level));
}
echo json_encode(['level' => $level]);
