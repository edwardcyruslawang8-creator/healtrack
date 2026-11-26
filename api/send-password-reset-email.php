<?php
/**
 * =====================================================================
 * Send Password Reset Email (api/send-password-reset-email.php)
 * ---------------------------------------------------------------------
 * Called by assets/js/forgot-password.js instead of Firebase's own
 * client-side firebase.auth().sendPasswordResetEmail() — that call
 * always sends Firebase's generic "Reset your password for
 * project-NNNN" email and links to Firebase's own hosted reset page
 * (healtrackdb-4e786.firebaseapp.com/__/auth/action), not this app's
 * branded reset-password.php, and Firebase's console email templates
 * don't support the custom HTML (logo, button) this app wants.
 *
 * Instead: $firebaseAuth->getPasswordResetLink() (Kreait Admin SDK)
 * generates the real, secure, single-use reset link WITHOUT emailing
 * anything itself. Only the oobCode from that link is kept — rebuilt
 * as a link straight to this app's own reset-password.php — and the
 * actual email is sent through includes/shared/mailer.php's own SMTP
 * (hc_send_email() + hc_password_reset_email_shell()), same mailer
 * every other account-lifecycle email in this app already uses.
 *
 * Replaces api/report-password-reset-request.php (deleted — this file
 * now does its job too): same anti-enumeration rule (always the same
 * generic success response, never reveals whether the email belongs to
 * a real account) and the same audit log entry for a real account.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/constants.php'; // BASE_URL
require_once __DIR__ . '/../config/firebase.php'; // $firestore, $firebaseAuth
require_once __DIR__ . '/../includes/shared/mailer.php'; // hc_send_email(), hc_password_reset_email_shell()

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$email = trim((string) ($body['email'] ?? ''));

// Same generic response this whole flow always uses — returned at the
// very end (and early-exited into) regardless of whether an email
// actually went out, so a slow network tab can't be used to tell real
// accounts apart from made-up ones either.
$GENERIC_SUCCESS = ['success' => true];

if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    echo json_encode($GENERIC_SUCCESS);
    exit;
}

// Light per-email cooldown so this self-hosted send path can't be used
// to spam one inbox with reset emails — same 60s shape
// api/send-registration-otp.php already uses for its own OTP cooldown.
$cooldownKey = 'lastPasswordResetSentAt_' . md5(strtolower($email));
$lastSentAt = $_SESSION[$cooldownKey] ?? 0;
if (time() - $lastSentAt < 60) {
    echo json_encode($GENERIC_SUCCESS);
    exit;
}

try {
    $authUser = $firebaseAuth->getUserByEmail($email);
} catch (\Throwable $e) {
    echo json_encode($GENERIC_SUCCESS); // no such account — nothing real to send
    exit;
}

$_SESSION[$cooldownKey] = time();

try {
    $rawLink = $firebaseAuth->getPasswordResetLink($authUser->email);
    $query = [];
    parse_str((string) parse_url($rawLink, PHP_URL_QUERY), $query);
    $oobCode = $query['oobCode'] ?? '';

    if ($oobCode === '') {
        throw new \RuntimeException('Generated reset link had no oobCode.');
    }

    $appBase = (($_SERVER['HTTPS'] ?? 'off') !== 'off' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . BASE_URL;
    $resetUrl = $appBase . '/reset-password.php?oobCode=' . urlencode($oobCode);
    $logoUrl = $appBase . '/assets/images/main_logo.png';

    hc_send_email(
        $authUser->email,
        $authUser->displayName ?? '',
        'Reset Your HEALTrack Password',
        hc_password_reset_email_shell($logoUrl, $authUser->email, $resetUrl)
    );
} catch (\Throwable $e) {
    error_log('[send-password-reset-email] Failed for ' . $email . ': ' . $e->getMessage());
    echo json_encode($GENERIC_SUCCESS); // still generic — the earlier lookup already proved the account is real, no need to expose a server-side failure to the client
    exit;
}

// Real account, real email sent — worth a real audit log entry, same
// one api/report-password-reset-request.php used to write. No session
// yet at this point (the person isn't signed in), so this goes straight
// to Firestore rather than through write_audit_log().
$userDoc = $firestore->getDocument('users', $authUser->uid);
if ($userDoc !== null) {
    $actorRole = match ($userDoc['role'] ?? null) {
        'admin' => 'System Administrator',
        'subadmin' => 'Sub-Administrator',
        'healthcare_staff' => 'Healthcare Staff',
        'pharmacist' => 'Pharmacist',
        'doctor' => 'Doctor',
        'medical_technician' => 'Medical Technologist',
        'radio_technologist' => 'Radiologic Technologist',
        default => 'System',
    };

    $firestore->addDocument('audit_logs', [
        'actorUid' => $authUser->uid,
        'actorName' => (string) ($userDoc['name'] ?? 'Unknown'),
        'actorRole' => $actorRole,
        'action' => 'updated',
        'module' => 'Account Security',
        'details' => 'Password reset requested for "' . ($userDoc['name'] ?? 'Unknown') . '".',
        'status' => 'success',
        'timestamp' => date('c'),
    ]);
}

echo json_encode($GENERIC_SUCCESS);
