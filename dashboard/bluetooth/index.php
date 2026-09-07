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
require_once __DIR__ . '/../include/ble.php';

// The companion app's BLE service (hotspot-bluetooth, installed by
// RF.Guru's own install-bluetooth.sh) advertises with no pairing/PIN and
// no encrypt-* flags on its characteristics by default -- so iOS/Android
// connect without a prompt (see the vendor script's own comments on why:
// Android's eager bonding stack fights encrypted GATT in ways that broke
// their app). That means anyone within BLE range while it's on can send
// DTMF, restart SvxLink, or reboot/power off the device -- no password,
// unless bonded mode (below) is turned on.
//
// Since forcing bonded mode isn't guaranteed compatible with every client
// (we don't have the app's source to test against, though RF.Guru's own
// BLE.md documents it as the supported option for a mobile/public node),
// the default mitigation is exposure time instead: this toggle only
// starts/stops the service for the current session and deliberately never
// touches its systemd "enabled" state, so it always comes back OFF after a
// reboot regardless of how it was left -- turn it on only while actually
// using the app, then off again.
$pollAfterAction = false;
if (isset($_POST['btnOn'])) {
    exec('sudo systemctl start hotspot-bluetooth > /dev/null 2>&1 &');
    $message = 'Turning Bluetooth on -- the companion app should find this hotspot within a few seconds.';
    $pollAfterAction = true;
}

if (isset($_POST['btnOff'])) {
    exec('sudo systemctl stop hotspot-bluetooth > /dev/null 2>&1 &');
    $message = 'Turning Bluetooth off.';
    $pollAfterAction = true;
}

$bleIsInstalled = bleInstalled();
$bleBonded = $bleIsInstalled && bleBondedModeEnabled();
$bleMsg = null;

if ($bleIsInstalled && isset($_POST['btnSaveBonded'])) {
    $bleWanted = isset($_POST['ble_bonded']);
    if ($bleWanted !== $bleBonded) {
        try {
            setBleBondedMode($bleWanted);
            $bleBonded = $bleWanted;
            $bleMsg = $bleWanted
                ? 'Bluetooth now requires pairing (bonded) for DTMF/command writes.'
                : 'Bluetooth pairing requirement removed -- back to open/unbonded.';
        } catch (Throwable $e) {
            $bleMsg = 'Bluetooth security mode NOT changed: ' . $e->getMessage();
        }
    } else {
        $bleMsg = 'No change.';
    }
}

$isActive = trim((string)@shell_exec('systemctl is-active hotspot-bluetooth 2>/dev/null')) === 'active';
$hostname = trim((string)@shell_exec('hostname'));
?>

<div class="mx-card" style="max-width: 560px;">
  <h1>Bluetooth</h1>
  <p class="mx-sub">Lets the <a href="https://svxlink-hotspot.app" target="_blank" rel="noopener">companion app</a> (iOS/Android) drive this hotspot over BLE -- send DTMF, restart SvxLink, toggle 4G, watch live state -- without SSH.</p>

<?php if (isset($message)): ?>
  <p class="mx-msg mx-msg-ok"><?php echo htmlspecialchars($message); ?></p>
<?php endif; ?>
<?php if ($bleMsg !== null): ?>
  <p class="mx-msg mx-msg-ok"><?php echo htmlspecialchars($bleMsg); ?></p>
<?php endif; ?>

  <p style="font-weight:600; margin-bottom:14px;">Status:
    <span id="ble-status-label"><?php echo $isActive
        ? '<span style="color:#15803d;">&#9679; On</span> — advertising as ' . htmlspecialchars($hostname)
        : '<span style="color:var(--mx-text-dim);">&#9675; Off</span>'; ?></span>
    <?php if ($bleBonded): ?><span style="color:#2563eb;"> &middot; pairing required</span><?php endif; ?>
  </p>

<?php if (!$bleBonded): ?>
  <div class="mx-msg" style="background:#fef3c7;border:1px solid #fbbf24;color:#92400e;">
    &#9888; No pairing or password is required to connect while this is on -- anyone within Bluetooth range (roughly 10-30m) can send DTMF, restart SvxLink, or reboot/power off the device. Turn it on only while you're actively using the app nearby, then off again. It always starts OFF after a reboot, regardless of how you leave it here.
  </div>
<?php endif; ?>

  <form method="post" style="margin-top:14px;">
    <div id="ble-off-controls" style="display:<?php echo $isActive ? 'none' : 'contents'; ?>;">
      <button name="btnOn" type="submit" class="mx-btn" style="width:200px;">Turn on</button>
    </div>
    <div id="ble-on-controls" style="display:<?php echo $isActive ? 'contents' : 'none'; ?>;">
      <button name="btnOff" type="submit" class="mx-btn mx-btn-danger" style="width:200px;">Turn off</button>
    </div>
  </form>

<?php if ($bleIsInstalled): ?>
  <div class="mx-section">Security</div>
  <form method="post">
    <div class="mx-row"><label for="ble_bonded">Require pairing for Bluetooth commands</label>
      <input type="checkbox" id="ble_bonded" name="ble_bonded" <?php echo $bleBonded ? 'checked' : ''; ?>></div>
    <p class="mx-hint">Off by default: DTMF and device commands (reboot, restart SvxLink) are accepted from anyone in range with no pairing. Turn this on to require the phone to pair (bond) first -- recommended if this hotspot is mobile or somewhere public rather than on a home desk. Applies immediately if Bluetooth is currently on. Note: reinstalling Bluetooth support resets this back off.</p>
    <button name="btnSaveBonded" type="submit" class="mx-btn" style="width:200px;">Save</button>
  </form>
<?php endif; ?>
</div>
<?php if ($pollAfterAction): ?>
<script>
(function () {
  var label = document.getElementById('ble-status-label');
  var onCtl = document.getElementById('ble-on-controls');
  var offCtl = document.getElementById('ble-off-controls');
  var hostname = <?php echo json_encode($hostname); ?>;
  var attempts = 0;
  var maxAttempts = 15; // BLE start/stop is usually fast, but the service
  // waits on hci0 + bluetooth.service in its own ExecStartPre first.

  function render(active) {
    label.innerHTML = active
      ? '<span style="color:#15803d;">&#9679; On</span> — advertising as ' + hostname
      : '<span style="color:var(--mx-text-dim);">&#9675; Off</span>';
    onCtl.style.display = active ? 'contents' : 'none';
    offCtl.style.display = active ? 'none' : 'contents';
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
