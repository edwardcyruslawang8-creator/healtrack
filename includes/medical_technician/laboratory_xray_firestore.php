<?php
/**
 * =====================================================================
 * Laboratory Processing (Medical Technologist) — Data
 * ---------------------------------------------------------------------
 * Unlike medical-staff/subadmin's patient_laboratory_xray.php (a single
 * PATIENT's own lab history, reached from their profile), this is a
 * technician-facing QUEUE across every patient — every real
 * lab_results/{id} doc (hc_get_all_lab_result_docs() in
 * includes/firestore/lab_results.php), joined with each doc's patient
 * name/age via includes/firestore/patients.php. X-ray is deliberately
 * NOT included here — that's radio_technologist's own queue/role now
 * (includes/radiologic_technologist/xray_consultation_firestore.php), kept
 * separate so the same exam is never processed from two different
 * roles' screens.
 *
 * REAL for everything the schema actually models: patient, examination
 * name, ordered by, date/time, and status (only "pending"/"completed"
 * exist as real status values right now — there's no "in_progress" or
 * "cancelled" doc anywhere yet, so those aren't offered as filter
 * options rather than kept as dead choices that never match anything,
 * same principle as includes/subadmin/user_management_firestore.php's
 * trimmed role list).
 *
 * "Priority" is the real priority lane (Pregnant/PWD/Senior, or None)
 * of the consultation each test was ordered from — lab_results docs
 * have no priority field of their own; see
 * hc_referral_priority_resolver() in includes/firestore/consultations.php.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore
require_once __DIR__ . '/../firestore/patients.php';
require_once __DIR__ . '/../firestore/lab_results.php';
require_once __DIR__ . '/../firestore/consultations.php'; // hc_referral_priority_resolver()

$myUid = $_SESSION['uid'] ?? '';
$myDoc = $myUid !== '' ? $firestore->getDocument('users', $myUid) : null;

$staffProfile = [
    'name'     => $_SESSION['name'] ?? 'Medical Technologist',
    'role'     => 'Medical Technologist',
    'facility' => $myDoc['facility'] ?? 'North Super Health Center',
];

$dashboardDateLabel = date('F j, Y');
$facilityOptions = ['North Super Health Center', 'East Super Health Center', 'West Super Health Center'];

// ---------------------------------------------------------------------
// Patient lookup — id -> name/age, for joining onto each request row.
// ---------------------------------------------------------------------
$patientsById = [];
foreach (hc_get_all_patients_summary() as $p) {
    $patientsById[$p['id']] = $p;
}

/** Light badge background for a lab_results doc's real resultBadgeColor accent. */
function hc_medtech_badge_bg_for_color(string $color): string
{
    return match ($color) {
        '#1E8E3E' => '#E4F7EA',
        '#E8710A' => '#FDECDD',
        '#D93025' => '#FCE4E4',
        '#1A73E8' => '#E8F0FE',
        '#7C3AED' => '#EFE7FB',
        default => '#EEF0F3',
    };
}

$statusMeta = [
    'completed'   => ['label' => 'Completed',   'bg' => '#E4F7EA', 'color' => '#1E8E3E'],
    'pending'     => ['label' => 'Pending',     'bg' => '#FDECDD', 'color' => '#E8710A'],
    'in_progress' => ['label' => 'In Progress', 'bg' => '#E8F0FE', 'color' => '#1A73E8'],
    'cancelled'   => ['label' => 'Cancelled',   'bg' => '#FCE4E4', 'color' => '#D93025'],
];

// ---------------------------------------------------------------------
// Build the request queue — real lab_results docs across every patient.
// ---------------------------------------------------------------------
$allRequests = [];
$resolvePriority = hc_referral_priority_resolver();

foreach (hc_get_all_lab_result_docs() as $doc) {
    $f = $doc['fields'];
    $patient = $patientsById[$f['patientId'] ?? ''] ?? null;
    $priority = $resolvePriority($f);
    $allRequests[] = [
        'id' => $doc['id'],
        'type' => 'lab',
        'typeLabel' => 'Laboratory',
        'patientId' => $f['patientId'] ?? '',
        'patientName' => $patient['name'] ?? 'Unknown Patient',
        'patientAge' => $patient['age'] ?? 0,
        'examination' => $f['testName'] ?? '—',
        'requestedBy' => $f['orderedBy'] ?? '—',
        'orderedDateRaw' => $f['orderedDate'] ?? '',
        'date' => isset($f['orderedDate']) ? date('M j, Y', strtotime($f['orderedDate'])) : '—',
        'time' => $f['orderedTime'] ?? '—',
        'status' => $f['status'] ?? 'pending',
        'priority' => $priority,
        'hasReport' => ($f['status'] ?? '') === 'completed',
        'resultBadge' => isset($f['resultBadgeLabel'])
            ? ['label' => $f['resultBadgeLabel'], 'bg' => hc_medtech_badge_bg_for_color($f['resultBadgeColor'] ?? '#6B7280'), 'color' => $f['resultBadgeColor'] ?? '#6B7280']
            : null,
    ];
}

usort($allRequests, fn ($a, $b) => strcmp($b['orderedDateRaw'], $a['orderedDateRaw']));

// ---------------------------------------------------------------------
// Summary cards — real counts over $allRequests.
// ---------------------------------------------------------------------
$requestsSummary = [
    'total' => count($allRequests),
    'pending' => count(array_filter($allRequests, fn ($r) => $r['status'] === 'pending')),
    'completed' => count(array_filter($allRequests, fn ($r) => $r['status'] === 'completed')),
    'labCount' => count(array_filter($allRequests, fn ($r) => $r['type'] === 'lab')),
];

// ---------------------------------------------------------------------
// Tabs — All Requests / Laboratory (?tab=). X-ray dropped — that's
// radio_technologist's own queue now, never medtech's.
// ---------------------------------------------------------------------
$activeTab = $_GET['tab'] ?? 'all';
if (!in_array($activeTab, ['all', 'lab'], true)) {
    $activeTab = 'all';
}

$tabFilteredRequests = $activeTab === 'all'
    ? $allRequests
    : array_values(array_filter($allRequests, fn ($r) => $r['type'] === $activeTab));

// ---------------------------------------------------------------------
// Search (?q=) + Status (?status=) + Priority (?priority=) + Date
// range (?from=, ?to=) filters — all real over $tabFilteredRequests.
// Status/Priority options are trimmed to values that actually appear
// in $allRequests (see header comment).
// ---------------------------------------------------------------------
$statusOptions = ['All Status', 'Pending', 'Completed'];
$priorityOptions = hc_referral_priority_filter_options();

$searchTerm = trim($_GET['q'] ?? '');
$statusFilter = $_GET['status'] ?? 'All Status';
$priorityFilter = $_GET['priority'] ?? 'All Priority';
if (!in_array($priorityFilter, $priorityOptions, true)) {
    $priorityFilter = 'All Priority'; // e.g. an old bookmarked ?priority=Routine from the placeholder days
}
$dateFrom = $_GET['from'] ?? '';
$dateTo = $_GET['to'] ?? '';

$filteredRequests = array_values(array_filter($tabFilteredRequests, function ($r) use ($searchTerm, $statusFilter, $priorityFilter, $dateFrom, $dateTo, $statusMeta) {
    if ($searchTerm !== '' && stripos($r['patientName'] . ' ' . $r['patientId'] . ' ' . $r['id'], $searchTerm) === false) {
        return false;
    }
    if ($statusFilter !== 'All Status' && ($statusMeta[$r['status']]['label'] ?? '') !== $statusFilter) {
        return false;
    }
    if ($priorityFilter !== 'All Priority' && $r['priority']['label'] !== $priorityFilter) {
        return false;
    }
    if ($dateFrom !== '' && $r['orderedDateRaw'] !== '' && $r['orderedDateRaw'] < $dateFrom) {
        return false;
    }
    if ($dateTo !== '' && $r['orderedDateRaw'] !== '' && $r['orderedDateRaw'] > $dateTo) {
        return false;
    }
    return true;
}));

// ---------------------------------------------------------------------
// Pagination — real total/page-count from the filtered result above.
// ---------------------------------------------------------------------
$requestsTotal = count($filteredRequests);
$requestsPerPage = 8;
$requestsCurrentPage = max(1, (int) ($_GET['page'] ?? 1));
$requestsTotalPages = max(1, (int) ceil($requestsTotal / $requestsPerPage));
$requestsCurrentPage = min($requestsCurrentPage, $requestsTotalPages);

$requests = array_slice($filteredRequests, ($requestsCurrentPage - 1) * $requestsPerPage, $requestsPerPage);

$requestsPaginationWindow = [];
if ($requestsTotalPages <= 5) {
    for ($p = 1; $p <= $requestsTotalPages; $p++) {
        $requestsPaginationWindow[] = $p;
    }
} else {
    $requestsPaginationWindow = [1, 2, 3, '...', $requestsTotalPages];
}

// ---------------------------------------------------------------------
// Recent Laboratory Results — newest completed lab docs. Badge is the
// REAL resultBadgeLabel/resultBadgeColor already on each lab_results
// doc (e.g. "Within Normal Range", "Slightly Elevated") — unlike
// x-ray's inferred one below, lab_results already models this
// structured field (see includes/firestore/lab_results.php).
// ---------------------------------------------------------------------
$recentLabResults = array_values(array_filter($allRequests, fn ($r) => $r['type'] === 'lab' && $r['hasReport']));
$recentLabResults = array_slice($recentLabResults, 0, 5);
