<?php
/**
 * =====================================================================
 * Radio Technologist Dashboard — Data
 * ---------------------------------------------------------------------
 * Landing dashboard for the Radio Technologist role (Firestore role
 * value "radio_technologist" — see assets/js/login.js's routeByRole()).
 * Modeled directly on
 * includes/medical_technician/medical_technician_dashboard_firestore.php
 * (same page structure/stat-card language), narrowed to the real
 * "xray_results" collection only (hc_get_all_xray_result_docs()) — this
 * role never sees laboratory referrals, which stay medical_technician's
 * own. $staffProfile is real, read from the signed-in technologist's
 * own Firestore users/{uid} document.
 *
 * SCOPED TO "MY OWN WORK" where the schema actually allows it:
 * api/complete-xray-result.php now stamps a real completedByUid on
 * every exam it completes, so "My Completed Exams"/"My Completed This
 * Week" and the Exams Processed Trend below are real per-technologist
 * numbers — but only for exams completed AFTER that field started
 * being written. There's no honest way to attribute an exam completed
 * before this change (no completedBy field existed at all), so those
 * stay out of "mine" rather than being guessed at. The Pending queue
 * stays facility-wide/unscoped on purpose — a pending exam has no
 * owner yet; any radio technologist at this facility can pick it up
 * (same shared-inbox model as medical_technician/lab_xray_consultation.php),
 * so there's no real "my pending" to show.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore
require_once __DIR__ . '/../firestore/patients.php';
require_once __DIR__ . '/../firestore/xray_results.php';
require_once __DIR__ . '/../shared/query_helpers.php'; // hc_url_with_params(), hc_matches_date_range_key()

$myUid = $_SESSION['uid'] ?? '';
$myDoc = $myUid !== '' ? $firestore->getDocument('users', $myUid) : null;

$staffProfile = [
    'name'     => $_SESSION['name'] ?? 'Radio Technologist',
    'role'     => 'Radio Technologist',
    'facility' => $myDoc['facility'] ?? 'North Super Health Center',
];

$dashboardDateLabel = date('F j, Y');
$facilityOptions = ['North Super Health Center', 'East Super Health Center', 'West Super Health Center'];

// ---------------------------------------------------------------------
// Patient lookup — id -> name, for joining onto each exam below.
// ---------------------------------------------------------------------
$patientsById = [];
foreach (hc_get_all_patients_summary() as $p) {
    $patientsById[$p['id']] = $p;
}

// ---------------------------------------------------------------------
// Every real xray_results doc across every patient.
// ---------------------------------------------------------------------
$allExams = [];
foreach (hc_get_all_xray_result_docs() as $doc) {
    $f = $doc['fields'];
    $patient = $patientsById[$f['patientId'] ?? ''] ?? null;
    $allExams[] = [
        'id' => $doc['id'],
        'patientName' => $patient['name'] ?? 'Unknown Patient',
        'examination' => $f['examName'] ?? '—',
        'orderedBy' => $f['orderedBy'] ?? '—',
        'orderedDateRaw' => $f['orderedDate'] ?? '',
        'orderedDate' => isset($f['orderedDate']) ? date('M j, Y', strtotime($f['orderedDate'])) : '—',
        'orderedTime' => $f['orderedTime'] ?? '—',
        'status' => $f['status'] ?? 'pending',
        'completedByUid' => $f['completedByUid'] ?? null,
        'completedDateRaw' => $f['completedDate'] ?? '',
    ];
}
usort($allExams, fn ($a, $b) => strcmp($b['orderedDateRaw'], $a['orderedDateRaw']));

// Facility-wide (shared referral inbox — see header comment).
$pendingCount = count(array_filter($allExams, fn ($r) => $r['status'] === 'pending'));

// "Mine" — only exams THIS technologist's own uid completed (see header
// comment for why older, unattributed completions can't be included).
$myCompletedExams = array_values(array_filter($allExams, fn ($r) => $r['status'] === 'completed' && $myUid !== '' && $r['completedByUid'] === $myUid));
$myCompletedCount = count($myCompletedExams);
$last7Days = array_map(fn ($i) => date('Y-m-d', strtotime("-$i days")), range(0, 6));
$myCompletedThisWeekCount = count(array_filter($myCompletedExams, fn ($r) => in_array($r['completedDateRaw'], $last7Days, true)));

$statCards = [
    ['icon' => 'bi-check-circle-fill',         'iconBg' => '#E4F7EA', 'iconColor' => '#1E8E3E', 'label' => 'My Completed Exams',     'value' => $myCompletedCount],
    ['icon' => 'bi-hourglass-split',           'iconBg' => '#FDECDD', 'iconColor' => '#E8710A', 'label' => 'Facility Pending Queue', 'value' => $pendingCount],
    ['icon' => 'bi-calendar2-week-fill',       'iconBg' => '#E8F0FE', 'iconColor' => '#1A73E8', 'label' => 'My Completed This Week', 'value' => $myCompletedThisWeekCount],
    ['icon' => 'bi-bell-fill',                 'iconBg' => '#FCE4E4', 'iconColor' => '#D93025', 'label' => 'Unread Notifications',   'value' => hc_radiotech_unread_notification_count(), 'link' => ['label' => 'View all notifications', 'href' => 'notifications.php']],
];

// ---------------------------------------------------------------------
// Exams Processed Trend — line chart. "My Completed" exams, bucketed by
// completedDate (scoped — see header comment), not orderedDate — this
// is about THIS technologist's own throughput, not facility-wide
// incoming referral volume.
// ---------------------------------------------------------------------
$examsTrendRangeState = hc_resolve_dashboard_range('trendRange', ['this_week' => 'This Week', 'this_month' => 'This Month', 'this_year' => 'This Year'], 'this_week');
$examsTrendRangeKey = $examsTrendRangeState['key'];
$examsTrendRange = $examsTrendRangeState['label'];

$examsTrend = hc_dashboard_trend_series(
    $myCompletedExams,
    fn ($r) => $r['completedDateRaw'],
    $examsTrendRangeKey,
    $examsTrendRangeState['customFrom'],
    $examsTrendRangeState['customTo']
);

// ---------------------------------------------------------------------
// Exams by Type — bar chart. Real distinct exam names, counted only
// over requests whose real orderedDate falls within the selected range.
// ---------------------------------------------------------------------
$examsByTypeRangeState = hc_resolve_dashboard_range('typeRange', ['this_week' => 'This Week', 'this_month' => 'This Month', 'this_year' => 'This Year'], 'this_week');
$examsByTypeRangeKey = $examsByTypeRangeState['key'];
$examsByTypeRange = $examsByTypeRangeState['label'];

$typeRangeExams = array_filter($allExams, fn ($r) => hc_matches_date_range_key($r['orderedDateRaw'], $examsByTypeRangeKey, $examsByTypeRangeState['customFrom'], $examsByTypeRangeState['customTo']));

$typeCounts = [];
foreach ($typeRangeExams as $r) {
    $typeCounts[$r['examination']] = ($typeCounts[$r['examination']] ?? 0) + 1;
}
arsort($typeCounts);
$examsByType = [
    'labels' => array_keys($typeCounts),
    'values' => array_values($typeCounts),
];

// ---------------------------------------------------------------------
// Result Status — doughnut chart. Two real, differently-scoped numbers
// side by side (not pretending they're the same population) — my own
// completed total vs. the shared facility backlog still open to be
// picked up, labeled distinctly so that's clear.
// ---------------------------------------------------------------------
$resultStatusTotal = $myCompletedCount + $pendingCount;
$resultStatus = array_values(array_filter([
    $myCompletedCount > 0 ? ['label' => 'My Completed', 'value' => $myCompletedCount, 'pct' => $resultStatusTotal > 0 ? $myCompletedCount / $resultStatusTotal * 100 : 0, 'color' => '#1E8E3E'] : null,
    $pendingCount > 0 ? ['label' => 'Facility Pending', 'value' => $pendingCount, 'pct' => $resultStatusTotal > 0 ? $pendingCount / $resultStatusTotal * 100 : 0, 'color' => '#E8710A'] : null,
]));

// ---------------------------------------------------------------------
// Recently Ordered Exams — real, the 3 most recently ordered.
// ---------------------------------------------------------------------
$recentlyOrderedExams = array_slice(array_map(fn ($r) => [
    'name' => $r['examination'] . ' — ' . $r['patientName'],
    'when' => $r['orderedDate'],
    'time' => $r['orderedBy'],
], $allExams), 0, 3);

// ---------------------------------------------------------------------
// Pending Exams queue — real, every exam with status=pending.
// ---------------------------------------------------------------------
$pendingExamsQueue = array_values(array_filter($allExams, fn ($r) => $r['status'] === 'pending'));

$examStatusMeta = [
    'completed'   => ['label' => 'Completed',   'bg' => '#E4F7EA', 'color' => '#1E8E3E'],
    'in_progress' => ['label' => 'In Progress', 'bg' => '#E8F0FE', 'color' => '#1A73E8'],
    'pending'     => ['label' => 'Pending',     'bg' => '#FDECDD', 'color' => '#E8710A'],
];

// ---------------------------------------------------------------------
// Pending X-ray Results — real, same rows as $pendingExamsQueue above,
// shaped for the bottom table.
// ---------------------------------------------------------------------
$pendingResults = array_map(fn ($r) => [
    'id' => $r['id'],
    'test' => $r['examination'],
    'patient' => $r['patientName'],
    'orderedBy' => $r['orderedBy'],
    'dueDate' => $r['orderedDate'],
], $pendingExamsQueue);
