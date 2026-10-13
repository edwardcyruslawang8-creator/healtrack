<?php
/**
 * =====================================================================
 * Create User (api/create-user.php)
 * ---------------------------------------------------------------------
 * Called by assets/js/add-user-subadmin.js when a sub-admin submits
 * the "Add User" modal (subadmin/user_management.php's #addUserModal).
 * Creates a real Firebase Auth account server-side (via the Admin SDK,
 * so it doesn't touch the signing-in sub-admin's own session) plus a
 * matching Firestore users/{uid} profile document — same shape as
 * api/create-subadmin.php, except the facility is never a client-
 * supplied value here: it's always this sub-admin's OWN real facility,
 * resolved from their own users/{uid} doc, since a sub-admin only
 * manages their own facility's staff directory (see
 * includes/subadmin/user_management_firestore.php's header comment).
 *
 * Role must be one of the roles subadmin/user_management.php's table
 * actually manages ($roleMeta there) — anything else (including
 * "admin"/"patient") is refused.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../includes/shared/audit_log_writer.php'; // write_audit_log()

header('Content-Type: application/json');

if (($_SESSION['role'] ?? null) !== 'subadmin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Not authorized.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

$allowedRoles = ['subadmin', 'healthcare_staff', 'pharmacist', 'medical_technician', 'doctor'];

$body = json_decode(file_get_contents('php://input'), true) ?? [];

$name = trim((string) ($body['name'] ?? ''));
$email = trim((string) ($body['email'] ?? ''));
$role = trim((string) ($body['role'] ?? ''));
$password = (string) ($body['password'] ?? '');
$confirmPassword = (string) ($body['confirmPassword'] ?? '');

if ($name === '' || $email === '' || $role === '' || $password === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Please fill out every field.']);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Please enter a valid email address.']);
    exit;
}
if (!in_array($role, $allowedRoles, true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Please select a valid role.']);
    exit;
}
if (strlen($password) < 6) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Password must be at least 6 characters.']);
    exit;
}
if ($password !== $confirmPassword) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Passwords do not match.']);
    exit;
}

$myUid = $_SESSION['uid'] ?? '';
$myDoc = $myUid !== '' ? $firestore->getDocument('users', $myUid) : null;
if ($myDoc === null || empty($myDoc['facility'])) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not resolve your own facility. Please try again.']);
    exit;
}

try {
    $userRecord = $firebaseAuth->createUser([
        'email' => $email,
        'password' => $password,
        'displayName' => $name,
    ]);
} catch (\Throwable $e) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Could not create account: ' . $e->getMessage()]);
    exit;
}

$uid = $userRecord->uid;

$saved = $firestore->setDocument('users', $uid, [
    'name' => $name,
    'email' => $email,
    'role' => $role,
    'facility' => $myDoc['facility'],
    'facilityId' => $myDoc['facilityId'] ?? null,
    'facilityLocation' => $myDoc['facilityLocation'] ?? null,
    'status' => 'active',
    'createdAt' => date('c'),
]);

if (!$saved) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Account was created but saving the profile failed. Please try again or contact support.']);
    exit;
}

write_audit_log('created', 'User Management', 'Added user "' . $name . '" (' . $role . ') at ' . $myDoc['facility'] . '.');

echo json_encode([
    'success' => true,
    'user' => [
        'id' => $uid,
        'name' => $name,
        'email' => $email,
        'role' => $role,
    ],
]);
