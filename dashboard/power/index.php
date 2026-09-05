<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <link href="/css/modern.css" type="text/css" rel="stylesheet" />
  </head>
<body style="background: var(--mx-bg); margin: 0;">
<?php include_once __DIR__ . '/../include/site_header.php'; ?>

<?php

// Each command runs backgrounded ("&") so PHP can respond with a status
// message immediately, rather than the page hanging until svxlink has
// fully restarted (or, for Restart/Power OFF, never responding at all
// because the device goes down mid-request).
$message = null;

if (isset($_POST['btnPower'])) {
    exec("sudo shutdown -h now > /dev/null 2>&1 &");
    $message = "Powering off now. The device will go offline in a few seconds -- you'll need physical access to turn it back on.";
}

if (isset($_POST['btnSvxlink'])) {
    exec("sudo service svxlink restart > /dev/null 2>&1 &");
    $message = "Restarting the SVXlink service. This takes a few seconds -- the dashboard itself stays up throughout.";
}

if (isset($_POST['btnRestart'])) {
    exec("sudo shutdown -r now > /dev/null 2>&1 &");
    $message = "Restarting the device now. The dashboard will be unreachable for about a minute.";
}

?>

<div class="mx-card" style="max-width: 500px; text-align: center;">
  <h1 style="text-align: center;">Power</h1>

<?php if ($message): ?>
  <p class="mx-msg mx-msg-ok"><?php echo htmlspecialchars($message); ?></p>
<?php endif; ?>

<form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]);?>">
    <p><button name="btnSvxlink" type="submit" class="mx-btn" style="width:260px;">Restart SVXlink Service</button></p>
    <p><button name="btnRestart" type="submit" class="mx-btn mx-btn-danger" style="width:260px;" onclick="return confirm('Restart the device now?');">Restart Device</button></p>
    <p><button name="btnPower" type="submit" class="mx-btn mx-btn-danger" style="width:260px;" onclick="return confirm('Power off the device now? You will need physical access to turn it back on.');">Power OFF</button></p>
</form>

</div>
</body>
</html>
