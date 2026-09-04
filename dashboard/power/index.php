<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <link href="/css/modern.css" type="text/css" rel="stylesheet" />
  </head>
<body style="background: var(--mx-bg); margin: 0;">
<?php include_once __DIR__ . '/../include/top_menu.php'; ?>

<?php

if (isset($_POST['btnPower']))
    {
        $retval = null;
        $screen = null;
        $command = "sudo shutdown -h now 2>&1";
        exec($command,$screen,$retval);
}

if (isset($_POST['btnSvxlink']))
    {
        $retval = null;
        $screen = null;
        $command = "sudo service svxlink restart 2>&1";
        exec($command,$screen,$retval);
}

if (isset($_POST['btnRestart']))
    {
        $retval = null;
        $screen = null;
        $command = "sudo shutdown -r now 2>&1";
        exec($command,$screen,$retval);
}

?>

<div class="mx-card" style="max-width: 500px; text-align: center;">
  <h1 style="text-align: center;">Power</h1>

<form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]);?>">
    <p><button name="btnSvxlink" type="submit" class="mx-btn" style="width:260px;">Restart SVXlink Service</button></p>
    <p><button name="btnRestart" type="submit" class="mx-btn mx-btn-danger" style="width:260px;" onclick="return confirm('Restart the device now?');">Restart Device</button></p>
    <p><button name="btnPower" type="submit" class="mx-btn mx-btn-danger" style="width:260px;" onclick="return confirm('Power off the device now? You will need physical access to turn it back on.');">Power OFF</button></p>
</form>

</div>
</body>
</html>
