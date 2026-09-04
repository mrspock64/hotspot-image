<?php
include_once __DIR__.'/config.php';
include_once __DIR__.'/tools.php';
include_once __DIR__.'/functions.php';
include_once __DIR__.'/tgdb_store.php';
$tgdb_array = loadTgDb();
$svxConfigFile = '/etc/svxlink/svxlink.conf';
    if (fopen($svxConfigFile,'r'))
       { $svxconfig = parse_ini_file($svxConfigFile,true,INI_SCANNER_RAW);  
        $callsign = $svxconfig['ReflectorLogic']['CALLSIGN'];
        $fmnetwork =$svxconfig['ReflectorLogic']['FMNET'];
}

?>
<span style="font-weight: bold;font-size:14px;">Talk Groups <a href="/tgnames/" style="font-size:11px;font-weight:normal;">(edit names)</a></span>
<fieldset style=" width:550px;box-shadow:5px 5px 20px #999;background-color:#e8e8e8e8;margin-top:10px;margin-left:0px;margin-right:0px;font-size:12px;border-top-left-radius: 10px; border-top-right-radius: 10px;border-bottom-left-radius: 10px; border-bottom-right-radius: 10px;">
  <form method="post">
  <table style="margin-top:0px;">
    <tr height=25px>
      <th width=100px>TG #</th>
      <th width=30px> M </th>
      <th width=30px> A </th>
      <th>TG Name</th>
    </tr>
<?php
foreach ($tgdb_array as $tg => $tgname)
{
		echo "<tr>";
		echo "<td align=\"left\">&nbsp;<span style=\"color:#b5651d;font-weight:bold;\">" . htmlspecialchars((string)$tg) . "</span></td>";
		echo "<td><button type=submit id=jumptoM name=jmptoM class=monitor_id value=\"" . htmlspecialchars((string)$tg) . "\"><i class=\"material-icons\" style=\"font-size:15px;\">volume_up</i></button></td>";
                echo "<td><button type=submit id=jumptoA name=jmptoA class=active_id value=\"" . htmlspecialchars((string)$tg) . "\"><i class=\"material-icons\" style=\"font-size:15px;\">cell_tower</i></button></td>";
		echo "<td style=\"font-weight:bold;color:#464646;\">&nbsp;<b>" . htmlspecialchars((string)$tgname) . "</b></td>";
		echo"</tr>\n";
};

?>
  </table>
</form>
</fieldset>
