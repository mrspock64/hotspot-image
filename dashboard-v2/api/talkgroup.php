<?php
// Talkgroup/reflector-info module. Real live source found for the "which
// talkgroup is active right now" data api/frequency.php's own comment
// said didn't exist yet: SvxLink's own /var/log/svxlink already logs it
// verbatim, same log tag_and_encode.py already tails for the exact same
// "Talker start on TG #<n>: <call>" line (see that file's TALKER_RE) --
// this module just reads more of it and adds "Selecting TG #<n>" (which
// TG this node's logic core currently has linked) and Node joined/left
// counts. No new backend daemon, no new state file -- cheap enough
// (~600KB/~8000 lines on svxlinkuhf) to tail and regex-scan fresh on
// every request, same cost class as frequency.php's own file reads, not
// the qsolog collector's ffprobe-per-file problem.
//
// Also confirmed live (2026-09-10/11): a talkgroup number can be large/
// odd-looking (e.g. "TG #240216") without being any kind of bug --
// SvxLink Reflector dynamically regroups a QSO from a static TG (e.g.
// 2400) into a generated dynamic TG so it can release repeaters that
// aren't actually listening. Reported here exactly as SvxLink logs it,
// no attempt to "clean up" or collapse it back to a static TG.
header('Content-Type: application/json');

const LOG_FILE = '/var/log/svxlink';
const TAIL_LINES = 3000;

function tailLines(string $path, int $n): array
{
    $out = [];
    exec('tail -n ' . (int)$n . ' ' . escapeshellarg($path) . ' 2>/dev/null', $out);
    return $out;
}

/** SvxLink's own log timestamp format: "Thu Sep 10 23:37:48 2026". No
 * timezone in the log -- same assumption tag_and_encode.py already makes
 * (strptime with no tz, i.e. server-local), so this matches that. */
function parseLogTimestamp(string $s): ?int
{
    $ts = strtotime($s);
    return $ts !== false ? $ts : null;
}

$lines = tailLines(LOG_FILE, TAIL_LINES);

$selectedTg = null;
$lastTalkerEvent = null; // ['type' => 'start'|'stop', 'tg' => int, 'call' => string, 'at' => int]
$nodesOnline = 0;

foreach ($lines as $line) {
    if (preg_match('/^(.+?): ReflectorLogic: Selecting TG #(\d+)/', $line, $m)) {
        $selectedTg = (int)$m[2];
    } elseif (preg_match('/^(.+?): ReflectorLogic: Talker start on TG #(\d+): (\S+)/', $line, $m)) {
        $at = parseLogTimestamp($m[1]);
        if ($at !== null) {
            $lastTalkerEvent = ['type' => 'start', 'tg' => (int)$m[2], 'call' => $m[3], 'at' => $at];
        }
    } elseif (preg_match('/^(.+?): ReflectorLogic: Talker stop on TG #(\d+): (\S+)/', $line, $m)) {
        $at = parseLogTimestamp($m[1]);
        if ($at !== null) {
            $lastTalkerEvent = ['type' => 'stop', 'tg' => (int)$m[2], 'call' => $m[3], 'at' => $at];
        }
    } elseif (str_contains($line, 'ReflectorLogic: Node joined:')) {
        $nodesOnline++;
    } elseif (str_contains($line, 'ReflectorLogic: Node left:')) {
        $nodesOnline--;
    }
}

$talker = null;
if ($lastTalkerEvent !== null) {
    $talker = [
        'tg' => $lastTalkerEvent['tg'],
        'callsign' => $lastTalkerEvent['call'],
        'active' => $lastTalkerEvent['type'] === 'start',
        'since_seconds' => max(0, time() - $lastTalkerEvent['at']),
    ];
}

echo json_encode([
    'selected_tg' => $selectedTg,
    'talker' => $talker,
    // Only a rough count over the tailed window, not a true total (a
    // node that joined before TAIL_LINES' start is never counted) -- good
    // enough as "roughly how busy is the reflector", not authoritative.
    'nodes_online_approx' => max(0, $nodesOnline),
]);
