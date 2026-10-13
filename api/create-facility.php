<?php
/**
 * =====================================================================
 * Create Facility (api/create-facility.php)
 * ---------------------------------------------------------------------
 * Called by assets/js/add-facility.js when an admin submits the "Add
 * New Facility" modal (includes/add_facility_modal.php). Writes a new
 * document to Firestore's "facilities" collection — the same
 * collection includes/facility_management_firestore.php reads for
 * admin/facility_management.php's card grid and
 * admin/admin_dashboard.php's Facility Overview table.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../includes/shared/audit_log_writer.php'; // write_audit_log()

header('Content-Type: application/json');

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

$name = trim((string) ($body['name'] ?? ''));
$location = trim((string) ($body['location'] ?? ''));
$status = trim((string) ($body['status'] ?? 'active'));

if ($name === '' || $location === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Please fill out every field.']);
    exit;
}

$validStatuses = ['active', 'inactive'];
if (!in_array($status, $validStatuses, true)) {
    $status = 'active';
}

// Slugify the name into a doc id (e.g. "South Super Health Center" ->
// "south-super-health-center"), matching the ids already seeded for the
// original 3 facilities and what facility_analytics.php?facility_id=...
// links expect.
$slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $name), '-'));
if ($slug === '') {
    $slug = 'facility-' . time();
}

if ($firestore->getDocument('facilities', $slug) !== null) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'A facility with that name already exists.']);
    exit;
}

$saved = $firestore->setDocument('facilities', $slug, [
    'name' => $name,
    'type' => 'Super Health Center',
    'location' => $location,
    'icon' => 'bi-building',
    'patients' => 0,
    'records' => 0,
    'staff' => 0,
    'status' => $status,
    'dateAdded' => date('c'),
]);

if (!$saved) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not save the facility. Please try again.']);
    exit;
}

write_audit_log('created', 'Facility Management', 'Added facility "' . $name . '".');

echo json_encode([
    'success' => true,
    'facility' => [
        'id' => $slug,
        'name' => $name,
        'type' => 'Super Health Center',
        'location' => $location,
        'status' => $status,
    ],
]);
