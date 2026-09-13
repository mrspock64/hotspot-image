<?php
// Talkgroup/reflector-info module. Backed by the shared
// dashboard/include/svxlink_log_state.php parser (one tail + regex pass
// shared across every log-based module) -- see that file's header
// comment for why a shared parser exists.
//
// This module's own bug, and the reason the shared parser exists at all:
// "talker" used to be tracked as "last talker event anywhere in the tail
// window", completely independent of "which TG is currently selected" --
// confirmed live 2026-09-14, switching TGs left this showing a stale
// talker from a *different* TG than the one the header chip said was
// linked. Now it's looked up as talker_by_tg[selected_tg] specifically:
// no talker on the currently-linked TG yet means "No recent talker
// activity", not a leftover from wherever the node used to be.
header('Content-Type: application/json');

require_once __DIR__ . '/../../dashboard/include/svxlink_log_state.php';

$s = getSvxlinkLogState();
$selectedTg = $s['selected_tg'] !== null ? (int)$s['selected_tg'] : null;

$talkerEvent = $selectedTg !== null ? ($s['talker_by_tg'][(string)$selectedTg] ?? null) : null;

$talker = null;
if ($talkerEvent !== null) {
    $talker = [
        'tg' => $selectedTg,
        'callsign' => $talkerEvent['callsign'],
        'active' => $talkerEvent['type'] === 'start',
        'since_seconds' => max(0, $s['now'] - $talkerEvent['at']),
    ];
}

echo json_encode([
    'selected_tg' => $selectedTg,
    'talker' => $talker,
    // Only a rough count over the tailed window, not a true total (a
    // node that joined before the window started is never counted) --
    // good enough as "roughly how busy is the reflector", not
    // authoritative.
    'nodes_online_approx' => count($s['online_nodes']),
]);
