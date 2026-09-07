<?php
/**
 * Cached "is a dashboard update available" check for the header badge.
 * Deliberately cheap on every page load: git fetch only happens once per
 * CACHE_TTL_SECONDS, cached to a file, so browsing the dashboard doesn't
 * mean every single page hits the network. Silent on any failure (no
 * remote configured yet, repo still private, network down, ...) -- the
 * badge is a nice-to-have, not something that should ever break or slow
 * down a page load.
 */

// NOT /tmp: apache2's systemd unit runs with PrivateTmp=yes (Debian's
// default hardening), which gives Apache -- and every PHP script running
// under it -- its own private /tmp namespace, invisible to everything
// else on the system (an SSH session, a cron job, another service).
// Confirmed live: this cache silently never worked when it lived in
// /tmp -- Apache read and wrote its own private copy every time, so the
// badge always recomputed the live (always-false, repo still private)
// result instead of ever actually hitting the cache.
const UPDATE_CHECK_CACHE_DIR = '/var/cache/hotspot-image';
const UPDATE_CHECK_CACHE_FILE = UPDATE_CHECK_CACHE_DIR . '/update_check.json';
const UPDATE_CHECK_TTL_SECONDS = 6 * 3600;
const UPDATE_CHECK_REPO_DIR = '/opt/hotspot-image';

/** @return array{available: bool} */
function isDashboardUpdateAvailable(): array
{
    if (is_file(UPDATE_CHECK_CACHE_FILE) && (time() - filemtime(UPDATE_CHECK_CACHE_FILE)) < UPDATE_CHECK_TTL_SECONDS) {
        $cached = json_decode((string)@file_get_contents(UPDATE_CHECK_CACHE_FILE), true);
        if (is_array($cached) && isset($cached['available'])) {
            return $cached;
        }
    }

    $result = ['available' => false];
    if (is_dir(UPDATE_CHECK_REPO_DIR . '/.git')) {
        $dir = escapeshellarg(UPDATE_CHECK_REPO_DIR);
        // sudo, not a plain www-data git fetch: the deploy key
        // (/etc/hotspot-image/deploy_key) is 600 root:root, the correct/
        // secure mode -- OpenSSH's strict permission check specifically
        // rejects a key that's readable by any group/other the *reading*
        // process isn't itself a member of, which ruled out a www-data-
        // readable group grant as a fix (confirmed live: chmod 640
        // root:www-data let www-data read it fine, but then check.
        // dashboard.sh/update.dashboard.sh -- which run as root via sudo,
        // root's own group being "root", not "www-data" -- started
        // failing that same strict check instead). www-data already has
        // passwordless sudo on this node (same model the Update page's
        // own scripts rely on), so route through that instead of trying
        // to find one file mode that satisfies two different users.
        exec("cd $dir && sudo git fetch origin 2>/dev/null", $out, $code);
        if ($code === 0) {
            $local = trim((string)shell_exec("cd $dir && git rev-parse HEAD 2>/dev/null"));
            // --verify -q, not a bare rev-parse: on a checkout whose
            // origin/HEAD symbolic ref was never set (confirmed live --
            // this repo was fetched into after the fact, never actually
            // `git clone`d against the real remote), a bare `git rev-parse
            // origin/HEAD` prints the literal string "origin/HEAD" to
            // STDOUT before failing, and because both sides of a
            // `cmd1 || cmd2` shell fallback still feed the same capture,
            // that bogus text got prepended to origin/main's real SHA
            // instead of being discarded. --verify -q is the
            // scripting-safe form: silent, empty stdout, clean non-zero
            // exit on failure -- see check.dashboard.sh's fix for the same
            // gotcha, hit for real there first. The looksLikeSha check
            // below was already a safety net for this and still is one,
            // but shouldn't be the only line of defense.
            $remote = trim((string)shell_exec("cd $dir && git rev-parse --verify -q origin/HEAD 2>/dev/null || cd $dir && git rev-parse --verify -q origin/main 2>/dev/null"));
            $looksLikeSha = fn($s) => (bool)preg_match('/^[0-9a-f]{40}$/', $s);
            if ($looksLikeSha($local) && $looksLikeSha($remote) && $local !== $remote) {
                $result['available'] = true;
            }
        }
    }

    if (!is_dir(UPDATE_CHECK_CACHE_DIR)) {
        @mkdir(UPDATE_CHECK_CACHE_DIR, 0755, true);
    }
    @file_put_contents(UPDATE_CHECK_CACHE_FILE, json_encode($result));
    return $result;
}
