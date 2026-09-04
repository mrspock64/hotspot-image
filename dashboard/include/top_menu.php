    <p style="padding-right: 5px; text-align: right; color: #000000;">
	<a style="color: black;">Display</a> |
	<a href="/index.php" style="color: #0000ff;">Dashboard</a> | 
	<a href="/tg.php" style="color: #0000ff;">Talk Groups</a> | 
	<a href="/" onclick="javascript:event.target.port=4200" style="color: #0000ff;">Shell</a> | 
	<a href="/power.php" style="color: #0000ff;">Power</a></p>
	
</p>
	 

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
	
