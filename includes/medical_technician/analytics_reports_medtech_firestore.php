<?php
/**
 * =====================================================================
 * Analytics & Reports (Medical Technologist) — Data
 * ---------------------------------------------------------------------
 * Facility-wide over the real "lab_results" collection
 * (hc_get_all_lab_result_docs(), the same shared reader
 * lab_xray_consultation.php and laboratory_xray.php already use) —
 * unlike doctor's/medical-staff's own analytics pages, this is NOT
 * scoped to "tests I personally completed": the collection has never
 * recorded who completed a given test (see api/complete-lab-result.php
 * etc. — completedDate/completedTime are written, no completedBy field
 * exists anywhere), so a per-technician split would have to be
 * fabricated. This shows the same real, honestly-scoped numbers every
 * technician at this facility would see. X-ray is NOT included here —
 * that's radiologic_technologist/analytics_reports.php's own page now.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore
require_once __DIR__ . '/../firestore/patients.php';
require_once __DIR__ . '/../firestore/lab_results.php';
require_once __DIR__ . '/../shared/analytics_comparison.php'; // hc_facility_growth_pct()

$myUid = $_SESSION['uid'] ?? '';
$myDoc = $myUid !== '' ? $firestore->getDocument('users', $myUid) : null;

$staffProfile = [
    'name'     => $_SESSION['name'] ?? 'Medical Technologist',
    'role'     => 'Medical Technologist',
    'facility' => $myDoc['facility'] ?? 'North Super Health Center',
];

$dashboardDateLabel = date('F j, Y');
$analyticsDateRangeLabel = 'Last 14 Days';

$labDocs = hc_get_all_lab_result_docs();

$completedLab = array_values(array_filter($labDocs, fn ($d) => ($d['fields']['status'] ?? '') === 'completed'));
$pendingLab = array_values(array_filter($labDocs, fn ($d) => ($d['fields']['status'] ?? '') === 'pending'));

// ---------------------------------------------------------------------
// KPI cards
// ---------------------------------------------------------------------
$thisMonthPrefix = date('Y-m');
$lastMonthPrefix = date('Y-m', strtotime('first day of last month'));
$completedThisMonth = count(array_filter($completedLab, fn ($d) => str_starts_with($d['fields']['completedDate'] ?? '', $thisMonthPrefix)));
$completedLastMonth = count(array_filter($completedLab, fn ($d) => str_starts_with($d['fields']['completedDate'] ?? '', $lastMonthPrefix)));
$completedGrowthPct = hc_facility_growth_pct($completedThisMonth, $completedLastMonth);
$last7DaysPrefix = array_map(fn ($i) => date('Y-m-d', strtotime("-$i days")), range(0, 6));
$completedThisWeek = count(array_filter($completedLab, fn ($d) => in_array($d['fields']['completedDate'] ?? '', $last7DaysPrefix, true)));

$kpiCards = [
    ['icon' => 'bi-clipboard2-pulse-fill', 'iconBg' => '#E4F7EA', 'iconColor' => '#1E8E3E', 'label' => 'Lab Tests Completed',  'value' => count($completedLab),  'sub' => 'All-time', 'trend' => 'up'],
    ['icon' => 'bi-calendar2-week-fill', 'iconBg' => '#E8F0FE', 'iconColor' => '#1A73E8', 'label' => 'Completed This Week', 'value' => $completedThisWeek, 'sub' => 'Last 7 days', 'trend' => 'up'],
    ['icon' => 'bi-hourglass-split',       'iconBg' => '#FEF3C7', 'iconColor' => '#B45309', 'label' => 'Pending Referrals',     'value' => count($pendingLab), 'sub' => 'Laboratory', 'trend' => 'up'],
    ['icon' => 'bi-calendar2-check-fill',  'iconBg' => '#FCE4E4', 'iconColor' => '#D93025', 'label' => 'Completed This Month',  'value' => $completedThisMonth,   'sub' => abs($completedGrowthPct) . '% vs last month', 'trend' => $completedGrowthPct < 0 ? 'down' : 'up'],
];

// ---------------------------------------------------------------------
// Trend — lab tests completed over the last 14 days.
// ---------------------------------------------------------------------
$trendLabels = [];
$trendCounts = [];
for ($i = 13; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-$i days"));
    $trendLabels[] = date('M j', strtotime($day));
    $trendCounts[] = count(array_filter($completedLab, fn ($d) => ($d['fields']['completedDate'] ?? '') === $day));
}

// ---------------------------------------------------------------------
// Completed Tests by Type (donut) — single-category Laboratory now that
// X-ray is radio_technologist's own (same honest-single-slice pattern
// used elsewhere in this app, e.g. a role with only one real status
// value still shows it rather than fabricating a second category).
// ---------------------------------------------------------------------
$testsByType = count($completedLab) > 0 ? [['label' => 'Laboratory', 'value' => count($completedLab), 'color' => '#7C3AED']] : [];
$testsByTypeTotal = array_sum(array_column($testsByType, 'value'));

// ---------------------------------------------------------------------
// Referral Status (donut) — pending vs completed.
// ---------------------------------------------------------------------
$referralStatus = [];
$pendingTotal = count($pendingLab);
$completedTotal = count($completedLab);
if ($pendingTotal > 0) {
    $referralStatus[] = ['label' => 'Pending', 'value' => $pendingTotal, 'color' => '#B45309'];
}
if ($completedTotal > 0) {
    $referralStatus[] = ['label' => 'Completed', 'value' => $completedTotal, 'color' => '#1E8E3E'];
}
$referralStatusTotal = array_sum(array_column($referralStatus, 'value'));

// ---------------------------------------------------------------------
// Top 5 Lab Tests Performed — testName counts among completed lab_results.
// ---------------------------------------------------------------------
$testNameCounts = [];
foreach ($completedLab as $d) {
    $name = trim((string) ($d['fields']['testName'] ?? ''));
    if ($name === '') {
        continue;
    }
    $testNameCounts[$name] = ($testNameCounts[$name] ?? 0) + 1;
}
arsort($testNameCounts);
$topLabTests = [];
foreach (array_slice($testNameCounts, 0, 5, true) as $label => $value) {
    $topLabTests[] = ['label' => $label, 'value' => $value];
}
$topLabTestsMax = $topLabTests !== [] ? max(array_column($topLabTests, 'value')) : 1;
