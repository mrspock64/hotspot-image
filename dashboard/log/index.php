<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <link href="/css/modern.css" type="text/css" rel="stylesheet" />
  </head>
<body style="background: var(--mx-bg); margin: 0;">
<?php include_once __DIR__ . '/../include/site_header.php'; ?>

<div class="mx-card" style="max-width: 600px;">
  <h1 style="text-align:center;">Log viewer</h1>

<?php

$screen[0] = "Welcome to Svxlink log viewer tool.";
$screen[1] = "";
$screen[2] = "Click on the button to get current running log.";
$screen[3] = "";

if (isset($_POST['btnLog']))
    {
        $retval = null;
        $screen = null;
        // Was "tail -l /var/log/svxlink.log" -- -l isn't a real tail flag
        // and the log file has no .log suffix (it's /var/log/svxlink; see
        // include/system.php's SVXLOGPATH/SVXLOGPREFIX constants, not
        // pulled in here to avoid pulling in that file's own output).
        // Both errors went to stderr, which exec() doesn't capture, so
        // this silently produced an empty $screen instead of a visible
        // error -- hence "nothing shows up" when clicking the button.
        $command = "tail -n 200 " . escapeshellarg("/var/log/svxlink") . " 2>&1";
        exec($command,$screen,$retval);
}

?>

<form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]);?>">
  <textarea name="scan" rows="18" style="width:100%; box-sizing:border-box; background:#111; color:#0f0; border:1px solid #000; font-family: 'Courier New', monospace; font-size:11px; padding:8px; border-radius:6px;"><?php
			echo implode("\n",$screen); ?></textarea>
  <p><button name="btnLog" type="submit" class="mx-btn">Show Log</button></p>
</form>

</div>
</body>
</html>
