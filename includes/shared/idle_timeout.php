<?php
/**
 * =====================================================================
 * Idle-Session Auto-Logout (30 minutes)
 * ---------------------------------------------------------------------
 * HEALTrack handles real patient health information, so a session left
 * open on a shared clinical workstation needs to actually expire after
 * real inactivity — not just eventually get garbage-collected by PHP's
 * own session.gc_maxlifetime, which is a cleanup setting (probabilistic,
 * not guaranteed to fire at any particular time), not a security
 * control.
 *
 * This is the SERVER-SIDE, authoritative half of the mechanism — it
 * catches a stale session even when the client-side timer never got
 * the chance to fire (tab closed without warning, laptop went to
 * sleep, JavaScript disabled). assets/js/idle-timeout.js is the
 * client-side half: it proactively redirects to api/logout.php after
 * 30 real minutes of no mouse/keyboard/touch/scroll activity, without
 * waiting for the user's next page request.
 *
 * Both halves funnel through api/logout.php so a timeout gets the
 * exact same cleanup a manual "Log out" click does — PHP session
 * destroyed and the Firebase client SDK signed out.
 *
 * Wired into config/constants.php (required by every authenticated
 * page and several api/*.php endpoints), so this runs everywhere
 * automatically without editing each of those files individually.
 * =====================================================================
 */

const HC_IDLE_TIMEOUT_SECONDS = 1800; // 30 minutes

if (!function_exists('hc_enforce_idle_timeout')) {
    function hc_enforce_idle_timeout(): void
    {
        if (($_SESSION['uid'] ?? '') === '') {
            return; // not signed in (or mid-2FA, which also has no uid yet) — nothing to time out
        }

        // api/logout.php is the redirect target below — never redirect it
        // to itself. Without this guard, a genuinely-idle session hitting
        // logout.php (which itself requires config/constants.php) would
        // trigger this same check again, exit() on its own redirect
        // before logout.php's own cleanup code ever runs, and loop.
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        if (str_ends_with($script, '/api/logout.php') || str_ends_with($script, '\\api\\logout.php')) {
            return;
        }

        $now = time();
        $lastActivity = (int) ($_SESSION['lastActivity'] ?? $now);

        if ($now - $lastActivity > HC_IDLE_TIMEOUT_SECONDS) {
            header('Location: ' . BASE_URL . '/api/logout.php?reason=idle');
            exit;
        }

        $_SESSION['lastActivity'] = $now;
    }
}
