<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <link href="/css/modern.css" type="text/css" rel="stylesheet" />
    <title>Log</title>
  </head>
<body style="background: var(--mx-bg); margin: 0;">
<?php include_once __DIR__ . '/../include/site_header.php'; ?>

<div class="mx-card" style="max-width: 700px;">
  <h1 style="text-align:center;">Log viewer</h1>
  <p class="mx-sub" style="text-align:center;">Live -- streams new lines as SvxLink writes them (a real <code>tail -f</code>, not a periodic refresh). Stops after 10 minutes to free up the connection; click Reconnect to keep watching.</p>

  <p id="logStatus" style="text-align:center; font-size:12.5px; color:var(--mx-text-dim);">Connecting…</p>

  <pre id="logOutput" style="background:#111; color:#0f0; border:1px solid #000; font-family:'Courier New', monospace; font-size:11px; padding:8px; border-radius:6px; height:420px; overflow-y:auto; white-space:pre-wrap; word-break:break-all; margin:0 0 12px;"></pre>

  <p style="text-align:center;">
    <button type="button" id="reconnectBtn" class="mx-btn" style="display:none;" onclick="connect()">Reconnect</button>
  </p>
</div>

<script>
let source = null;
const output = document.getElementById('logOutput');
const status = document.getElementById('logStatus');
const reconnectBtn = document.getElementById('reconnectBtn');

function appendLine(text) {
  const atBottom = output.scrollTop + output.clientHeight >= output.scrollHeight - 20;
  output.textContent += text + "\n";
  if (atBottom) {
    output.scrollTop = output.scrollHeight;
  }
}

function connect() {
  reconnectBtn.style.display = 'none';
  status.textContent = 'Connecting…';
  output.textContent = '';

  source = new EventSource('/log/stream.php');

  source.onopen = () => { status.textContent = 'Live'; };

  source.onmessage = (e) => appendLine(e.data);

  source.addEventListener('timeout', (e) => {
    appendLine('--- ' + e.data + ' ---');
    status.textContent = 'Stopped (timed out)';
    reconnectBtn.style.display = 'inline-block';
    source.close();
  });

  source.addEventListener('error', (e) => {
    if (e.data) appendLine('--- ' + e.data + ' ---');
  });

  source.onerror = () => {
    // A real connection drop (network/server), not our own timeout event
    // above -- EventSource would otherwise retry forever on its own.
    status.textContent = 'Disconnected';
    reconnectBtn.style.display = 'inline-block';
    source.close();
  };
}

connect();
</script>
</body>
</html>
