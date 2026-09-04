<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <title>svxlink.conf Reference</title>
    <link href="/css/css.php" type="text/css" rel="stylesheet" />
<style type="text/css">
body { background-color: #eee; font-size: 15px; font-family: Arial; color: #444; }
.wrap { max-width: 900px; width: 95%; box-sizing: border-box; margin: 15px auto; }
h1 { color:#00aee8; font: 18pt arial, sans-serif; font-weight:bold; text-shadow: 0.25px 0.25px gray; }
.hint { color:#777; font-size: 12px; margin-bottom: 10px; }
#searchBox { width: 100%; max-width: 400px; padding: 6px; box-sizing: border-box; margin-bottom: 8px; }
#matchCount { color: #777; font-size: 12px; }
pre {
  background: #fff; border: 1px solid #ccc; border-radius: 6px;
  padding: 16px; overflow-x: auto; white-space: pre; font-size: 13px;
  line-height: 1.4;
}
mark { background: #ffe08a; }
mark.current { background: #ff9a3d; }
.msg-err { background:#f7d7d7; border:1px solid #c33; padding:8px; border-radius:6px; margin-bottom:10px; }
</style>
  </head>
  <body>
<div class="wrap">
<center><h1>svxlink.conf Reference</h1></center>

<?php
// This renders the actual `man svxlink.conf` page installed alongside
// svxlink-server on this node -- not a copy we maintain, so it's always
// correct for whatever SvxLink version is actually running here. RF.Guru
// never surfaces this anywhere in the dashboard even though it ships with
// every install; a full accurate reference beats anything we'd write by
// hand and inevitably let drift out of date.
$output = [];
$exitCode = 0;
exec('MANWIDTH=100 man svxlink.conf 2>&1 | col -b', $output, $exitCode);
$text = implode("\n", $output);
?>

<?php if ($exitCode !== 0 || trim($text) === ''): ?>
  <div class="msg-err">Could not load the man page (man svxlink.conf failed). Is svxlink-server installed?</div>
<?php else: ?>
  <input type="text" id="searchBox" placeholder="Search (e.g. SQL_DET, CTCSS_TO_TG, MONITOR_TGS)...">
  <span id="matchCount"></span>
  <div class="hint">Live copy of this node's own <code>man svxlink.conf</code> — always matches the SvxLink version actually installed here.</div>
  <pre id="manText"><?php echo htmlspecialchars($text); ?></pre>
<?php endif; ?>

</div>
<script>
(function() {
  var pre = document.getElementById('manText');
  var box = document.getElementById('searchBox');
  var countEl = document.getElementById('matchCount');
  if (!pre || !box) return;
  var original = pre.textContent;
  var matches = [];
  var current = -1;

  function escapeRegex(s) { return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); }
  function escapeHtml(s) {
    return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }

  box.addEventListener('input', function() {
    var q = box.value.trim();
    current = -1;
    if (q === '') {
      pre.innerHTML = escapeHtml(original);
      countEl.textContent = '';
      return;
    }
    var re = new RegExp(escapeRegex(q), 'gi');
    var html = escapeHtml(original).replace(re, function(m) { return '<mark>' + m + '</mark>'; });
    pre.innerHTML = html;
    matches = Array.prototype.slice.call(pre.querySelectorAll('mark'));
    countEl.textContent = matches.length + ' match' + (matches.length === 1 ? '' : 'es');
    if (matches.length > 0) {
      current = 0;
      matches[0].classList.add('current');
      matches[0].scrollIntoView({ block: 'center' });
    }
  });

  box.addEventListener('keydown', function(e) {
    if (e.key !== 'Enter' || matches.length === 0) return;
    e.preventDefault();
    matches[current].classList.remove('current');
    current = (current + (e.shiftKey ? -1 : 1) + matches.length) % matches.length;
    matches[current].classList.add('current');
    matches[current].scrollIntoView({ block: 'center' });
  });
})();
</script>
  </body>
</html>
