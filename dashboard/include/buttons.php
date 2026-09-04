<div class="content">
<?php
require_once __DIR__ . '/buttons_store.php';
$dashButtons = loadButtons();

// Buttons used to be hardcoded button1..button10 fields, each shelling out
// individually (and one of them, KEY6, additionally wrote to
// /tmp/dtmf_svx -- a transport nothing on this hardware reads; see the
// same dead code already removed from dtmf/index.php). Now indexed by
// position so the list can be any length via the Buttons admin page.
foreach ($dashButtons as $i => $btn) {
    if (array_key_exists("button_$i", $_POST)) {
        shell_exec('/usr/sbin/hotspot_dtmf ' . escapeshellarg($btn['dtmf']));
        echo "<meta http-equiv='refresh' content='0'>";
    }
}
?>

<fieldset style="box-shadow:5px 5px 20px #999;background-color:#e8e8e8e8; width:855px;margin-top:5px;margin-bottom:14px;margin-left:6px;margin-right:0px;font-size:12px;border-top-left-radius: 10px; border-top-right-radius: 10px;border-bottom-left-radius: 10px; border-bottom-right-radius: 10px;">
<div style="padding:0px;width:100%;background-image: linear-gradient(to bottom, #e9e9e9 50%, #bcbaba 100%);border-radius: 10px;-moz-border-radius:10px;-webkit-border-radius:10px;border: 1px solid LightGrey;margin-left:0px; margin-right:0px;margin-top:4px;margin-bottom:0px;white-space:normal;">
<p style="margin-bottom:0px;"></p>
<form method="post">
    <center>
<?php foreach ($dashButtons as $i => $btn): ?>
        <input type="submit" name="button_<?php echo $i; ?>"
            class="<?php echo htmlspecialchars($btn['color']); ?>" value="<?php echo htmlspecialchars($btn['label']); ?>" />
<?php endforeach; ?>
    </center>
    </form>
<p style="margin: 0 auto;"></p>
<form action="" method="POST" style="margin-top:4px;">
  <center>
  <label style="text-shadow: 1px 1px 1px Lightgrey, 0 0 0.5em LightGrey, 0 0 1em whitesmoke;font-weight:bold;color:#464646;" for="dtmfsvx">DTMF command (must end with #):</label>
  <input type="text" id="dtmfsvx" name="dtmfsvx">
  <input type="submit" value="Send DTMF code" class="green"><br>
  </center>
</form>
<?php
  if (isset($_POST["dtmfsvx"])){
	shell_exec('/usr/sbin/hotspot_dtmf ' . escapeshellarg($_POST['dtmfsvx']));
   echo "<meta http-equiv='refresh' content='0'>";
    }
  if (isset($_POST["jmpto"])) {
   shell_exec('/usr/sbin/hotspot_dtmf ' . escapeshellarg('91' . $_POST['jmpto'] . '#'));
   echo "<meta http-equiv='refresh' content='0'>";
    }
 if (isset($_POST["jmptoA"])) {
   shell_exec('/usr/sbin/hotspot_dtmf ' . escapeshellarg('91' . $_POST['jmptoA'] . '#'));
   echo "<meta http-equiv='refresh' content='0'>";
    }
if (isset($_POST["jmptoM"])) {
   shell_exec('/usr/sbin/hotspot_dtmf ' . escapeshellarg('94' . $_POST['jmptoM'] . '#'));
   echo "<meta http-equiv='refresh' content='0'>";
    }


?>
<p style="margin-bottom:-2px;"></p>
</div>
</fieldset>
