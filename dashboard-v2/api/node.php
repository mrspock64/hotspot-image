<?php
// Node/vitals module. Two kinds of field, per lib/dashboard-v2/collect-
// node.sh's own header comment:
//  - Fast, instant reads done live right here on every request (hardware
//    model, firmware version, locator, perf mode, temp, load avg, uptime)
//  - Slow/disk-heavy ones (CPU load%, memory%, iowait%, QSO disk use%)
//    read from the collector's periodic snapshot instead of recomputed
//    per request
//
// Reuses existing dashboard/include/*.php logic rather than re-deriving
// it, per docs/dashboard-v2-brief.md's module contract -- format_uptime()
// (tools.php) and getPerfMode()/isPiZero2W() (perf_mode.php) are the
// production dashboard's own functions, required straight from this git
// checkout (never from the deployed /var/www/html copy -- this stays
// entirely within the branch's own checkout, nothing here touches
// production).
//
// Deliberately NOT requiring config.php for CPU_TEMP_OFFSET: confirmed
// live on svxlinkuhf that the real generated config.inc.php (which
// config.php prefers when present) never defines it, so
// dashboard/include/system.php's own "+CPU_TEMP_OFFSET" is actually an
// undefined-constant error waiting to happen on every real node -- a
// pre-existing production bug, not something to import into new code.
// This hardware (Pi Zero 2 W) is never the "Orange Pi Zero LTS" case that
// constant exists for anyway (see config.php's own comment on it), so the
// offset is unconditionally 0 here.
header('Content-Type: application/json');

require_once __DIR__ . '/../../dashboard/include/tools.php';
require_once __DIR__ . '/../../dashboard/include/perf_mode.php';

const STATE_FILE = '/var/cache/hotspot-image/dashboard-v2-node.json';
const NODE_INFO_FILE = '/etc/svxlink/node_info.json';

$snapshot = [];
if (is_file(STATE_FILE)) {
    $snapshot = json_decode((string)@file_get_contents(STATE_FILE), true) ?: [];
}

// Same source as dashboard/include/perf_mode.php's own isPiZero2W().
$hardware = trim((string)@file_get_contents('/proc/device-tree/model'), "\0 \t\n\r") ?: 'unknown';

// Same source as dashboard/include/site_header.php's $mxDashboardVersion.
$firmware = trim((string)@shell_exec('git -C /opt/hotspot-image rev-parse --short HEAD 2>/dev/null')) ?: 'unknown';

$locator = '';
$nodeInfoRaw = @file_get_contents(NODE_INFO_FILE);
if ($nodeInfoRaw !== false) {
    $nodeInfo = json_decode($nodeInfoRaw, true);
    if ($nodeInfo === null) {
        // Same trailing-comma tolerance as site_header.php -- this file
        // has shipped genuinely invalid JSON on real nodes before.
        $nodeInfo = json_decode(preg_replace('/,(\s*[}\]])/', '$1', $nodeInfoRaw), true);
    }
    $locator = $nodeInfo['qth'][0]['pos']['loc'] ?? '';
}

$perfMode = getPerfMode();
$coreCount = (int)trim((string)@shell_exec('nproc'));
$cpuFreqKhz = (int)trim((string)@file_get_contents('/sys/devices/system/cpu/cpu0/cpufreq/scaling_cur_freq'));
$cpuFreqMhz = $cpuFreqKhz > 0 ? round($cpuFreqKhz / 1000) : null;

$tempC = null;
if (is_file('/sys/class/thermal/thermal_zone0/temp')) {
    $raw = trim((string)@file_get_contents('/sys/class/thermal/thermal_zone0/temp'));
    if ($raw !== '') {
        $tempC = round(abs((float)$raw) / 1000); // offset 0 -- see header comment
    }
}

$loadAvg = sys_getloadavg();

$uptimeRaw = trim((string)@shell_exec('cat /proc/uptime'));
$uptimeSeconds = $uptimeRaw !== '' ? (float)strtok($uptimeRaw, ' ') : 0;

echo json_encode(array_merge($snapshot, [
    'hardware' => $hardware,
    'firmware' => $firmware,
    'locator' => $locator,
    'perf_mode' => $perfMode,
    'cpu_cores' => $coreCount ?: null,
    'cpu_freq_mhz' => $cpuFreqMhz,
    'temp_c' => $tempC,
    'load_avg' => $loadAvg,
    'uptime_html' => format_uptime($uptimeSeconds),
]));
