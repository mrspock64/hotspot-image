<?php
// Reflector Activity module. Backed by the shared dashboard/include/
// svxlink_log_state.php parser (one tail + regex pass shared across
// every log-based module) -- see that file's header comment for the
// full story. Answers a different question than Talkgroup: Talkgroup
// shows "what's happening right now" (a glance), this shows "what just
// happened" (a scrollable recent history) -- separate module rather
// than growing Talkgroup's own job, per the one-panel-one-purpose
// pattern the rest of dashboard-v2 follows.
header('Content-Type: application/json');

require_once __DIR__ . '/../../dashboard/include/svxlink_log_state.php';

// Deliberately more than the panel shows without scrolling/resizing --
// the card's default height only fits a handful of rows, but the
// .activity-list is now resizable (drag handle, see tokens.css +
// js/resizable-list.js) and a viewer who drags it taller should find
// real history there, not just whitespace. 50 costs nothing extra to
// compute (same array_filter/array_slice either way), it just changes
// how far back a stretched-out card can actually see.
const EVENT_LIMIT = 50;

$s = getSvxlinkLogState();

// Only the event types this module's own history cares about -- tx/rx
// blips are Radio Status's job, not shown here. The shared parser's
// events list carries more types than any one module needs; each module
// filters to its own contract.
$wanted = ['talker_start', 'talker_stop', 'node_joined', 'node_left', 'tg_selected'];
$events = array_values(array_filter($s['events'], fn($e) => in_array($e['type'], $wanted, true)));

// Most recent first, capped to EVENT_LIMIT -- this is a glanceable recent
// history, not a full log viewer.
$events = array_reverse($events);
$events = array_slice($events, 0, EVENT_LIMIT);

foreach ($events as &$event) {
    $event['seconds_ago'] = max(0, $s['now'] - $event['at']);
    unset($event['at']);
}
unset($event);

echo json_encode(['events' => $events]);
