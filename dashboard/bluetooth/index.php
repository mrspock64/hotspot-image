<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <title>Bluetooth</title>
    <link href="/css/modern.css" type="text/css" rel="stylesheet" />
  </head>
<body style="background: var(--mx-bg); margin: 0;">
<?php include_once __DIR__ . '/../include/site_header.php'; ?>

<?php
// The companion app's BLE service (hotspot-bluetooth, installed by
// RF.Guru's own install-bluetooth.sh) advertises with no pairing/PIN and
// no encrypt-* flags on its characteristics -- by design, so iOS/Android
// connect without a prompt (see the vendor script's own comments on why:
// Android's eager bonding stack fights encrypted GATT in ways that broke
// their app). That means anyone within BLE range while it's on can send
// DTMF, restart SvxLink, or reboot/power off the device -- no password.
//
// Since we can't change that without risking breaking the app, and don't
// have its source to test against, the mitigation is exposure time
// instead: this toggle only starts/stops the service for the current
// session and deliberately never touches its systemd "enabled" state, so
// it always comes back OFF after a reboot regardless of how it was left
// -- turn it on only while actually using the app, then off again.
if (isset($_POST['btnOn'])) {
    exec('sudo systemctl start hotspot-bluetooth > /dev/null 2>&1 &');
    $message = 'Turning Bluetooth on -- the companion app should find this hotspot within a few seconds.';
}

if (isset($_POST['btnOff'])) {
    exec('sudo systemctl stop hotspot-bluetooth > /dev/null 2>&1 &');
    $message = 'Turning Bluetooth off.';
}

$isActive = trim((string)@shell_exec('systemctl is-active hotspot-bluetooth 2>/dev/null')) === 'active';
?>

<div class="mx-card" style="max-width: 560px;">
  <h1>Bluetooth</h1>
  <p class="mx-sub">Lets the <a href="https://svxlink-hotspot.app" target="_blank" rel="noopener">companion app</a> (iOS/Android) drive this hotspot over BLE -- send DTMF, restart SvxLink, toggle 4G, watch live state -- without SSH.</p>

<?php if (isset($message)): ?>
  <p class="mx-msg mx-msg-ok"><?php echo htmlspecialchars($message); ?></p>
<?php endif; ?>

  <p style="font-weight:600; margin-bottom:14px;">Status:
    <?php echo $isActive
        ? '<span style="color:#15803d;">&#9679; On</span> — advertising as ' . htmlspecialchars(trim((string)@shell_exec('hostname')))
        : '<span style="color:var(--mx-text-dim);">&#9675; Off</span>'; ?>
  </p>

  <div class="mx-msg" style="background:#fef3c7;border:1px solid #fbbf24;color:#92400e;">
    &#9888; No pairing or password is required to connect while this is on -- anyone within Bluetooth range (roughly 10-30m) can send DTMF, restart SvxLink, or reboot/power off the device. Turn it on only while you're actively using the app nearby, then off again. It always starts OFF after a reboot, regardless of how you leave it here.
  </div>

  <form method="post" style="margin-top:14px;">
<?php if ($isActive): ?>
    <button name="btnOff" type="submit" class="mx-btn mx-btn-danger" style="width:200px;">Turn off</button>
<?php else: ?>
    <button name="btnOn" type="submit" class="mx-btn" style="width:200px;">Turn on</button>
<?php endif; ?>
  </form>
</div>
</body>
</html>
