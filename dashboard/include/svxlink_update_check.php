<?php
/**
 * Cached "is a SvxLink update available" check for the header badge --
 * same pattern as update_check.php's dashboard-update badge, but against
 * sm0svx/svxlink's own public GitHub releases instead of this project's
 * repo. Public repo, no auth/deploy-key needed, unlike the dashboard
 * check.
 */

const SVXLINK_UPDATE_CHECK_CACHE_DIR = '/var/cache/hotspot-image';
const SVXLINK_UPDATE_CHECK_CACHE_FILE = SVXLINK_UPDATE_CHECK_CACHE_DIR . '/svxlink_update_check.json';
const SVXLINK_UPDATE_CHECK_TTL_SECONDS = 6 * 3600;

/** @return array{available: bool, installed: ?string, latest: ?string} */
function isSvxlinkUpdateAvailable(): array
{
    if (is_file(SVXLINK_UPDATE_CHECK_CACHE_FILE) && (time() - filemtime(SVXLINK_UPDATE_CHECK_CACHE_FILE)) < SVXLINK_UPDATE_CHECK_TTL_SECONDS) {
        $cached = json_decode((string)@file_get_contents(SVXLINK_UPDATE_CHECK_CACHE_FILE), true);
        if (is_array($cached) && isset($cached['available'])) {
            return $cached;
        }
    }

    $result = ['available' => false, 'installed' => null, 'latest' => null];

    // svxlink --version prints something like "1.10.1@26.05.1 ..." -- two
    // separate version numbers baked into the same binary, not a typo or
    // a bug: "1.10.1" is the core/protocol version (what the SvxReflector
    // node list shows for every node, ours included -- confirmed live
    // against https://reflector-sm.svxlink.org/nodes), "26.05.1" is
    // sm0svx/svxlink's own dated GitHub release tag (confirmed against
    // the GitHub API, tag_name "26.05.1" for the same release this binary
    // is built from). They can never disagree with each other since
    // they're compiled into the one binary together -- but showing the
    // "26.05.1" half in our own header while the reflector shows "1.10.1"
    // for the exact same install read as two different versions, which is
    // what prompted this. Now: the part before "@" is what's shown in the
    // dashboard (matches the reflector), the part after "@" is still what
    // gets compared against GitHub's tag_name (that's the half GitHub
    // actually uses for its own release tags).
    $versionOut = trim((string)@shell_exec('svxlink --version 2>/dev/null'));
    if (preg_match('/([0-9.]+)@([0-9.]+)/', $versionOut, $m)) {
        $result['installed'] = $m[1];
        $installedTag = $m[2];

        // --max-time so a slow/unreachable GitHub can't hang a page load;
        // -A required or GitHub's API rejects the request with 403.
        $json = @shell_exec('curl -s --max-time 5 -A hotspot-image-dashboard '
            . 'https://api.github.com/repos/sm0svx/svxlink/releases/latest 2>/dev/null');
        $release = json_decode((string)$json, true);
        $tag = $release['tag_name'] ?? null;
        if (is_string($tag) && preg_match('/^[0-9.]+$/', $tag)) {
            $result['latest'] = $tag;
            if ($tag !== $installedTag) {
                $result['available'] = true;
            }
        }
    }

    if (!is_dir(SVXLINK_UPDATE_CHECK_CACHE_DIR)) {
        @mkdir(SVXLINK_UPDATE_CHECK_CACHE_DIR, 0755, true);
    }
    @file_put_contents(SVXLINK_UPDATE_CHECK_CACHE_FILE, json_encode($result));
    return $result;
}
