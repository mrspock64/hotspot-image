<?php
// Radio Status module -- TX/RX/idle state of the radio itself, not the
// reflector network (that's Talkgroup/Reflector Activity). Backed by the
// shared dashboard/include/svxlink_log_state.php parser (one tail +
// regex pass shared across every log-based module) -- see that file's
// header comment for the full story, including the crash-safety fix
// originally built here (stopping svxlink mid-transmission never logs a
// matching "OFF" line, and a fresh process never logs one either to
// contradict a stale pre-crash line still in the tail window -- both
// handled by the shared parser's own restart-banner reset now, not
// duplicated in this file).
header('Content-Type: application/json');

require_once __DIR__ . '/../../dashboard/include/svxlink_log_state.php';

$s = getSvxlinkLogState();

if (!$s['svxlink_active']) {
    echo json_encode(['state' => 'offline', 'tx' => null, 'rx' => null]);
    exit;
}

$txOn = $s['tx']['on'] ?? false;
$rxOpen = $s['rx']['open'] ?? false;

// TX takes priority over RX for the headline state -- if the radio is
// keying up, that's the thing worth surfacing first, even if the
// squelch also happens to still show open (typical during a local
// repeat/relay).
$state = $txOn ? 'tx' : ($rxOpen ? 'rx' : 'idle');

echo json_encode([
    'state' => $state,
    'tx' => $s['tx'] ? ['on' => $txOn, 'seconds_ago' => max(0, $s['now'] - $s['tx']['at'])] : null,
    'rx' => $s['rx'] ? ['open' => $rxOpen, 'signal' => $s['rx']['signal'], 'seconds_ago' => max(0, $s['now'] - $s['rx']['at'])] : null,
]);
