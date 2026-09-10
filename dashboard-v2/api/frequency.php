<?php
// Frequency module. Reuses the exact same three sources site_header.php
// already reads for the production header (callsign/FMNET from
// svxlink.conf's [ReflectorLogic], RX frequency from node_info.json --
// this hotspot is simplex, so RX and TX always match, same comment as
// site_header.php's own) plus power/status.php's systemctl check.
//
// Deliberately does NOT report a "currently active talkgroup" -- nothing
// in this codebase tracks that today. tg.php's Monitor/Activate buttons
// are one-way DTMF triggers (see events.d/Logic.tcl), not a readback of
// what SvxLink/the reflector actually has linked right now. Real "what's
// live on this TG right this second" needs reflector-side state this
// project doesn't have a source for yet -- see docs/dashboard-v2-brief.md's
// "reflektor-info" idea for that, a separate future module, not something
// to fake here.
header('Content-Type: application/json');

$svxConfigFile = '/etc/svxlink/svxlink.conf';
$callsign = 'NOCALL';
$fmnetwork = 'not registered';
if (@fopen($svxConfigFile, 'r')) {
    $svxconfig = parse_ini_file($svxConfigFile, true, INI_SCANNER_RAW);
    $callsign = $svxconfig['ReflectorLogic']['CALLSIGN'] ?? 'NOCALL';
    $fmnetwork = $svxconfig['ReflectorLogic']['FMNET'] ?? '';
}

$freqMhz = null;
$nodeInfoRaw = @file_get_contents('/etc/svxlink/node_info.json');
if ($nodeInfoRaw !== false) {
    $nodeInfo = json_decode($nodeInfoRaw, true);
    if ($nodeInfo === null) {
        $nodeInfo = json_decode(preg_replace('/,(\s*[}\]])/', '$1', $nodeInfoRaw), true);
    }
    $val = $nodeInfo['qth'][0]['rx']['A']['freq'] ?? 0;
    if ($val) {
        $freqMhz = number_format((float)$val, 4);
    }
}

$svxlinkActive = trim((string)@shell_exec('systemctl is-active svxlink 2>/dev/null')) === 'active';

echo json_encode([
    'callsign' => $callsign,
    'network' => $fmnetwork,
    'freq_mhz' => $freqMhz,
    'mode' => 'FM · Simplex',
    'svxlink_active' => $svxlinkActive,
]);
