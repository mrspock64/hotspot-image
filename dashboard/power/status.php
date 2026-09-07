<?php
// Tiny JSON endpoint for power/index.php's post-action polling -- start/
// stop/restart all run backgrounded, so the page the browser lands on
// right after submitting reflects whatever systemctl said at that exact
// instant, not the eventual result a few seconds later. Polled from JS
// instead of a full reload so the status line and button set can update
// in place once the change has actually taken effect.
header('Content-Type: application/json');
$active = trim((string)@shell_exec('systemctl is-active svxlink 2>/dev/null')) === 'active';
echo json_encode(['active' => $active]);
