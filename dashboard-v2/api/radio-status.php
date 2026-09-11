<?php
// Radio Status module -- TX/RX/idle state of the radio itself, not the
// reflector network (that's Talkgroup/Reflector Activity). Two more real
// lines in the same /var/log/svxlink source: "Tx1: Turning the
// transmitter ON/OFF" and "Rx1: The squelch is OPEN/CLOSED (<freq
// offset>:<signal level>)" -- SvxLink's own core, not something
// events.d/Logic.tcl adds. Confirmed live: both fire in real time on
// svxlinkuhf. No new backend daemon needed, same tail+regex-scan cost
// class as the other log-based modules.
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
