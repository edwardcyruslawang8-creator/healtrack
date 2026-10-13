<?php
/**
 * =====================================================================
 * Verify 2FA Code at Login (api/verify-2fa.php)
 * ---------------------------------------------------------------------
 * Called by assets/js/login.js's code-entry step, which only appears
 * when api/session-login.php returned {requires2fa: true} instead of
 * starting a full session — it stashed the signed-in uid at
 * $_SESSION['pending2faUid'] (same PHP session/cookie, just without
 * role/name/etc. set yet) rather than trusting the client to resubmit
 * it. This endpoint reads THAT, not anything from the request body, so
 * a code can only ever be checked against the account that actually
 * just passed the Firebase Authentication step — never an arbitrary
 * uid someone hands it directly.
 *
 * On a correct code, this completes the session exactly the way
 * api/session-login.php would have if 2FA weren't enabled (same
 * fields, same admin/subadmin audit-log line) — see that file's
 * header comment for why each one is set. That includes the real
 * "Remember me" cookie lifetime — api/session-login.php stashed that
 * decision at $_SESSION['pendingRememberMe'] (this being a separate
 * request, with no memory of the original login request's body), read
 * back below and re-applied right before session_regenerate_id(), the
 * only point a cookie's lifetime can actually change.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../includes/shared/audit_log_writer.php'; // write_audit_log()
require_once __DIR__ . '/../includes/shared/session_cookie_helpers.php'; // hc_apply_remember_me_cookie_lifetime()
require_once __DIR__ . '/../includes/shared/remember_me.php'; // hc_issue_remember_token(), hc_clear_remember_token()

use PragmaRX\Google2FA\Google2FA;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

$uid = $_SESSION['pending2faUid'] ?? '';
if ($uid === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Nothing to verify. Please sign in again.']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$code = trim((string) ($body['code'] ?? ''));

if ($code === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Please enter your 6-digit code.']);
    exit;
}

$userDoc = $firestore->getDocument('users', $uid);
$secret = $userDoc['twoFactorSecret'] ?? '';
if ($userDoc === null || !($userDoc['twoFactorEnabled'] ?? false) || $secret === '') {
    // Account state changed since the pending-2FA session was created
    // (e.g. 2FA got disabled from another tab/device mid-login) —
    // nothing left to verify against.
    unset($_SESSION['pending2faUid']);
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Two-factor authentication is no longer required for this account. Please sign in again.']);
    exit;
}

$google2fa = new Google2FA();
$valid = false;
try {
    $valid = $google2fa->verifyKey($secret, $code);
} catch (\Throwable $e) {
    $valid = false;
}

if (!$valid) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'That code is incorrect or has expired. Please try again.']);
    exit;
}

unset($_SESSION['pending2faUid']);
$remember = (bool) ($_SESSION['pendingRememberMe'] ?? false);
unset($_SESSION['pendingRememberMe']);

// hc_apply_remember_me_cookie_lifetime() can only run while no session is
// active — session_set_cookie_params() unconditionally warns (and does
// nothing) otherwise, regardless of an upcoming session_regenerate_id().
// This request already called session_start() up top (to read
// pending2faUid), so the session has to be closed and reopened around
// this call — session_write_close() flushes/releases the current session
// without destroying it, session_start() picks the same session back up,
// and session_regenerate_id() right after is what actually issues the
// new-lifetime cookie. Skipping this meant a correct 6-digit code always
// warned (leaking raw HTML into this endpoint's JSON response, which
// could fail res.json() parsing client-side) AND "Remember me" silently
// never took effect for any 2FA-enabled account.
session_write_close();
hc_apply_remember_me_cookie_lifetime($remember);
session_start();
session_regenerate_id(true);

$email = $userDoc['email'] ?? '';
$role = $userDoc['role'] ?? null;
$name = $userDoc['name'] ?? ucfirst(strstr($email, '@', true) ?: $email);

$_SESSION['uid'] = $uid;
$_SESSION['email'] = $email;
$_SESSION['name'] = $name;
$_SESSION['role'] = $role;
$_SESSION['theme'] = $userDoc['theme'] ?? 'Light';
$_SESSION['language'] = $userDoc['language'] ?? 'English';
$_SESSION['ntfReadIds'] = $userDoc['notificationsReadIds'] ?? [];
$_SESSION['ntfDismissedIds'] = $userDoc['notificationsDismissedIds'] ?? [];
// api/session-login.php sources this from the ID token's own
// "email_verified" claim (free, already decoded there); this endpoint
// never sees that original token, so it asks the Admin SDK directly
// instead — same real Firebase Auth property either way.
try {
    $_SESSION['emailVerified'] = $firebaseAuth->getUser($uid)->emailVerified;
} catch (\Throwable $e) {
    $_SESSION['emailVerified'] = false;
}

// See includes/shared/remember_me.php's header comment for why this is a
// separate mechanism from the session cookie's own lifetime above.
if ($remember) {
    hc_issue_remember_token($uid);
} else {
    hc_clear_remember_token();
}

if (in_array($role, ['admin', 'subadmin'], true)) {
    write_audit_log('logged_in', 'User Management', 'User logged in to the system.');
}

echo json_encode(['success' => true, 'role' => $role]);
