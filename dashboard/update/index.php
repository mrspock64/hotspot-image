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
// (e.g. "sh check.os.sh"), which assumes PHP's cwd is this exact
// directory -- not guaranteed otherwise (e.g. testing this dashboard from
// anywhere other than /var/www/html, as we did during development).
chdir(__DIR__);

// Every check.*.sh/update.*.sh below writes to the same shared log file,
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
// Deliberately NOT under /var/www/html: update.dashboard.sh's own job is
// to `rm -rf` and replace that entire directory mid-run -- confirmed
// live, a screen.log living inside it got deleted out from under the
// still-running script's own open file handle, silently losing every
// line written after that point (including the "finished" marker), so
// the page it drives got stuck looking perpetually in-progress. A path
// outside the directory being replaced survives regardless of which
// action is running.
define('UPDATER_SCREEN_LOG', '/var/cache/hotspot-image/updater-screen.log');
function runUpdaterScript(string $scriptName): void
{
    $inner = 'sudo nice -n 19 sh ' . escapeshellarg($scriptName) . ' > ' . escapeshellarg(UPDATER_SCREEN_LOG) . ' 2>&1';
    $cmd = sprintf(
        'flock -n %s sh -c %s > /dev/null 2>&1 &',
        escapeshellarg(UPDATER_LOCK_FILE),
        escapeshellarg($inner)
    );
    exec($cmd);
}

require_once __DIR__ . '/../include/inisync.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_autoupdate'])) {
    iniSyncUpdateSection('/etc/svxlink/svxlink.conf', 'Dashboard', [
        'AUTO_UPDATE_DASHBOARD' => isset($_POST['auto_update_dashboard']) ? '1' : '0',
    ]);
}
$autoUpdateDashboard = getAutoUpdateDashboard();

$actions = [
    'btnChkOs'          => 'check.os.sh',
    'btnUpdateOs'        => 'update.os.sh',
    'btnChkSvxlink'      => 'check.svxlink.sh',
    'btnUpdateSvxlink'   => 'update.svxlink.sh',
    'btnChkDashboard'    => 'check.dashboard.sh',
    'btnUpdateDashboard' => 'update.dashboard.sh',
];
$justTriggered = false;
foreach ($actions as $btn => $script) {
    if (isset($_POST[$btn])) {
        $justTriggered = true;
        // Deliberately NOT clearing screen.log here first: it might belong
        // to a still-running job (if this click is about to lose the flock
        // race in runUpdaterScript()), and unlinking out from under an
        // actively-writing process would orphan its output -- this page
        // would then show the "Welcome" placeholder while that job kept
        // running, invisibly, on its now-detached file. Each script's own
        // "> screen.log" redirection truncates it within microseconds of
        // actually starting, which is a fine enough race window to accept.
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
        break;
    }
}

// Shows whatever screen.log actually contains, if anything -- NOT gated on
// PHP session state (an earlier version was, via $_SESSION['refresh']), so
// coming back to this page always reflects reality regardless of how long
// you were away, whether your session cookie survived, or whether you're
// even the same browser: the background job itself runs completely
// independent of the browser/session either way. Auto-refreshes (every 3s)
// for as long as the file doesn't yet end with the "finished" marker every
// check.*.sh/update.*.sh script ends with. Top-to-bottom, matching the
// order the script actually wrote it -- this used to pipe through `tac` to
// show the newest line first, which read fine for a single-line status but
// made multi-line output (apt's progress, SvxLink's release notes) look
// backwards. Auto-scrolled to the bottom via JS instead, so the latest
// line is still what's visible without needing to scroll.
$screen = [
    "Welcome to HotSpot Updater.",
    "",
    "Please use buttons for appropriate actions.",
];
$stillRunning = $justTriggered;
if (is_file(UPDATER_SCREEN_LOG) && filesize(UPDATER_SCREEN_LOG) > 0) {
    $screen = [];
    exec('tail -n 500 ' . escapeshellarg(UPDATER_SCREEN_LOG) . ' 2>&1', $screen);
    if (($screen[count($screen) - 1] ?? '') !== '###-FINISH-####') {
        $stillRunning = true;
    }
}
// The flock in runUpdaterScript() already stops a second action from
// actually running (or corrupting the shared log), but until now gave no
// visual sign that a click while one was already in progress did nothing
// -- confirmed live, the user had no way to tell a check click landed on
// a no-op while an upgrade was still building. Disabling every button
// while something is running (log.php polling below re-enables them --
// via a one-time reload once it finishes) makes that state visible
// instead of silent.
//
// Used to be header('Refresh: 3') -- a full page reload every 3s.
// Confirmed live (2026-09-10): individual responses stayed fast (~0.2s)
// even mid-upgrade, so the reload itself (re-running every page script,
// re-fetching every asset) was the slow part users actually felt, not
// the server. log.php + JS below polls for just the log text instead.
?>

<form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]);?>">

  <textarea id="updater-screen" name="scan" rows="14" style="width:100%; box-sizing:border-box; background:#111; color:#0f0; border:1px solid #000; font-family: 'Courier New', monospace; font-size:11px; padding:8px; border-radius:6px;"><?php
			echo htmlspecialchars(implode("\n", $screen)); ?></textarea>
  <script>document.getElementById('updater-screen').scrollTop = 1e9;</script>

<?php if ($stillRunning): ?>
  <p class="mx-msg" style="background:#fef3c7;border:1px solid #fbbf24;color:#92400e;">&#9203; An action is already running -- buttons are disabled until it finishes. The log below updates live.</p>
<?php endif; ?>
  <div class="mx-section">Check versions</div>
  <button name="btnChkOs" type="submit" class="mx-btn mx-btn-ghost"<?php echo $stillRunning ? ' disabled' : ''; ?>>OS</button>
  <button name="btnChkSvxlink" type="submit" class="mx-btn mx-btn-ghost"<?php echo $stillRunning ? ' disabled' : ''; ?>>SVXLink</button>
  <button name="btnChkDashboard" type="submit" class="mx-btn mx-btn-ghost"<?php echo $stillRunning ? ' disabled' : ''; ?>>Dashboard</button>

  <div class="mx-section">Upgrade</div>
  <button name="btnUpdateOs" type="submit" class="mx-btn"<?php echo $stillRunning ? ' disabled' : ''; ?> onclick="return confirm('Upgrade OS packages now? A kernel/firmware upgrade may need a device restart afterwards to fully take effect.');">OS</button>
  <button name="btnUpdateSvxlink" type="submit" class="mx-btn"<?php echo $stillRunning ? ' disabled' : ''; ?> onclick="return confirm('Upgrade SvxLink now? The radio will be unavailable while it rebuilds and restarts.');">SVXLink</button>
  <button name="btnUpdateDashboard" type="submit" class="mx-btn"<?php echo $stillRunning ? ' disabled' : ''; ?> onclick="return confirm('Upgrade the dashboard now? It will briefly reload mid-upgrade.');">Dashboard</button>
  <p class="mx-hint" style="margin-top:8px;">Sounds and Config updates from the original SVXLink-Dash-V2 project pointed at an unrelated ham network's own GitHub repo and would have overwritten this node's sound pack / event scripts with theirs -- removed rather than pointed at a real destination. See <a href="/help/">Help</a>.</p>

  <div class="mx-section">Auto-update</div>
  <label style="font-size:13px; font-weight:normal; display:block; margin-bottom:6px;">
    <input type="checkbox" name="auto_update_dashboard" <?php echo $autoUpdateDashboard ? 'checked' : ''; ?>>
    Automatically apply a new dashboard version, hourly
  </label>
  <p class="mx-hint" style="margin:0 0 8px;">On by default. Checks GitHub roughly once an hour (plus up to 5 minutes' random delay) and applies a new commit the same way "Update Dashboard" above does -- backup, re-sync, restart SvxLink. Deliberately scoped to the dashboard only, never OS or SvxLink -- those stay manual/opt-in, they're far more invasive (a kernel upgrade needing a reboot, an SvxLink rebuild taking the better part of an hour).</p>
  <button type="submit" name="save_autoupdate" class="mx-btn mx-btn-ghost">Save</button>

</form>

<?php if ($stillRunning): ?>
<script>
// Polls log.php for new log text every 2s instead of reloading the whole
// page -- see the "Used to be header('Refresh: 3')" comment above for
// why. One real reload happens once the action actually finishes, to
// cleanly restore normal (non-disabled) button state and pick up
// anything else that changed (dashboard/svxlink version strings, etc.)
// rather than trying to patch all of that up via JS too.
(function () {
  var ta = document.getElementById('updater-screen');
  function poll() {
    fetch('log.php', { cache: 'no-store' }).then(function (r) { return r.json(); }).then(function (d) {
      ta.value = d.log;
      ta.scrollTop = 1e9;
      if (d.running) {
        setTimeout(poll, 2000);
      } else {
        // Not location.reload() -- this page was itself loaded via a POST
        // (the button click that started the action), and reload() on a
        // POST-loaded document resubmits that POST. Confirmed live: it
        // silently re-triggered the same check/upgrade action a second
        // time. A plain GET to the same path avoids that entirely.
        window.location.href = window.location.pathname;
      }
    }).catch(function () {
      setTimeout(poll, 2000);
    });
  }
  setTimeout(poll, 2000);
})();
</script>
<?php endif; ?>

</div>
</body>
</html>
