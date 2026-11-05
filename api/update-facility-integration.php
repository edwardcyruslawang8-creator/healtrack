<?php
/**
 * =====================================================================
 * Update Facility Integration (api/update-facility-integration.php)
 * ---------------------------------------------------------------------
 * Called by assets/js/add-facility.js when an admin flips a facility
 * card's "Integrated with HEALTrack" switch on
 * admin/facility_management.php. Updates ONLY the real
 * facilities/{id} doc's isIntegrated field — never name/location/
 * status/lat/lng (there's still no general Edit Facility feature, see
 * includes/admin/add_facility_modal.php's header comment). This flag
 * alone gates whether that facility's "View Analytics" link is
 * clickable — a facility can be freshly added and pinned without the
 * system actually being set up there yet, and can later be switched
 * off again if it stops being integrated.
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
$id = trim((string) ($body['id'] ?? ''));
$isIntegrated = !empty($body['isIntegrated']);

if ($id === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Missing facility id.']);
    exit;
}

$existing = $firestore->getDocument('facilities', $id);
if ($existing === null) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Facility not found.']);
    exit;
}

// Turning this ON requires a real sub-administrator already assigned to
// this facility (users/{id}.role === 'subadmin', same facility name) —
// they're who actually runs day-to-day consent/account decisions once
// the system is live there. assets/js/add-facility.js checks this same
// rule client-side first (so the toggle never fires a doomed request),
// but it's enforced here too since a client-side-only check can be
// bypassed by calling this endpoint directly.
if ($isIntegrated) {
    $facilityName = $existing['name'] ?? '';
    $hasSubadmin = count(array_filter(
        $firestore->listDocuments('users'),
        fn ($u) => ($u['fields']['role'] ?? '') === 'subadmin' && ($u['fields']['facility'] ?? '') === $facilityName
    )) > 0;
    if (!$hasSubadmin) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'This facility needs a sub-administrator before it can be integrated with HEALTrack.']);
        exit;
    }
}

$saved = $firestore->setDocument('facilities', $id, array_merge($existing, [
    'isIntegrated' => $isIntegrated,
]));

if (!$saved) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not save. Please try again.']);
    exit;
}

$facilityName = $existing['name'] ?? $id;
write_audit_log(
    'updated',
    'Facility Management',
    ($isIntegrated ? 'Marked "' : 'Unmarked "') . $facilityName . '" as integrated with HEALTrack' . ($isIntegrated ? '.' : ' (no longer integrated).')
);

echo json_encode(['success' => true, 'isIntegrated' => $isIntegrated]);
