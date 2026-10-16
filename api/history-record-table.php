<?php
/**
 * =====================================================================
 * Patient History — Record History table, AJAX refresh (api/history-record-table.php)
 * ---------------------------------------------------------------------
 * Called by assets/js/patient-history-filters.js on doctor/history.php,
 * medical-staff/history.php, and subadmin/history.php whenever the
 * Record Type/Date Range filter changes, or a pagination link inside
 * #hisRecordTableWrap is clicked — instead of following the filter
 * form's/pagination link's own GET navigation (a full page reload),
 * the client intercepts it, fetches the exact same query here, and
 * swaps in just the returned HTML. Same "shared fragment, two callers"
 * pattern api/doctor-queue-status.php already established.
 *
 * Reuses each role's OWN real history_*_firestore.php data file as-is —
 * same $_GET-driven filtering/pagination/facility access gate a full
 * page load already runs (see that file's own header comment), so this
 * can never drift from what a full reload would have shown for the
 * same query. Role picks WHICH data file, since each bakes in its own
 * facility-scoped gate — same per-role split this app's other
 * patient-profile tabs already have, just addressed from one shared
 * endpoint instead of three near-identical ones.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php';

header('Content-Type: application/json');

$role = $_SESSION['role'] ?? null;
$dataFile = match ($role) {
    'doctor' => __DIR__ . '/../includes/doctor/history_doctor_firestore.php',
    'healthcare_staff' => __DIR__ . '/../includes/medical-staff/history_medstaff_firestore.php',
    'subadmin' => __DIR__ . '/../includes/subadmin/history_firestore.php',
    default => null,
};

if ($dataFile === null) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Not authorized.']);
    exit;
}

require $dataFile;
// $recordHistory, $consultationTypeMeta, $recordHistoryTotal,
// $recordHistoryCurrentPage, $recordHistoryPerPage, $recordHistoryTotalPages
// — same "blank the id, render as not-found" gate every other patient-
// profile tab AJAX/page already relies on if $_GET['id'] doesn't
// resolve to a patient this signed-in user can actually see.

ob_start();
require __DIR__ . '/../includes/shared/_history_record_table.php';
$html = ob_get_clean();

echo json_encode(['success' => true, 'html' => $html]);
