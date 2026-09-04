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

ini_set("allow_url_fopen", 1);
session_start();
$isSimplex = false;
$isRepeater = false;
$svxConfigFile = '/etc/svxlink/svxlink.conf';
if (fopen($svxConfigFile,'r')) {$svxconfig = parse_ini_file($svxConfigFile,true,INI_SCANNER_RAW); }
$logics = explode(",",$svxconfig['GLOBAL']['LOGICS']);
foreach ($logics as $key) {
  if ($key == "SimplexLogic") $isSimplex = true;
  if ($key == "RepeaterLogic") $isRepeater = true; 
};
$tgUri = $svxconfig['ReflectorLogic']['TG_URI'];




//if ($_SERVER["REQUEST_METHOD"] == "POST") {
//  if (empty($_POST["ssid"])) {
//     echo "Name is required";
//  } else {
//    $ssid = $_POST["ssid"]);
//  }
//}}

// load the connlist
$retval = null;
$conns = null;
//exec('nmcli  -t -f NAME  con show',$conns,$retval);

// find the gateway
$ipgw = null;
$screen = null;


$screen[0] = "Welcome to HotSpot Updater.";
$screen[1] = "";
$screen[2] = "Please use buttons for appriopriate acctions.";
$screen[3] = "";
$screen[4] = "";



if ($_SESSION['refresh']){
	$screen =null;
	$command = "tail -n 500 screen.log |tac 2>&1";
        exec($command,$screen,$retval);

	$str = $screen[0];
	if ($str === "###-FINISH-####") {
		$_SESSION['refresh'] = False;
	}  else {
	//else {$_SESSION['refresh'] = False;}
	header("Refresh: 3");
	}
};



if (isset($_POST['btnChkOs']))
    {

        $retval = null;
        $screen = null;
        //$sAconn = $_POST['sAconn'];
        //$password = $_POST['password'];
        //exec('nmcli dev wifi rescan');
        $command = "sudo nice -n 19 sh check.os.sh > screen.log 2>&1 &";
        exec($command,$screen,$retval);
	
	$_SESSION['refresh']=True; header("Refresh: 3");
	//sleep(1);
	//$command = "tail -n 500 screen.log |tac 2>&1";
        //exec($command,$screen,$retval);       

	
}



if (isset($_POST['btnUpdateOs']))
    {

        $retval = null;
        $screen = null;
        //$sAconn = $_POST['sAconn'];
        //$password = $_POST['password'];
        //exec('nmcli dev wifi rescan');
        $command = "sudo nice -n 19 sh update.os.sh > screen.log 2>&1 &";
        exec($command,$screen,$retval);

	$_SESSION['refresh']=True; header("Refresh: 3");



};



if (isset($_POST['btnChkSounds']))
    {

        $retval = null;
        $screen = null;
        //$sAconn = $_POST['sAconn'];
        //$password = $_POST['password'];
        //exec('nmcli dev wifi rescan');
        $command = "sudo nice -n 19 sh check.sounds.sh > screen.log 2>&1 &";
        exec($command,$screen,$retval);

        $_SESSION['refresh']=True; header("Refresh: 3");
        //sleep(1);
        //$command = "tail -n 500 screen.log |tac 2>&1";
        //exec($command,$screen,$retval);


}


if (isset($_POST['btnUpdateSounds']))
    {

        $retval = null;
        $screen = null;
        //$sAconn = $_POST['sAconn'];
        //$password = $_POST['password'];
        //exec('nmcli dev wifi rescan');
        $command = "sudo nice -n 19 sh update.sounds.sh > screen.log 2>&1 &";
        exec($command,$screen,$retval);

        $_SESSION['refresh']=True; header("Refresh: 3");



};


if (isset($_POST['btnChkConfig']))
    {

        $retval = null;
        $screen = null;
        //$sAconn = $_POST['sAconn'];
        //$password = $_POST['password'];
        //exec('nmcli dev wifi rescan');
        $command = "sudo nice -n 19 sh check.config.sh > screen.log 2>&1 &";
        exec($command,$screen,$retval);

        $_SESSION['refresh']=True; header("Refresh: 3");
        //sleep(1);
        //$command = "tail -n 500 screen.log |tac 2>&1";
        //exec($command,$screen,$retval);


}


if (isset($_POST['btnUpdateConfig']))
    {

        $retval = null;
        $screen = null;
        //$sAconn = $_POST['sAconn'];
        //$password = $_POST['password'];
        //exec('nmcli dev wifi rescan');
        $command = "sudo nice -n 19 sh update.config.sh > screen.log 2>&1 &";
        exec($command,$screen,$retval);

        $_SESSION['refresh']=True; header("Refresh: 3");



};


if (isset($_POST['btnChkDashboard']))
    {

        $retval = null;
        $screen = null;
        
	$command = "sudo nice -n 19 sh check.dashboard.sh > screen.log 2>&1 &";
        exec($command,$screen,$retval);
        
	$_SESSION['refresh']=True; header("Refresh: 3");
}


if (isset($_POST['btnUpdateDashboard']))
    {

        $retval = null;
        $screen = null;
        
	$command = "sudo cp update.dashboard.sh /opt";
	exec($command,$screen,$retval);
	$command = "sudo nice -n 19 sh /opt/update.dashboard.sh > screen.log 2>&1 &";
        exec($command,$screen,$retval);
        //exec('nmcli dev wifi rescan');
        //$command3 = "sudo wget ".$tgUri." >> screen.log 2>&1";
        //exec($command3,$screen,$retval);
	//if ($retval) {
	//echo "*";
	//$command4 = "sudo mv /var/www/html/tgdb.txt /var/www/html/include/tgdb.php >> screen.log 2>&1";
        //exec($command4,$screen,$retval);
	//}
        //$_SESSION['refresh']=True; header("Refresh: 3");
        $_SESSION['refresh']=True; header("Refresh: 3");

};

if (isset($_POST['btnChkSvxlink']))
    {

        $retval = null;
        $screen = null;
        $command = "sudo nice -n 19 sh check.svxlink.sh > screen.log 2>&1 &"; 
        exec($command,$screen,$retval);
        $_SESSION['refresh']=True; header("Refresh: 3");
}





if (isset($_POST['btnUpdateSvxlink']))
    {

        $retval = null;
        $screen = null;
        $command = "sudo nice -n 19 sh update.svxlink.sh > screen.log 2>&1 &";
        exec($command,$screen,$retval);

        $_SESSION['refresh']=True; header("Refresh: 3");

};

?>

<form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]);?>">

  <textarea name="scan" rows="14" style="width:100%; box-sizing:border-box; background:#111; color:#0f0; border:1px solid #000; font-family: 'Courier New', monospace; font-size:11px; padding:8px; border-radius:6px;"><?php
			echo implode("\n",$screen); ?></textarea>

  <div class="mx-section">Check versions</div>
  <button name="btnChkOs" type="submit" class="mx-btn mx-btn-ghost">OS</button>
  <button name="btnChkSounds" type="submit" class="mx-btn mx-btn-ghost">Sounds</button>
  <button name="btnChkConfig" type="submit" class="mx-btn mx-btn-ghost">Config</button>
  <button name="btnChkSvxlink" type="submit" class="mx-btn mx-btn-ghost">SVXLink</button>
  <button name="btnChkDashboard" type="submit" class="mx-btn mx-btn-ghost">Dashboard</button>

  <div class="mx-section">Upgrade</div>
  <button name="btnUpdateOs" type="submit" class="mx-btn">OS</button>
  <button name="btnUpdateSounds" type="submit" class="mx-btn">Sounds</button>
  <button name="btnUpdateConfig" type="submit" class="mx-btn">Config</button>
  <button name="btnUpdateSvxlink" type="submit" class="mx-btn">SVXLink</button>
  <button name="btnUpdateDashboard" type="submit" class="mx-btn">Dashboard</button>

</form>

</div>
</body>
</html>
