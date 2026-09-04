<link href="/css/modern.css" type="text/css" rel="stylesheet" />
<?php
$mxCurrent = basename(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH));
if ($mxCurrent === '' || $mxCurrent === 'index.php') { $mxCurrent = 'index.php'; }
function mxNavLink(string $href, string $label, string $current): string
{
    $file = basename(parse_url($href, PHP_URL_PATH));
    $isActive = ($file === $current) || ($file === '' && $current === 'index.php');
    $cls = $isActive ? 'mx-active' : '';
    return '<a href="' . htmlspecialchars($href) . '" class="' . $cls . '">' . htmlspecialchars($label) . '</a>';
}
?>
<nav class="mx-nav">
<?php
echo mxNavLink('/index.php', 'Dashboard', $mxCurrent);
echo mxNavLink('/tg.php', 'Talk Groups', $mxCurrent);
echo mxNavLink('/buttons/', 'Buttons', $mxCurrent);
echo mxNavLink('/setup/', 'Setup', $mxCurrent);
echo mxNavLink('/wifi.php', 'WiFi', $mxCurrent);
echo mxNavLink('/network.php', 'Network', $mxCurrent);
echo mxNavLink('/update/', 'Update', $mxCurrent);
echo mxNavLink('/backup/', 'Backup', $mxCurrent);
echo mxNavLink('/docs/', 'Docs', $mxCurrent);
echo mxNavLink('/log.php', 'Log', $mxCurrent);
?>
<a href="/" onclick="event.target.port=4200">Shell</a>
<?php echo mxNavLink('/power.php', 'Power', $mxCurrent); ?>
</nav>

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
