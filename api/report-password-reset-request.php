<?php
/**
 * =====================================================================
 * Report Password Reset Request (api/report-password-reset-request.php)
 * ---------------------------------------------------------------------
 * Called by assets/js/forgot-password.js right after
 * firebase.auth().sendPasswordResetEmail() settles (success OR
 * 'auth/user-not-found' — forgot-password.php's whole flow is client-
 * side Firebase with no backend endpoint of its own otherwise, so this
 * is the only place a reset REQUEST ever becomes a real server-side
 * record at all). Fire-and-forget from the client: its response is
 * never read, so there's nothing to leak through it either way.
 *
 * Same anti-enumeration precedent as api/report-failed-login.php:
 * writes nothing for an email with no real account — there's no real
 * uid to attach a log entry to, and silently no-op-ing here is exactly
 * as safe as that file's own identical choice.
 *
 * Written directly to "audit_logs" (not via
 * includes/shared/audit_log_writer.php's write_audit_log()) for the
 * same reason includes/shared/login_lockout.php's own entries are:
 * there's no session during this request — the person isn't signed in.
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
    echo json_encode(['success' => true]); // no such account — nothing real to log
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

echo json_encode(['success' => true]);
