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

// Each command runs backgrounded ("&") so PHP can respond with a status
// message immediately, rather than the page hanging until svxlink has
// fully restarted/stopped (or, for Restart Device/Power OFF, never
// responding at all because the device goes down mid-request).
$message = null;

if (isset($_POST['btnSvxlinkStart'])) {
    exec("sudo service svxlink start > /dev/null 2>&1 &");
    $message = "Starting the SVXlink service.";
}

if (isset($_POST['btnSvxlinkStop'])) {
    exec("sudo service svxlink stop > /dev/null 2>&1 &");
    $message = "Stopping the SVXlink service -- the radio goes silent until you start it again here (no separate restart needed after a reboot; svxlink starts on its own).";
}

if (isset($_POST['btnSvxlinkRestart'])) {
    exec("sudo service svxlink restart > /dev/null 2>&1 &");
    $message = "Restarting the SVXlink service. This takes a few seconds -- the dashboard itself stays up throughout.";
}

if (isset($_POST['btnRestart'])) {
    exec("sudo shutdown -r now > /dev/null 2>&1 &");
    $message = "Restarting the device now. The dashboard will be unreachable for about a minute.";
}

if (isset($_POST['btnPower'])) {
    exec("sudo shutdown -h now > /dev/null 2>&1 &");
    $message = "Powering off now. The device will go offline in a few seconds -- you'll need physical access to turn it back on.";
}

// Shown state lags an action by a moment (backgrounded, see above) --
// good enough for "which button(s) make sense right now", not meant to be
// an instant live indicator.
$svxActive = trim((string)@shell_exec('systemctl is-active svxlink 2>/dev/null')) === 'active';

?>

<div class="mx-card" style="max-width: 460px;">
  <h1 style="text-align: center;">Power</h1>

<?php if ($message): ?>
  <p class="mx-msg mx-msg-ok"><?php echo htmlspecialchars($message); ?></p>
<?php endif; ?>

  <p style="text-align:center; font-weight:600; margin: 0 0 4px;">SvxLink:
    <?php echo $svxActive
        ? '<span style="color:#15803d;">&#9679; Running</span>'
        : '<span style="color:var(--mx-text-dim);">&#9675; Stopped</span>'; ?>
  </p>

  <div style="display:flex; flex-direction:column; align-items:center; gap:10px; margin-top:14px;">
<?php if ($svxActive): ?>
    <form method="post" style="margin:0;">
      <button name="btnSvxlinkRestart" type="submit" class="mx-btn" style="width:260px;">Restart SVXlink Service</button>
    </form>
    <form method="post" style="margin:0;">
      <button name="btnSvxlinkStop" type="submit" class="mx-btn mx-btn-danger" style="width:260px;">Stop SVXlink Service</button>
    </form>
<?php else: ?>
    <form method="post" style="margin:0;">
      <button name="btnSvxlinkStart" type="submit" class="mx-btn" style="width:260px;">Start SVXlink Service</button>
    </form>
<?php endif; ?>

    <div style="width:260px; border-top:1px solid var(--mx-border, #e5e7eb); margin:6px 0;"></div>

    <form method="post" style="margin:0;">
      <button name="btnRestart" type="submit" class="mx-btn mx-btn-danger" style="width:260px;" onclick="return confirm('Restart the device now?');">Restart Device</button>
    </form>
    <form method="post" style="margin:0;">
      <button name="btnPower" type="submit" class="mx-btn mx-btn-danger" style="width:260px;" onclick="return confirm('Power off the device now? You will need physical access to turn it back on.');">Power OFF</button>
    </form>
  </div>

</div>
</body>
</html>
