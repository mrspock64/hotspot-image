<?php 
function build_ini_string(array $a) {
    $out = '';
    $sectionless = '';
    foreach($a as $rootkey => $rootvalue){
        if(is_array($rootvalue)){
            // find out if the root-level item is an indexed or associative array
            $indexed_root = array_keys($rootvalue) == range(0, count($rootvalue) - 1);
            // associative arrays at the root level have a section heading
            if(!$indexed_root) $out .= PHP_EOL."[$rootkey]".PHP_EOL;
            // loop through items under a section heading
            foreach($rootvalue as $key => $value){
                if(is_array($value)){
                    // indexed arrays under a section heading will have their key omitted
                    $indexed_item = array_keys($value) == range(0, count($value) - 1);
                    foreach($value as $subkey=>$subvalue){
                        // omit subkey for indexed arrays
                        if($indexed_item) $subkey = "";
                        // add this line under the section heading
                        $out .= "{$key}[$subkey] = $subvalue" . PHP_EOL;
                    }
                }else{
                    if($indexed_root){
                        // root level indexed array becomes sectionless
                        $sectionless .= "{$rootkey}[] = $value" . PHP_EOL;
                    }else{
                        // plain values within root level sections
                        $out .= "$key = $value" . PHP_EOL;
                    }
                }
            }

        }else{
            // root level sectionless values
            $sectionless .= "$rootkey = $rootvalue" . PHP_EOL;
        }
    }
    return $sectionless.$out;
}


$password = "www-data";
$command = "echo '$password' | sudo -S chmod -R 777 /etc/svxlink/";
exec($command);
exec('sudo chown -R www-data:www-data /etc/svxlink/');
exec('sudo chown -R www-data:www:data /var/www/html');
exec('sudo cp /etc/svxlink/node_info.json /etc/svxlink/node_info.bak');
$nodeInfoFile = '/etc/svxlink/node_info.json';  
echo $nodeInfoFile;
if (fopen($nodeInfoFile,'r'))
{
	$filedata = file_get_contents($nodeInfoFile);
	$nodeInfo = json_decode($filedata,true);
        
	build_ini_string(array($nodeInfo));
	
};






if (isset($_POST['btnSave']))
    {
	$nodeInfo["Location"] = $_POST['inLocation']; $nodeInfo["Locator"] = $_POST['inLocator'];$nodeInfo["SysOp"] = $_POST['inSysOp'];
	$nodeInfo["LAT"] = $_POST['inLAT']; $nodeInfo["LONG"] = $_POST['inLONG'];$nodeInfo["RXFREQ"] = $_POST['inRXFREQ'];
	$nodeInfo["TXFREQ"] = $_POST['inTXFREQ']; $nodeInfo["Website"] = $_POST['inWebsite'];$nodeInfo["Mode"] = $_POST['inMode'];
	$nodeInfo["Type"] = $_POST['inType']; $nodeInfo["Echolink"] = $_POST['inEcholink'];$nodeInfo["nodeLocation"] = $_POST['innodeLocation'];
	$nodeInfo["Sysop"] = $_POST['inSysop']; $nodeInfo["Network"] = $_POST['inNetwork'];$nodeInfo["CTCSS"] = $_POST['inCTCSS'];
	$nodeInfo["LinkedTo"] = $_POST['inLinkedTo'];$nodeInfo["DefaultTg"] = $_POST['inDefaultTg'];

	$jsonNodeInfo = json_encode($nodeInfo);
	file_put_contents("/var/www/html/nodeInfo/node_info.json", $jsonNodeInfo ,FILE_USE_INCLUDE_PATH);

        $retval = null;
        $screen = null;


	///file manipulation section
		//archive the current config
		exec('sudo cp /etc/svxlink/node_info.json /etc/svxlink/node_info.json.' .date("YmdThis") ,$screen,$retval);
		//move generated file to current config
		exec('sudo mv /var/www/html/nodeInfo/node_info.json /etc/svxlink/node_info.json', $screen, $retval);
        	//Service SVXlink restart
       		exec('sudo service svxlink restart 2>&1',$screen,$retval);
            exec('sudo chown -R www-data:root /etc/svxlink/');
           };

//  	$svxconfig = parse_ini_file($svxConfigFile,true,INI_SCANNER_RAW);
//        $inCallsign = $svxconfig['ReflectorLogic']['CALLSIGN'];


	$inLocation = $nodeInfo["Location"];$inLocator = $nodeInfo["Locator"]; $inSysOp = $nodeInfo["SysOp"];
	$inLAT = $nodeInfo["LAT"];$inLONG = $nodeInfo["LONG"]; $inRXFREQ = $nodeInfo["RXFREQ"];
	$inTXFREQ = $nodeInfo["TXFREQ"];$inWebsite = $nodeInfo["Website"]; $inMode = $nodeInfo["Mode"];
	$inType = $nodeInfo["Type"];$inEcholink = $nodeInfo["Echolink"]; $innodeLocation = $nodeInfo["nodeLocation"];
	$inSysop = $nodeInfo["Sysop"];$inNetwork = $nodeInfo["Network"]; $inCTCSS = $nodeInfo["CTCSS"];
	$inLinkedTo = $nodeInfo["LinkedTo"];$inDefaultTg = $nodeInfo["DefaultTg"];
echo $inLocation . " " . $inLocator;
?>

<form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]);?>">

<table>
        <tr>
        <th width = "380px">Node Info Input</th>
	<th width = "100px">Action</th>
        </tr>
<tr>
<TD>

<Table style="border-collapse: collapse; border: none;">
        <tr style="border: none;">
                <th width = "30%"></th>
                <th width = "70%"></th>
        </tr>
        <tr style="border: none;"> 
        <td style="border: none;">Location
        </td>
        <td style="border: none;">
        <input type="text" name="inLocation" style="width:98%" value="<?php echo $inLocation;?>">
        </td></tr>
        <tr style="border: none;"> 
        <td style="border: none;">Locator</td>
        <td style="border: none;">
        <input  type="text" name="inLocator" style="width:98%" value="<?php echo $inLocator;?>">
        </td></tr>
        <tr style="border: none;"> 
        <td style="border: none;">SysOp</td>
        <td style="border: none;">
        <input  type="text" name="inSysOp" style="width:98%" value="<?php echo $inSysOp;?>">
        </td></tr>
        <tr style="border: none;"> 
        <td style="border: none;">Lat</td>
        <td style="border: none;">
        <input  type="text" name="inLAT" style="width:98%" value="<?php echo $inLAT;?>">
        </td></tr>
        <tr style="border: none;"> 
        <td style="border: none;">Long</td>
        <td style="border: none;">
        <input  type="text" name="inLONG" style="width:98%" value="<?php echo $inLONG;?>">
        </td></tr>
        <tr style="border: none;"> 
        <td style="border: none;">Rx Freq</td>
        <td style="border: none;">
        <input  type="text" name="inRXFREQ" style="width:98%" value="<?php echo $inRXFREQ;?>">
        </td></tr>
        <tr style="border: none;"> 
        <td style="border: none;">Tx Freq</td>
        <td style="border: none;">
        <input  type="text" name="inTXFREQ" style="width:98%" value="<?php echo $inTXFREQ;?>">
        </td></tr>
        <tr style="border: none;"> 
        <td style="border: none;">Website</td>
        <td style="border: none;">
        <input  type="text" name="inWebsite" style="width:98%" value="<?php echo $inWebsite;?>">
        </td></tr>
        <tr style="border: none;"> 
        <td style="border: none;">Mode</td>
        <td style="border: none;">
        <input  type="text" name="inMode" style="width:98%" value="<?php echo $inMode;?>">
        </td></tr>
        <tr style="border: none;"> 
        <td style="border: none;">Type</td>
        <td style="border: none;">
        <input  type="text" name="inType" style="width:98%" value="<?php echo $inType;?>">
        </td></tr>
        <tr style="border: none;"> 
        <td style="border: none;">EchoLink</td>
        <td style="border: none;">
        <input  type="text" name="inEcholink" style="width:98%" value="<?php echo $inEcholink;?>">
        </td></tr>
        <tr style="border: none;"> 
        <td style="border: none;">Node Location</td>
        <td style="border: none;">
        <input  type="text" name="innodeLocation" style="width:98%" value="<?php echo $innodeLocation;?>">
        </td></tr>
        <tr style="border: none;"> 
        <td style="border: none;">Sysop</td>
        <td style="border: none;">
        <input  type="text" name="inSysop" style="width:98%" value="<?php echo $inSysop;?>">
        </td></tr>
        <tr style="border: none;"> 
        <td style="border: none;">Network</td>
        <td style="border: none;">
        <input  type="text" name="inNetwork" style="width:98%" value="<?php echo $inNetwork;?>">
        </td></tr>
        <tr style="border: none;"> 
        <td style="border: none;">LinkedTo</td>
        <td style="border: none;">
        <input  type="text" name="inLinkedTo" style="width:98%" value="<?php echo $inLinkedTo;?>">
        </td></tr>
        <tr style="border: none;"> 
        <td style="border: none;">CTCSS</td>
        <td style="border: none;">
        <input  type="text" name="inCTCSS" style="width:98%" value="<?php echo $inCTCSS;?>">
        </td></tr>
        </td></tr>
        <tr style="border: none;"> 
        <td style="border: none;">DefaultTG</td>
        <td style="border: none;">
        <input  type="text" name="inDefaultTg" style="width:98%" value="<?php echo $inDefaultTg;?>">
        </td></tr>

    </table>
</td>
<td> 
	<button name="btnSave" type="submit" class="red" style="height:100px; width:105px; font-size:12px;">Save <BR><Br> & <BR><BR> ReLoad</button>
</td>
</tr>
</table>
</form>