<?php
// Monitored Talkgroups module -- this node's own TG "plan" (MONITOR_TGS
// in svxlink.conf's [ReflectorLogic], with priority markers, and their
// friendly names), each cross-referenced with its own most recent
// activity from /var/log/svxlink. Distinct from the reflector-wide
// Reflector Activity module: this answers "how are the talkgroups I
// actually care about doing", not "what's happening on the reflector at
// large".
//
// Reuses dashboard/include/tgdb_store.php's loadTgDb()/
// loadMonitoredTgNumbers()/loadMonitoredTgPriorities() directly (a real
// require(), not duplicated logic) -- unlike Setup's readCurrent(), this
// lives in a proper dashboard/include/*.php file already, so there's
// nothing to duplicate.
//
// ?action=select&tg=<n>: switches the node's active talkgroup, reusing
// production's own sendTgSelectDtmf() (dashboard/include/tg_select.php,
// extracted from buttons.php specifically so this endpoint could require
// it without pulling in that file's page template) -- same "91<tg>#" DTMF
// command and double-send workaround the production TG page's own "A"
// (cell_tower) button sends. Only accepts TG numbers already on this
// node's own MONITOR_TGS list, not an arbitrary string -- this endpoint
// has no separate "add a new favorite TG" concern, that stays on the
// production TG Names page.
header('Content-Type: application/json');

require_once __DIR__ . '/../../dashboard/include/tgdb_store.php';

$action = $_GET['action'] ?? ($_POST['action'] ?? '');
if ($action === 'select') {
    require_once __DIR__ . '/../../dashboard/include/tg_select.php';
    $tg = $_GET['tg'] ?? ($_POST['tg'] ?? '');
    $monitored = loadMonitoredTgNumbers();
    if (!ctype_digit((string)$tg) || !in_array((string)$tg, $monitored, true)) {
        http_response_code(400);
        echo json_encode(['error' => 'tg must be a number on the monitored list']);
        exit;
    }
    sendTgSelectDtmf('91' . $tg . '#');
    echo json_encode(['selected' => (string)$tg]);
    exit;
}

// Same timezone trap as api/talkgroup.php and api/reflector-activity.php.
$systemTz = trim((string)@file_get_contents('/etc/timezone'));
if ($systemTz !== '' && in_array($systemTz, timezone_identifiers_list(), true)) {
    date_default_timezone_set($systemTz);
}

const LOG_FILE = '/var/log/svxlink';
const TAIL_LINES = 3000;

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

$monitoredTgs = loadMonitoredTgNumbers();
$priorities = loadMonitoredTgPriorities();
$names = loadTgDb();

// Most recent talker event per TG number, scanning the same tail window
// the other two log-based modules use. Only tracked for TGs actually on
// the monitored list -- no point remembering activity for TGs this node
// doesn't care about.
$lastActivity = []; // tg (string) => ['type','callsign','at']
$selectedTg = null; // this node's currently active TG, same log line api/talkgroup.php reads
$lines = tailLines(LOG_FILE, TAIL_LINES);
foreach ($lines as $line) {
    if (preg_match('/^(.+?): ReflectorLogic: Selecting TG #(\d+)/', $line, $m)) {
        $selectedTg = $m[2];
        continue;
    }
    if (!preg_match('/^(.+?): ReflectorLogic: Talker (start|stop) on TG #(\d+): (\S+)/', $line, $m)) {
        continue;
    }
    $tg = $m[3];
    if (!in_array($tg, $monitoredTgs, true)) {
        continue;
    }
    $at = parseLogTimestamp($m[1]);
    if ($at === null) {
        continue;
    }
    $lastActivity[$tg] = ['type' => $m[2], 'callsign' => $m[4], 'at' => $at];
}

$now = time();
$result = [];
foreach ($monitoredTgs as $tg) {
    $activity = $lastActivity[$tg] ?? null;
    $result[] = [
        'tg' => $tg,
        'name' => $names[$tg] ?? null,
        'priority' => $priorities[$tg] ?? 0,
        'activity' => $activity ? [
            'callsign' => $activity['callsign'],
            'active' => $activity['type'] === 'start',
            'seconds_ago' => max(0, $now - $activity['at']),
        ] : null,
    ];
}

// Most recently active first (no activity in this window sorts last), so
// the talkgroups actually seeing traffic float to the top rather than
// staying in whatever order MONITOR_TGS happens to list them.
usort($result, function ($a, $b) {
    $aAt = $a['activity']['seconds_ago'] ?? PHP_INT_MAX;
    $bAt = $b['activity']['seconds_ago'] ?? PHP_INT_MAX;
    return $aAt <=> $bAt;
});

echo json_encode(['talkgroups' => $result, 'selected_tg' => $selectedTg]);
