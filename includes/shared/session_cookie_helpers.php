<?php
/**
 * =====================================================================
 * Shared — Session Cookie Helpers ("Remember me")
 * ---------------------------------------------------------------------
 * hc_apply_remember_me_cookie_lifetime($remember) sets the PHP session
 * cookie's real lifetime — 30 days when "Remember me" was checked at
 * login, or the PHP default (expires when the browser closes) when it
 * wasn't. MUST be called while NO session is currently active —
 * session_set_cookie_params() unconditionally warns (and silently does
 * nothing) if one is, even if session_regenerate_id() is about to be
 * called right after. That means: before session_start() on a fresh
 * request (api/session-login.php's own non-2FA path), or — on a request
 * that already called session_start() earlier for its own reasons, like
 * api/verify-2fa.php reading $_SESSION['pending2faUid'] — only after a
 * session_write_close()/session_start() round-trip closes and reopens
 * it first. Either way, session_regenerate_id() must still follow
 * immediately after, since that's the actual moment the new lifetime
 * gets issued to the browser.
 *
 * Used by both api/session-login.php (the normal path, and the 2FA-
 * pending path, which stashes this same decision at
 * $_SESSION['pendingRememberMe'] for the second call below) and
 * api/verify-2fa.php (reads that stashed decision back once 2FA
 * actually completes the session) — one real cookie-lifetime decision,
 * applied consistently regardless of which of those two requests ends
 * up being the one that finally starts the full session.
 * =====================================================================
 */

if (!function_exists('hc_apply_remember_me_cookie_lifetime')) {
    function hc_apply_remember_me_cookie_lifetime(bool $remember): void
    {
        $current = session_get_cookie_params();
        session_set_cookie_params([
            'lifetime' => $remember ? 60 * 60 * 24 * 30 : 0, // 30 days, or "until the browser closes"
            'path' => $current['path'],
            'domain' => $current['domain'],
            'secure' => $current['secure'],
            'httponly' => $current['httponly'],
            'samesite' => $current['samesite'] ?? 'Lax',
        ]);
    }
}
