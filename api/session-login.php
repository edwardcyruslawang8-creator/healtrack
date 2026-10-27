<?php
/**
 * =====================================================================
 * Server-side session login (api/session-login.php)
 * ---------------------------------------------------------------------
 * Called by assets/js/login.js right after Firebase Authentication
 * signs the user in client-side. Verifies the ID token with the
 * Firebase Admin SDK (so it can't be spoofed), looks up the user's
 * role at Firestore doc users/{uid}, and starts a PHP session — so
 * PHP pages (not just client-side JS) know who's logged in and what
 * role they have.
 *
 * ACCOUNT STATUS GATE: a "pending" account (self-registered via
 * register.php/api/register.php, awaiting admin approval — see that
 * file's header comment), a "locked" account, a "inactive" (suspended —
 * subadmin/user_management.php's "Suspend Account" action,
 * api/suspend-user.php) account, or a uid with NO users/{uid} doc at
 * all (e.g. Google sign-in on login.php for someone who's never
 * registered) gets NO session at all — every dashboard page gates on
 * $_SESSION['role'], so refusing to set it here is the real
 * access-control boundary, not just a client-side message. Firebase
 * Authentication itself still succeeds (the credential was valid);
 * this only blocks the app-level session.
 *
 * 2FA GATE: an account with twoFactorEnabled=true (Profile / Settings
 * -> "Enable 2FA", api/confirm-2fa-setup.php) doesn't get a full
 * session here either, even though it's active — only
 * $_SESSION['pending2faUid'] is set, and assets/js/login.js shows a
 * code-entry step instead of routing anywhere. api/verify-2fa.php is
 * what actually completes the session, once a valid TOTP code comes
 * back.
 * =====================================================================
 */

require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../includes/shared/audit_log_writer.php'; // write_audit_log()
require_once __DIR__ . '/../includes/shared/login_lockout.php'; // hc_login_lockout_status(), hc_reset_login_attempts()
require_once __DIR__ . '/../includes/shared/account_password_helpers.php'; // hc_account_has_password()

use Kreait\Firebase\Exception\Auth\FailedToVerifyToken;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
$idToken = $body['idToken'] ?? null;

if (!$idToken) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing idToken']);
    exit;
}

try {
    $verifiedToken = $firebaseAuth->verifyIdToken($idToken);
} catch (FailedToVerifyToken $e) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid or expired token']);
    exit;
}

$uid = $verifiedToken->claims()->get('sub');
$email = $verifiedToken->claims()->get('email');
$userDoc = $firestore->getDocument('users', $uid);

// No users/{uid} doc at all (e.g. a Google sign-in on login.php for
// someone who's never registered) — without this check, $status below
// would default to 'active' and a real session would start with
// $_SESSION['role'] = null, a dead end (every dashboard gates on role,
// so it wouldn't reach one, but the account would still look "signed
// in"). Refuse the session the same way a pending/locked account is
// refused below, rather than silently continuing with no role.
if ($userDoc === null) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'no_account', 'message' => 'No account found for this login. Please register first, or contact your system administrator.']);
    exit;
}

$role = $userDoc['role'] ?? null;
$name = $userDoc['name'] ?? ucfirst(strstr($email, '@', true) ?: $email);
$status = $userDoc['status'] ?? 'active';

if ($status === 'pending') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'pending_approval', 'message' => 'Your account is still awaiting admin approval. Please check back soon.']);
    exit;
}
// Suspended by a sub-admin (api/suspend-user.php) — a deliberate,
// reversible hold distinct from the "locked" lockout case below (that's
// an automatic security response to failed sign-ins; this is a manual
// administrative decision). Only api/reactivate-user.php clears it.
if ($status === 'inactive') {
    http_response_code(403);
    $suspendedUnlocker = ($role === 'subadmin') ? 'the system administrator' : 'your sub-administrator';
    echo json_encode(['success' => false, 'error' => 'suspended', 'message' => 'Your account has been suspended. Please contact ' . $suspendedUnlocker . '.']);
    exit;
}
// The real backstop for the escalating login-lockout penalty
// (includes/shared/login_lockout.php) — Firebase Auth has already
// PROVEN this is the correct password (a real ID token just verified
// above), but a lockout still refuses the session either way. This is
// what actually makes the lockout unbypassable: even a request that
// skipped assets/js/login.js's own pre-check (api/check-login-lockout.php)
// can't get past this. Covers both the PERMANENT case ($status ===
// 'locked') and the temporary lockedUntil window in one check — a
// sub-admin's own account gets a role-aware message here ("contact the
// system administrator" instead of "your sub-administrator", since no
// facility peer can unlock them — see that function's own comment).
$lockoutStatus = hc_login_lockout_status($userDoc);
if ($lockoutStatus['locked']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'locked', 'message' => $lockoutStatus['message']]);
    exit;
}

session_start();
session_regenerate_id(true);

// The password has now genuinely been proven correct (a real ID token
// just verified above) — clear the failed-attempt counter/temporary
// lockout regardless of whether 2FA still needs to complete below;
// that's a separate step, not another password check.
hc_reset_login_attempts($uid, $userDoc);

// 2FA (api/generate-2fa-secret.php / api/confirm-2fa-setup.php, set
// from Profile / Settings) — the account is genuinely active, but a
// code from their authenticator app is still needed before the real
// session below starts. Only the uid is stashed (not role/name/etc.,
// which is what actually grants dashboard access), and only
// api/verify-2fa.php reads it back — the client never gets to just
// resubmit an arbitrary uid to skip this.
if ($userDoc['twoFactorEnabled'] ?? false) {
    $_SESSION['pending2faUid'] = $uid;
    echo json_encode(['success' => true, 'requires2fa' => true]);
    exit;
}

// PASSWORD-SETUP GATE: api/register.php now sets a real password at
// registration time for BOTH the manual and "Sign up with Google"
// paths (changeUserPassword() on the Google path, adding a password
// credential to the same Firebase Auth account the popup created), so
// this should rarely fire for an account created from here on. It
// stays in place as a fallback for any account created before that
// changed — a "Sign up with Google" account from back then has NO
// password in Firebase Auth at all (confirmed via a real
// forgot-password test: sendPasswordResetEmail() silently does nothing
// for such an account, since there's no password credential to
// reset). hc_account_has_password() checks the REAL Firebase Auth
// provider list (not just the 'authProvider' Firestore field), so this
// still correctly catches those older accounts. Only reachable for an
// active, non-2FA-pending account, i.e. on this person's first real
// login after whatever still needed this gate.
if (!hc_account_has_password($uid)) {
    $_SESSION['pendingPasswordSetupUid'] = $uid;
    echo json_encode(['success' => true, 'requiresPasswordSetup' => true]);
    exit;
}

$_SESSION['uid'] = $uid;
$_SESSION['email'] = $email;
$_SESSION['name'] = $name;
$_SESSION['role'] = $role;
// Real Firebase Auth state (the "email_verified" claim every ID token
// carries — confirmed present via a live decoded token) — Profile /
// Settings' "Email Verification" row (includes/*/profile_settings_*_firestore.php)
// reads this, with a "Resend Email" action (assets/js/email-verification.js)
// when it's false. Not a login gate — see that row's own comment for why.
$_SESSION['emailVerified'] = (bool) $verifiedToken->claims()->get('email_verified');
// Admin appearance preferences (assets/js/theme.js, includes/i18n.php) —
// read once at login so every admin/*.php page can apply them without a
// Firestore round trip per page load; api/update-profile-settings.php
// keeps these in sync whenever they're changed.
$_SESSION['theme'] = $userDoc['theme'] ?? 'Light';
$_SESSION['language'] = $userDoc['language'] ?? 'English';

// Profile photo (includes/shared/profile_photo_storage.php's
// hc_render_sidebar_avatar()) — same "read once at login, cached in
// session" reasoning as theme/language above; api/upload-profile-photo.php
// keeps this in sync whenever a new photo is saved.
$_SESSION['profilePhotoFileName'] = $userDoc['profilePhotoFileName'] ?? null;
$_SESSION['profilePhotoUpdatedAt'] = $userDoc['profilePhotoUpdatedAt'] ?? null;

// Restore which notifications this subadmin already marked read on a
// previous login — api/mark-notifications-read.php writes this same
// field back to users/{uid} whenever something is marked read, so
// unread state survives logging out and back in instead of resetting
// to the seed data's original 5 every time (see
// config/constants.php's subadmin_unread_notification_count()).
$_SESSION['ntfReadIds'] = $userDoc['notificationsReadIds'] ?? [];

// Same idea, for notifications this user had dismissed back when
// notifications.php still had a Delete action (removed — no page can
// write a new value here any more, but old dismissed ids from before
// that removal still apply, so this keeps honoring them).
$_SESSION['ntfDismissedIds'] = $userDoc['notificationsDismissedIds'] ?? [];

// Only admin/subadmin sign-ins are relevant to the admin audit trail —
// patient logins would just be noise in that log.
if (in_array($role, ['admin', 'subadmin'], true)) {
    write_audit_log('logged_in', 'User Management', 'User logged in to the system.');
}

echo json_encode(['success' => true, 'role' => $role]);
