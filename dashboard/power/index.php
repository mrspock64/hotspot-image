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

// Each command runs backgrounded ("&") so PHP can respond with a status
// message immediately, rather than the page hanging until svxlink has
// fully restarted/stopped (or, for Restart Device/Power OFF, never
// responding at all because the device goes down mid-request). That
// means the page you land on right after submitting reflects whatever
// systemctl said at that exact instant -- before the change has actually
// happened -- not the eventual result a few seconds later. The JS below
// polls status.php to catch up once it has.
$message = null;
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
    }
}

$svxActive = trim((string)@shell_exec('systemctl is-active svxlink 2>/dev/null')) === 'active';
$perfMode = getPerfMode();

?>

<div class="mx-card" style="max-width: 460px;">
  <h1 style="text-align: center;">Power</h1>

<?php if ($message): ?>
  <p class="mx-msg mx-msg-ok"><?php echo htmlspecialchars($message); ?></p>
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

    <div style="width:260px; border-top:1px solid var(--mx-border, #e5e7eb); margin:6px 0;"></div>

    <p style="text-align:center; font-weight:600; margin:0 0 4px;">Performance mode:
      <span style="color:<?php echo $perfMode === 'turbo' ? '#15803d' : 'var(--mx-text-dim)'; ?>;"><?php echo $perfMode === 'turbo' ? 'Turbo &#9889;' : 'Guru (throttled)'; ?></span>
    </p>
    <form method="post" style="margin:0 0 4px;">
      <input type="hidden" name="perf_mode" value="<?php echo $perfMode === 'turbo' ? 'guru' : 'turbo'; ?>">
      <button name="btnPerfMode" type="submit" class="mx-btn mx-btn-ghost" style="width:260px;">Switch to <?php echo $perfMode === 'turbo' ? 'Guru mode' : 'Turbo mode'; ?></button>
    </form>
    <p class="mx-hint" style="width:260px; text-align:center; margin:0 0 10px;">Turbo: all 4 real cores, full clock speed -- confirmed live, no thermal throttling either way. Guru: RF.Guru's stock "Temperature Tuning" (2 cores, ~30% slower, undervolted). Needs a restart below to take effect.</p>

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
