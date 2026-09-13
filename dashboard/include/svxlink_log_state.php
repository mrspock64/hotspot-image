<?php
// Single shared parser for everything dashboard-v2's log-based modules
// need to know about SvxLink's live state. One tail() + one regex pass
// over /var/log/svxlink, instead of every module tailing and re-parsing
// the same file independently.
//
// Confirmed live 2026-09-14: letting each module (Talkgroup, Radio
// Status, Monitored Talkgroups, Reflector Activity) maintain its own
// separate tail+scan let them drift out of sync with each other --
// api/talkgroup.php tracked "last talker anywhere in the log" completely
// independently of "which TG is currently selected", so switching TGs
// left it showing a stale talker from a *different* TG with nothing
// tying the two together. Four independent parsers of the same file is
// how that kind of bug happens; one shared parser makes it structurally
// impossible for "selected TG" and "last talker" to come from different
// scans.
//
// HOUSE RULE for future log-based modules: if you need something from
// /var/log/svxlink, add a branch to the foreach loop below (or read the
// returned 'lines' array for a one-off pattern nothing else parses yet)
// -- never add a second tail() call in a new api/*.php file. That is the
// entire point of this file existing; a new module quietly going back to
// its own tail+scan just recreates the same drift risk this replaced.
//
// Also centralizes the crash-safety fix originally built for Radio
// Status: stopping svxlink mid-transmission (or mid- anything) never
// logs a matching close-out line -- the process just dies, so trusting
// the log's last-known line for any "current state" field left it stuck
// showing stale info forever, even past a restart (a fresh process never
// logs an explicit "OFF"/whatever to contradict the old line still
// sitting in the tail window). SvxLink prints its version banner
// ("SvxLink vX.Y.Z@... Copyright ...") exactly once per process start,
// used below as a hard reset point: every "current state" field
// (selected_tg, tx, rx, talker_by_tg, online_nodes) is cleared whenever a
// banner line is seen, so a fresh process always starts every poll from
// "nothing known yet" rather than replaying a previous incarnation's
// last word. The classified `events` list is NOT reset by a restart --
// it's a historical log, not current state, and old events aging
// further into the past as time passes is the correct behavior there.

const SVXLINK_LOG_FILE = '/var/log/svxlink';
const SVXLINK_LOG_TAIL_LINES = 3000;

function svxlinkTailLines(string $path, int $n): array
{
    $out = [];
    exec('tail -n ' . (int)$n . ' ' . escapeshellarg($path) . ' 2>/dev/null', $out);
    return $out;
}

/** SvxLink's own log timestamp format: "Thu Sep 10 23:37:48 2026" -- no
 * timezone in the string, plain localtime(). Caller is responsible for
 * date_default_timezone_set()'ing to the system's real zone first (done
 * once in getSvxlinkLogState() below) -- PHP defaults to UTC on this
 * node while the system is actually Europe/Brussels, confirmed live to
 * silently produce a permanent, wrong "0s ago" otherwise. */
function svxlinkParseLogTimestamp(string $s): ?int
{
    $ts = strtotime($s);
    return $ts !== false ? $ts : null;
}

/**
 * One tail + one regex pass over /var/log/svxlink. Returns an
 * associative array:
 *  - now: time() at the moment of the scan, so callers compute
 *    "seconds_ago" against the same clock reading rather than calling
 *    time() again themselves.
 *  - lines: the raw tailed lines, oldest first -- for a module that
 *    needs a pattern nothing below parses yet. Still only one tail()
 *    call total, however many modules ask for state this request.
 *  - svxlink_active: bool, systemctl is-active svxlink.
 *  - selected_tg: string|null, this node's currently linked TG number
 *    (from the most recent "Selecting TG #<n>" since the last restart).
 *  - tx: ['on' => bool, 'at' => int]|null -- last Tx1 ON/OFF.
 *  - rx: ['open' => bool, 'signal' => string|null, 'at' => int]|null --
 *    last Rx1 OPEN/CLOSED.
 *  - talker_by_tg: array<string, array{type:'start'|'stop', callsign:
 *    string, at:int}> -- last talker event per TG number seen since the
 *    last restart, keyed by TG number as a string.
 *  - online_nodes: array<string, true> (used as a set) -- reflector
 *    nodes seen joining without a later leave, since the last restart.
 *  - events: chronological (oldest first) classified event list, each
 *    ['type' => ..., 'at' => int, ...fields], types: talker_start,
 *    talker_stop, node_joined, node_left, tg_selected, tx_on, tx_off,
 *    rx_open, rx_closed. NOT reset by a restart -- this is history, not
 *    current state.
 */
function getSvxlinkLogState(int $tailLines = SVXLINK_LOG_TAIL_LINES): array
{
    $systemTz = trim((string)@file_get_contents('/etc/timezone'));
    if ($systemTz !== '' && in_array($systemTz, timezone_identifiers_list(), true)) {
        date_default_timezone_set($systemTz);
    }

    $lines = svxlinkTailLines(SVXLINK_LOG_FILE, $tailLines);

    $selectedTg = null;
    $tx = null;        // ['on' => bool, 'at' => int]
    $rx = null;         // ['open' => bool, 'signal' => string|null, 'at' => int]
    $talkerByTg = [];   // tg (string) => ['type' => 'start'|'stop', 'callsign' => string, 'at' => int]
    $onlineNodes = [];  // callsign => true, used as a set
    $events = [];

    foreach ($lines as $line) {
        if (preg_match('/^.+?: SvxLink v[\d.]+@\S+ Copyright/', $line)) {
            $selectedTg = null;
            $tx = null;
            $rx = null;
            $talkerByTg = [];
            $onlineNodes = [];
            continue;
        }

        if (preg_match('/^(.+?): ReflectorLogic: Selecting TG #(\d+)/', $line, $m)) {
            $selectedTg = $m[2];
            $at = svxlinkParseLogTimestamp($m[1]);
            if ($at !== null) {
                $events[] = ['type' => 'tg_selected', 'tg' => (int)$m[2], 'at' => $at];
            }
            continue;
        }

        if (preg_match('/^(.+?): ReflectorLogic: Talker (start|stop) on TG #(\d+): (\S+)/', $line, $m)) {
            $at = svxlinkParseLogTimestamp($m[1]);
            if ($at !== null) {
                $talkerByTg[$m[3]] = ['type' => $m[2], 'callsign' => $m[4], 'at' => $at];
                $events[] = ['type' => 'talker_' . $m[2], 'tg' => (int)$m[3], 'callsign' => $m[4], 'at' => $at];
            }
            continue;
        }

        if (preg_match('/^(.+?): ReflectorLogic: Node joined: (\S+)/', $line, $m)) {
            $onlineNodes[$m[2]] = true;
            $at = svxlinkParseLogTimestamp($m[1]);
            if ($at !== null) {
                $events[] = ['type' => 'node_joined', 'callsign' => $m[2], 'at' => $at];
            }
            continue;
        }

        if (preg_match('/^(.+?): ReflectorLogic: Node left: (\S+)/', $line, $m)) {
            unset($onlineNodes[$m[2]]);
            $at = svxlinkParseLogTimestamp($m[1]);
            if ($at !== null) {
                $events[] = ['type' => 'node_left', 'callsign' => $m[2], 'at' => $at];
            }
            continue;
        }

        if (preg_match('/^(.+?): Tx1: Turning the transmitter (ON|OFF)/', $line, $m)) {
            $at = svxlinkParseLogTimestamp($m[1]);
            if ($at !== null) {
                $tx = ['on' => $m[2] === 'ON', 'at' => $at];
                $events[] = ['type' => $m[2] === 'ON' ? 'tx_on' : 'tx_off', 'at' => $at];
            }
            continue;
        }

        if (preg_match('/^(.+?): Rx1: The squelch is (OPEN|CLOSED)(?: \(([^)]*)\))?/', $line, $m)) {
            $at = svxlinkParseLogTimestamp($m[1]);
            if ($at !== null) {
                $rx = ['open' => $m[2] === 'OPEN', 'signal' => $m[3] ?? null, 'at' => $at];
                $events[] = ['type' => $m[2] === 'OPEN' ? 'rx_open' : 'rx_closed', 'signal' => $m[3] ?? null, 'at' => $at];
            }
            continue;
        }
    }

    $svxlinkActive = trim((string)@shell_exec('systemctl is-active svxlink 2>/dev/null')) === 'active';

    return [
        'now' => time(),
        'lines' => $lines,
        'svxlink_active' => $svxlinkActive,
        'selected_tg' => $selectedTg,
        'tx' => $tx,
        'rx' => $rx,
        'talker_by_tg' => $talkerByTg,
        'online_nodes' => $onlineNodes,
        'events' => $events,
    ];
}
