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
        exec("cd $dir && git fetch origin 2>/dev/null", $out, $code);
        if ($code === 0) {
            $local = trim((string)shell_exec("cd $dir && git rev-parse HEAD 2>/dev/null"));
            $remote = trim((string)shell_exec("cd $dir && git rev-parse origin/HEAD 2>/dev/null || cd $dir && git rev-parse origin/main 2>/dev/null"));
            // rev-parse falls back to echoing an unresolvable ref name
            // verbatim instead of failing cleanly -- see check.dashboard.sh's
            // fix for the same gotcha. Reject anything that isn't a real
            // 40-char SHA before trusting it.
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
