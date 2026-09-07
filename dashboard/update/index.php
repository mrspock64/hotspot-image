<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
  </head>
<body style="background: var(--mx-bg); margin: 0;">
<?php include_once __DIR__ . '/../include/site_header.php'; ?>

<div class="mx-card" style="max-width: 600px;">
  <h1 style="text-align:center;">Updater</h1>

<?php
// The check.*.sh/update.*.sh scripts below are invoked by bare filename
// (e.g. "sh check.os.sh"), and their output was written to a hardcoded
// /var/www/html/update/screen.log -- both assume PHP's cwd is this exact
// directory, which isn't guaranteed (e.g. testing this dashboard from
// anywhere other than /var/www/html, as we did during development).
// Fixed the cwd explicitly instead and switched every reference below to
// a plain relative "screen.log" / "check.os.sh" etc.
chdir(__DIR__);

session_start();

// Every check.*.sh/update.*.sh below writes to the same shared screen.log,
// backgrounded with "&" so the page can respond immediately -- meaning
// nothing stopped two of them running at once (e.g. clicking Check while
// a previous check was still finishing) from interleaving their output
// into the same file. Confirmed live: clicking Check OS then Check
// SVXLink in quick succession produced a garbled, mixed-up screen.log --
// not a bug in either script individually. flock -n makes the second
// click a clean "already running" message instead of silent corruption.
// On lock failure this deliberately does NOT touch screen.log -- an
// earlier version wrote a "busy" message there instead, which raced the
// still-running job's own writes to the exact same file just as badly as
// the original bug (confirmed live: a garbled screen.log with the busy
// message overwritten mid-write). Leaving screen.log alone means a
// rejected click just falls through to the existing polling below, which
// keeps showing the real ongoing job's actual progress -- more useful
// than a terse rejection anyway, and impossible to interleave wrong.
define('UPDATER_LOCK_FILE', '/var/cache/hotspot-image/updater.lock');
function runUpdaterScript(string $scriptName): void
{
    $inner = 'sudo nice -n 19 sh ' . escapeshellarg($scriptName) . ' > screen.log 2>&1';
    $cmd = sprintf(
        'flock -n %s sh -c %s > /dev/null 2>&1 &',
        escapeshellarg(UPDATER_LOCK_FILE),
        escapeshellarg($inner)
    );
    exec($cmd);
}

$screen = [
    "Welcome to HotSpot Updater.",
    "",
    "Please use buttons for appropriate actions.",
];

// Auto-refresh (every 3s) while the last-triggered action is still
// running, polling screen.log for the "finished" marker every check.*.sh/
// update.*.sh script ends with. Top-to-bottom, matching the order the
// script actually wrote it -- this used to pipe through `tac` to show the
// newest line first, which read fine for a single-line status but made
// multi-line output (apt's progress, SvxLink's release notes) look
// backwards. Auto-scrolled to the bottom via JS instead, so the latest
// line is still what's visible without needing to scroll.
if (!empty($_SESSION['refresh'])) {
    $screen = [];
    exec('tail -n 500 screen.log 2>&1', $screen);
    if (($screen[count($screen) - 1] ?? '') === '###-FINISH-####') {
        $_SESSION['refresh'] = false;
    } else {
        header('Refresh: 3');
    }
}

$actions = [
    'btnChkOs'          => 'check.os.sh',
    'btnUpdateOs'        => 'update.os.sh',
    'btnChkSvxlink'      => 'check.svxlink.sh',
    'btnUpdateSvxlink'   => 'update.svxlink.sh',
    'btnChkDashboard'    => 'check.dashboard.sh',
    'btnUpdateDashboard' => 'update.dashboard.sh',
];
foreach ($actions as $btn => $script) {
    if (isset($_POST[$btn])) {
        // update.dashboard.sh alone needs to survive /var/www/html itself
        // being replaced mid-run (it re-syncs dashboard/ from /opt/
        // hotspot-image into /var/www/html), so it's copied out to /opt
        // first and run from there instead of from this directory.
        if ($btn === 'btnUpdateDashboard') {
            exec('sudo cp update.dashboard.sh /opt');
            runUpdaterScript('/opt/update.dashboard.sh');
        } else {
            runUpdaterScript($script);
        }
        $_SESSION['refresh'] = true;
        header('Refresh: 3');
        break;
    }
}
?>

<form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]);?>">

  <textarea id="updater-screen" name="scan" rows="14" style="width:100%; box-sizing:border-box; background:#111; color:#0f0; border:1px solid #000; font-family: 'Courier New', monospace; font-size:11px; padding:8px; border-radius:6px;"><?php
			echo htmlspecialchars(implode("\n", $screen)); ?></textarea>
  <script>document.getElementById('updater-screen').scrollTop = 1e9;</script>

  <div class="mx-section">Check versions</div>
  <button name="btnChkOs" type="submit" class="mx-btn mx-btn-ghost">OS</button>
  <button name="btnChkSvxlink" type="submit" class="mx-btn mx-btn-ghost">SVXLink</button>
  <button name="btnChkDashboard" type="submit" class="mx-btn mx-btn-ghost">Dashboard</button>

  <div class="mx-section">Upgrade</div>
  <button name="btnUpdateOs" type="submit" class="mx-btn">OS</button>
  <button name="btnUpdateSvxlink" type="submit" class="mx-btn">SVXLink</button>
  <button name="btnUpdateDashboard" type="submit" class="mx-btn">Dashboard</button>
  <p class="mx-hint" style="margin-top:8px;">Sounds and Config updates from the original SVXLink-Dash-V2 project pointed at an unrelated ham network's own GitHub repo and would have overwritten this node's sound pack / event scripts with theirs -- removed rather than pointed at a real destination. See <a href="/help/">Help</a>.</p>

</form>

</div>
</body>
</html>
