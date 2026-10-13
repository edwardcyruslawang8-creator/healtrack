<?php
/**
 * =====================================================================
 * Analytics & Reports (Medical Technologist) — Data
 * ---------------------------------------------------------------------
 * Facility-wide over the real "lab_results"/"xray_results" collections
 * (hc_get_all_lab_result_docs()/hc_get_all_xray_result_docs(), the same
 * shared readers lab_xray_consultation.php and laboratory_xray.php
 * already use) — unlike doctor's/medical-staff's own analytics pages,
 * this is NOT scoped to "tests I personally completed": neither
 * collection has ever recorded who completed a given test (see
 * api/complete-lab-result.php etc. — completedDate/completedTime are
 * written, no completedBy field exists anywhere), so a per-technician
 * split would have to be fabricated. This shows the same real,
 * honestly-scoped numbers every technician at this facility would see.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore
require_once __DIR__ . '/../firestore/patients.php';
require_once __DIR__ . '/../firestore/lab_results.php';
require_once __DIR__ . '/../firestore/xray_results.php';

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
$xrayDocs = hc_get_all_xray_result_docs();

$completedLab = array_values(array_filter($labDocs, fn ($d) => ($d['fields']['status'] ?? '') === 'completed'));
$completedXray = array_values(array_filter($xrayDocs, fn ($d) => ($d['fields']['status'] ?? '') === 'completed'));
$pendingLab = array_values(array_filter($labDocs, fn ($d) => ($d['fields']['status'] ?? '') === 'pending'));
$pendingXray = array_values(array_filter($xrayDocs, fn ($d) => ($d['fields']['status'] ?? '') === 'pending'));

// ---------------------------------------------------------------------
// KPI cards
// ---------------------------------------------------------------------
$thisMonthPrefix = date('Y-m');
$completedThisMonth = count(array_filter(array_merge($completedLab, $completedXray), fn ($d) => str_starts_with($d['fields']['completedDate'] ?? '', $thisMonthPrefix)));

$kpiCards = [
    ['icon' => 'bi-clipboard2-pulse-fill', 'iconBg' => '#E4F7EA', 'iconColor' => '#1E8E3E', 'label' => 'Lab Tests Completed',  'value' => count($completedLab),  'sub' => 'All-time', 'trend' => 'up'],
    ['icon' => 'bi-file-earmark-medical-fill', 'iconBg' => '#E8F0FE', 'iconColor' => '#1A73E8', 'label' => 'X-ray Exams Completed', 'value' => count($completedXray), 'sub' => 'All-time', 'trend' => 'up'],
    ['icon' => 'bi-hourglass-split',       'iconBg' => '#FEF3C7', 'iconColor' => '#B45309', 'label' => 'Pending Referrals',     'value' => count($pendingLab) + count($pendingXray), 'sub' => 'Lab + X-ray', 'trend' => 'up'],
    ['icon' => 'bi-calendar2-check-fill',  'iconBg' => '#FCE4E4', 'iconColor' => '#D93025', 'label' => 'Completed This Month',  'value' => $completedThisMonth,   'sub' => date('F Y'), 'trend' => 'up'],
];

// ---------------------------------------------------------------------
// Trend — tests completed (lab + x-ray combined) over the last 14 days.
// ---------------------------------------------------------------------
$allCompleted = array_merge($completedLab, $completedXray);
$trendLabels = [];
$trendCounts = [];
for ($i = 13; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-$i days"));
    $trendLabels[] = date('M j', strtotime($day));
    $trendCounts[] = count(array_filter($allCompleted, fn ($d) => ($d['fields']['completedDate'] ?? '') === $day));
}

// ---------------------------------------------------------------------
// Completed Tests by Type (donut) — Laboratory vs X-ray.
// ---------------------------------------------------------------------
$testsByType = [];
if (count($completedLab) > 0) {
    $testsByType[] = ['label' => 'Laboratory', 'value' => count($completedLab), 'color' => '#7C3AED'];
}
if (count($completedXray) > 0) {
    $testsByType[] = ['label' => 'X-ray', 'value' => count($completedXray), 'color' => '#D93025'];
}
$testsByTypeTotal = array_sum(array_column($testsByType, 'value'));

// ---------------------------------------------------------------------
// Referral Status (donut) — pending vs completed, across both.
// ---------------------------------------------------------------------
$referralStatus = [];
$pendingTotal = count($pendingLab) + count($pendingXray);
$completedTotal = count($completedLab) + count($completedXray);
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
