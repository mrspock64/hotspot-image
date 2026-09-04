<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <link href="/css/modern.css" type="text/css" rel="stylesheet" />
  </head>
<body style="background: var(--mx-bg); margin: 0;">
<?php include_once __DIR__ . '/../include/site_header.php'; ?>

<div class="mx-card" style="max-width: 600px;">
  <h1 style="text-align:center;">EchoLink Configurator</h1>

<?php
include_once('include/functions.php');

$elConfigFile = '/etc/svxlink/svxlink.d/ModuleEchoLink.conf';
if (fopen($elConfigFile,'r'))
      {
          $elconfig = parse_ini_file($elConfigFile,true,INI_SCANNER_RAW);
      };
  $module = str_split($elconfig['ModuleEchoLink']);
  foreach ($module as $key) {
//if ($logics[0] == "[ModuleEchoLink]") $isEchoLink = true;
  }

if (isset($_POST['btnSave'])) {
  $retval = null;
  $screen = null;


  $elconfig['ModuleEchoLink']['DEFAULT_LANG'] = $_POST['inElDefaultLang'];
  $elconfig['ModuleEchoLink']['CALLSIGN'] = $_POST['inElCallsign'];
  $elconfig['ModuleEchoLink']['PASSWORD'] = $_POST['inElPassword'];
  $elconfig['ModuleEchoLink']['SYSOPNAME'] = $_POST['inElSysOpName'];
  $elconfig['ModuleEchoLink']['LOCATION'] = $_POST['inElLocation'];

  $elconfig['ModuleEchoLink']['SERVERS'] = $_POST['inElServers'];
  $elconfig['ModuleEchoLink']['PROXY_SERVER'] = $_POST['inElProxyServer'];
  $elconfig['ModuleEchoLink']['PROXY_PORT'] = $_POST['inElProxyPort'];
  $elconfig['ModuleEchoLink']['PROXY_PASSWORD'] = $_POST['inElProxyPassword'];

  $elconfig['ModuleEchoLink']['DESCRIPTION'] = $_POST['inElDescription'];


  $elconfig['ModuleEchoLink']['MUTE_LOGIC_LINKING'] = $_POST['inElMuteLogicLinking'];

  $ini = build_ini_string($elconfig);

  // Was a hardcoded /var/www/html/echolink/ path -- only correct when
  // this dashboard is actually deployed there, not when testing it from
  // anywhere else. Use a path relative to this script instead.
  $draftFile = __DIR__ . '/ModuleEchoLink.conf';
  file_put_contents($draftFile, $ini, FILE_USE_INCLUDE_PATH);

  ///file manipulation section

  $retval = null;
  $screen = null;
  //archive the current config
  exec('sudo cp /etc/svxlink/svxlink.d/ModuleEchoLink.conf /etc/svxlink/svxlink.d/ModuleEchoLink.conf.' . date("YmdThis"), $screen, $retval);
  //move generated file to current config
  exec('sudo mv ' . escapeshellarg($draftFile) . ' /etc/svxlink/svxlink.d/ModuleEchoLink.conf', $screen, $retval);

  //Service SVXlink restart
  exec('sudo service svxlink restart 2>&1', $screen, $retval);
}

      $inElDefaultLang = $elconfig['ModuleEchoLink']['DEFAULT_LANG'];
        $inElCallsign = $elconfig['ModuleEchoLink']['CALLSIGN'];
        $inElPassword = $elconfig['ModuleEchoLink']['PASSWORD'];
        $inElSysOpName = $elconfig['ModuleEchoLink']['SYSOPNAME'];
        $inElLocation = $elconfig['ModuleEchoLink']['LOCATION'];
        $inElServers = $elconfig['ModuleEchoLink']['SERVERS'];
        $inElProxyServer =  $elconfig['ModuleEchoLink']['PROXY_SERVER'];
        $inElProxyPort = $elconfig['ModuleEchoLink']['PROXY_PORT'];
        $inElProxyPassword = $elconfig['ModuleEchoLink']['PROXY_PASSWORD'];
        $inElMuteLogicLinking = $elconfig['ModuleEchoLink']['MUTE_LOGIC_LINKING'];

?>

<form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]);?>">

  <div class="mx-row"><label>Default Language</label>
    <input type="text" name="inElDefaultLang" value="<?php echo htmlspecialchars((string)$inElDefaultLang);?>"></div>
  <div class="mx-row"><label>Callsign</label>
    <input type="text" name="inElCallsign" value="<?php echo htmlspecialchars((string)$inElCallsign);?>"></div>
  <div class="mx-row"><label>Password</label>
    <input type="text" name="inElPassword" value="<?php echo htmlspecialchars((string)$inElPassword);?>"></div>
  <div class="mx-row"><label>SysOp Name</label>
    <input type="text" name="inElSysOpName" value="<?php echo htmlspecialchars((string)$inElSysOpName);?>"></div>
  <div class="mx-row"><label>Location</label>
    <input type="text" name="inElLocation" value="<?php echo htmlspecialchars((string)$inElLocation);?>"></div>
  <div class="mx-row"><label>Servers</label>
    <input type="text" name="inElServers" value="<?php echo htmlspecialchars((string)$inElServers);?>"></div>
  <div class="mx-row"><label>Proxy Server</label>
    <input type="text" name="inElProxyServer" value="<?php echo htmlspecialchars((string)$inElProxyServer);?>"></div>
  <div class="mx-row"><label>Proxy Port</label>
    <input type="text" name="inElProxyPort" value="<?php echo htmlspecialchars((string)$inElProxyPort);?>"></div>
  <div class="mx-row"><label>Proxy Password</label>
    <input type="text" name="inElProxyPassword" value="<?php echo htmlspecialchars((string)$inElProxyPassword);?>"></div>
  <div class="mx-row"><label>Mute Logic Linking</label>
    <input type="text" name="inElMuteLogicLinking" value="<?php echo htmlspecialchars((string)$inElMuteLogicLinking);?>"></div>

  <p><button name="btnSave" type="submit" class="mx-btn">Save & Reload</button></p>
</form>

</div>
</body>
</html>
