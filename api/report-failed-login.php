<?php
/**
 * =====================================================================
 * Report Failed Login (api/report-failed-login.php)
 * ---------------------------------------------------------------------
 * Called by assets/js/login.js's .catch() the moment Firebase Auth
 * itself rejects a password (auth/wrong-password, auth/invalid-credential,
 * auth/user-not-found) — the only way this server ever learns an
 * attempt failed at all, since Firebase Authentication verifies
 * passwords entirely client-side (see includes/shared/login_lockout.php's
 * header comment). Increments the real per-account counter and applies
 * whichever escalating penalty the attempt count has just earned
 * (hc_register_failed_login()).
 *
 * Never reveals whether $email belongs to a real account — an unknown
 * email gets the exact same "not locked" response a real account would
 * if this were its first-ever wrong attempt, so this can't be used to
 * enumerate registered emails, and there's nothing real to increment
 * for an email nobody registered with anyway.
 * =====================================================================
 */

require_once __DIR__ . '/../config/firebase.php'; // $firestore, $firebaseAuth
require_once __DIR__ . '/../includes/shared/login_lockout.php'; // hc_register_failed_login()

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
    echo json_encode($notLocked); // no such account — nothing real to count against
    exit;
}

$userDoc = $firestore->getDocument('users', $authUser->uid);
if ($userDoc === null) {
    echo json_encode($notLocked);
    exit;
}

// Already permanently locked or mid-lockout — don't keep incrementing
// past whatever already happened (a locked-out person's client-side
// pre-check should have stopped them before Firebase was even asked,
// but this is the authoritative side, not the UI, so it re-checks
// rather than trusting that already happened).
$already = hc_login_lockout_status($userDoc);
if ($already['locked']) {
    echo json_encode(array_merge(['success' => true], $already));
    exit;
}

echo json_encode(array_merge(['success' => true], hc_register_failed_login($authUser->uid, $userDoc)));
