<?php
/**
 * =====================================================================
 * Check Email Verified (api/check-email-verified.php)
 * ---------------------------------------------------------------------
 * Called by assets/js/email-verification.js — once on page load, and
 * again whenever the Profile/Settings tab regains focus (the moment
 * right after someone plausibly just clicked the verification link in
 * another tab or their email client). Live-checks Firebase Auth
 * directly rather than the possibly-stale $_SESSION['emailVerified']
 * this page's own markup was rendered from (that value is only ever
 * set once, at login — see api/session-login.php).
 *
 * If now verified, refreshes $_SESSION['emailVerified'] too, so every
 * OTHER page reading it during this same session is immediately
 * accurate as well — not just this one open tab's own "Resend Email"
 * row, which the caller removes client-side on a true response.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php'; // $firebaseAuth

header('Content-Type: application/json');

$uid = $_SESSION['uid'] ?? '';
if ($uid === '') {
    http_response_code(401);
    echo json_encode(['verified' => false]);
    exit;
}

try {
    $authUser = $firebaseAuth->getUser($uid);
} catch (\Throwable $e) {
    echo json_encode(['verified' => false]);
    exit;
}

if ($authUser->emailVerified) {
    $_SESSION['emailVerified'] = true;
}

echo json_encode(['verified' => $authUser->emailVerified]);
