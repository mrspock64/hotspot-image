<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <title>WiFi</title>
    <link href="/css/modern.css" type="text/css" rel="stylesheet" />
  </head>
<body style="background: var(--mx-bg); margin: 0;">
<?php include_once __DIR__ . '/../include/site_header.php'; ?>

<div class="mx-card" style="max-width: 640px;">
  <h1>WiFi</h1>
  <p class="mx-sub">Scan for networks, connect, manage saved WiFi connections, and check connectivity (via NetworkManager's <code>nmcli</code>).</p>

<?php
// RF.Guru's own fallback connection: if the node can't reach any configured
// network it falls back to broadcasting this as its own access point, which
// is how you'd reach this dashboard at all to fix a broken WiFi config in
// the first place. Deleting it removes that recovery path -- so unlike
// every other saved network, it must never be a selectable delete target.
const MX_PROTECTED_CONNECTIONS = ['AccessPopup'];

$ssid = '';
$output = null;
$message = null;
$ok = null;

// Saved WiFi connections only -- "nmcli con show" also lists non-WiFi
// entries (e.g. the "lo" loopback), which is just noise for a page about
// managing WiFi networks.
function mxSavedWifiConnections(): array
{
    $lines = [];
    exec('nmcli -t -f NAME,TYPE con show 2>&1', $lines);
    $names = [];
    foreach ($lines as $line) {
        $parts = explode(':', $line, 2);
        if (($parts[1] ?? '') === '802-11-wireless') {
            $names[] = $parts[0];
        }
    }
    return $names;
}

// The currently active WiFi connection (if any) -- static IP / ping below
// act on this automatically instead of asking the user to pick from a
// dropdown of every saved profile (most of which aren't even connected).
function mxActiveWifiConnection(): ?array
{
    $lines = [];
    exec('nmcli -t -f NAME,TYPE,DEVICE con show --active 2>&1', $lines);
    foreach ($lines as $line) {
        $parts = explode(':', $line, 3);
        if (($parts[1] ?? '') === '802-11-wireless') {
            return ['name' => $parts[0], 'device' => $parts[2] ?? ''];
        }
    }
    return null;
}

if (isset($_POST['btnScan'])) {
    exec('nmcli dev wifi rescan');
    exec('nmcli dev wifi list 2>&1', $output, $code);
    $message = 'Nearby networks:';
    $ok = $code === 0;
}

if (isset($_POST['btnConnList'])) {
    $output = mxSavedWifiConnections();
    $message = 'Saved WiFi networks:';
    $ok = true;
}

if (isset($_POST['btnWifiOn'])) {
    exec('nmcli radio wifi on 2>&1', $output, $code);
    $message = $code === 0 ? 'WiFi radio turned on.' : 'Could not turn WiFi radio on.';
    $ok = $code === 0;
}

if (isset($_POST['btnSwitch'])) {
    $ssid = trim($_POST['ssidSaved'] ?? '');
    if ($ssid === '') {
        $message = 'Choose a saved network first.';
        $ok = false;
    } else {
        exec('nmcli con up ' . escapeshellarg($ssid) . ' 2>&1', $output, $code);
        $message = $code === 0 ? "Connected to \"$ssid\"." : "Could not connect to \"$ssid\".";
        $ok = $code === 0;
    }
}

if (isset($_POST['btnDelete'])) {
    $ssid = trim($_POST['ssidSaved'] ?? '');
    if ($ssid === '') {
        $message = 'Choose a saved network first.';
        $ok = false;
    } elseif (in_array($ssid, MX_PROTECTED_CONNECTIONS, true)) {
        // Belt-and-suspenders: the dropdown below already omits protected
        // connections, so this should be unreachable via the UI -- but a
        // raw POST could still try it directly.
        $message = "\"$ssid\" is this node's fallback hotspot connection and can't be deleted.";
        $ok = false;
    } else {
        exec('nmcli con delete ' . escapeshellarg($ssid) . ' 2>&1', $output, $code);
        $message = $code === 0 ? "Deleted saved network \"$ssid\"." : "Could not delete \"$ssid\".";
        $ok = $code === 0;
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

if (isset($_POST['btnPingGw'])) {
    $active = mxActiveWifiConnection();
    $ipgwStr = '';
    if ($active !== null && $active['device'] !== '') {
        $ipgw = [];
        exec('nmcli -g IP4.GATEWAY device show ' . escapeshellarg($active['device']) . ' 2>&1', $ipgw);
        $ipgwStr = trim(implode("\n", $ipgw));
    }
    if ($ipgwStr === '' || !filter_var($ipgwStr, FILTER_VALIDATE_IP)) {
        $message = 'No gateway to ping -- not currently connected to a network.';
        $ok = false;
    } else {
        exec('ping ' . escapeshellarg($ipgwStr) . ' -c 1 2>&1', $output, $code);
        $message = $code === 0 ? "Gateway ($ipgwStr) reachable." : "Gateway ($ipgwStr) did not respond.";
        $ok = $code === 0;
    }
}

if (isset($_POST['btnPingInternet'])) {
    exec('ping 8.8.8.8 -c 1 2>&1', $output, $code);
    $message = $code === 0 ? 'Internet (8.8.8.8) reachable.' : 'Internet (8.8.8.8) did not respond.';
    $ok = $code === 0;
}

if (isset($_POST['btnDetails'])) {
    $active = mxActiveWifiConnection();
    if ($active === null) {
        $message = 'Not currently connected to a network.';
        $ok = false;
    } else {
        exec('nmcli -p -f ipv4,general con show ' . escapeshellarg($active['name']) . ' 2>&1', $output, $code);
        $message = "Details for \"{$active['name']}\":";
        $ok = $code === 0;
    }
}

if (isset($_POST['btnAuto'])) {
    $active = mxActiveWifiConnection();
    if ($active === null) {
        $message = 'Not currently connected to a network.';
        $ok = false;
    } else {
        exec('nmcli con mod ' . escapeshellarg($active['name']) . ' ipv4.method auto 2>&1', $output, $code);
        exec('nmcli con up ' . escapeshellarg($active['name']) . ' 2>&1', $output2, $code2);
        $message = $code === 0 ? "\"{$active['name']}\" set to automatic (DHCP) addressing and reconnected." : "Could not change \"{$active['name']}\".";
        $ok = $code === 0;
    }
}

if (isset($_POST['btnStatic'])) {
    $active = mxActiveWifiConnection();
    $myIp = trim($_POST['myIp'] ?? '');
    $cidr = trim($_POST['cidr'] ?? '');
    $gw = trim($_POST['gw'] ?? '');
    $dns = trim($_POST['dns'] ?? '');

    // Validate before anything touches a shell command -- escaping alone
    // stops injection, but nmcli will happily accept garbage and
    // misconfigure the interface, which is worse to recover from
    // remotely than a rejected form.
    $validInput = $active !== null
        && filter_var($myIp, FILTER_VALIDATE_IP)
        && ctype_digit($cidr) && (int)$cidr >= 0 && (int)$cidr <= 32
        && filter_var($gw, FILTER_VALIDATE_IP);
    foreach (explode(',', $dns) as $dnsServer) {
        if (trim($dnsServer) !== '' && !filter_var(trim($dnsServer), FILTER_VALIDATE_IP)) {
            $validInput = false;
        }
    }

    if (!$validInput) {
        $message = $active === null
            ? 'Not currently connected to a network.'
            : 'Invalid IP / CIDR / gateway / DNS value -- nothing was changed.';
        $ok = false;
    } else {
        exec('nmcli con mod ' . escapeshellarg($active['name']) . ' ipv4.addresses ' . escapeshellarg("$myIp/$cidr") . ' 2>&1', $output, $code);
        exec('nmcli con mod ' . escapeshellarg($active['name']) . ' ipv4.gateway ' . escapeshellarg($gw) . ' 2>&1', $output, $code);
        exec('nmcli con mod ' . escapeshellarg($active['name']) . ' ipv4.dns ' . escapeshellarg($dns) . ' 2>&1', $output, $code);
        exec('nmcli con mod ' . escapeshellarg($active['name']) . ' ipv4.method manual 2>&1', $output, $code);
        exec('nmcli con up ' . escapeshellarg($active['name']) . ' 2>&1', $output2, $code2);
        $message = "Static IP set on \"{$active['name']}\" and reconnected.";
        $ok = true;
    }
}

$radioStatus = trim((string)@shell_exec('nmcli radio wifi 2>/dev/null'));
$radioOn = strtolower($radioStatus) === 'enabled';
$savedWifi = mxSavedWifiConnections();
$deletableWifi = array_values(array_diff($savedWifi, MX_PROTECTED_CONNECTIONS));
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
  <form method="post" style="display:inline;"><button name="btnConnList" type="submit" class="mx-btn mx-btn-ghost">Saved networks</button></form>

  <div class="mx-section">Add a new network</div>
  <form method="post">
    <div class="mx-row"><label for="ssid">SSID (network name)</label>
      <input type="text" id="ssid" name="ssid" value="<?php echo htmlspecialchars($ssid); ?>"></div>
    <div class="mx-row"><label for="password">Password</label>
      <input type="password" id="password" name="password"></div>
    <button name="btnAdd" type="submit" class="mx-btn">Add / connect</button>
  </form>

  <div class="mx-section">Manage saved networks</div>
<?php if (empty($savedWifi)): ?>
  <p class="mx-hint">No saved WiFi networks yet.</p>
<?php else: ?>
  <form method="post">
    <div class="mx-row"><label for="ssidSaved">Saved network</label>
      <select id="ssidSaved" name="ssidSaved" style="padding:6px; border-radius:6px; border:1px solid var(--mx-border);">
<?php foreach ($savedWifi as $s): ?>
        <option value="<?php echo htmlspecialchars($s); ?>"><?php echo htmlspecialchars($s); ?><?php echo in_array($s, MX_PROTECTED_CONNECTIONS, true) ? ' (protected)' : ''; ?></option>
<?php endforeach; ?>
      </select>
    </div>
    <button name="btnSwitch" type="submit" class="mx-btn mx-btn-ghost" style="margin-right:8px;">Switch to this network</button>
<?php if (!empty($deletableWifi)): ?>
    <button name="btnDelete" type="submit" class="mx-btn mx-btn-danger" formaction="#" onclick="var sel=document.getElementById('ssidSaved'); if (![<?php echo implode(',', array_map(fn($s) => json_encode($s), $deletableWifi)); ?>].includes(sel.value)) { alert('\''+sel.value+'\'' + ' is protected and can\'t be deleted.'); return false; } return confirm('Delete the saved network \'' + sel.value + '\'? This cannot be undone.');">Delete selected</button>
<?php endif; ?>
    <p class="mx-hint">"AccessPopup" is this node's built-in fallback hotspot (it takes over automatically when no configured network is reachable) and can't be deleted here.</p>
  </form>
<?php endif; ?>

  <div class="mx-section">Connectivity</div>
  <form method="post" style="display:inline;"><button name="btnPingGw" type="submit" class="mx-btn mx-btn-ghost" style="margin-right:8px;">Ping gateway</button></form>
  <form method="post" style="display:inline;"><button name="btnPingInternet" type="submit" class="mx-btn mx-btn-ghost" style="margin-right:8px;">Ping internet</button></form>
  <form method="post" style="display:inline;"><button name="btnDetails" type="submit" class="mx-btn mx-btn-ghost">Show details</button></form>

  <div class="mx-section">Static IP</div>
  <p class="mx-hint">Applies to the currently active network<?php $active = mxActiveWifiConnection(); echo $active !== null ? ' ("' . htmlspecialchars($active['name']) . '").' : ' -- not currently connected.'; ?></p>
  <form method="post">
    <div class="mx-row"><label for="myIp">IP / CIDR</label>
      <span><input type="text" id="myIp" name="myIp" style="width:150px; display:inline-block;" value="<?php echo htmlspecialchars($myIp ?? ''); ?>"> / <input type="text" name="cidr" style="width:60px; display:inline-block;" value="<?php echo htmlspecialchars($cidr ?? ''); ?>"></span></div>
    <div class="mx-row"><label for="gw">Gateway</label>
      <input type="text" id="gw" name="gw" value="<?php echo htmlspecialchars($gw ?? ''); ?>"></div>
    <div class="mx-row"><label for="dns">DNS</label>
      <input type="text" id="dns" name="dns" value="<?php echo htmlspecialchars($dns ?? ''); ?>"></div>
    <p class="mx-hint">CIDR: 24 for a 255.255.255.0 mask. DNS: comma-separated if more than one.</p>
    <button name="btnAuto" type="submit" class="mx-btn mx-btn-ghost" style="margin-right:8px;">Set automatic (DHCP)</button>
    <button name="btnStatic" type="submit" class="mx-btn">Set static IP</button>
  </form>

</div>
</body>
</html>
