<?php
/**
 * =====================================================================
 * Shared — "Remember Me" Persistent Login
 * ---------------------------------------------------------------------
 * WHY THIS EXISTS: extending the PHP session COOKIE's lifetime (see
 * includes/shared/session_cookie_helpers.php's hc_apply_remember_me_cookie_lifetime())
 * only controls how long the browser keeps sending it — it does nothing
 * about how long the session DATA behind that cookie actually survives
 * on the server. XAMPP's php.ini sets session.gc_maxlifetime=1440 (24
 * minutes); PHP's garbage collector deletes any session file idle
 * longer than that regardless of the cookie's own expiry, so a 30-day
 * "remember me" cookie was pointing at data that was already gone after
 * 24 minutes of inactivity. This module is a second, independent
 * persistence mechanism that doesn't depend on PHP's session GC at all.
 *
 * DESIGN: a random 256-bit token is split into what the browser holds
 * (the raw token, in a long-lived 'hc_remember' cookie) and what the
 * server holds (only sha256($token) as the remember_tokens/{hash}
 * Firestore doc id, plus which uid it belongs to and when it expires) —
 * so a leaked Firestore doc alone reveals nothing usable, and checking
 * a cookie back against Firestore is one getDocument() by id, not a
 * collection scan. Hashing scheme intentionally simple (no
 * selector/validator split): this app's threat model doesn't need
 * timing-attack-resistant lookups on top of that.
 *
 * hc_restore_session_from_remember_token() is the one function that
 * matters app-wide — called once from config/constants.php (required
 * by every real page, always right after that page's own session_start()),
 * so it runs everywhere without editing each of this app's 100+ page
 * files individually. It's a no-op (and skips touching Firestore at
 * all) unless $_SESSION already has no uid AND a remember cookie is
 * actually present, so normal already-logged-in page loads pay no cost.
 * =====================================================================
 */

const HC_REMEMBER_COOKIE_NAME = 'hc_remember';
const HC_REMEMBER_TOKEN_TTL_DAYS = 30;

if (!function_exists('hc_remember_cookie_options')) {
    /** @return array Shared setcookie()/setcookie-removal options for the remember cookie. */
    function hc_remember_cookie_options(int $expiresAt): array
    {
        return [
            'expires' => $expiresAt,
            'path' => '/',
            'secure' => (($_SERVER['HTTPS'] ?? 'off') !== 'off'),
            'httponly' => true,
            'samesite' => 'Lax',
        ];
    }
}

if (!function_exists('hc_issue_remember_token')) {
    /**
     * Call right after a real session is fully established (both
     * api/session-login.php's non-2FA path and api/verify-2fa.php's
     * successful-code path) when the "remember me" checkbox was on.
     */
    function hc_issue_remember_token(string $uid): void
    {
        // `global` must come BEFORE `require_once`, not after — this
        // function is always called from another function's scope, so
        // require_once's own top-level `$firestore = ...` assignment
        // lands in THIS function's local scope, not the global one;
        // binding the name to the global slot only afterward would
        // silently null it back out instead of picking up that value.
        global $firestore;
        require_once __DIR__ . '/../../config/firebase.php'; // $firestore

        $token = bin2hex(random_bytes(32)); // 256 bits — only this raw value ever leaves the server
        $hash = hash('sha256', $token);
        $expiresAt = time() + (HC_REMEMBER_TOKEN_TTL_DAYS * 86400);

        $firestore->setDocument('remember_tokens', $hash, [
            'uid' => $uid,
            'expiresAt' => date('c', $expiresAt),
            'createdAt' => date('c'),
        ]);

        setcookie(HC_REMEMBER_COOKIE_NAME, $token, hc_remember_cookie_options($expiresAt));
    }
}

if (!function_exists('hc_clear_remember_token')) {
    /**
     * Call at logout, and whenever someone logs back in WITHOUT
     * checking "remember me" (so an old remembered session for this
     * browser doesn't keep silently working after that). Safe to call
     * even when no remember cookie exists.
     */
    function hc_clear_remember_token(): void
    {
        $token = $_COOKIE[HC_REMEMBER_COOKIE_NAME] ?? '';
        if ($token !== '') {
            global $firestore; // must precede require_once — see hc_issue_remember_token()'s comment
            require_once __DIR__ . '/../../config/firebase.php'; // $firestore
            $firestore->deleteDocument('remember_tokens', hash('sha256', $token));
        }
        if (isset($_COOKIE[HC_REMEMBER_COOKIE_NAME])) {
            unset($_COOKIE[HC_REMEMBER_COOKIE_NAME]);
            setcookie(HC_REMEMBER_COOKIE_NAME, '', hc_remember_cookie_options(time() - 3600));
        }
    }
}

if (!function_exists('hc_revoke_all_remember_tokens_for_uid')) {
    /**
     * Deletes EVERY remember_tokens/{hash} doc for $uid, not just the
     * one in whichever browser happens to be mid-reset — called once a
     * real password reset actually completes (api/report-password-reset-completed.php,
     * reached via reset-password.php's own confirmPasswordReset() call)
     * specifically so a persistent "remember me" session on a DIFFERENT,
     * possibly compromised browser stops working the moment the account
     * owner resets their password, rather than silently continuing to
     * work until its own 30-day TTL happens to expire on its own.
     */
    function hc_revoke_all_remember_tokens_for_uid(string $uid): void
    {
        global $firestore; // must precede require_once — see hc_issue_remember_token()'s comment
        require_once __DIR__ . '/../../config/firebase.php'; // $firestore

        $matches = $firestore->queryDocuments([
            'from' => [['collectionId' => 'remember_tokens']],
            'where' => $firestore->fieldEqualsFilter('uid', $uid),
        ]);
        foreach ($matches as $doc) {
            $firestore->deleteDocument('remember_tokens', $doc['id']);
        }
    }
}

if (!function_exists('hc_restore_session_from_remember_token')) {
    /**
     * Silently re-establishes a full session from a valid remember
     * cookie when the real PHP session has none of its own (expired,
     * garbage-collected, or a brand-new browser session) — populating
     * the exact same $_SESSION fields api/session-login.php and
     * api/verify-2fa.php set on a normal login, read fresh from
     * Firestore (not cached in the token) so theme/notifications/role
     * are always current. Requires session_start() to have already run
     * (true for every caller — see this file's header comment).
     */
    function hc_restore_session_from_remember_token(): void
    {
        // Already have a real session, or one's actively mid-2FA — never
        // let a stale remember cookie override either of those.
        if (($_SESSION['uid'] ?? '') !== '' || ($_SESSION['pending2faUid'] ?? '') !== '') {
            return;
        }
        $token = $_COOKIE[HC_REMEMBER_COOKIE_NAME] ?? '';
        if ($token === '') {
            return;
        }

        global $firestore; // must precede require_once — see hc_issue_remember_token()'s comment
        require_once __DIR__ . '/../../config/firebase.php'; // $firestore

        $hash = hash('sha256', $token);
        $tokenDoc = $firestore->getDocument('remember_tokens', $hash);
        if ($tokenDoc === null || ($tokenDoc['expiresAt'] ?? '') < date('c')) {
            if ($tokenDoc !== null) {
                $firestore->deleteDocument('remember_tokens', $hash);
            }
            hc_clear_remember_token();
            return;
        }

        $uid = $tokenDoc['uid'] ?? '';
        $userDoc = $uid !== '' ? $firestore->getDocument('users', $uid) : null;
        // The account may have been deleted, locked, or put back to
        // "pending" since this token was issued — a remember cookie must
        // never grant more than a fresh login would right now.
        if ($userDoc === null || ($userDoc['status'] ?? 'active') !== 'active') {
            $firestore->deleteDocument('remember_tokens', $hash);
            hc_clear_remember_token();
            return;
        }

        // Prevents a captured/replayed old session id from riding along
        // once a real session exists again — same reasoning
        // api/session-login.php's own session_regenerate_id(true) call
        // already documents for a normal login.
        session_regenerate_id(true);

        $_SESSION['uid'] = $uid;
        $_SESSION['email'] = $userDoc['email'] ?? '';
        $_SESSION['name'] = $userDoc['name'] ?? '';
        $_SESSION['role'] = $userDoc['role'] ?? null;
        // Only the ID token itself carries "email_verified" as a free
        // claim (api/session-login.php's own source for this) — this
        // restoration path never sees a token, so it asks the Admin SDK
        // directly instead, same fallback api/verify-2fa.php already
        // uses for the same reason.
        global $firebaseAuth; // must precede require_once — see hc_issue_remember_token()'s comment
        require_once __DIR__ . '/../../config/firebase.php'; // $firebaseAuth
        try {
            $_SESSION['emailVerified'] = $firebaseAuth->getUser($uid)->emailVerified;
        } catch (\Throwable $e) {
            $_SESSION['emailVerified'] = false;
        }
        $_SESSION['theme'] = $userDoc['theme'] ?? 'Light';
        $_SESSION['language'] = $userDoc['language'] ?? 'English';
        $_SESSION['ntfReadIds'] = $userDoc['notificationsReadIds'] ?? [];
        $_SESSION['ntfDismissedIds'] = $userDoc['notificationsDismissedIds'] ?? [];
        // Same reasoning as api/session-login.php's own copy of this —
        // a remember-me auto-login never goes through that file, so it
        // needs to cache this itself too.
        $_SESSION['profilePhotoFileName'] = $userDoc['profilePhotoFileName'] ?? null;
        $_SESSION['profilePhotoUpdatedAt'] = $userDoc['profilePhotoUpdatedAt'] ?? null;
    }
}
