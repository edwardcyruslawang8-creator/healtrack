<?php
/**
 * =====================================================================
 * Analytics & Reports (Radiologic Technologist) — Data
 * ---------------------------------------------------------------------
 * Facility-wide over the real "xray_results" collection
 * (hc_get_all_xray_result_docs(), the same shared reader
 * xray_consultation.php uses) — unlike doctor's/medical-staff's own
 * analytics pages, this is NOT scoped to "exams I personally
 * completed": xray_results never records who completed a given exam
 * (see api/complete-xray-result.php — completedDate/completedTime are
 * written, no completedBy field exists), so a per-technologist split
 * would have to be fabricated. This shows the same real, honestly-
 * scoped numbers every radiologic technologist at this facility would see.
 * Modeled directly on
 * includes/medical_technician/analytics_reports_medtech_firestore.php.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore
require_once __DIR__ . '/../firestore/patients.php';
require_once __DIR__ . '/../firestore/xray_results.php';

$myUid = $_SESSION['uid'] ?? '';
$myDoc = $myUid !== '' ? $firestore->getDocument('users', $myUid) : null;

$staffProfile = [
    'name'     => $_SESSION['name'] ?? 'Radiologic Technologist',
    'role'     => 'Radiologic Technologist',
    'facility' => $myDoc['facility'] ?? 'North Super Health Center',
];

$dashboardDateLabel = date('F j, Y');
$analyticsDateRangeLabel = 'Last 14 Days';

$xrayDocs = hc_get_all_xray_result_docs();

$completedXray = array_values(array_filter($xrayDocs, fn ($d) => ($d['fields']['status'] ?? '') === 'completed'));
$pendingXray = array_values(array_filter($xrayDocs, fn ($d) => ($d['fields']['status'] ?? '') === 'pending'));

// ---------------------------------------------------------------------
// KPI cards
// ---------------------------------------------------------------------
$thisMonthPrefix = date('Y-m');
$completedThisMonth = count(array_filter($completedXray, fn ($d) => str_starts_with($d['fields']['completedDate'] ?? '', $thisMonthPrefix)));

$kpiCards = [
    ['icon' => 'bi-file-earmark-medical-fill', 'iconBg' => '#E8F0FE', 'iconColor' => '#1A73E8', 'label' => 'X-ray Exams Completed', 'value' => count($completedXray), 'sub' => 'All-time', 'trend' => 'up'],
    ['icon' => 'bi-hourglass-split',           'iconBg' => '#FEF3C7', 'iconColor' => '#B45309', 'label' => 'Pending Referrals',      'value' => count($pendingXray),     'sub' => 'Awaiting a result', 'trend' => 'up'],
    ['icon' => 'bi-calendar2-check-fill',      'iconBg' => '#FCE4E4', 'iconColor' => '#D93025', 'label' => 'Completed This Month',   'value' => $completedThisMonth,     'sub' => date('F Y'), 'trend' => 'up'],
    ['icon' => 'bi-clipboard2-pulse-fill',     'iconBg' => '#E4F7EA', 'iconColor' => '#1E8E3E', 'label' => 'Total X-ray Exams',      'value' => count($xrayDocs),        'sub' => 'All-time', 'trend' => 'up'],
];

// ---------------------------------------------------------------------
// Trend — exams completed over the last 14 days.
// ---------------------------------------------------------------------
$trendLabels = [];
$trendCounts = [];
for ($i = 13; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-$i days"));
    $trendLabels[] = date('M j', strtotime($day));
    $trendCounts[] = count(array_filter($completedXray, fn ($d) => ($d['fields']['completedDate'] ?? '') === $day));
}

// ---------------------------------------------------------------------
// Referral Status (donut) — pending vs completed.
// ---------------------------------------------------------------------
$referralStatus = [];
$pendingTotal = count($pendingXray);
$completedTotal = count($completedXray);
if ($pendingTotal > 0) {
    $referralStatus[] = ['label' => 'Pending', 'value' => $pendingTotal, 'color' => '#B45309'];
}
if ($completedTotal > 0) {
    $referralStatus[] = ['label' => 'Completed', 'value' => $completedTotal, 'color' => '#1E8E3E'];
}
$referralStatusTotal = array_sum(array_column($referralStatus, 'value'));

// ---------------------------------------------------------------------
// Top 5 X-ray Exams Performed — examName counts among completed exams.
// ---------------------------------------------------------------------
$examNameCounts = [];
foreach ($completedXray as $d) {
    $name = trim((string) ($d['fields']['examName'] ?? ''));
    if ($name === '') {
        continue;
    }
    $examNameCounts[$name] = ($examNameCounts[$name] ?? 0) + 1;
}
arsort($examNameCounts);
$topXrayExams = [];
foreach (array_slice($examNameCounts, 0, 5, true) as $label => $value) {
    $topXrayExams[] = ['label' => $label, 'value' => $value];
}
$topXrayExamsMax = $topXrayExams !== [] ? max(array_column($topXrayExams, 'value')) : 1;
