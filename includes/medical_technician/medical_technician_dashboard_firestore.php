<?php
/**
 * =====================================================================
 * Medical Technologist Dashboard — Data
 * ---------------------------------------------------------------------
 * CONNECTED to real Firestore data — every stat card and chart below
 * is computed from the actual lab_results docs (via
 * includes/firestore/lab_results.php's hc_get_all_lab_result_docs(),
 * the same function medical_technician/laboratory_xray.php uses),
 * joined with real patient names via includes/firestore/patients.php.
 * $staffProfile is real too, same as every other role's dashboard.
 * X-ray is NOT included — that's radio_technologist's own dashboard
 * now (includes/radiologic_technologist/radiologic_technologist_dashboard_firestore.php).
 *
 * With only a handful of real seed lab docs right now, these numbers
 * are genuinely small (not the rounder placeholder figures this file
 * used to have) — that's expected given the current Firestore data,
 * not a bug. Same tradeoff already made for
 * medical_technician/laboratory_xray.php.
 *
 * Two things are still NOT modeled anywhere in Firestore and stay
 * clearly-labeled placeholders rather than being faked real:
 *   - Specimen & Equipment Alerts — there's no specimen-tracking or
 *     equipment-maintenance collection in this app. Replaced with a
 *     genuinely real "Recently Ordered Tests" feed instead of keeping
 *     fabricated alert rows.
 *   - Recent Notifications — deliberately left as-is (still static
 *     placeholder content, matching medical_technician/notifications.php's
 *     own placeholder rows) — connecting this needs a real
 *     "notifications" collection, a separate, bigger decision not
 *     covered by this pass.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore
require_once __DIR__ . '/../firestore/patients.php';
require_once __DIR__ . '/../firestore/lab_results.php';
require_once __DIR__ . '/../shared/query_helpers.php'; // hc_url_with_params(), hc_matches_date_range_key()

$myUid = $_SESSION['uid'] ?? '';
$myDoc = $myUid !== '' ? $firestore->getDocument('users', $myUid) : null;

$staffProfile = [
    'name'     => $_SESSION['name'] ?? 'Medical Technologist',
    'role'     => 'Medical Technologist',
    'facility' => $myDoc['facility'] ?? 'North Super Health Center',
];

$dashboardDateLabel = date('F j, Y');

// Facility selector — mirrors every other role dashboard's names.
$facilityOptions = ['North Super Health Center', 'East Super Health Center', 'West Super Health Center'];

// ---------------------------------------------------------------------
// Patient lookup — id -> name, for joining onto each request below
// (same approach as includes/medical_technician/laboratory_xray_firestore.php).
// ---------------------------------------------------------------------
$patientsById = [];
foreach (hc_get_all_patients_summary() as $p) {
    $patientsById[$p['id']] = $p;
}

// ---------------------------------------------------------------------
// Build the request list — every real lab_results doc across every
// patient, same shape/source as laboratory_xray.php. X-ray is NOT
// included here — that's radio_technologist's own dashboard now.
// ---------------------------------------------------------------------
$allRequests = [];

foreach (hc_get_all_lab_result_docs() as $doc) {
    $f = $doc['fields'];
    $patient = $patientsById[$f['patientId'] ?? ''] ?? null;
    $allRequests[] = [
        'id' => $doc['id'],
        'type' => 'Lab',
        'patientName' => $patient['name'] ?? 'Unknown Patient',
        'examination' => $f['testName'] ?? '—',
        'orderedBy' => $f['orderedBy'] ?? '—',
        'orderedDateRaw' => $f['orderedDate'] ?? '',
        'orderedDate' => isset($f['orderedDate']) ? date('M j, Y', strtotime($f['orderedDate'])) : '—',
        'orderedTime' => $f['orderedTime'] ?? '—',
        'status' => $f['status'] ?? 'pending',
    ];
}

usort($allRequests, fn ($a, $b) => strcmp($b['orderedDateRaw'], $a['orderedDateRaw']));

// ---------------------------------------------------------------------
// Summary stat cards — real counts, same figures
// medical_technician/laboratory_xray.php's cards use. No separate
// "Laboratory Tests" card — with X-ray gone, that would always equal
// Total Tests Processed, so it's dropped rather than shown redundant.
// ---------------------------------------------------------------------
$totalCount = count($allRequests);
$pendingCount = count(array_filter($allRequests, fn ($r) => $r['status'] === 'pending'));
$completedCount = count(array_filter($allRequests, fn ($r) => $r['status'] === 'completed'));

$statCards = [
    ['icon' => 'bi-clipboard2-pulse-fill', 'iconBg' => '#E8F0FE', 'iconColor' => '#1A73E8', 'label' => 'Total Tests Processed', 'value' => $totalCount],
    ['icon' => 'bi-hourglass-split',       'iconBg' => '#FDECDD', 'iconColor' => '#E8710A', 'label' => 'Pending Results',        'value' => $pendingCount],
    ['icon' => 'bi-check-circle-fill',     'iconBg' => '#E4F7EA', 'iconColor' => '#1E8E3E', 'label' => 'Completed Results',      'value' => $completedCount],
    ['icon' => 'bi-bell-fill',             'iconBg' => '#FCE4E4', 'iconColor' => '#D93025', 'label' => 'Unread Notifications',   'value' => hc_medtech_unread_notification_count(), 'link' => ['label' => 'View all notifications', 'href' => 'notifications.php']],
];

// ---------------------------------------------------------------------
// Tests Processed Trend — line chart. Real counts from each request's
// real orderedDate, bucketed to match the selected range: daily points
// for This Week (last 7 days)/This Month (month-to-date), monthly
// points for This Year (Jan through the current month). With only a
// handful of real docs the spread is sparse rather than a smooth curve
// — that's the real data, not a placeholder.
// ---------------------------------------------------------------------
$testsTrendRangeState = hc_resolve_dashboard_range('trendRange', ['this_week' => 'This Week', 'this_month' => 'This Month', 'this_year' => 'This Year'], 'this_week');
$testsTrendRangeKey = $testsTrendRangeState['key'];
$testsTrendRange = $testsTrendRangeState['label'];

$testsTrend = hc_dashboard_trend_series(
    $allRequests,
    fn ($r) => $r['orderedDateRaw'],
    $testsTrendRangeKey,
    $testsTrendRangeState['customFrom'],
    $testsTrendRangeState['customTo']
);

// ---------------------------------------------------------------------
// Tests by Type — bar chart. Real distinct test/exam names, counted
// only over requests whose real orderedDate falls within the selected
// range (same preset options as the trend chart above).
// ---------------------------------------------------------------------
$testsByTypeRangeState = hc_resolve_dashboard_range('typeRange', ['this_week' => 'This Week', 'this_month' => 'This Month', 'this_year' => 'This Year'], 'this_week');
$testsByTypeRangeKey = $testsByTypeRangeState['key'];
$testsByTypeRange = $testsByTypeRangeState['label'];

$typeRangeRequests = array_filter($allRequests, fn ($r) => hc_matches_date_range_key($r['orderedDateRaw'], $testsByTypeRangeKey, $testsByTypeRangeState['customFrom'], $testsByTypeRangeState['customTo']));

$typeCounts = [];
foreach ($typeRangeRequests as $r) {
    $typeCounts[$r['examination']] = ($typeCounts[$r['examination']] ?? 0) + 1;
}
arsort($typeCounts);
$testsByType = [
    'labels' => array_keys($typeCounts),
    'values' => array_values($typeCounts),
];

// ---------------------------------------------------------------------
// Result Status — doughnut chart. Real Completed/Pending counts —
// "Flagged" dropped since no status value for it exists anywhere in
// lab_results yet (same reasoning as laboratory_xray_firestore.php's
// trimmed status filter options).
// ---------------------------------------------------------------------
$resultStatusTotal = $totalCount;
$resultStatus = array_values(array_filter([
    $completedCount > 0 ? ['label' => 'Completed', 'value' => $completedCount, 'pct' => $totalCount > 0 ? $completedCount / $totalCount * 100 : 0, 'color' => '#1E8E3E'] : null,
    $pendingCount > 0 ? ['label' => 'Pending', 'value' => $pendingCount, 'pct' => $totalCount > 0 ? $pendingCount / $totalCount * 100 : 0, 'color' => '#E8710A'] : null,
]));

// ---------------------------------------------------------------------
// Recently Ordered Tests — replaces the old fabricated "Specimen &
// Equipment Alerts" list (see header comment: no specimen/equipment
// collection exists). Real: the 3 most recently ordered requests.
// ---------------------------------------------------------------------
$recentlyOrderedTests = array_slice(array_map(fn ($r) => [
    'name' => $r['examination'] . ' — ' . $r['patientName'],
    'when' => $r['orderedDate'],
    'time' => $r['orderedBy'],
], $allRequests), 0, 3);

// ---------------------------------------------------------------------
// Pending Tests queue — real, every request with status=pending
// (currently none in the seed data — every real record is completed).
// ---------------------------------------------------------------------
$pendingTestsQueue = array_values(array_filter($allRequests, fn ($r) => $r['status'] === 'pending'));

$testStatusMeta = [
    'completed'   => ['label' => 'Completed',   'bg' => '#E4F7EA', 'color' => '#1E8E3E'],
    'in_progress' => ['label' => 'In Progress', 'bg' => '#E8F0FE', 'color' => '#1A73E8'],
    'pending'     => ['label' => 'Pending',     'bg' => '#FDECDD', 'color' => '#E8710A'],
];
$testTypeMeta = [
    'Lab' => ['bg' => '#EEF0F3', 'color' => '#6B7280'],
];

// ---------------------------------------------------------------------
// Recent Notifications — deliberately left as static placeholder
// content (see header comment) — connecting this needs a real
// "notifications" collection, a separate decision.
// ---------------------------------------------------------------------
$recentNotifications = [
    ['icon' => 'bi-exclamation-triangle-fill', 'iconBg' => '#FCE4E4', 'iconColor' => '#D93025', 'title' => 'Specimen needs pickup',   'desc' => 'Blood sample — Patient PT-000103',        'timeAgo' => '20 minutes ago'],
    ['icon' => 'bi-droplet-fill',              'iconBg' => '#E8F0FE', 'iconColor' => '#1A73E8', 'title' => 'New lab order received',  'desc' => 'Ordered by Dr. Ana Reyes',                'timeAgo' => '50 minutes ago'],
    ['icon' => 'bi-check-circle-fill',         'iconBg' => '#E4F7EA', 'iconColor' => '#1E8E3E', 'title' => 'Result reviewed',         'desc' => 'Lipid Profile — Ana Reyes',               'timeAgo' => '3 hours ago'],
    ['icon' => 'bi-clipboard2-pulse-fill',     'iconBg' => '#FDECDD', 'iconColor' => '#E8710A', 'title' => 'Test result flagged',     'desc' => 'CBC — Juan Dela Cruz, abnormal reading',  'timeAgo' => '4 hours ago'],
];

// ---------------------------------------------------------------------
// Pending Laboratory Results — real, same rows as $pendingTestsQueue
// above, shaped for the bottom table (currently empty — no real
// pending records right now, which is honest given the seed data).
// ---------------------------------------------------------------------
$pendingResults = array_map(fn ($r) => [
    'id' => $r['id'],
    'test' => $r['examination'],
    'patient' => $r['patientName'],
    'type' => $r['type'],
    'orderedBy' => $r['orderedBy'],
    'dueDate' => $r['orderedDate'],
], $pendingTestsQueue);
