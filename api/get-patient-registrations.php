<?php
/**
 * =====================================================================
 * Get Patient Registrations (api/get-patient-registrations.php)
 * ---------------------------------------------------------------------
 * Backs subadmin/patient_reports.php's "New Patient Registrations"
 * card — its search box, Barangay filter, Date Registered filter,
 * sortable columns, and pagination all refresh through here via
 * includes/shared/patient_registrations_query.php's own
 * hc_query_patient_registrations(), the SAME function the page's own
 * default render calls, so this never drifts from what the page itself
 * would show.
 *
 * Locked to THIS sub-admin's own real facility (their users/{uid}.facility
 * field) — never trusted from the query string, same boundary every
 * other sub-admin report page/endpoint in this app already enforces.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../includes/shared/patient_registrations_query.php';

header('Content-Type: application/json');

if (($_SESSION['role'] ?? null) !== 'subadmin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Not authorized.']);
    exit;
}

$myUid = $_SESSION['uid'] ?? '';
$myDoc = $myUid !== '' ? $firestore->getDocument('users', $myUid) : null;
$facilityName = (string) ($myDoc['facility'] ?? '');
if ($facilityName === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'No facility assigned to this account.']);
    exit;
}

$daysRaw = trim((string) ($_GET['days'] ?? ''));
$days = in_array($daysRaw, ['7', '30', '90'], true) ? (int) $daysRaw : null;

$result = hc_query_patient_registrations($facilityName, [
    'q' => (string) ($_GET['q'] ?? ''),
    'barangay' => (string) ($_GET['barangay'] ?? ''),
    'days' => $days,
    'sort' => (string) ($_GET['sort'] ?? 'dateRegistered'),
    'dir' => (string) ($_GET['dir'] ?? 'desc'),
    'page' => (int) ($_GET['page'] ?? 1),
]);

echo json_encode(['success' => true] + $result);
