<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <title>WiFi</title>
    <link href="/css/modern.css" type="text/css" rel="stylesheet" />
  </head>
<body style="background: var(--mx-bg); margin: 0;">
<?php include_once __DIR__ . '/../include/site_header.php'; ?>

<div class="mx-card" style="max-width: 600px;">
  <h1>WiFi</h1>
  <p class="mx-sub">Scan for networks, connect, or manage saved WiFi connections (via NetworkManager's <code>nmcli</code>).</p>

<?php
$ssid = '';
$output = null;
$message = null;
$ok = null;

if (isset($_POST['btnScan'])) {
    exec('nmcli dev wifi rescan');
    exec('nmcli dev wifi list 2>&1', $output, $code);
    $message = 'Nearby networks:';
    $ok = $code === 0;
}

if (isset($_POST['btnConnList'])) {
    exec('nmcli con show --order type 2>&1', $output, $code);
    $message = 'Saved connections:';
    $ok = $code === 0;
}

if (isset($_POST['btnWifiOn'])) {
    exec('nmcli radio wifi on 2>&1', $output, $code);
    $message = $code === 0 ? 'WiFi radio turned on.' : 'Could not turn WiFi radio on.';
    $ok = $code === 0;
}

if (isset($_POST['btnSwitch'])) {
    $ssid = trim($_POST['ssid'] ?? '');
    if ($ssid === '') {
        $message = 'Enter a network name (SSID) first.';
        $ok = false;
    } else {
        exec('nmcli dev wifi connect ' . escapeshellarg($ssid) . ' 2>&1', $output, $code);
        $message = $code === 0 ? "Connected to \"$ssid\"." : "Could not connect to \"$ssid\".";
        $ok = $code === 0;
    }
}

if (isset($_POST['btnDelete'])) {
    $ssid = trim($_POST['ssid'] ?? '');
    if ($ssid === '') {
        $message = 'Enter a network name (SSID) first.';
        $ok = false;
    } else {
        exec('nmcli con delete ' . escapeshellarg($ssid) . ' 2>&1', $output, $code);
        $message = $code === 0 ? "Deleted saved network \"$ssid\"." : "Could not delete \"$ssid\".";
        $ok = $code === 0;
        $ssid = ''; // deleted -- don't leave it sitting in the field
    }
}

if (isset($_POST['btnAdd'])) {
    $ssid = trim($_POST['ssid'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    if ($ssid === '') {
        $message = 'Enter a network name (SSID) first.';
        $ok = false;
    } else {
        $cmd = 'nmcli dev wifi connect ' . escapeshellarg($ssid);
        if ($password !== '') {
            $cmd .= ' password ' . escapeshellarg($password);
        }
        exec($cmd . ' 2>&1', $output, $code);
        $message = $code === 0 ? "Connected to \"$ssid\"." : "Could not connect to \"$ssid\" -- check the password and try again.";
        $ok = $code === 0;
    }
}
// Never echo the password back into the form, success or failure -- it's
// only ever needed once per submit, and leaving it in the page source
// (even in a type="password" field, where it's just visually hidden, not
// actually protected) is needless exposure.

$radioStatus = trim((string)@shell_exec('nmcli radio wifi 2>/dev/null'));
$radioOn = strtolower($radioStatus) === 'enabled';
?>

<?php if ($message !== null): ?>
  <div class="mx-msg <?php echo $ok ? 'mx-msg-ok' : 'mx-msg-err'; ?>"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

  <p style="font-weight:600; margin-bottom:14px;">WiFi radio:
    <?php echo $radioOn
        ? '<span style="color:#15803d;">&#9679; On</span>'
        : '<span style="color:var(--mx-text-dim);">&#9675; Off</span>'; ?>
<?php if (!$radioOn): ?>
    <form method="post" style="display:inline;"><button name="btnWifiOn" type="submit" class="mx-btn" style="width:auto; padding:3px 10px; font-size:12px; margin-left:8px;">Turn on</button></form>
<?php endif; ?>
  </p>

<?php if ($output !== null): ?>
  <textarea readonly rows="8" style="width:100%; box-sizing:border-box; background:#111; color:#0f0; border:1px solid #000; font-family: 'Courier New', monospace; font-size:11px; padding:8px; border-radius:6px; margin-bottom:14px;"><?php echo htmlspecialchars(implode("\n", $output)); ?></textarea>
<?php endif; ?>

  <div class="mx-section" style="margin-top:0;">Scan / list</div>
  <form method="post" style="display:inline;"><button name="btnScan" type="submit" class="mx-btn mx-btn-ghost">Scan for networks</button></form>
  <form method="post" style="display:inline;"><button name="btnConnList" type="submit" class="mx-btn mx-btn-ghost">Saved connections</button></form>

  <div class="mx-section">Connect / manage</div>
  <form method="post">
    <div class="mx-row"><label for="ssid">SSID (network name)</label>
      <input type="text" id="ssid" name="ssid" value="<?php echo htmlspecialchars($ssid); ?>"></div>
    <div class="mx-row"><label for="password">Password</label>
      <input type="password" id="password" name="password"></div>
    <p class="mx-hint">Leave the password blank to reconnect to an already-saved network by name instead of adding a new one.</p>
    <button name="btnAdd" type="submit" class="mx-btn" style="margin-right:8px;">Add / connect</button>
    <button name="btnSwitch" type="submit" class="mx-btn mx-btn-ghost" style="margin-right:8px;">Switch to saved SSID</button>
    <button name="btnDelete" type="submit" class="mx-btn mx-btn-danger" onclick="return confirm('Delete the saved network \'' + (document.getElementById('ssid').value || '(empty)') + '\'? This cannot be undone.');">Delete saved SSID</button>
  </form>

</div>
</body>
</html>
