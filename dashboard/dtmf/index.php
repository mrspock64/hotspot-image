<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" lang="en">


  <head>
    <meta charset="UTF-8">
    <link href="/css/css.php" type="text/css" rel="stylesheet" />
<style type="text/css">
body {
  background-color: #eee;
  font-size: 18px;
  font-family: Arial;
  font-weight: 300;
  margin: 2em auto;
  max-width: 40em;
  line-height: 1.5;
  color: #444;
  padding: 0 0.5em;
}
h1, h2, h3 {
  line-height: 1.2;
}
a {
  color: #607d8b;
}
.highlighter-rouge {
  background-color: #fff;
  border: 1px solid #ccc;
  border-radius: .2em;
  font-size: .8em;
  overflow-x: auto;
  padding: .2em .4em;
}
pre {
  margin: 0;
  padding: .6em;
  overflow-x: auto;
}

#player {
    position:relative;
    width:205px;
    overflow: hidden;
    direction: ltl;
}


.button {
  border: none;
  color: #454545;
  padding: 16px 32px;
  text-align: center;
  text-decoration: none;
  display: inline-block;
  font-size: 16px;
  margin: 4px 2px;
  transition-duration: 0.4s;
  cursor: pointer;
}
.buttonh {
  background-image: linear-gradient(to bottom, #337ab7 0%, #265a88 100%);color:#454545;
  color: #454545;
}

.buttonh:hover {
  background-color: #4CAF50;
  color: #454545;
}
.green
{
  background-color: #448f47;
  border: none;
  color: white;
  font-weight: 600;
  font-size: 13px;
  padding: 4px 12px;
  text-decoration: none;
  margin: 4px 4px;
  cursor: pointer;
  border-radius: 4px;

}

.blue
{
  background-image: linear-gradient(to bottom, #337ab7 0%, #265a88 100%);color:#454545;
  border: none;
  color: white;
  font-weight: 600;
  font-size: 16px;
  padding: 4px 12px;
  text-decoration: none;
  margin: 4px 4px;
  cursor: pointer;
  border-radius: 4px;
  height:80px;
  width:150px;
}

.red
{
  background-color: #b00;
  border: none;
  color: white;
  font-weight: 600;
  font-size: 13px;
  padding: 4px 12px;
  text-decoration: none;
  margin: 4px 4px;
  cursor: pointer;
  border-radius: 4px;
}
.orange
{
  background-color: DarkOrange;
  border: none;
  color: white;
  font-weight: 600;
  font-size: 13px;
  padding: 4px 12px;
  text-decoration: none;
  margin: 4px 4px;
  cursor: pointer;
  border-radius: 4px;
}
.purple
{
  background-color: #800080;
  border: none;
  color: white;
  font-weight: 600;
  font-size: 13px;
  padding: 4px 12px;
  text-decoration: none;
  margin: 4px 4px;
  cursor: pointer;
  border-radius: 4px;
}
textarea {
    background-color: #111;
    border: 1px solid #000;
    color: #ffffff;
    padding: 1px;
    font-family: courier new;
    font-size:10px;
}
</style>
</head>

<body style="background-color: #e1e1e1;font: 11pt arial, sans-serif;">
<center>
<fieldset style="border:#3083b8 2px groove;box-shadow:5px 5px 20px #999; background-color:#f1f1f1; width:555px;margin-top:15px;margin-left:0px;margin-right:5px;font-size:13px;border-top-left-radius: 10px; border-top-right-radius: 10px;border-bottom-left-radius: 10px; border-bottom-right-radius: 10px;">
<div style="padding:0px;width:550px;background-image: linear-gradient(to bottom, #e9e9e9 50%, #bcbaba 100%);border-radius: 10px;-moz-border-radius:10px;-webkit-border-radius:10px;border: 1px solid LightGrey;margin-left:0px; margin-right:0px;margin-top:4px;margin-bottom:0px;line-height:1.6;white-space:normal;">
<center>
<h1 id="dtmf_info" style="color:#00aee8;font: 18pt arial, sans-serif;font-weight:bold; text-shadow: 0.25px 0.25px gray;">DTMF Dialer</h1>

<?php

    $url=$_SERVER['REQUEST_URI']."/include";
//    header("Refresh: 10; URL=$url");





// Defined buttons:
//
// button1-13 and button99 (power off) removed entirely -- none of them
// had a corresponding button in the rendered <form> below (only the
// 0-9/A-D keypad does), so they were only reachable by crafting a raw
// POST request, never through the UI. They also used a transport that
// doesn't work on this hardware: writing to /tmp/dtmf_svx, which nothing
// reads (confirmed: no DTMF_CTRL_PTY configured anywhere in
// /etc/svxlink/). The keypad below correctly uses /usr/sbin/hotspot_dtmf,
// which pipes into svxlink's own listener on 127.0.0.1:10000 (matches
// svxlink.service's ExecStart) -- that's the real, working mechanism.
// An unconfirmable "sudo poweroff" reachable only via raw POST was also
// removed as pure risk with no UI purpose; the Power page already has a
// proper, visible power-off control.

// Keyboard
 if (isset($_POST['button20']))
    {
        shell_exec('/usr/sbin/hotspot_dtmf 0');
        //echo '<pre><h1><center><p style="color: #454545; ">Send DTMF: 0</h1></p></pre>';
    }

 if (isset($_POST['button21']))
    {
        shell_exec('/usr/sbin/hotspot_dtmf 1');
     //  echo '<pre><h1><center><p style="color: #454545; ">Send DTMF: 1</center></h1></p></pre>';
    }

 if (isset($_POST['button22']))
    {
        shell_exec('/usr/sbin/hotspot_dtmf 2');
      //  echo '<pre><h1><center><p style="color: #454545; ">Send DTMF: 2</center></h1></p></pre>';
    }

 if (isset($_POST['button23']))
    {
        shell_exec('/usr/sbin/hotspot_dtmf 3');
       // echo '<pre><h1><center><p style="color: #454545; ">Send DTMF: 3</center></h1></p></pre>';
    }

 if (isset($_POST['button24']))
    {
        shell_exec('/usr/sbin/hotspot_dtmf 4');
       // echo '<pre><h1><center><p style="color: #454545; ">Send DTMF: 4</center></h1></p></pre>';
    }

 if (isset($_POST['button25']))
    {
        shell_exec('/usr/sbin/hotspot_dtmf 5');
       // echo '<pre><h1><center><p style="color: #454545; ">Send DTMF: 5</center></h1></p></pre>';
    }

 if (isset($_POST['button26']))
    {
        shell_exec('/usr/sbin/hotspot_dtmf 6');
       // echo '<pre><h1><center><p style="color: #454545; ">Send DTMF: 6</center></h1></p></pre>';
    }

 if (isset($_POST['button27']))
    {
        shell_exec('/usr/sbin/hotspot_dtmf 7');
      //  echo '<pre><h1><center><p style="color: #454545; ">Send DTMF: 7</center></h1></p></pre>';
    }

 if (isset($_POST['button28']))
    {
        shell_exec('/usr/sbin/hotspot_dtmf 8');
    //    echo '<pre><h1><center><p style="color: #454545; ">Send DTMF: 8</center></h1></p></pre>';
    }

 if (isset($_POST['button29']))
    {
        shell_exec('/usr/sbin/hotspot_dtmf 9');
    //    echo '<pre><h1><center><p style="color: #454545; ">Send DTMF: 9</center></h1></p></pre>';
    }

 if (isset($_POST['button30']))
    {
        shell_exec('/usr/sbin/hotspot_dtmf "*"');
       // echo '<pre><h1><center><p style="color: #454545; ">Send DTMF: *</center></h1></p></pre>';
    }

if (isset($_POST['button31']))
    {
        shell_exec('/usr/sbin/hotspot_dtmf "#"');
     //   echo '<pre><h1><center><p style="color: #454545; ">Send DTMF: #</center></h1></p></pre>';
    }

// Was "buttonAA" here but the rendered button below is named "buttonA"
// (single A) -- the mismatch meant clicking "A" submitted a POST field
// this code never checked for, so the A key silently did nothing.
if (isset($_POST['buttonA']))
    {
        shell_exec('/usr/sbin/hotspot_dtmf A');
    }
if (isset($_POST['buttonBB']))
    {
        shell_exec('/usr/sbin/hotspot_dtmf B');
    }

if (isset($_POST['buttonCC']))
    {
        shell_exec('/usr/sbin/hotspot_dtmf C');
    }

if (isset($_POST['buttonDD']))
    {
        shell_exec('/usr/sbin/hotspot_dtmf D');
    }
?>
<form method="post">
    <p>
         <center><button style="height: 60px; width: 100px;font-size:25px;" name="button21">1</button><button style="height: 60px; width: 100px;font-size:25px;" name="button22">2</button><button style="height: 60px; width: 100px;font-size:25px;" name="button23">3</button><button style="height: 60px; width: 100px;font-size:25px;" name="buttonA">A</button></center>
         <center><button style="height: 60px; width: 100px;font-size:25px;" name="button24">4</button><button style="height: 60px; width: 100px;font-size:25px;" name="button25">5</button><button style="height: 60px; width: 100px;font-size:25px;" name="button26">6</button><button style="height: 60px; width: 100px;font-size:25px;" name="buttonBB">B</button></center>
         <center><button style="height: 60px; width: 100px;font-size:25px;" name="button27">7</button><button style="height: 60px; width: 100px;font-size:25px;" name="button28">8</button><button style="height: 60px; width: 100px;font-size:25px;" name="button29">9</button><button style="height: 60px; width: 100px;font-size:25px;" name="buttonCC">C</button></center>
         <center><button style="height: 60px; width: 100px;font-size:25px;" name="button30">*</button><button style="height: 60px; width: 100px;font-size:25px;" name="button20">0</button><button style="height: 60px; width: 100px;font-size:25px;" name="button31">#</button><button style="height: 60px; width: 100px;font-size:25px;" name="buttonDD">D</button></center>
    </p>
    </form>
  

<?php
?>
</fieldset>
</body>
</html>
