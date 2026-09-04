<?php
// header lines for information
define("HEADER_CAT","FM-Repeater");
define("HEADER_QTH","");
define("HEADER_QRG","");
define("HEADER_SYSOP","");
define("FMNETWORK_EXTRA","");
define("EL_NODE_NR","");
define("FULLACCESS_OUTSIDE", 0);
define("ADD_BUTTONS", 1);
//
// Button keys define: description button, DTMF command or command, color of button
//
// DTMF keys
// syntax: 'KEY number,'Description','DTMF code','color button'.
//
define("KEY1", array(' TG4 ','914#','green'));
define("KEY2", array(' TG8 ','918#','green'));
define("KEY3", array(' TG23 ','9123#','green'));
define("KEY4", array(' TG50 ','9150#','orange'));
define("KEY5", array(' TG51 ','9151#','orange'));
define("KEY6", array(' TG52', '9152#','orange'));
define("KEY7", array(' TG53 ','9153#','orange'));
define("KEY8", array(' TG54 ','9154#','orange'));
define("KEY9", array(' TG55 ','9155#','orange'));
define("KEY10", array(' PARROT ','D1#','red'));
// additional DTMF keys
define("KEY11", array(' D11 ','*D11#','green'));
define("KEY12", array(' D12 ','*D12#','orange'));
define("KEY13", array(' D13 ','*D13#','orange'));
define("KEY14", array(' D14 ','*D14#','orange'));
define("KEY15", array(' D15 ','*D15#','purple'));
define("KEY16", array(' D16 ','*D16#','purple'));
define("KEY17", array(' D17 ','*D17#','orange'));
define("KEY18", array(' D18 ','*D18#','blue'));
define("KEY19", array(' D19 ','*D19#','blue'));
define("KEY20", array(' D20 ','*D20#','red'));
define("SVXCONFPATH", "/etc/svxlink/");
define("SVXCONFIG", "svxlink.conf");
define("SVXLOGPATH", "/var/log/");
define("SVXLOGPREFIX","svxlink");
define("CALLSIGN","");
define("LOGICS","");
define("REPORT_CTCSS","");
define("DTMF_CTRL_PTY","");
define("API","");
define("FMNET","SVXLink Flanders");
define("TG_URI","");
define("NODE_INFO_FILE","");
define("RF_MODULE","");
?>
