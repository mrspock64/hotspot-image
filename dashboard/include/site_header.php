<?php
// Shared banner + nav for every modernized page. Pages that already look
// up $callsign/$fmnetwork themselves (index.php, tg.php -- they need the
// values for <title> too) can set them before including this; everything
// else gets it looked up here so each page doesn't have to duplicate the
// same six lines.
if (!isset($callsign) || !isset($fmnetwork)) {
    $svxConfigFile = '/etc/svxlink/svxlink.conf';
    if (@fopen($svxConfigFile, 'r')) {
        $svxconfig = parse_ini_file($svxConfigFile, true, INI_SCANNER_RAW);
        $callsign = $svxconfig['ReflectorLogic']['CALLSIGN'] ?? 'NOCALL';
        $fmnetwork = $svxconfig['ReflectorLogic']['FMNET'] ?? '';
    } else {
        $callsign = 'NOCALL';
        $fmnetwork = 'not registered';
    }
}

// Frequency for the header -- same source as the Setup page's single
// Frequency field (this hotspot is simplex, so RX and TX always match).
$mxHeaderFreq = '';
$nodeInfoRaw = @file_get_contents('/etc/svxlink/node_info.json');
if ($nodeInfoRaw !== false) {
    $nodeInfoDecoded = json_decode($nodeInfoRaw, true);
    if ($nodeInfoDecoded === null) {
        $nodeInfoDecoded = json_decode(preg_replace('/,(\s*[}\]])/', '$1', $nodeInfoRaw), true);
    }
    $mxHeaderFreqVal = $nodeInfoDecoded['qth'][0]['rx']['A']['freq'] ?? 0;
    if ($mxHeaderFreqVal) {
        $mxHeaderFreq = number_format((float)$mxHeaderFreqVal, 4) . ' MHz';
    }
}
require_once __DIR__ . '/update_check.php';
$mxUpdateAvailable = isDashboardUpdateAvailable()['available'];

// Shown only while on (not a persistent "off" indicator) -- the point is a
// hard-to-miss reminder that the node is currently reachable, unauthenticated,
// over BLE (see dashboard/bluetooth/ and the ble_companion_app memory note),
// not routine status chrome. Cheap: systemctl is-active is near-instant,
// no caching needed like the update check's git-fetch.
$mxBleActive = trim((string)@shell_exec('systemctl is-active hotspot-bluetooth 2>/dev/null')) === 'active';
?>
<link href="/css/modern.css" type="text/css" rel="stylesheet" />
<div class="mx-banner">
  <img src="/images/svxlink.ico" alt="">
  <div>
    <div class="mx-callsign"><?php echo htmlspecialchars($callsign); ?></div>
    <div class="mx-network"><?php echo htmlspecialchars($fmnetwork); ?><?php echo ($fmnetwork !== '' && $mxHeaderFreq !== '') ? ' &middot; ' : ''; ?><?php echo htmlspecialchars($mxHeaderFreq); ?></div>
  </div>
<?php if ($mxBleActive || $mxUpdateAvailable): ?>
  <div style="margin-left:auto; display:flex; align-items:center; gap:8px;">
<?php if ($mxBleActive): ?>
    <a href="/bluetooth/" title="Bluetooth companion app access is on -- anyone in range can connect. Click to turn off." style="background:#fff; color:#2563eb; font-size:12px; font-weight:700; padding:5px 12px; border-radius:999px; text-decoration:none; white-space:nowrap; display:inline-flex; align-items:center; gap:5px;"><span style="width:7px; height:7px; border-radius:50%; background:#2563eb; display:inline-block; animation:mx-ble-pulse 2s ease-in-out infinite;"></span>BLE on</a>
<?php endif; ?>
<?php if ($mxUpdateAvailable): ?>
    <a href="/update/" style="background:#fff; color:var(--mx-accent-dark); font-size:12px; font-weight:700; padding:5px 12px; border-radius:999px; text-decoration:none; white-space:nowrap;">&#8593; Update available</a>
<?php endif; ?>
  </div>
<?php endif; ?>
</div>
<style>
@keyframes mx-ble-pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.35; } }
</style>
<?php include_once __DIR__ . '/top_menu.php'; ?>
