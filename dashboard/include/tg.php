<?php
include_once __DIR__.'/config.php';
include_once __DIR__.'/tools.php';
include_once __DIR__.'/functions.php';
include_once __DIR__.'/tgdb_store.php';
$tgdb_array = loadTgDb();
ksort($tgdb_array, SORT_NUMERIC);
$mxMonitored = loadMonitoredTgNumbers();
$mxPriorities = loadMonitoredTgPriorities();
$mxPrioLabels = ['No priority', '+', '++', '+++'];
$svxConfigFile = '/etc/svxlink/svxlink.conf';
    if (fopen($svxConfigFile,'r'))
       { $svxconfig = parse_ini_file($svxConfigFile,true,INI_SCANNER_RAW);
        $callsign = $svxconfig['ReflectorLogic']['CALLSIGN'];
        $fmnetwork =$svxconfig['ReflectorLogic']['FMNET'];
}

?>
<span style="font-weight: bold;font-size:14px;">Talk Groups <a href="/tgnames/" style="font-size:11px;font-weight:normal;">(edit names)</a></span>
<?php if (!empty($monitorMsg)): ?>
<div class="mx-msg mx-msg-ok" style="max-width:560px;margin:8px auto 0;"><?php echo htmlspecialchars($monitorMsg); ?></div>
<?php endif; ?>
<fieldset style=" width:560px;box-shadow:5px 5px 20px #999;background-color:#e8e8e8e8;margin-top:10px;margin-left:0px;margin-right:0px;font-size:12px;border-top-left-radius: 10px; border-top-right-radius: 10px;border-bottom-left-radius: 10px; border-bottom-right-radius: 10px;">
  <form method="post">
  <table style="margin-top:0px;table-layout:fixed;width:100%;">
    <tr height=25px>
      <th width=70px>TG #</th>
      <th width=30px> M </th>
      <th width=30px> A </th>
      <th width=150px>TG Name</th>
      <th width=55px>Monitor</th>
      <th width=90px>Priority</th>
    </tr>
<?php
foreach ($tgdb_array as $tg => $tgname)
{
        $tg = (string)$tg;
		echo "<tr>";
		echo "<td align=\"left\">&nbsp;<span style=\"color:#b5651d;font-weight:bold;\">" . htmlspecialchars($tg) . "</span></td>";
		echo "<td><button type=submit id=jumptoM name=jmptoM class=monitor_id value=\"" . htmlspecialchars($tg) . "\"><i class=\"material-icons\" style=\"font-size:15px;\">volume_up</i></button></td>";
                echo "<td><button type=submit id=jumptoA name=jmptoA class=active_id value=\"" . htmlspecialchars($tg) . "\"><i class=\"material-icons\" style=\"font-size:15px;\">cell_tower</i></button></td>";
		echo "<td style=\"font-weight:bold;color:#464646;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;\">&nbsp;<b>" . htmlspecialchars((string)$tgname) . "</b></td>";
		echo "<td align=\"center\"><input type=\"checkbox\" name=\"monitor[]\" value=\"" . htmlspecialchars($tg) . "\" " . (in_array($tg, $mxMonitored, true) ? 'checked' : '') . "></td>";
		echo "<td><select name=\"priority[" . htmlspecialchars($tg) . "]\" style=\"font-size:11px;\">";
		foreach ($mxPrioLabels as $p => $label) {
			echo "<option value=\"$p\" " . (($mxPriorities[$tg] ?? 0) === $p ? 'selected' : '') . ">$label</option>";
		}
		echo "</select></td>";
		echo"</tr>\n";
};

?>
  </table>
  <p style="padding:8px;margin:0;">
    <button type="submit" name="btnSaveMonitor" class="mx-btn">Save monitoring &amp; restart SvxLink</button>
  </p>
</form>
</fieldset>
