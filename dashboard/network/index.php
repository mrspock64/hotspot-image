<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <link href="/css/modern.css" type="text/css" rel="stylesheet" />
  </head>
<body style="background: var(--mx-bg); margin: 0;">
<?php include_once __DIR__ . '/../include/site_header.php'; ?>

<div class="mx-card" style="max-width: 640px;">
  <h1 style="text-align:center;">Network Configurator</h1>

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
$sAconn = '';
$myIp = '';
$cidr = '';
$gw = '';
$dns = '';

// load the connlist
$retval = null;
$conns = null;
exec('nmcli  -t -f NAME  con show 2>&1',$conns,$retval);

// find the gateway
$ipgw = null;

$screen[0] = "Welcome to NETWORK configuration tool.";
$screen[1] = "";
$screen[2] = "Please use buttons for actions.";
$screen[3] = "[Ping GW],[Ping Google],[Ping Reflector] works without parameter.";
$screen[4] = "[Show Details] [Set Auto IP]  [conn UP]  [conn DOWN] works with |connection|.";
$screen[5] = "[Set Static IP] needs |IP|/|CIDR| & |GW| & |DNS|.";
$screen[6] = "Please use 24 for 255.255.255.0 in CIDR. ect.";
$screen[7] = "For |IP| & |GW| & |DNS| please use IP notation like 192.168.1.2 ect.";
$screen[8] = "";
$screen[9] = "";




if (isset($_POST['btnPingGw']))
    {
        $retval = null;
	$screen = null;
	$sAconn = $_POST['sAconn'];
	$ipgw = null;
	//$ipgw_str =implode("\n",$ipgw);
	exec("nmcli -g ipv4.gateway con show " . escapeshellarg($sAconn) . " 2>&1",$ipgw,$retval);
	$ipgw_str = trim(implode("\n",$ipgw));
	exec("ping " . escapeshellarg($ipgw_str) . " -c 1 2>&1",$screen,$retval);
}

if (isset($_POST['btnPingGoogle']))
    {

	$retval = null;
	$screen = null;
	//exec('nmcli dev wifi rescan');
        exec('ping 8.8.8.8 -c 1 2>&1',$screen,$retval);
}


//tbc - load the data from ini RF.

if (isset($_POST['btnPingRef']))
    {

        $retval = null;
        $screen = null;
        //$ssid = $_POST['ssid'];
	//exec('nmcli dev wifi rescan');
        $command = 'nmap svxlink.pl -p 5295 2>&1';
	exec($command,$screen,$retval);
}


if (isset($_POST['btnAuto']))
    {

        $retval = null;
        $screen = null;
	$sAconn = $_POST['sAconn'];
        //$ssid = $_POST['ssid'];
        //$password = $_POST['password'];
	//exec('nmcli dev wifi rescan');
        //$command = "nmcli radio  2>&1";

	$command = "nmcli con mod " . escapeshellarg($sAconn) . " ipv4.method auto 2>&1";
        exec($command,$screen,$retval);
	$command = "nmcli -p -f ipv4,general con show " . escapeshellarg($sAconn) . " 2>&1";
        exec($command,$screen,$retval);



}


if (isset($_POST['btnStatic']))
    {

        $retval = false;
        $screen = null;
        $sAconn = $_POST['sAconn'];
	$myIp = $_POST['myIp'];
	$cidr = $_POST['cidr'];
	$gw = $_POST['gw'];
	$dns = $_POST['dns'];

	// Validate the network fields before they ever touch a shell command —
	// escaping alone stops injection, but nmcli will happily accept garbage
	// and misconfigure the interface, which is worse to recover from remotely.
	$validInput = filter_var($myIp, FILTER_VALIDATE_IP)
		&& ctype_digit((string)$cidr) && $cidr >= 0 && $cidr <= 32
		&& filter_var($gw, FILTER_VALIDATE_IP);
	foreach (explode(",", $dns) as $dnsServer) {
		if (trim($dnsServer) !== "" && !filter_var(trim($dnsServer), FILTER_VALIDATE_IP)) {
			$validInput = false;
		}
	}

	if (!$validInput) {
		$screen = array("Invalid IP/CIDR/gateway/DNS value — nothing was changed.");
	} else {

	$command = "nmcli con mod " . escapeshellarg($sAconn) . " ipv4.addresses " . escapeshellarg($myIp . "/" . $cidr) . " 2>&1";
        if (!$retval) exec($command,$screen,$retval);

	$command = "nmcli con mod " . escapeshellarg($sAconn) . " ipv4.gateway " . escapeshellarg($gw) . " 2>&1";
        if (!$retval) exec($command,$screen,$retval);

	$command = "nmcli con mod " . escapeshellarg($sAconn) . " ipv4.dns " . escapeshellarg($dns) . " 2>&1";
        if (!$retval) exec($command,$screen,$retval);

        $command = "nmcli con mod " . escapeshellarg($sAconn) . " ipv4.method manual 2>&1";
        if (!$retval) exec($command,$screen,$retval);

        $command = "nmcli -p -f ipv4,general con show " . escapeshellarg($sAconn) . " 2>&1";
	if (!$retval) exec($command,$screen,$retval);

	}

}



if (isset($_POST['btnDetails']))
    {

        $retval = null;
        $screen = null;
        $sAconn = $_POST['sAconn'];
        //$password = $_POST['password'];
        //exec('nmcli dev wifi rescan');
        $command = "nmcli -p -f ipv4,general con show " . escapeshellarg($sAconn) . " 2>&1";
        exec($command,$screen,$retval);
}

if (isset($_POST['btnUp']))
    {

        $retval = null;
        $screen = null;
        $sAconn = $_POST['sAconn'];
        //$password = $_POST['password'];
        //exec('nmcli dev wifi rescan');
        $command = "nmcli con up " . escapeshellarg($sAconn) . " 2>&1";
        exec($command,$screen,$retval);
}
if (isset($_POST['btnDown']))
    {

        $retval = null;
        $screen = null;
        $sAconn = $_POST['sAconn'];
        //$password = $_POST['password'];
        //exec('nmcli dev wifi rescan');
        $command = "nmcli con down " . escapeshellarg($sAconn) . " 2>&1";
        exec($command,$screen,$retval);
}

?>

<form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]);?>">

  <textarea name="scan" rows="8" style="width:100%; box-sizing:border-box; background:#111; color:#0f0; border:1px solid #000; font-family: 'Courier New', monospace; font-size:11px; padding:8px; border-radius:6px;"><?php
			echo implode("\n",$screen); ?></textarea>

  <div style="display:flex; gap:20px; margin-top:14px; flex-wrap:wrap;">
    <div>
	<button name="btnDetails" type="submit" class="mx-btn" style="width:150px; margin-bottom:6px;">Show Details</button><br>
	<button name="btnPingGw" type="submit" class="mx-btn" style="width:150px; margin-bottom:6px;">Ping GW</button><br>
	<button name="btnPingGoogle" type="submit" class="mx-btn" style="width:150px; margin-bottom:6px;">Ping Google</button><br>
        <button name="btnPingRef" type="submit" class="mx-btn" style="width:150px;">Ping Reflector</button>
    </div>
    <div style="flex:1; min-width:220px;">
	<div class="mx-row" style="margin-bottom:8px;">
	  <label style="font-weight:600; font-size:12.5px;">Connection</label>
	  <select name="sAconn" style="padding:6px; border-radius:6px; border:1px solid var(--mx-border);">
<?php
foreach ($conns as $conn){
   echo "<option value=\"".htmlspecialchars($conn) ."\">" .htmlspecialchars($conn)."</option>";
};
?>
	  </select>
	</div>
	<div class="mx-row" style="margin-bottom:8px;">
	  <label style="font-weight:600; font-size:12.5px;">IP / CIDR</label>
	  <input type="text" name="myIp" style="width:150px; display:inline-block;" value="<?php echo htmlspecialchars($myIp);?>">
	  / <input type="text" name="cidr" style="width:60px; display:inline-block;" value="<?php echo htmlspecialchars($cidr);?>">
	</div>
	<div class="mx-row" style="margin-bottom:8px;">
	  <label style="font-weight:600; font-size:12.5px;">Gateway</label>
	  <input type="text" name="gw" value="<?php echo htmlspecialchars($gw);?>">
	</div>
	<div class="mx-row" style="margin-bottom:8px;">
	  <label style="font-weight:600; font-size:12.5px;">DNS</label>
	  <input type="text" name="dns" value="<?php echo htmlspecialchars($dns);?>">
	</div>
	<button name="btnAuto" type="submit" class="mx-btn" style="margin-bottom:6px;">Set Auto IP</button><br>
	<button name="btnUp" type="submit" class="mx-btn mx-btn-ghost" style="margin-bottom:6px;">conn UP</button><br>
	<button name="btnDown" type="submit" class="mx-btn mx-btn-ghost" style="margin-bottom:6px;">conn DOWN</button><br>
	<button name="btnStatic" type="submit" class="mx-btn">Set Static IP</button>
    </div>
  </div>
</form>

</div>
</body>
</html>
