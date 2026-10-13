<?php
/**
 * =====================================================================
 * Consultations (Medical Staff) — Data
 * ---------------------------------------------------------------------
 * CONNECTED — reads the real "consultations" Firestore collection via
 * includes/firestore/consultations.php (this app's first real consultations
 * model; every earlier version of this page was front-end only). Stat
 * cards, the search/type/status/date-range filters, and pagination all
 * operate on real data. $patientOptions (for the "Set Consultation"
 * modal's patient picker) comes from the same real "patients"
 * collection every Patient Management page reads.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore
require_once __DIR__ . '/../firestore/patients.php';
require_once __DIR__ . '/../firestore/consultations.php';
require_once __DIR__ . '/../shared/fuzzy_search.php'; // hc_fuzzy_match()

$myUid = $_SESSION['uid'] ?? '';
$myDoc = $myUid !== '' ? $firestore->getDocument('users', $myUid) : null;

$staffProfile = [
    'name'     => $_SESSION['name'] ?? 'Medical Staff',
    'role'     => 'Medical Staff',
    'facility' => $myDoc['facility'] ?? 'North Super Health Center',
];

$dashboardDateLabel = date('F j, Y');
$facilityOptions = ['North Super Health Center', 'East Super Health Center', 'West Super Health Center'];

// ---------------------------------------------------------------------
// Filter toolbar option lists
// ---------------------------------------------------------------------
$consultationTypeMeta = hc_consultation_type_meta();
$consultationStatusMeta = hc_consultation_status_meta();
$consultationTypeOptions = ['All Types' => 'All Types'];
foreach ($consultationTypeMeta as $key => $meta) {
    $consultationTypeOptions[$key] = $meta['label'];
}
$consultationStatusOptions = ['All Status' => 'All Status'];
foreach ($consultationStatusMeta as $key => $meta) {
    $consultationStatusOptions[$key] = $meta['label'];
}

// Patients for the "Set Consultation" / "Edit" modal's patient picker,
// plus an id-keyed lookup so the table rows below can attach each
// consultation's patient gender/age/birthDate (for the Edit modal's
// patient card) without an O(n*m) search per row.
$patientOptions = hc_get_all_patients_summary();
$patientsById = array_column($patientOptions, null, 'id');

// Real doctor accounts at THIS staff member's own facility for the
// "Assigned Physician" dropdown — not a fabricated name list, and not
// healthcare_staff accounts either (see hc_get_physician_options()'s
// header comment).
$physicianOptions = hc_get_physician_options($staffProfile['facility']);
$consultationPriorityMeta = hc_consultation_priority_meta();

// ---------------------------------------------------------------------
// Real consultations + real stat cards.
// ---------------------------------------------------------------------
$allConsultations = hc_get_all_consultations();
$statCards = hc_consultation_stats($allConsultations);

// ---------------------------------------------------------------------
// Search (?q= matches patient name or consultation code) + type/status
// filters (?type=, ?status=) + date range (?from=, ?to=) — all real.
// ---------------------------------------------------------------------
$searchTerm = trim($_GET['q'] ?? '');
$typeFilter = $_GET['type'] ?? 'All Types';
$statusFilter = $_GET['status'] ?? 'All Status';
$dateFrom = trim($_GET['from'] ?? '');
$dateTo = trim($_GET['to'] ?? '');

$filteredConsultations = array_values(array_filter($allConsultations, function ($c) use ($searchTerm, $typeFilter, $statusFilter, $dateFrom, $dateTo) {
    if ($searchTerm !== '' && !hc_fuzzy_match($c['patientName'] . ' ' . $c['consultationCode'] . ' ' . $c['patientId'], $searchTerm)) {
        return false;
    }
    if ($typeFilter !== 'All Types' && $c['consultationType'] !== $typeFilter) {
        return false;
    }
    if ($statusFilter !== 'All Status' && $c['status'] !== $statusFilter) {
        return false;
    }
    $consultDate = substr($c['dateTime'], 0, 10);
    if ($dateFrom !== '' && $consultDate < $dateFrom) {
        return false;
    }
    if ($dateTo !== '' && $consultDate > $dateTo) {
        return false;
    }
    return true;
}));

// ---------------------------------------------------------------------
// Pagination
// ---------------------------------------------------------------------
$consultationsTotal = count($filteredConsultations);
$consultationsPerPage = 5;
$consultationsCurrentPage = max(1, (int) ($_GET['page'] ?? 1));
$consultationsTotalPages = max(1, (int) ceil($consultationsTotal / $consultationsPerPage));
$consultationsCurrentPage = min($consultationsCurrentPage, $consultationsTotalPages);

$consultations = array_slice($filteredConsultations, ($consultationsCurrentPage - 1) * $consultationsPerPage, $consultationsPerPage);
