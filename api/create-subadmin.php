<?php
/**
 * =====================================================================
 * Create Sub-Administrator (api/create-subadmin.php)
 * ---------------------------------------------------------------------
 * Called by assets/js/add-subadmin.js when an admin submits the "Add
 * Sub-Administrator" modal (includes/add_subadmin_modal.php). Creates a
 * real Firebase Auth account via the Admin SDK — server-side, so unlike
 * the client-side SDK's createUserWithEmailAndPassword, this does NOT
 * sign the calling admin's browser session out and into the new
 * account — plus a matching Firestore users/{uid} profile document with
 * role=subadmin, which is what includes/subadmin_management_firestore.php
 * queries back out for the table.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../includes/admin/facility_management_firestore.php'; // $facilities, for facilityId -> name/location lookup
require_once __DIR__ . '/../includes/shared/audit_log_writer.php';         // write_audit_log()

header('Content-Type: application/json');

// Only a signed-in admin may create sub-administrator accounts.
if (($_SESSION['role'] ?? null) !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Not authorized.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];

$username = trim((string) ($body['username'] ?? ''));
$email = trim((string) ($body['email'] ?? ''));
$facilityId = trim((string) ($body['facilityId'] ?? ''));
$password = (string) ($body['password'] ?? '');
$confirmPassword = (string) ($body['confirmPassword'] ?? '');

if ($username === '' || $email === '' || $facilityId === '' || $password === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Please fill out every field.']);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Please enter a valid email address.']);
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

$facility = null;
foreach ($facilities as $f) {
    if ($f['id'] === $facilityId) {
        $facility = $f;
        break;
    }
}
if ($facility === null) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Please select a valid facility.']);
    exit;
}

try {
    $userRecord = $firebaseAuth->createUser([
        'email' => $email,
        'password' => $password,
        'displayName' => $username,
    ]);
} catch (\Throwable $e) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Could not create account: ' . $e->getMessage()]);
    exit;
}

$uid = $userRecord->uid;

$saved = $firestore->setDocument('users', $uid, [
    'name' => $username,
    'email' => $email,
    'role' => 'subadmin',
    'facility' => $facility['name'],
    'facilityId' => $facility['id'],
    'facilityLocation' => $facility['location'],
    'status' => 'active',
    'createdAt' => date('c'),
]);

if (!$saved) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Account was created but saving the profile failed. Please try again or contact support.']);
    exit;
}

write_audit_log('created', 'Sub-Administrator Management', 'Added sub-administrator "' . $username . '" for ' . $facility['name'] . '.');

echo json_encode([
    'success' => true,
    'subadmin' => [
        'id' => $uid,
        'name' => $username,
        'email' => $email,
        'facility' => $facility['name'],
        'location' => $facility['location'],
        'status' => 'active',
    ],
]);
