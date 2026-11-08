<?php
/**
 * =====================================================================
 * Risk Alert History (api/get-risk-alert-history.php)
 * ---------------------------------------------------------------------
 * GET — feeds the "Alert History" modal (assets/js/risk-alert-history.js)
 * on the Predictive & Prescriptive tabs. Read-only.
 *
 * Access mirrors the pages that show the indicators:
 *   - admin:    any real facility, by ?facility_id= (admin/facility_analytics.php)
 *   - subadmin: always their OWN facility (resolved from users/{uid});
 *               any facility_id sent is ignored (subadmin/predictive_analytics.php)
 * Both also see system-wide inventory indicators (inventory has no
 * per-facility field). Returned rows carry no patient information.
 *
 * Query: barangay, riskType, severity, status, from, to (Y-m-d), page.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../includes/shared/facility_resolver.php'; // hc_resolve_own_facility_id(), $facilities
require_once __DIR__ . '/../includes/shared/risk_alerts.php';      // hc_risk_history()

header('Content-Type: application/json');

$role = $_SESSION['role'] ?? null;
if (!in_array($role, ['admin', 'subadmin'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Not authorized.']);
    exit;
}

$facilityId = $role === 'subadmin' ? hc_resolve_own_facility_id() : trim((string) ($_GET['facility_id'] ?? ''));
$facilityName = null;
foreach ($facilities as $f) {
    if ($facilityId !== null && $f['id'] === $facilityId) {
        $facilityName = $f['name'];
        break;
    }
}
if ($facilityName === null || $facilityName === '') {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Facility not found.']);
    exit;
}

// Whitelist every filter — never pass raw input through.
$validDate = fn ($v) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $v) ? (string) $v : '';
$barangayIds = array_map('hc_risk_barangay_id', HC_COVERED_BARANGAYS);
$filters = [
    // The tab the modal was opened from (Disease / Shortage / Replenishment / Barangay).
    'group' => in_array($_GET['group'] ?? '', array_unique(array_column(hc_risk_types(), 'group')), true) ? $_GET['group'] : '',
    'barangay' => in_array($_GET['barangay'] ?? '', $barangayIds, true) ? $_GET['barangay'] : '',
    'riskType' => isset(hc_risk_types()[$_GET['riskType'] ?? '']) ? $_GET['riskType'] : '',
    'severity' => in_array($_GET['severity'] ?? '', ['CRITICAL', 'ALERT', 'ELEVATED', 'WATCH'], true) ? $_GET['severity'] : '',
    'status' => in_array($_GET['status'] ?? '', ['ACTIVE', 'RESOLVED'], true) ? $_GET['status'] : '',
    'from' => $validDate($_GET['from'] ?? ''),
    'to' => $validDate($_GET['to'] ?? ''),
];

$result = hc_risk_history($facilityName, $filters, (int) ($_GET['page'] ?? 1), (int) hc_risk_config()['history_page_size']);
if ($firestore->lastRequestFailed) {
    http_response_code(503);
    echo json_encode(['success' => false, 'error' => 'Could not load alert history right now. Please try again.']);
    exit;
}

echo json_encode(['success' => true, 'facility' => $facilityName] + $result);
