<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <title>Radio Test</title>
    <link href="/css/modern.css" type="text/css" rel="stylesheet" />
  </head>
<body style="background: var(--mx-bg); margin: 0;">
<?php include_once __DIR__ . '/../include/site_header.php'; ?>

<?php
define('QSO_SIM_SCRIPT', '/opt/load-monitor/qso_simulate.sh');
define('QSO_SIM_PID_FILE', '/var/cache/hotspot-image/qso_sim.pid');
define('QSO_SIM_LOG_FILE', '/var/cache/hotspot-image/qso_sim_log');
define('QSO_SIM_RESULT_FILE', '/var/cache/hotspot-image/qso_sim_result');

function qsoSimRunning(): bool
{
    if (!is_file(QSO_SIM_PID_FILE)) {
        return false;
    }
    $pid = trim((string)@file_get_contents(QSO_SIM_PID_FILE));
    return ctype_digit($pid) && is_dir("/proc/$pid");
}

$error = null;
$message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btnStart']) && !qsoSimRunning()) {
    // Sane bounds -- this keys a real transmitter, so the form fields are
    // clamped server-side too, not just via the <input min/max> below.
    $exchanges = max(1, min(60, (int)($_POST['exchanges'] ?? 12)));
    $minTx = max(1, min(60, (int)($_POST['min_tx'] ?? 5)));
    $maxTx = max($minTx, min(60, (int)($_POST['max_tx'] ?? 20)));
    $minPause = max(1, min(30, (int)($_POST['min_pause'] ?? 2)));
    $maxPause = max($minPause, min(30, (int)($_POST['max_pause'] ?? 5)));
    $keepSvxlink = isset($_POST['keep_svxlink']) ? 1 : 0;

    @unlink(QSO_SIM_RESULT_FILE);
    $cmd = sprintf(
        'sudo %s %d %d %d %d %d %d > %s 2>&1 &',
        escapeshellarg(QSO_SIM_SCRIPT),
        $exchanges,
        $minTx,
        $maxTx,
        $minPause,
        $maxPause,
        $keepSvxlink,
        escapeshellarg('/dev/null')
    );
    exec($cmd);
    $message = $keepSvxlink
        ? 'QSO simulation started -- SvxLink stays running for this test (combined-load mode).'
        : 'QSO simulation started -- svxlink will go offline for the duration and come back automatically when it finishes (or if you abort it).';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btnAbort']) && qsoSimRunning()) {
    $pid = trim((string)@file_get_contents(QSO_SIM_PID_FILE));
    exec('sudo kill ' . escapeshellarg($pid) . ' 2>&1');
    $message = 'Aborting -- svxlink restarts automatically as soon as the current transmission stops.';
}

$running = qsoSimRunning();
$log = is_file(QSO_SIM_LOG_FILE) ? (string)@file_get_contents(QSO_SIM_LOG_FILE) : '';
$result = is_file(QSO_SIM_RESULT_FILE) ? json_decode((string)@file_get_contents(QSO_SIM_RESULT_FILE), true) : null;
$svxActive = trim((string)@shell_exec('systemctl is-active svxlink 2>/dev/null')) === 'active';
?>

<div class="mx-card" style="max-width: 600px;">
  <h1>Radio Test</h1>
  <p class="mx-sub">
    Simulates a real QSO's transmit pattern -- variable-length transmissions with pauses, played through
    the actual radio (same GPIO PTT line and audio path SvxLink itself uses) -- to measure how much the
    radio module's own PA heats the case, separate from CPU/SoC heat. Requires a real antenna or dummy
    load connected -- sustained transmission into an unloaded output can damage the radio module.
  </p>

<?php if ($message): ?>
  <div class="mx-msg mx-msg-ok"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<?php if ($running): ?>
  <div class="mx-msg" style="background:#fef3c7;border:1px solid #fbbf24;color:#92400e;">
    &#9203; Simulation running -- SvxLink is currently <?php echo $svxActive ? 'still running (combined-load mode)' : 'offline until it finishes'; ?>.
  </div>
  <form method="post" style="margin-bottom:16px;">
    <button name="btnAbort" type="submit" class="mx-btn mx-btn-danger" onclick="return confirm('Abort the test now? SvxLink restarts automatically once the current transmission stops.');">Abort test</button>
  </form>
<?php else: ?>
  <p style="font-weight:600; margin-bottom:14px;">SvxLink:
    <?php echo $svxActive
        ? '<span style="color:#15803d;">&#9679; Running</span>'
        : '<span style="color:var(--mx-text-dim);">&#9675; Stopped</span>'; ?>
  </p>

  <form method="post">
    <div class="mx-row"><label for="exchanges">Number of exchanges</label>
      <input type="text" id="exchanges" name="exchanges" value="12" style="width:80px;"></div>
    <div class="mx-row"><label for="min_tx">TX length (sec)</label>
      <span><input type="text" id="min_tx" name="min_tx" value="5" style="width:60px; display:inline-block;"> to <input type="text" name="max_tx" value="20" style="width:60px; display:inline-block;"></span></div>
    <div class="mx-row"><label for="min_pause">Pause between (sec)</label>
      <span><input type="text" id="min_pause" name="min_pause" value="2" style="width:60px; display:inline-block;"> to <input type="text" name="max_pause" value="5" style="width:60px; display:inline-block;"></span></div>
    <p class="mx-hint">12 exchanges of 5-20s with 2-5s pauses runs roughly 4-6 minutes total.</p>
    <label style="font-size:13px; font-weight:normal; display:block; margin-bottom:8px;">
      <input type="checkbox" name="keep_svxlink" style="width:auto; vertical-align:middle;">
      Keep SvxLink running during the test
    </label>
    <p class="mx-hint">Off (default): SvxLink stops for the test and restarts automatically -- isolates the radio module's own heat. On: SvxLink keeps running so this measures combined real-world load instead -- but both then reach for the same PTT line and audio device, so if a real transmission lands at the same moment, expect possible audio glitches or PTT flicker (not hardware damage).</p>
    <button name="btnStart" type="submit" class="mx-btn mx-btn-danger" onclick="return confirm('Start the QSO simulation? This transmits real RF for several minutes -- make sure a real antenna or dummy load is connected.');">Start test</button>
  </form>
<?php endif; ?>

<?php if ($result): ?>
  <div class="mx-section">Last result</div>
  <table class="mx-table">
    <tr><th>Start temp</th><td><?php echo htmlspecialchars((string)$result['start_temp']); ?>&deg;C</td></tr>
    <tr><th>Peak temp</th><td><?php echo htmlspecialchars((string)$result['peak_temp']); ?>&deg;C</td></tr>
    <tr><th>End temp</th><td><?php echo htmlspecialchars((string)$result['end_temp']); ?>&deg;C</td></tr>
    <tr><th>Exchanges</th><td><?php echo htmlspecialchars((string)$result['exchanges']); ?></td></tr>
  </table>
<?php endif; ?>

<?php if ($log !== ''): ?>
  <div class="mx-section">Log</div>
  <textarea id="radiotest-log" readonly rows="14" style="width:100%; box-sizing:border-box; background:#111; color:#0f0; border:1px solid #000; font-family: 'Courier New', monospace; font-size:11px; padding:8px; border-radius:6px;"><?php echo htmlspecialchars($log); ?></textarea>
  <script>document.getElementById('radiotest-log').scrollTop = 1e9;</script>
<?php endif; ?>

</div>
<?php if ($running): ?>
<script>setTimeout(function () { location.reload(); }, 3000);</script>
<?php endif; ?>
</body>
</html>
