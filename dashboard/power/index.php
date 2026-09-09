<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <title>Power</title>
    <link href="/css/modern.css" type="text/css" rel="stylesheet" />
  </head>
<body style="background: var(--mx-bg); margin: 0;">
<?php include_once __DIR__ . '/../include/site_header.php'; ?>

<?php

require_once __DIR__ . '/../include/perf_mode.php';
require_once __DIR__ . '/../include/inisync.php';

// Each command runs backgrounded ("&") so PHP can respond with a status
// message immediately, rather than the page hanging until svxlink has
// fully restarted/stopped (or, for Restart Device/Power OFF, never
// responding at all because the device goes down mid-request). That
// means the page you land on right after submitting reflects whatever
// systemctl said at that exact instant -- before the change has actually
// happened -- not the eventual result a few seconds later. The JS below
// polls status.php to catch up once it has.
$message = null;
$messageOk = true;
$pollAfterAction = false;

if (isset($_POST['btnSvxlinkStart'])) {
    exec("sudo service svxlink start > /dev/null 2>&1 &");
    $message = "Starting the SVXlink service.";
    $pollAfterAction = true;
}

if (isset($_POST['btnSvxlinkStop'])) {
    exec("sudo service svxlink stop > /dev/null 2>&1 &");
    $message = "Stopping the SVXlink service -- the radio goes silent until you start it again here (no separate restart needed after a reboot; svxlink starts on its own).";
    $pollAfterAction = true;
}

if (isset($_POST['btnSvxlinkRestart'])) {
    exec("sudo service svxlink restart > /dev/null 2>&1 &");
    $message = "Restarting the SVXlink service. This takes a few seconds -- the dashboard itself stays up throughout.";
    $pollAfterAction = true;
}

if (isset($_POST['btnRestart'])) {
    exec("sudo shutdown -r now > /dev/null 2>&1 &");
    $message = "Restarting the device now. The dashboard will be unreachable for about a minute.";
}

if (isset($_POST['btnPower'])) {
    exec("sudo shutdown -h now > /dev/null 2>&1 &");
    $message = "Powering off now. The device will go offline in a few seconds -- you'll need physical access to turn it back on.";
}

if (isset($_POST['btnPerfMode'])) {
    $requestedMode = $_POST['perf_mode'] ?? '';
    try {
        setPerfMode($requestedMode);
        $modeLabel = $requestedMode === 'turbo' ? 'Turbo' : 'Guru';
        $message = "$modeLabel mode set -- restart the device below for it to take effect.";
    } catch (Throwable $e) {
        $message = 'Failed to change performance mode: ' . $e->getMessage();
        $messageOk = false;
    }
}

if (isset($_POST['save_temp_protect'])) {
    $tempThreshold = trim($_POST['temp_threshold'] ?? '');
    if (!ctype_digit($tempThreshold) || (int)$tempThreshold < 40 || (int)$tempThreshold > 85) {
        $message = 'Temperature threshold must be a number between 40 and 85 (°C).';
        $messageOk = false;
    } else {
        iniSyncUpdateSection('/etc/svxlink/svxlink.conf', 'Dashboard', [
            'LOAD_MONITOR_TEMP_THRESHOLD_C' => $tempThreshold,
            'LOAD_MONITOR_AUTO_STOP_SVXLINK' => isset($_POST['temp_auto_stop']) ? '1' : '0',
        ]);
        $message = 'Saved.';
    }
}

$svxActive = trim((string)@shell_exec('systemctl is-active svxlink 2>/dev/null')) === 'active';
$perfMode = getPerfMode();
$isZero2W = isPiZero2W();
$tempThreshold = getLoadMonitorTempThreshold();
$tempAutoStop = getLoadMonitorAutoStopSvxlink();
$currentTempC = null;
if (is_readable('/sys/class/thermal/thermal_zone0/temp')) {
    $raw = trim((string)@file_get_contents('/sys/class/thermal/thermal_zone0/temp'));
    if (is_numeric($raw)) {
        $currentTempC = (int)round(((float)$raw) / 1000);
    }
}

?>

<div class="mx-card" style="max-width: 460px;">
  <h1 style="text-align: center;">Power</h1>

<?php if ($message): ?>
  <p class="mx-msg <?php echo $messageOk ? 'mx-msg-ok' : 'mx-msg-err'; ?>"><?php echo htmlspecialchars($message); ?></p>
<?php endif; ?>

  <p id="svx-status" style="text-align:center; font-weight:600; margin: 0 0 4px;">SvxLink:
    <span id="svx-status-label"><?php echo $svxActive
        ? '<span style="color:#15803d;">&#9679; Running</span>'
        : '<span style="color:var(--mx-text-dim);">&#9675; Stopped</span>'; ?></span>
  </p>

  <div style="display:flex; flex-direction:column; align-items:center; gap:10px; margin-top:14px;">
    <div id="svx-active-controls" style="display:<?php echo $svxActive ? 'contents' : 'none'; ?>;">
      <form method="post" style="margin:0;">
        <button name="btnSvxlinkRestart" type="submit" class="mx-btn" style="width:260px;">Restart SVXlink Service</button>
      </form>
      <form method="post" style="margin:0;">
        <button name="btnSvxlinkStop" type="submit" class="mx-btn mx-btn-danger" style="width:260px;">Stop SVXlink Service</button>
      </form>
    </div>
    <div id="svx-inactive-controls" style="display:<?php echo $svxActive ? 'none' : 'contents'; ?>;">
      <form method="post" style="margin:0;">
        <button name="btnSvxlinkStart" type="submit" class="mx-btn" style="width:260px;">Start SVXlink Service</button>
      </form>
    </div>

<?php if ($isZero2W): ?>
    <div style="width:260px; border-top:1px solid var(--mx-border, #e5e7eb); margin:6px 0;"></div>

    <p style="text-align:center; font-weight:600; margin:0 0 4px;">Performance mode:
      <span style="color:<?php echo $perfMode === 'turbo' ? '#15803d' : 'var(--mx-text-dim)'; ?>;"><?php echo $perfMode === 'turbo' ? 'Turbo &#9889;' : 'Guru (throttled)'; ?></span>
    </p>
    <form method="post" style="margin:0 0 4px;">
      <input type="hidden" name="perf_mode" value="<?php echo $perfMode === 'turbo' ? 'guru' : 'turbo'; ?>">
      <button name="btnPerfMode" type="submit" class="mx-btn mx-btn-ghost" style="width:260px;">Switch to <?php echo $perfMode === 'turbo' ? 'Guru mode' : 'Turbo mode'; ?></button>
    </form>
    <p class="mx-hint" style="width:260px; text-align:center; margin:0 0 10px;">Turbo: all 4 real cores, full clock speed -- confirmed live, no thermal throttling either way in open air (this option exists specifically for RF.Guru's own plastic case, which traps heat more). Guru: RF.Guru's stock "Temperature Tuning" (2 cores, ~30% slower, undervolted). Needs a restart below to take effect.</p>
<?php elseif ($perfMode === 'guru'): ?>
    <div style="width:260px; border-top:1px solid var(--mx-border, #e5e7eb); margin:6px 0;"></div>

    <p class="mx-msg mx-msg-err" style="width:260px; box-sizing:border-box;">This SD card has RF.Guru's "Guru mode" throttling left over from a Pi Zero 2 W, but this board isn't one -- those settings break SA818 radio comms on other boards (mini-UART timing). Clean it up below.</p>
    <form method="post" style="margin:0 0 10px;">
      <input type="hidden" name="perf_mode" value="turbo">
      <button name="btnPerfMode" type="submit" class="mx-btn" style="width:260px;">Clean up (switch to Turbo)</button>
    </form>
<?php endif; ?>

    <div style="width:260px; border-top:1px solid var(--mx-border, #e5e7eb); margin:6px 0;"></div>

    <p style="text-align:center; font-weight:600; margin:0 0 4px;">Temperature:
      <span style="color:<?php echo ($currentTempC !== null && $currentTempC >= $tempThreshold) ? '#dc2626' : '#15803d'; ?>;"><?php echo $currentTempC !== null ? htmlspecialchars((string)$currentTempC) . '&deg;C' : 'unknown'; ?></span>
    </p>
    <form method="post" style="margin:0; width:260px;">
      <div style="display:flex; align-items:center; gap:8px; justify-content:center; margin-bottom:6px;">
        <label for="temp_threshold" style="font-size:13px;">Stop SvxLink above</label>
        <input type="text" id="temp_threshold" name="temp_threshold" value="<?php echo htmlspecialchars((string)$tempThreshold); ?>" style="width:50px; margin:0; text-align:center;">
        <span style="font-size:13px;">&deg;C</span>
      </div>
      <label style="font-size:13px; font-weight:normal; display:block; text-align:center; margin-bottom:6px;">
        <input type="checkbox" name="temp_auto_stop" <?php echo $tempAutoStop ? 'checked' : ''; ?> style="width:auto; vertical-align:middle;">
        Automatically stop SvxLink if it stays that hot
      </label>
      <p class="mx-hint" style="text-align:center; margin:0 0 8px;">Checked every ~30s by the load monitor; needs ~2 minutes sustained above the threshold before acting (a brief spike isn't enough). Off by default -- it only ever stops the service, never restarts it, and only takes effect if it's on to begin with. Pi firmware itself throttles at 80&deg;C.</p>
      <button type="submit" name="save_temp_protect" class="mx-btn mx-btn-ghost" style="width:260px;">Save</button>
    </form>

    <div style="width:260px; border-top:1px solid var(--mx-border, #e5e7eb); margin:6px 0;"></div>

    <form method="post" style="margin:0;">
      <button name="btnRestart" type="submit" class="mx-btn mx-btn-danger" style="width:260px;" onclick="return confirm('Restart the device now?');">Restart Device</button>
    </form>
    <form method="post" style="margin:0;">
      <button name="btnPower" type="submit" class="mx-btn mx-btn-danger" style="width:260px;" onclick="return confirm('Power off the device now? You will need physical access to turn it back on.');">Power OFF</button>
    </form>
  </div>

</div>
<?php if ($pollAfterAction): ?>
<script>
(function () {
  var label = document.getElementById('svx-status-label');
  var activeCtl = document.getElementById('svx-active-controls');
  var inactiveCtl = document.getElementById('svx-inactive-controls');
  var attempts = 0;
  var maxAttempts = 20; // ~20s -- covers the ~10-16s a real start took in testing

  function render(active) {
    label.innerHTML = active
      ? '<span style="color:#15803d;">&#9679; Running</span>'
      : '<span style="color:var(--mx-text-dim);">&#9675; Stopped</span>';
    activeCtl.style.display = active ? 'contents' : 'none';
    inactiveCtl.style.display = active ? 'none' : 'contents';
  }

  function poll() {
    attempts++;
    fetch('status.php', {cache: 'no-store'})
      .then(function (r) { return r.json(); })
      .then(function (data) { render(data.active); })
      .catch(function () {})
      .finally(function () {
        if (attempts < maxAttempts) {
          setTimeout(poll, 1000);
        }
      });
  }

  setTimeout(poll, 1000);
})();
</script>
<?php endif; ?>
</body>
</html>
