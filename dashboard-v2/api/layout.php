<?php
// Layout module's read/write endpoint. GET returns the *effective* layout
// for one page (merged with each module's own title, for the settings
// page to display) -- POST saves a new one.
//
// `page` (e.g. "dashboard", "qsolog") lets more than one page share this
// same engine while each keeps its own module selection -- see
// docs/dashboard-v2-brief.md's multi-page notes. Validated against a
// plain [a-z0-9-]+ pattern before it ever touches a path, since it's
// user-controlled input.
//
// Deliberately does NOT write dashboard-v2/pages/<page>/layout.json
// itself: that file lives inside the git checkout, and both the manual
// "Update Dashboard" button and the hourly auto-updater already do `git
// pull`/`git reset --hard` against this checkout (see dashboard/update/
// update.dashboard.sh) -- a live edit sitting in a tracked file would
// either get silently discarded on the next pull or make the checkout
// dirty and break that pull outright. Same reasoning as every other
// piece of local, live state in this project (see dashboard/include/
// qso_recorder.php's settings, /var/cache/hotspot-image/update_check.json,
// etc.): actual state lives under /var/cache/hotspot-image, outside git
// entirely. Each page's layout.json in the repo stays what it always
// was -- the *shipped default* -- and is only ever read here, as the
// fallback for a node that has never saved a custom layout for that page
// yet.
header('Content-Type: application/json');

const REPO_DIR = __DIR__ . '/..';
const MODULES_DIR = REPO_DIR . '/modules';

$page = $_GET['page'] ?? 'dashboard';
if (!is_string($page) || !preg_match('/^[a-z0-9-]+$/', $page)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid page.']);
    exit;
}

// "dashboard" keeps the original, pre-multi-page filename
// (dashboard-v2-layout.json, no page suffix) so a layout already saved
// from the settings page before pages existed keeps working unchanged.
$savedLayoutFile = '/var/cache/hotspot-image/dashboard-v2-layout' . ($page === 'dashboard' ? '' : '-' . $page) . '.json';
$defaultLayoutFile = REPO_DIR . '/pages/' . $page . '/layout.json';

function readValidLayout(string $path): ?array
{
    if (!is_readable($path)) {
        return null;
    }
    $data = json_decode((string)file_get_contents($path), true);
    return is_array($data) ? $data : null;
}

/** All module ids this checkout actually has, so a module added to disk
 * but never yet saved into a layout (default or custom) still shows up
 * in the settings page instead of silently never being offered. Shared
 * across every page -- any module can be placed on any page. */
function knownModuleIds(): array
{
    $ids = [];
    foreach (glob(MODULES_DIR . '/*/manifest.json') ?: [] as $manifestPath) {
        $ids[] = basename(dirname($manifestPath));
    }
    sort($ids);
    return $ids;
}

function moduleTitle(string $id): string
{
    $manifest = readValidLayout(MODULES_DIR . '/' . $id . '/manifest.json');
    return (string)($manifest['title'] ?? $id);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body)) {
        http_response_code(400);
        echo json_encode(['error' => 'Body must be a JSON array of {id, col, enabled}.']);
        exit;
    }
    $clean = [];
    foreach ($body as $entry) {
        if (!is_array($entry) || !isset($entry['id']) || !is_string($entry['id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Each entry needs a string id.']);
            exit;
        }
        // 4, not 3 -- matches api/page-meta.php's MAX_COLUMNS, since a
        // page can now be configured for up to 4 columns.
        $col = (int)($entry['col'] ?? 1);
        if ($col < 1 || $col > 4) {
            http_response_code(400);
            echo json_encode(['error' => 'col must be between 1 and 4 (entry: ' . $entry['id'] . ').']);
            exit;
        }
        $clean[] = [
            'id' => $entry['id'],
            'col' => $col,
            'enabled' => (bool)($entry['enabled'] ?? true),
        ];
    }
    if (!is_dir(dirname($savedLayoutFile))) {
        mkdir(dirname($savedLayoutFile), 0755, true);
    }
    if (file_put_contents($savedLayoutFile, json_encode($clean, JSON_PRETTY_PRINT)) === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Could not write ' . $savedLayoutFile . ' -- check www-data can write /var/cache/hotspot-image.']);
        exit;
    }
    echo json_encode(['saved' => true]);
    exit;
}

// GET: saved layout if one exists, else the shipped default for this
// page. Fill in any module that exists on disk but isn't in that layout
// yet (newly added, never saved before) as a disabled entry in column 1,
// so it's visible and toggleable in the settings page rather than
// invisible until someone edits JSON by hand.
$layout = readValidLayout($savedLayoutFile) ?? readValidLayout($defaultLayoutFile) ?? [];
$seenIds = array_column($layout, 'id');
foreach (knownModuleIds() as $id) {
    if (!in_array($id, $seenIds, true)) {
        $layout[] = ['id' => $id, 'col' => 1, 'enabled' => false];
    }
}

$withTitles = array_map(function ($entry) {
    $entry['title'] = moduleTitle($entry['id']);
    return $entry;
}, $layout);

echo json_encode([
    'layout' => $withTitles,
    'using_saved' => is_readable($savedLayoutFile),
]);
