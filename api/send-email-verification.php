<?php
/**
 * =====================================================================
 * Send Email Verification (api/send-email-verification.php)
 * ---------------------------------------------------------------------
 * Called by assets/js/email-verification.js's "Resend Email" button
 * (Profile / Settings > Security Settings, all 6 roles). Replaces the
 * old client-side firebase.auth().currentUser.sendEmailVerification()
 * call — see includes/shared/email_verification_mailer.php's header
 * comment for why this moved server-side.
 *
 * Re-checks emailVerified LIVE against Firebase Auth itself rather than
 * trusting the page's own $_SESSION['emailVerified'] (which can be
 * stale — e.g. verified in another tab/device since this page loaded),
 * so this can never re-send to an account that's already verified.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php'; // $firebaseAuth
require_once __DIR__ . '/../includes/shared/email_verification_mailer.php'; // hc_send_verification_email()

header('Content-Type: application/json');

$uid = $_SESSION['uid'] ?? '';
if ($uid === '') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Your session has expired. Please log out and back in.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

try {
    $authUser = $firebaseAuth->getUser($uid);
} catch (\Throwable $e) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Account not found.']);
    exit;
}

if ($authUser->emailVerified) {
    echo json_encode(['success' => false, 'error' => 'This email address is already verified.']);
    exit;
}

$email = (string) $authUser->email;
$name = (string) ($_SESSION['name'] ?? 'there');

echo json_encode(hc_send_verification_email($email, $name));
