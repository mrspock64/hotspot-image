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
?>
<link href="/css/modern.css" type="text/css" rel="stylesheet" />
<div class="mx-banner">
  <img src="/images/svxlink.ico" alt="">
  <div>
    <div class="mx-callsign"><?php echo htmlspecialchars($callsign); ?></div>
    <div class="mx-network"><?php echo htmlspecialchars($fmnetwork); ?><?php echo ($fmnetwork !== '' && $mxHeaderFreq !== '') ? ' &middot; ' : ''; ?><?php echo htmlspecialchars($mxHeaderFreq); ?></div>
  </div>
<?php if ($mxUpdateAvailable): ?>
  <a href="/update/" style="margin-left:auto; background:#fff; color:var(--mx-accent-dark); font-size:12px; font-weight:700; padding:5px 12px; border-radius:999px; text-decoration:none; white-space:nowrap;">&#8593; Update available</a>
<?php endif; ?>
</div>
<?php include_once __DIR__ . '/top_menu.php'; ?>
