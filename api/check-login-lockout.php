<?php
/**
 * =====================================================================
 * Check Login Lockout (api/check-login-lockout.php)
 * ---------------------------------------------------------------------
 * Called by assets/js/login.js right BEFORE it attempts
 * firebase.auth().signInWithEmailAndPassword() — so a locked-out person
 * sees the real wait time (or "contact your sub-admin") immediately,
 * without even spending a Firebase Auth attempt. See
 * includes/shared/login_lockout.php's header comment for why this is a
 * separate endpoint from api/report-failed-login.php and why
 * api/session-login.php ALSO re-checks this after a real password
 * verification — this endpoint alone is just the fast, friendly
 * pre-check, not the actual security boundary.
 *
 * Never reveals whether $email belongs to a real account — an unknown
 * email gets the exact same "not locked" response a real, never-failed
 * account would, so this can't be used to enumerate registered emails.
 * =====================================================================
 */

require_once __DIR__ . '/../config/firebase.php'; // $firestore, $firebaseAuth
require_once __DIR__ . '/../includes/shared/login_lockout.php'; // hc_login_lockout_status()

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$email = trim((string) ($body['email'] ?? ''));

$notLocked = ['success' => true, 'locked' => false, 'permanentlyLocked' => false, 'retryAfterSeconds' => null, 'message' => null];

if ($email === '') {
    echo json_encode($notLocked);
    exit;
}

try {
    $authUser = $firebaseAuth->getUserByEmail($email);
} catch (\Throwable $e) {
    echo json_encode($notLocked); // no such account — same response as "not locked"
    exit;
}

$userDoc = $firestore->getDocument('users', $authUser->uid);
if ($userDoc === null) {
    echo json_encode($notLocked);
    exit;
}

echo json_encode(array_merge(['success' => true], hc_login_lockout_status($userDoc)));
