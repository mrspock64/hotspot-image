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

require_once __DIR__ . '/svxlink_update_check.php';
$mxSvxlinkUpdate = isSvxlinkUpdateAvailable();
$mxSvxlinkUpdateAvailable = $mxSvxlinkUpdate['available'];

// Short commit hash of whatever's actually deployed -- doesn't need the
// deploy key or the network (unlike the update check above), just reads
// the local checkout's own HEAD, so no sudo/caching needed here either.
$mxDashboardVersion = trim((string)@shell_exec('git -C /opt/hotspot-image rev-parse --short HEAD 2>/dev/null'));
$mxSvxlinkVersion = $mxSvxlinkUpdate['installed'];

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
  <div style="margin-left:auto; display:flex; flex-direction:column; align-items:flex-end; gap:4px;">
    <div style="display:flex; align-items:center; gap:8px;">
      <a id="mx-ble-badge" href="/bluetooth/" title="Bluetooth companion app access is on -- anyone in range can connect. Click to turn off." style="display:<?php echo $mxBleActive ? 'inline-flex' : 'none'; ?>; background:#fff; color:#2563eb; font-size:12px; font-weight:700; padding:5px 12px; border-radius:999px; text-decoration:none; white-space:nowrap; align-items:center; gap:5px;"><span style="width:7px; height:7px; border-radius:50%; background:#2563eb; display:inline-block; animation:mx-ble-pulse 2s ease-in-out infinite;"></span>BLE on</a>
      <a id="mx-update-badge" href="/update/" style="display:<?php echo $mxUpdateAvailable ? 'inline-flex' : 'none'; ?>; background:#fff; color:var(--mx-accent-dark); font-size:12px; font-weight:700; padding:5px 12px; border-radius:999px; text-decoration:none; white-space:nowrap; align-items:center;">&#8593; Dashboard update</a>
      <a id="mx-svxlink-update-badge" href="/update/" style="display:<?php echo $mxSvxlinkUpdateAvailable ? 'inline-flex' : 'none'; ?>; background:#fff; color:var(--mx-accent-dark); font-size:12px; font-weight:700; padding:5px 12px; border-radius:999px; text-decoration:none; white-space:nowrap; align-items:center;">&#8593; SvxLink update</a>
    </div>
<?php if ($mxDashboardVersion !== '' || $mxSvxlinkVersion): ?>
    <div style="font-size:10px; color:rgba(255,255,255,0.75); white-space:nowrap;">
<?php if ($mxDashboardVersion !== ''): ?>dashboard <?php echo htmlspecialchars($mxDashboardVersion); ?><?php endif; ?>
<?php if ($mxDashboardVersion !== '' && $mxSvxlinkVersion): ?> &middot; <?php endif; ?>
<?php if ($mxSvxlinkVersion): ?>svxlink <?php echo htmlspecialchars($mxSvxlinkVersion); ?><?php endif; ?>
    </div>
<?php endif; ?>
  </div>
</div>
<script>
// Lets any page update these two header badges in place after an action
// changes the underlying state (e.g. dashboard/bluetooth/'s on/off toggle),
// without needing a full page reload to pick it up -- window-scoped so
// other pages' own polling scripts can call it directly.
window.mxSetHeaderBadge = function (id, visible) {
  var el = document.getElementById(id);
  if (el) el.style.display = visible ? 'inline-flex' : 'none';
};
</script>
<style>
@keyframes mx-ble-pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.35; } }
</style>
<?php include_once __DIR__ . '/top_menu.php'; ?>
