<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <link href="/css/modern.css" type="text/css" rel="stylesheet" />
  </head>
<body style="background: var(--mx-bg); margin: 0;">
<?php include_once __DIR__ . '/../include/site_header.php'; ?>

<div class="mx-card" style="max-width: 600px;">
  <h1 style="text-align:center;">WiFi Configurator</h1>

<?php



//if ($_SERVER["REQUEST_METHOD"] == "POST") {
//  if (empty($_POST["ssid"])) {
//     echo "Name is required";
//  } else {
//    $ssid = $_POST["ssid"]);
//  }
//}}

// Only set inside specific POST branches below, but echoed into the form
// on every load (including a plain GET) -- initialize so that doesn't
// throw "Undefined variable" warnings the rest of the time.
$ssid = '';
$password = '';

$screen[0] = "Welcome to WIFI configuration tool.";
$screen[1] = "";
$screen[2] = "Please use buttons for actions.";
$screen[3] = "[Air Scan],[Conn List],[WiFi Status],[WiFi On] works without parameter.";
$screen[4] = "[Switch to SSID] or [Delete SSID] needs |SSID (network name)|.";
$screen[5] = "[Add Network & Connect] needs |SSID (network name)| & |Password| & wifi network.";
$screen[6] = "";


if (isset($_POST['btnScan']))
    {
        $retval = null;
	$screen = null;
	exec('nmcli dev wifi rescan');
	exec('nmcli dev wifi list 2>&1',$screen,$retval);
	// Was "$screen[$screen.$length]=..." -- JS array-append syntax that
	// doesn't exist in PHP; $screen (an array) got string-concatenated
	// with an undefined $length, writing to a bogus "Array" key instead
	// of appending to the end.
	$screen[] = "Keep in mind the non-standard WIFI antenna.";
}

if (isset($_POST['btnConnList']))
    {

	$retval = null;
	$screen = null;
	//exec('nmcli dev wifi rescan');
        exec('nmcli con show --order type 2>&1',$screen,$retval);
}

if (isset($_POST['btnSwitch']))
    {

        $retval = null;
        $screen = null;
        $ssid = $_POST['ssid'];
	//exec('nmcli dev wifi rescan');
        $command = "nmcli dev wifi connect " . escapeshellarg($ssid) . " 2>&1";
	exec($command,$screen,$retval);
}

if (isset($_POST['btnDelete']))
    {

        $retval = null;
        $screen = null;
        $ssid = $_POST['ssid'];
        //exec('nmcli dev wifi rescan');
        $command = "nmcli con delete " . escapeshellarg($ssid) . " 2>&1";
        exec($command,$screen,$retval);
}

if (isset($_POST['btnAdd']))
    {

        $retval = null;
        $screen = null;
        $ssid = $_POST['ssid'];
        $password = $_POST['password'];
	//exec('nmcli dev wifi rescan');
        $command = "nmcli dev wifi connect " . escapeshellarg($ssid) . " password " . escapeshellarg($password) . " 2>&1";
        exec($command,$screen,$retval);
}

if (isset($_POST['btnWifiStatus']))
    {

        $retval = null;
        $screen = null;
        //$ssid = $_POST['ssid'];
        //$password = $_POST['password'];
        //exec('nmcli dev wifi rescan');
        $command = 'nmcli radio 2>&1';
        exec($command,$screen,$retval);
}


if (isset($_POST['btnWifiOn']))
    {

        $retval = null;
        $screen = null;
        //$ssid = $_POST['ssid'];
        //$password = $_POST['password'];
        //exec('nmcli dev wifi rescan');
        $command = 'nmcli radio wifi on 2>&1';
        exec($command,$screen,$retval);
	$command = 'nmcli radio wifi 2>&1';
        exec($command,$screen,$retval);



}


?>
 <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]);?>">

  <textarea name="scan" rows="8" style="width:100%; box-sizing:border-box; background:#111; color:#0f0; border:1px solid #000; font-family: 'Courier New', monospace; font-size:11px; padding:8px; border-radius:6px;"><?php
			echo implode("\n",$screen); ?></textarea>

  <div style="display:flex; gap:20px; margin-top:14px; flex-wrap:wrap;">
    <div>
        <button name="btnScan" type="submit" class="mx-btn" style="width:150px; margin-bottom:6px;">Air Scan</button><br>
	<button name="btnConnList" type="submit" class="mx-btn" style="width:150px; margin-bottom:6px;">Conn List</button><br>
	<button name="btnWifiStatus" type="submit" class="mx-btn" style="width:150px; margin-bottom:6px;">WiFi Status</button><br>
	<button name="btnWifiOn" type="submit" class="mx-btn" style="width:150px;">WiFi On</button>
    </div>
    <div style="flex:1; min-width:220px;">
        <div class="mx-row" style="margin-bottom:8px;">
          <label style="font-weight:600; font-size:12.5px;">SSID (network name)</label>
          <input type="text" name="ssid" value="<?php echo htmlspecialchars($ssid);?>">
        </div>
        <div class="mx-row" style="margin-bottom:8px;">
          <label style="font-weight:600; font-size:12.5px;">Password</label>
          <input type="password" name="password" value="<?php echo htmlspecialchars($password);?>">
        </div>
        <button name="btnAdd" type="submit" class="mx-btn" style="margin-bottom:6px;">Add Network & Connect</button><br>
        <button name="btnSwitch" type="submit" class="mx-btn mx-btn-ghost" style="margin-bottom:6px;">Switch to SSID</button><br>
        <button name="btnDelete" type="submit" class="mx-btn mx-btn-danger">Delete SSID</button>
    </div>
  </div>
</form>

</div>
</body>
</html>
