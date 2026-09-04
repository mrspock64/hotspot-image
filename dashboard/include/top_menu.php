<?php
// modern.css is loaded by site_header.php, which includes this file --
// not linked again here to avoid a duplicate <link> on every page.
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
    ['/network/', 'Network'],
    ['/echolink/', 'EchoLink'],
    ['/dtmf/', 'DTMF'],
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
</nav>
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
