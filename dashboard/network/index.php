<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <title>Network</title>
    <link href="/css/modern.css" type="text/css" rel="stylesheet" />
  </head>
<body style="background: var(--mx-bg); margin: 0;">
<?php include_once __DIR__ . '/../include/site_header.php'; ?>

<div class="mx-card" style="max-width: 640px;">
  <h1>Network</h1>
  <p class="mx-sub">Check connectivity, view connection details, or set a static IP (via NetworkManager's <code>nmcli</code>).</p>

<?php
$sAconn = '';
$myIp = '';
$cidr = '';
$gw = '';
$dns = '';
$output = null;
$message = null;
$ok = null;

$conns = [];
exec('nmcli -t -f NAME con show 2>&1', $conns);

if (isset($_POST['btnPingGw'])) {
    $sAconn = $_POST['sAconn'] ?? '';
    // nmcli ... con show <name> reads the connection PROFILE -- its
    // ipv4.gateway field is only ever populated for a manually-set static
    // IP, empty for the far more common DHCP case even while actually
    // connected with a real gateway. Confirmed live: this silently
    // reported "no gateway" for the node's own active DHCP connection.
    // The live, DHCP-assigned gateway lives on the DEVICE instead, so
    // resolve the connection's device first, then ask that.
    $device = [];
    exec('nmcli -g GENERAL.DEVICES con show ' . escapeshellarg($sAconn) . ' 2>&1', $device);
    $deviceStr = trim(implode("\n", $device));
    $ipgw = [];
    if ($deviceStr !== '') {
        exec('nmcli -g IP4.GATEWAY device show ' . escapeshellarg($deviceStr) . ' 2>&1', $ipgw);
    }
    $ipgwStr = trim(implode("\n", $ipgw));
    if ($ipgwStr === '' || !filter_var($ipgwStr, FILTER_VALIDATE_IP)) {
        $message = "\"$sAconn\" has no gateway to ping (not currently connected).";
        $ok = false;
    } else {
        exec('ping ' . escapeshellarg($ipgwStr) . ' -c 1 2>&1', $output, $code);
        $message = $code === 0 ? "Gateway ($ipgwStr) reachable." : "Gateway ($ipgwStr) did not respond.";
        $ok = $code === 0;
    }
}

if (isset($_POST['btnPingGoogle'])) {
    exec('ping 8.8.8.8 -c 1 2>&1', $output, $code);
    $message = $code === 0 ? 'Internet (8.8.8.8) reachable.' : 'Internet (8.8.8.8) did not respond.';
    $ok = $code === 0;
}

if (isset($_POST['btnAuto'])) {
    $sAconn = $_POST['sAconn'] ?? '';
    exec('nmcli con mod ' . escapeshellarg($sAconn) . ' ipv4.method auto 2>&1', $output, $code);
    exec('nmcli -p -f ipv4,general con show ' . escapeshellarg($sAconn) . ' 2>&1', $output, $code2);
    $message = $code === 0 ? "\"$sAconn\" set to automatic (DHCP) addressing -- reconnect (conn UP) for it to take effect." : "Could not change \"$sAconn\".";
    $ok = $code === 0;
}

if (isset($_POST['btnStatic'])) {
    $sAconn = $_POST['sAconn'] ?? '';
    $myIp = trim($_POST['myIp'] ?? '');
    $cidr = trim($_POST['cidr'] ?? '');
    $gw = trim($_POST['gw'] ?? '');
    $dns = trim($_POST['dns'] ?? '');

    // Validate before anything touches a shell command -- escaping alone
    // stops injection, but nmcli will happily accept garbage and
    // misconfigure the interface, which is worse to recover from
    // remotely than a rejected form.
    $validInput = filter_var($myIp, FILTER_VALIDATE_IP)
        && ctype_digit($cidr) && (int)$cidr >= 0 && (int)$cidr <= 32
        && filter_var($gw, FILTER_VALIDATE_IP);
    foreach (explode(',', $dns) as $dnsServer) {
        if (trim($dnsServer) !== '' && !filter_var(trim($dnsServer), FILTER_VALIDATE_IP)) {
            $validInput = false;
        }
    }

    if (!$validInput) {
        $message = 'Invalid IP / CIDR / gateway / DNS value -- nothing was changed.';
        $ok = false;
    } else {
        exec('nmcli con mod ' . escapeshellarg($sAconn) . ' ipv4.addresses ' . escapeshellarg("$myIp/$cidr") . ' 2>&1', $output, $code);
        exec('nmcli con mod ' . escapeshellarg($sAconn) . ' ipv4.gateway ' . escapeshellarg($gw) . ' 2>&1', $output, $code);
        exec('nmcli con mod ' . escapeshellarg($sAconn) . ' ipv4.dns ' . escapeshellarg($dns) . ' 2>&1', $output, $code);
        exec('nmcli con mod ' . escapeshellarg($sAconn) . ' ipv4.method manual 2>&1', $output, $code);
        exec('nmcli -p -f ipv4,general con show ' . escapeshellarg($sAconn) . ' 2>&1', $output, $code);
        $message = "Static IP set on \"$sAconn\" -- reconnect (conn UP) for it to take effect.";
        $ok = true;
    }
}

if (isset($_POST['btnDetails'])) {
    $sAconn = $_POST['sAconn'] ?? '';
    exec('nmcli -p -f ipv4,general con show ' . escapeshellarg($sAconn) . ' 2>&1', $output, $code);
    $message = "Details for \"$sAconn\":";
    $ok = $code === 0;
}

if (isset($_POST['btnUp'])) {
    $sAconn = $_POST['sAconn'] ?? '';
    exec('nmcli con up ' . escapeshellarg($sAconn) . ' 2>&1', $output, $code);
    $message = $code === 0 ? "\"$sAconn\" is up." : "Could not bring \"$sAconn\" up.";
    $ok = $code === 0;
}

if (isset($_POST['btnDown'])) {
    $sAconn = $_POST['sAconn'] ?? '';
    exec('nmcli con down ' . escapeshellarg($sAconn) . ' 2>&1', $output, $code);
    $message = $code === 0 ? "\"$sAconn\" is down." : "Could not bring \"$sAconn\" down.";
    $ok = $code === 0;
}
?>

<?php if ($message !== null): ?>
  <div class="mx-msg <?php echo $ok ? 'mx-msg-ok' : 'mx-msg-err'; ?>"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<?php if ($output !== null): ?>
  <textarea readonly rows="8" style="width:100%; box-sizing:border-box; background:#111; color:#0f0; border:1px solid #000; font-family: 'Courier New', monospace; font-size:11px; padding:8px; border-radius:6px; margin-bottom:14px;"><?php echo htmlspecialchars(implode("\n", $output)); ?></textarea>
<?php endif; ?>

  <form method="post">

  <div class="mx-section" style="margin-top:0;">Connectivity</div>
  <button name="btnPingGw" type="submit" class="mx-btn mx-btn-ghost" style="margin-right:8px;">Ping gateway</button>
  <button name="btnPingGoogle" type="submit" class="mx-btn mx-btn-ghost">Ping internet</button>
  <p class="mx-hint">"Ping gateway" uses whichever connection is selected below.</p>

  <div class="mx-section">Connection</div>
  <div class="mx-row"><label for="sAconn">Connection</label>
    <select id="sAconn" name="sAconn" style="padding:6px; border-radius:6px; border:1px solid var(--mx-border);">
<?php foreach ($conns as $conn): ?>
      <option value="<?php echo htmlspecialchars($conn); ?>" <?php echo $conn === $sAconn ? 'selected' : ''; ?>><?php echo htmlspecialchars($conn); ?></option>
<?php endforeach; ?>
    </select>
  </div>
  <div style="margin:10px 0;">
    <button name="btnDetails" type="submit" class="mx-btn mx-btn-ghost" style="margin-right:8px;">Show details</button>
    <button name="btnUp" type="submit" class="mx-btn mx-btn-ghost" style="margin-right:8px;">conn UP</button>
    <button name="btnDown" type="submit" class="mx-btn mx-btn-danger" onclick="return confirm('Bring this connection down now? If it\'s the one you\'re using to reach this dashboard, you will be disconnected and may need physical/console access to bring it back up.');">conn DOWN</button>
  </div>

  <div class="mx-section">Static IP</div>
  <div class="mx-row"><label for="myIp">IP / CIDR</label>
    <span><input type="text" id="myIp" name="myIp" style="width:150px; display:inline-block;" value="<?php echo htmlspecialchars($myIp); ?>"> / <input type="text" name="cidr" style="width:60px; display:inline-block;" value="<?php echo htmlspecialchars($cidr); ?>"></span></div>
  <div class="mx-row"><label for="gw">Gateway</label>
    <input type="text" id="gw" name="gw" value="<?php echo htmlspecialchars($gw); ?>"></div>
  <div class="mx-row"><label for="dns">DNS</label>
    <input type="text" id="dns" name="dns" value="<?php echo htmlspecialchars($dns); ?>"></div>
  <p class="mx-hint">CIDR: 24 for a 255.255.255.0 mask. DNS: comma-separated if more than one. Applies to whichever connection is selected above.</p>
  <button name="btnAuto" type="submit" class="mx-btn mx-btn-ghost" style="margin-right:8px;">Set automatic (DHCP)</button>
  <button name="btnStatic" type="submit" class="mx-btn">Set static IP</button>

  </form>

</div>
</body>
</html>
