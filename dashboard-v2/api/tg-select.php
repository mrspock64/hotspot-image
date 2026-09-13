<?php
// Shared TG-select endpoint -- switches this node's active talkgroup,
// reusing production's own sendTgSelectDtmf() (dashboard/include/
// tg_select.php, extracted from buttons.php specifically so this could
// require it without pulling in that file's page template). Same
// "91<tg>#" DTMF command and double-send workaround the production TG
// page's own "A" (cell_tower) button sends.
//
// Extracted out of api/monitored-talkgroups.php once Reflector Activity
// also wanted to trigger a TG switch (click a live QSO in the recent-
// history list to jump to it) -- two modules needing the same action is
// exactly the situation dashboard/include/svxlink_log_state.php's own
// "don't duplicate, share" house rule is about, just for an action
// instead of a log-parse this time.
//
// Validation is deliberately just "digits only", not "must already be a
// named TG in the TG Names database" (that was api/monitored-
// talkgroups.php's own earlier, stricter rule, dropped here): Reflector
// Activity's rows are real, currently-active talkgroups straight from
// SvxLink's own log, which are often dynamic reflector regroupings
// (e.g. "TG 240216", see the svxlink_dynamic_talkgroups memory note) that
// will never appear in the local name database at all. Restricting to
// named TGs would make "jump to the QSO you can see is happening right
// now" impossible for exactly the QSOs most worth jumping to. ctype_digit
// alone is already sufficient against injection -- the value only ever
// reaches shell_exec() as an escapeshellarg()'d numeric string inside
// sendTgSelectDtmf() -- so the stricter check was a UX/scope choice, not
// a safety one, and doesn't need to survive being shared.
header('Content-Type: application/json');

require_once __DIR__ . '/../../dashboard/include/tg_select.php';

$tg = $_GET['tg'] ?? ($_POST['tg'] ?? '');
if (!ctype_digit((string)$tg)) {
    http_response_code(400);
    echo json_encode(['error' => 'tg must be a number']);
    exit;
}

sendTgSelectDtmf('91' . $tg . '#');
echo json_encode(['selected' => (string)$tg]);
