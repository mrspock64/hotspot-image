<?php
// Reflector Activity module. Same /var/log/svxlink source as
// api/talkgroup.php (see that file's comment for the full story on why
// this log has everything needed and no new backend was required), but
// answers a different question: Talkgroup shows "what's happening right
// now" (a glance), this shows "what just happened" (a scrollable recent
// history) -- separate module rather than growing Talkgroup's own job,
// per the one-panel-one-purpose pattern the rest of dashboard-v2 follows.
header('Content-Type: application/json');

// Same timezone trap as talkgroup.php: SvxLink's log timestamps are
// plain localtime() with no timezone in the string, and this node's PHP
// defaults to UTC while the system is actually Europe/Brussels -- see
// that file's header comment for how this was found live (every "ago"
// value read as a permanent, wrong "0s ago" otherwise).
$systemTz = trim((string)@file_get_contents('/etc/timezone'));
if ($systemTz !== '' && in_array($systemTz, timezone_identifiers_list(), true)) {
    date_default_timezone_set($systemTz);
}

const LOG_FILE = '/var/log/svxlink';
const TAIL_LINES = 3000;
const EVENT_LIMIT = 25;

function tailLines(string $path, int $n): array
{
    $out = [];
    exec('tail -n ' . (int)$n . ' ' . escapeshellarg($path) . ' 2>/dev/null', $out);
    return $out;
}

function parseLogTimestamp(string $s): ?int
{
    $ts = strtotime($s);
    return $ts !== false ? $ts : null;
}

$lines = tailLines(LOG_FILE, TAIL_LINES);

$events = [];
foreach ($lines as $line) {
    $event = null;
    if (preg_match('/^(.+?): ReflectorLogic: Talker start on TG #(\d+): (\S+)/', $line, $m)) {
        $event = ['type' => 'talker_start', 'tg' => (int)$m[2], 'callsign' => $m[3]];
    } elseif (preg_match('/^(.+?): ReflectorLogic: Talker stop on TG #(\d+): (\S+)/', $line, $m)) {
        $event = ['type' => 'talker_stop', 'tg' => (int)$m[2], 'callsign' => $m[3]];
    } elseif (preg_match('/^(.+?): ReflectorLogic: Node joined: (\S+)/', $line, $m)) {
        $event = ['type' => 'node_joined', 'callsign' => $m[2]];
    } elseif (preg_match('/^(.+?): ReflectorLogic: Node left: (\S+)/', $line, $m)) {
        $event = ['type' => 'node_left', 'callsign' => $m[2]];
    } elseif (preg_match('/^(.+?): ReflectorLogic: Selecting TG #(\d+)/', $line, $m)) {
        $event = ['type' => 'tg_selected', 'tg' => (int)$m[2]];
    }
    if ($event === null) {
        continue;
    }
    $at = parseLogTimestamp($m[1]);
    if ($at === null) {
        continue;
    }
    $event['at'] = $at;
    $events[] = $event;
}

// Most recent first, capped to EVENT_LIMIT -- this is a glanceable recent
// history, not a full log viewer.
$events = array_reverse($events);
$events = array_slice($events, 0, EVENT_LIMIT);

$now = time();
foreach ($events as &$event) {
    $event['seconds_ago'] = max(0, $now - $event['at']);
    unset($event['at']);
}
unset($event);

echo json_encode(['events' => $events]);
