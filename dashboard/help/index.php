<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link href="/css/css.php" type="text/css" rel="stylesheet" />
<link href="/css/modern.css" type="text/css" rel="stylesheet" />
<title>Help</title>
</head>
<body style="background: var(--mx-bg);">
<?php include_once __DIR__ . '/../include/site_header.php'; ?>

<div class="mx-card">
  <h1>Help</h1>
  <p class="mx-sub">What each page does, and what's new since this dashboard was forked from RF.Guru's stock image.</p>

  <div class="mx-section" style="margin-top:0;">New in this fork</div>
  <ul style="padding-left:20px; font-size:13px; line-height:1.7;">
    <li><b>RX Monitor</b> (small speaker button, top right of the Dashboard) — listen live to whatever this node is currently receiving or relaying, right in your browser. See "RX Monitor" below for what it does and doesn't cover.</li>
    <li><b>QSO Log</b> — records transmissions when switched on, browsable/playable/downloadable, with on/off and disk-limit controls right on the page. Off by default — turn it on here when you actually need it. Each new recording is auto-tagged with the talkgroup and callsign that was talking (when it can be determined), and you can restrict recording to only specific talkgroups.</li>
    <li><b>Talk Group Names</b> — the names shown on the Talk Groups page are now editable, with a one-click import from Setup's monitored talkgroups list.</li>
    <li><b>Buttons</b> — the front-page quick-DTMF buttons (TG4, TG8, ...) are editable instead of requiring an SSH login and a text editor.</li>
    <li><b>Backup / Restore</b> — one download bundles the reflector certificate, node config, buttons, and TG names; restoring is one upload.</li>
    <li>A long list of security fixes (command injection, an unauthenticated file-edit vulnerability, and more) and a visual overhaul — see the <a href="https://github.com/mrspock64/hotspot-image" target="_blank" rel="noopener">GitHub repo</a> for the full history.</li>
  </ul>

  <div class="mx-section">Daily use</div>
  <table class="mx-table">
    <tr><th style="width:22%">Page</th><th>What it's for</th></tr>
    <tr><td><b>Dashboard</b></td><td>Node status at a glance — active logics, loaded modules, last-heard stations, system info. The RX Monitor button (if present) lets you listen live.</td></tr>
    <tr><td><b>Talk Groups</b></td><td>One row per talkgroup. <b>M</b> = Monitor (listen to that TG without switching to it — you'll hear it but stay on your current one). <b>A</b> = Activate (switch to it). Names are edited on the <a href="/tgnames/">TG Names</a> page.</td></tr>
    <tr><td><b>Buttons</b></td><td>The quick-DTMF buttons shown at the top of Dashboard/Talk Groups. Add, remove, or relabel them here; each just sends a DTMF string when clicked.</td></tr>
    <tr><td><b>QSO Log</b></td><td>Records transmissions (receivers, modules, and reflector traffic all included) once switched on -- off by default. Play or download any recording, turn logging on/off, set the disk-space limit and how long a gap between transmissions counts as a new recording. Recordings show the talkgroup and callsign when known (recordings made before this feature existed show "--"); optionally restrict recording to only chosen talkgroups. The list paginates once there are many recordings.</td></tr>
    <tr><td><b>Power</b></td><td>Restart SvxLink, restart the device, or power it off.</td></tr>
  </table>

  <div class="mx-section">Admin menu</div>
  <table class="mx-table">
    <tr><th style="width:22%">Page</th><th>What it's for</th></tr>
    <tr><td><b>Setup</b></td><td>Full node configuration: callsign, reflector connection, radio (CTCSS-to-TG mapping, frequency — this actually retunes the physical radio module), monitored talkgroups, identification timing, location.</td></tr>
    <tr><td><b>TG Names</b></td><td>Edit the names shown on the Talk Groups page. "Import from monitored talkgroups" pulls the TG numbers straight from Setup so you don't have to retype them.</td></tr>
    <tr><td><b>WiFi</b></td><td>Scan for networks, connect, or manage saved WiFi connections.</td></tr>
    <tr><td><b>Network</b></td><td>Check connectivity (ping), view connection details, or set a static IP.</td></tr>
    <tr><td><b>EchoLink</b></td><td>Configure the EchoLink module (callsign, password, servers) if you use it.</td></tr>
    <tr><td><b>DTMF</b></td><td>An on-screen keypad — sends DTMF digits to SvxLink the same as pressing them on a radio.</td></tr>
    <tr><td><b>Update</b></td><td>Check installed versions and trigger updates for the OS, SvxLink, and the dashboard itself.</td></tr>
    <tr><td><b>Backup</b></td><td>Download everything specific to this node (reflector certificate + key, svxlink.conf, node_info.json, custom buttons, TG names) as one zip, or restore from a previous one. Keep it private — it contains the node's private key.</td></tr>
    <tr><td><b>Docs</b></td><td>The full <code>svxlink.conf(5)</code> reference manual, live from this node's own installed SvxLink version — always accurate for whatever's actually running here.</td></tr>
    <tr><td><b>Log</b></td><td>Raw tail of the running SvxLink log file — useful for troubleshooting.</td></tr>
    <tr><td><b>Shell</b></td><td>Opens a web terminal on port 4200, if one is running on this node.</td></tr>
  </table>

  <div class="mx-section">RX Monitor — what it does and doesn't cover</div>
  <p style="font-size:13px; line-height:1.6;">
  The small speaker button streams live audio from SvxLink's own QSO Recorder, which captures audio
  from receivers, modules, <i>and reflector traffic</i> — so it covers both your own local transmissions
  and other stations' QSOs being relayed through this node, not just local RX. No open recording right
  now (nobody transmitting, no active reflector talker on a monitored TG) means no audio — that's
  expected, not a bug.
  </p>

</div>
</body>
</html>
