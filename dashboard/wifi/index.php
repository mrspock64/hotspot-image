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

// Every currently active connection (WiFi or wired), loopback excluded --
// used both to show live status at the top of the page and to pick a
// sensible default for Ping/Show details below, so plugging in a USB
// Ethernet adapter (which shows up here as its own 802-3-ethernet
// connection) doesn't leave those actions reporting "not connected".
function mxActiveConnections(): array
{
    $lines = [];
    exec('nmcli -t -f NAME,TYPE,DEVICE con show --active 2>&1', $lines);
    $conns = [];
    foreach ($lines as $line) {
        $parts = explode(':', $line, 3);
        $type = $parts[1] ?? '';
        if ($type === 'loopback') {
            continue;
        }
        $conns[] = ['name' => $parts[0], 'type' => $type, 'device' => $parts[2] ?? ''];
    }
    return $conns;
}

// Prefer an active WiFi connection for Ping/Details, but fall back to
// whatever else is active (e.g. wired) rather than reporting "not
// connected" when the node actually has connectivity another way.
function mxPreferredActiveConnection(): ?array
{
    $active = mxActiveConnections();
    foreach ($active as $c) {
        if ($c['type'] === '802-11-wireless') {
            return $c;
        }
    }
    return $active[0] ?? null;
}

if (isset($_POST['btnScan'])) {
    exec('nmcli dev wifi rescan 2>&1', $rescanOutput, $rescanCode);
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
    $active = mxPreferredActiveConnection();
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
    $active = mxPreferredActiveConnection();
    if ($active === null) {
        $message = 'Not currently connected to a network.';
        $ok = false;
    } else {
        exec('nmcli -p -f ipv4,general con show ' . escapeshellarg($active['name']) . ' 2>&1', $output, $code);
        $message = "Details for \"{$active['name']}\":";
        $ok = $code === 0;
    }
}

$radioStatus = trim((string)@shell_exec('nmcli radio wifi 2>/dev/null'));
$radioOn = strtolower($radioStatus) === 'enabled';
$savedWifi = mxSavedWifiConnections();
$deletableWifi = array_values(array_diff($savedWifi, MX_PROTECTED_CONNECTIONS));
$activeConns = mxActiveConnections();
$activeWifi = null;
$activeWired = [];
foreach ($activeConns as $c) {
    if ($c['type'] === '802-11-wireless' && $activeWifi === null) {
        $activeWifi = $c;
    } elseif ($c['type'] === '802-3-ethernet') {
        $activeWired[] = $c;
    }
}
?>

<?php if ($message !== null): ?>
  <div class="mx-msg <?php echo $ok ? 'mx-msg-ok' : 'mx-msg-err'; ?>"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

  <p style="font-weight:600; margin-bottom:6px;">WiFi radio:
    <?php echo $radioOn
        ? '<span style="color:#15803d;">&#9679; On</span>'
        : '<span style="color:var(--mx-text-dim);">&#9675; Off</span>'; ?>
<?php if (!$radioOn): ?>
    <form method="post" style="display:inline;"><button name="btnWifiOn" type="submit" class="mx-btn" style="width:auto; padding:3px 10px; font-size:12px; margin-left:8px;">Turn on</button></form>
<?php endif; ?>
  </p>

  <p style="font-weight:600; margin-bottom:4px;">Active network:
<?php if ($activeWifi !== null): ?>
    <span style="color:#15803d;">&#9679; <?php echo htmlspecialchars($activeWifi['name']); ?></span> <span class="mx-hint" style="display:inline;">(WiFi, <?php echo htmlspecialchars($activeWifi['device']); ?>)</span>
<?php else: ?>
    <span style="color:var(--mx-text-dim);">&#9675; not connected</span>
<?php endif; ?>
  </p>
<?php foreach ($activeWired as $w): ?>
  <p style="font-weight:600; margin-bottom:4px;">Wired connection: <span style="color:#15803d;">&#9679; <?php echo htmlspecialchars($w['name']); ?></span> <span class="mx-hint" style="display:inline;">(Ethernet, <?php echo htmlspecialchars($w['device']); ?>)</span></p>
<?php endforeach; ?>
  <p class="mx-hint" style="margin-bottom:14px;">A plugged-in USB/LAN adapter shows up here automatically once it has its own connection.</p>

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

</div>
</body>
</html>
