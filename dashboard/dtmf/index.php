<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <link href="/css/modern.css" type="text/css" rel="stylesheet" />
    <style>
      .mx-keypad { display: grid; grid-template-columns: repeat(4, 64px); gap: 8px; justify-content: center; }
      .mx-keypad button {
        height: 56px; font-size: 20px; font-weight: 700; border: none; border-radius: 8px;
        background: var(--mx-surface); border: 1px solid var(--mx-border); color: var(--mx-text);
        cursor: pointer;
      }
      .mx-keypad button:hover { background: #eef2ff; color: var(--mx-accent-dark); }
    </style>
  </head>
<body style="background: var(--mx-bg); margin: 0;">

<div class="mx-card" style="max-width: 400px; box-shadow: none; border: none;">
  <h1 style="text-align:center;">DTMF Dialer</h1>

<?php

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

$dtmfMap = [
    'button20' => '0', 'button21' => '1', 'button22' => '2', 'button23' => '3',
    'button24' => '4', 'button25' => '5', 'button26' => '6', 'button27' => '7',
    'button28' => '8', 'button29' => '9', 'button30' => '*', 'button31' => '#',
    'buttonA' => 'A', 'buttonBB' => 'B', 'buttonCC' => 'C', 'buttonDD' => 'D',
];
foreach ($dtmfMap as $field => $digit) {
    if (isset($_POST[$field])) {
        shell_exec('/usr/sbin/hotspot_dtmf ' . escapeshellarg($digit));
    }
}
?>
<form method="post">
  <div class="mx-keypad">
    <button style="width: 64px;" name="button21">1</button><button style="width: 64px;" name="button22">2</button><button style="width: 64px;" name="button23">3</button><button style="width: 64px;" name="buttonA">A</button>
    <button style="width: 64px;" name="button24">4</button><button style="width: 64px;" name="button25">5</button><button style="width: 64px;" name="button26">6</button><button style="width: 64px;" name="buttonBB">B</button>
    <button style="width: 64px;" name="button27">7</button><button style="width: 64px;" name="button28">8</button><button style="width: 64px;" name="button29">9</button><button style="width: 64px;" name="buttonCC">C</button>
    <button style="width: 64px;" name="button30">*</button><button style="width: 64px;" name="button20">0</button><button style="width: 64px;" name="button31">#</button><button style="width: 64px;" name="buttonDD">D</button>
  </div>
</form>

</div>
</body>
</html>
