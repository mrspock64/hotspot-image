<?php
// Page metadata (display label, column count) -- GET/POST, same shipped-
// default-vs-saved-override split as api/layout.php and the same reason:
// a live edit must never land in the git-tracked checkout, since both the
// manual Update button and the hourly auto-updater run `git pull`/`git
// reset --hard` against it. See that file's own header comment for the
// full story -- this one is deliberately terse to avoid repeating it.
//
// Deliberately does NOT let the URL/page id itself be renamed here --
// only the *displayed* label and column count. Renaming the id would mean
// either breaking every bookmark/link to /<page>/ or having to rewrite
// this page's whole directory + every other page's nav list, for a
// cosmetic change that doesn't need it.
header('Content-Type: application/json');

const REPO_DIR = __DIR__ . '/..';
const MIN_COLUMNS = 1;
const MAX_COLUMNS = 4;
const DEFAULT_LABEL = 'Untitled';
const DEFAULT_COLUMNS = 3;

$page = $_GET['page'] ?? 'dashboard';
if (!is_string($page) || !preg_match('/^[a-z0-9-]+$/', $page)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid page.']);
    exit;
}

$savedFile = '/var/cache/hotspot-image/dashboard-v2-meta-' . $page . '.json';
$defaultFile = REPO_DIR . '/pages/' . $page . '/meta.json';

function readValidMeta(string $path): ?array
{
    if (!is_readable($path)) {
        return null;
    }
    $data = json_decode((string)file_get_contents($path), true);
    return is_array($data) ? $data : null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body)) {
        http_response_code(400);
        echo json_encode(['error' => 'Body must be a JSON object with label/columns.']);
        exit;
    }
    $label = trim((string)($body['label'] ?? ''));
    // mbstring isn't installed on svxlinkuhf (confirmed live: a real 500
    // the very first time this endpoint was actually used -- "Call to
    // undefined function mb_strlen()"). function_exists() guard rather
    // than depending on an extension this image doesn't ship; a label a
    // few bytes longer than intended for non-ASCII text (Swedish å/ä/ö
    // etc.) is harmless, an uncaught fatal on every save is not.
    $labelLen = function_exists('mb_strlen') ? mb_strlen($label) : strlen($label);
    if ($label === '' || $labelLen > 40) {
        http_response_code(400);
        echo json_encode(['error' => 'Label must be 1-40 characters.']);
        exit;
    }
    $columns = (int)($body['columns'] ?? DEFAULT_COLUMNS);
    if ($columns < MIN_COLUMNS || $columns > MAX_COLUMNS) {
        http_response_code(400);
        echo json_encode(['error' => 'columns must be between ' . MIN_COLUMNS . ' and ' . MAX_COLUMNS . '.']);
        exit;
    }
    $clean = ['label' => $label, 'columns' => $columns];
    if (!is_dir(dirname($savedFile))) {
        mkdir(dirname($savedFile), 0755, true);
    }
    if (file_put_contents($savedFile, json_encode($clean, JSON_PRETTY_PRINT)) === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Could not write ' . $savedFile . ' -- check www-data can write /var/cache/hotspot-image.']);
        exit;
    }
    echo json_encode(['saved' => true]);
    exit;
}

$meta = readValidMeta($savedFile) ?? readValidMeta($defaultFile) ?? [];
echo json_encode([
    'label' => (string)($meta['label'] ?? $page),
    'columns' => max(MIN_COLUMNS, min(MAX_COLUMNS, (int)($meta['columns'] ?? DEFAULT_COLUMNS))),
    'using_saved' => is_readable($savedFile),
]);
