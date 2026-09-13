<?php
// Radio Status module -- TX/RX/idle state of the radio itself, not the
// reflector network (that's Talkgroup/Reflector Activity). Two more real
// lines in the same /var/log/svxlink source: "Tx1: Turning the
// transmitter ON/OFF" and "Rx1: The squelch is OPEN/CLOSED (<freq
// offset>:<signal level>)" -- SvxLink's own core, not something
// events.d/Logic.tcl adds. Confirmed live: both fire in real time on
// svxlinkuhf. No new backend daemon needed, same tail+regex-scan cost
// class as the other log-based modules.
//
// Confirmed live (2026-09-13): stopping svxlink mid-transmission leaves
// no "Turning the transmitter OFF" line behind -- the process just dies
// -- so trusting the log's last-known TX/RX line alone left this stuck
// showing "TRANSMITTING" forever after a stop. Same systemctl check
// api/frequency.php already uses for its own "stopped" badge: when
// svxlink isn't running, state is forced to "offline" and tx/rx are
// reported as unknown (null) rather than replaying a stale log line as
// if it were still true.
//
// Also confirmed live: a plain restart isn't enough on its own -- the
// stale pre-crash "Turning the transmitter ON" line is still the most
// recent Tx1 line in the tail window even once svxlink is back up and
// genuinely idle, since a fresh process never logs an explicit "OFF" to
// contradict it. SvxLink prints its version banner ("SvxLink vX.Y.Z@...
// Copyright ...") exactly once per process start, so it's used below as
// a hard reset marker: any TX/RX line seen before the most recent banner
// belongs to a previous incarnation and is discarded, not just aged.
header('Content-Type: application/json');

// Same timezone trap as the other log-based modules -- see
// api/talkgroup.php's header comment for the full story.
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

$lines = tailLines(LOG_FILE, TAIL_LINES);

$lastTx = null; // ['on' => bool, 'at' => int]
$lastRx = null; // ['open' => bool, 'signal' => string|null, 'at' => int]

foreach ($lines as $line) {
    if (preg_match('/^.+?: SvxLink v[\d.]+@\S+ Copyright/', $line)) {
        $lastTx = null;
        $lastRx = null;
        continue;
    }
    if (preg_match('/^(.+?): Tx1: Turning the transmitter (ON|OFF)/', $line, $m)) {
        $at = parseLogTimestamp($m[1]);
        if ($at !== null) {
            $lastTx = ['on' => $m[2] === 'ON', 'at' => $at];
        }
    } elseif (preg_match('/^(.+?): Rx1: The squelch is (OPEN|CLOSED)(?: \(([^)]*)\))?/', $line, $m)) {
        $at = parseLogTimestamp($m[1]);
        if ($at !== null) {
            $lastRx = ['open' => $m[2] === 'OPEN', 'signal' => $m[3] ?? null, 'at' => $at];
        }
    }
}

$svxlinkActive = trim((string)@shell_exec('systemctl is-active svxlink 2>/dev/null')) === 'active';

if (!$svxlinkActive) {
    echo json_encode(['state' => 'offline', 'tx' => null, 'rx' => null]);
    exit;
}

$now = time();
$txOn = $lastTx['on'] ?? false;
$rxOpen = $lastRx['open'] ?? false;

// TX takes priority over RX for the headline state -- if the radio is
// keying up, that's the thing worth surfacing first, even if the
// squelch also happens to still show open (typical during a local
// repeat/relay).
$state = $txOn ? 'tx' : ($rxOpen ? 'rx' : 'idle');

echo json_encode([
    'state' => $state,
    'tx' => $lastTx ? ['on' => $txOn, 'seconds_ago' => max(0, $now - $lastTx['at'])] : null,
    'rx' => $lastRx ? ['open' => $rxOpen, 'signal' => $lastRx['signal'], 'seconds_ago' => max(0, $now - $lastRx['at'])] : null,
]);
