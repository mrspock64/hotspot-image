<?php
// modern.css is loaded by site_header.php, which includes this file --
// not linked again here to avoid a duplicate <link> on every page.
// isProcessRunning() (below, gating the RX Monitor button) lives in
// tools.php -- not every page that includes this file happens to have
// already loaded it (only index.php/tg.php/node.php do), so pull it in
// here rather than assuming.
require_once __DIR__ . '/tools.php';
$mxCurrent = basename(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH));
if ($mxCurrent === '' || $mxCurrent === 'index.php') { $mxCurrent = 'index.php'; }
function mxFileOf(string $href): string
{
    return basename(parse_url($href, PHP_URL_PATH));
}
function mxIsActive(string $href, string $current): bool
{
    $file = mxFileOf($href);
    return ($file === $current) || ($file === '' && $current === 'index.php');
}
function mxNavLink(string $href, string $label, string $current): string
{
    $cls = mxIsActive($href, $current) ? 'mx-active' : '';
    return '<a href="' . htmlspecialchars($href) . '" class="' . $cls . '">' . htmlspecialchars($label) . '</a>';
}

// Admin group: everything that's configuration or maintenance rather than
// something clicked during normal day-to-day use. Kept as a single
// dropdown rather than split further -- one bucket is enough (see the
// "en admin-grupp räcker" decision).
$mxAdminItems = [
    ['/setup/', 'Setup'],
    ['/tgnames/', 'TG Names'],
    ['/wifi/', 'WiFi'],
    // EchoLink deliberately hidden from the nav for now, per user request --
    // not configured (still placeholder credentials) and the module isn't
    // even in svxlink.conf's MODULES= list, so the page wouldn't actually
    // do anything useful yet. The page itself (dashboard/echolink/) is
    // untouched and still reachable directly by URL -- add the row back
    // here (and finish wiring MODULES= toggling + basic cleanup, see the
    // EchoLink investigation this came from) if EchoLink is ever wanted.
    ['/dtmf/', 'DTMF'],
    ['/bluetooth/', 'Bluetooth'],
    ['/radiotest/', 'Radio Test'],
    ['/soundlib/', 'Sound Library'],
    ['/update/', 'Update'],
    ['/backup/', 'Backup'],
    ['/docs/', 'Docs'],
    ['/log/', 'Log'],
];
$mxAdminOpen = false;
foreach ($mxAdminItems as [$href, ]) {
    if (mxIsActive($href, $mxCurrent)) { $mxAdminOpen = true; break; }
}
?>
<nav class="mx-nav">
<?php
echo mxNavLink('/index.php', 'Dashboard', $mxCurrent);
echo mxNavLink('/tg.php', 'Talk Groups', $mxCurrent);
echo mxNavLink('/buttons/', 'Buttons', $mxCurrent);
echo mxNavLink('/qsolog/', 'QSO Log', $mxCurrent);
echo mxNavLink('/power/', 'Power', $mxCurrent);
echo mxNavLink('/help/', 'Help', $mxCurrent);
?>
<details class="mx-dropdown<?php echo $mxAdminOpen ? ' mx-active' : ''; ?>">
  <summary>Admin</summary>
  <div class="mx-dropdown-content">
<?php foreach ($mxAdminItems as [$href, $label]): ?>
    <?php echo mxNavLink($href, $label, $mxCurrent); ?>
<?php endforeach; ?>
    <a href="/" onclick="event.target.port=4200">Shell</a>
  </div>
</details>
<?php if (isProcessRunning('node')): ?>
<?php if ($mxQsoRecorderActive ?? false): ?>
  <button id="mx-rxmon-btn" onclick="mxToggleRxMonitor(this)" class="mx-rxmon-btn" style="margin-left:auto;">
    <img src="/images/speaker.png" alt="" style="vertical-align:middle;height:12px;margin-right:4px;">RX Monitor
  </button>
<?php else: ?>
  <a href="/qsolog/" class="mx-rxmon-btn" style="margin-left:auto; opacity:0.6; text-decoration:none;" title="RX Monitor needs QSO Recorder turned on first -- click to go to the QSO Log page and turn it on.">
    <img src="/images/speaker.png" alt="" style="vertical-align:middle;height:12px;margin-right:4px;">RX Monitor (off)
  </a>
<?php endif; ?>
<?php endif; ?>
</nav>
<script>
// RX Monitor's own player (pcm-player.min.js's SVXPlayer) lives entirely
// in page JS -- a full page navigation (every link in this multi-page
// dashboard) tears it down along with everything else, since there's no
// SPA-style persistence here. Full persistence-without-a-gap would need
// restructuring the whole dashboard as an SPA; this is the practical
// middle ground instead -- remember "was playing" across the navigation
// in localStorage, and auto-resume on the next page if it's still
// available there (a second or two gap while the new page loads, not
// seamless, but no manual re-click needed).
(function () {
  var RXMON_KEY = 'mxRxMonitorPlaying';
  window.mxToggleRxMonitor = function (btn) {
    playAudioToggle(8080, btn);
    // playAudioToggle() toggles synchronously (isPlaying() flips inside
    // the same call), so the state right after the call is the new state.
    try {
      if (window.svxp && window.svxp.isPlaying()) {
        localStorage.setItem(RXMON_KEY, '1');
      } else {
        localStorage.removeItem(RXMON_KEY);
      }
    } catch (e) { /* localStorage unavailable (private mode, etc) -- just skip persistence */ }
  };
  try {
    if (localStorage.getItem(RXMON_KEY) === '1') {
      var btn = document.getElementById('mx-rxmon-btn');
      // Only auto-resume if this page actually has the button (QSO
      // Recorder still on, rx-monitor-proxy still running) -- otherwise
      // clear the stale flag so it doesn't keep trying on every future
      // page too.
      if (btn) {
        playAudioToggle(8080, btn);
      } else {
        localStorage.removeItem(RXMON_KEY);
      }
    }
  } catch (e) { /* localStorage unavailable -- nothing to resume */ }
})();
</script>
<script>
document.addEventListener('click', function (e) {
  document.querySelectorAll('.mx-dropdown[open]').forEach(function (d) {
    if (!d.contains(e.target)) d.removeAttribute('open');
  });
});
</script>

<?php
include_once('parse_svxconf.php')
/*if (fopen($svxConfigFile,'r'))
{

  $svxconfig = parse_ini_file($svxConfigFile,true,INI_SCANNER_RAW);
  $logics = explode(",",$svxconfig['GLOBAL']['LOGICS']);
  foreach ($logics as $key) {
	if ($key == "SimplexLogic") $isSimplex = true;
	if ($key == "RepeaterLogic") $isRepeater = true;
  };
  $logics = explode(",",$svxconfig['GLOBAL']['LOGICS']);
  if ($isSimplex) $modules = explode(",",str_replace('Module','',$svxconfig['SimplexLogic']['MODULES']));
  if ($isRepeater) $modules = explode(",",str_replace('Module','',$svxconfig['RepeaterLogic']['MODULES']));
  foreach ($modules as $key){
	if ($key == "EchoLink") $isEchoLink = true;
 }
 */
 //if ($isEchoLink==true) {echo ' <a href="/echolink.php" style="color: #0000ff;">EchoLink</a> |';};
//$globalRf = $svxconfig['GLOBAL']['RF_MODULE'];

/*if ($globalRf <> "No")
{
	echo'	<a href="/rf.php" style="color: #0000ff;"> Rf</a> |';
}
}*/
?>
