<?php
/**
 * =====================================================================
 * Report Password Reset Completed (api/report-password-reset-completed.php)
 * ---------------------------------------------------------------------
 * Called by assets/js/reset-password.js right after
 * firebase.auth().confirmPasswordReset() itself already succeeded — the
 * real password change already happened on Firebase's side by the time
 * this runs; this endpoint only writes a real "audit_logs" entry for
 * it, same direct-write pattern includes/shared/login_lockout.php's own
 * entries use (no session exists here either — the person isn't signed
 * in, they just finished a password-reset link).
 *
 * Best-effort from the client's own perspective (reset-password.js
 * shows success regardless of this call's outcome, since the actual
 * password change already genuinely happened via Firebase before this
 * was ever called) — but still writes the entry above for real.
 * =====================================================================
 */

require_once __DIR__ . '/../config/firebase.php'; // $firestore, $firebaseAuth

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false]);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$email = trim((string) ($body['email'] ?? ''));

if ($email === '') {
    echo json_encode(['success' => true]);
    exit;
}

try {
    $authUser = $firebaseAuth->getUserByEmail($email);
} catch (\Throwable $e) {
    echo json_encode(['success' => true]); // nothing real to act on
    exit;
}

$userDoc = $firestore->getDocument('users', $authUser->uid);
if ($userDoc === null) {
    echo json_encode(['success' => true]);
    exit;
}

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
    'details' => 'Password reset completed via email link for "' . ($userDoc['name'] ?? 'Unknown') . '".',
    'status' => 'success',
    'timestamp' => date('c'),
]);

echo json_encode(['success' => true]);
