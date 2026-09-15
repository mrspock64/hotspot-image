<?php
/**
 * Standalone switch between the two dashboards served on :80.
 *
 * Deliberately lives OUTSIDE both /var/www/html (v1) and the dashboard-v2
 * worktree, and is reached via its own Apache Alias (/switch), not through
 * whichever DocumentRoot happens to be active. If it lived inside either
 * tree, switching away from it would make the switch itself unreachable
 * without SSH -- see docs/dashboard-v2-brief.md for the full writeup.
 *
 * What it actually flips: /var/www/dashboard-active, a symlink that IS
 * Apache's DocumentRoot (set in the 000-default.conf vhost). Flipping it
 * is instant and fully reversible -- no files are copied or deleted, only
 * a symlink target changes, followed by an Apache reload to pick it up.
 * Port :8081 (dashboard-v2-preview.service) is untouched either way, so
 * the preview stays reachable there regardless of what :80 shows.
 */

const ACTIVE_LINK = '/var/www/dashboard-active';

// Whitelisted targets only -- never build the symlink target from request
// input, so there's no way to point DocumentRoot at an arbitrary path.
const TARGETS = [
    'v1' => [
        'path' => '/var/www/html',
        'label' => 'Production dashboard (v1)',
        'sub' => '/var/www/html -- the deployed copy of dashboard/, synced by the dashboard\'s own Update page',
    ],
    'v2' => [
        'path' => '/opt/hotspot-image-dashboard-v2/dashboard-v2',
        'label' => 'dashboard-v2 preview',
        'sub' => 'the live dashboard-v2 worktree -- same code already served on :8081',
    ],
];

function currentActiveKey(): ?string
{
    if (!is_link(ACTIVE_LINK)) {
        return null;
    }
    $target = realpath(ACTIVE_LINK);
    foreach (TARGETS as $key => $info) {
        if ($target !== false && $target === realpath($info['path'])) {
            return $key;
        }
    }
    return null;
}

$message = null;
$messageOk = true;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'switch') {
    $target = $_POST['target'] ?? '';
    if (!isset(TARGETS[$target])) {
        $message = 'Unknown target.';
        $messageOk = false;
    } else {
        $path = TARGETS[$target]['path'];
        exec('sudo ln -sfn ' . escapeshellarg($path) . ' ' . escapeshellarg(ACTIVE_LINK) . ' 2>&1', $lnOut, $lnRc);
        exec('sudo systemctl reload apache2 2>&1', $reloadOut, $reloadRc);
        if ($lnRc === 0 && $reloadRc === 0) {
            $message = 'Port :80 now serves ' . TARGETS[$target]['label'] . '.';
            $messageOk = true;
        } else {
            $message = 'Switch failed: ' . implode(' ', array_merge($lnOut, $reloadOut));
            $messageOk = false;
        }
    }
}

$activeKey = currentActiveKey();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dashboard switch</title>
<style>
  :root {
    --bg: #14181d;
    --panel: #1b2129;
    --border: #2a323d;
    --text: #e7ebef;
    --text-dim: #8993a1;
    --accent: #ff9d4d;
    --ok: #3ecf8e;
    --err: #ff6767;
  }
  * { box-sizing: border-box; }
  body {
    margin: 0;
    background: var(--bg);
    color: var(--text);
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    display: flex;
    justify-content: center;
    padding: 40px 16px;
  }
  main { width: 100%; max-width: 520px; }
  h1 { font-size: 1.3rem; margin: 0 0 4px; }
  .sub { color: var(--text-dim); font-size: 0.85rem; margin: 0 0 24px; }
  .card {
    background: var(--panel);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 16px 18px;
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
  }
  .card.active { border-color: var(--accent); }
  .info .label { font-weight: 600; }
  .info .desc { color: var(--text-dim); font-size: 0.8rem; margin-top: 2px; }
  .badge {
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--accent);
    border: 1px solid var(--accent);
    border-radius: 999px;
    padding: 2px 8px;
    white-space: nowrap;
  }
  button {
    background: var(--accent);
    color: #1a1206;
    border: none;
    border-radius: 6px;
    padding: 8px 14px;
    font-weight: 600;
    cursor: pointer;
    white-space: nowrap;
  }
  button:disabled { opacity: 0.5; cursor: default; }
  .msg {
    border-radius: 8px;
    padding: 10px 14px;
    font-size: 0.85rem;
    margin-bottom: 16px;
  }
  .msg.ok { background: rgba(62, 207, 142, 0.12); color: var(--ok); }
  .msg.err { background: rgba(255, 103, 103, 0.12); color: var(--err); }
  .note { color: var(--text-dim); font-size: 0.78rem; margin-top: 24px; line-height: 1.5; }
  .note a { color: var(--accent); }
</style>
</head>
<body>
<main>
  <h1>Dashboard switch</h1>
  <p class="sub">Chooses which dashboard <b>:80</b> serves. Preview stays reachable on <b>:8081</b> either way.</p>

  <?php if ($message): ?>
    <div class="msg <?= $messageOk ? 'ok' : 'err' ?>"><?= htmlspecialchars($message) ?></div>
  <?php endif; ?>
  <?php if ($activeKey === null): ?>
    <div class="msg err">Could not determine which dashboard is currently active on :80.</div>
  <?php endif; ?>

  <?php foreach (TARGETS as $key => $info): ?>
    <div class="card <?= $key === $activeKey ? 'active' : '' ?>">
      <div class="info">
        <div class="label"><?= htmlspecialchars($info['label']) ?></div>
        <div class="desc"><?= htmlspecialchars($info['sub']) ?></div>
      </div>
      <?php if ($key === $activeKey): ?>
        <span class="badge">Active on :80</span>
      <?php else: ?>
        <form method="post" class="switch-form" data-label="<?= htmlspecialchars($info['label']) ?>">
          <input type="hidden" name="action" value="switch">
          <input type="hidden" name="target" value="<?= htmlspecialchars($key) ?>">
          <button type="submit">Switch to this</button>
        </form>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>

  <p class="note">
    Flips a symlink (<code>/var/www/dashboard-active</code>) and reloads Apache -- no files are copied, nothing else changes. This page stays reachable at <code>/switch/</code> no matter which dashboard is active. Reachable from within each dashboard's own settings page.
  </p>
</main>
<script>
document.querySelectorAll('.switch-form').forEach(function (form) {
  form.addEventListener('submit', function (e) {
    var label = form.dataset.label;
    if (!window.confirm('Switch port :80 to "' + label + '"? Anyone on the LAN hitting this node\'s default address will see the change immediately.')) {
      e.preventDefault();
    }
  });
});
</script>
</body>
</html>
