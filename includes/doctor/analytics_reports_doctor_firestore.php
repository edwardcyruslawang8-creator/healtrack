<?php
/**
 * =====================================================================
 * Analytics & Reports (Doctor) — Data
 * ---------------------------------------------------------------------
 * Scoped to the signed-in doctor's OWN consultations only
 * (assignedPersonnel === their name, the same real field every other
 * doctor page already scopes "my queue" by — see
 * includes/doctor/consultations_doctor_firestore.php) — a doctor's
 * analytics are about their own caseload, not the whole facility's
 * (that's subadmin/analytics_reports.php's job). Real Firestore data
 * throughout: hc_get_all_consultations() is the same shared reader
 * doctor/consultations.php already uses.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore
require_once __DIR__ . '/../firestore/consultations.php';

$myUid = $_SESSION['uid'] ?? '';
$myDoc = $myUid !== '' ? $firestore->getDocument('users', $myUid) : null;
$myName = $_SESSION['name'] ?? '';

$doctorProfile = [
    'name'     => $myName ?: 'Doctor',
    'role'     => 'Doctor',
    'facility' => $myDoc['facility'] ?? 'North Super Health Center',
];

$dashboardDateLabel = date('F j, Y');
$analyticsDateRangeLabel = 'Last 14 Days';

$typeMeta = hc_consultation_type_meta();
$statusMeta = hc_consultation_status_meta();

$allConsultations = hc_get_all_consultations();
$myConsultations = array_values(array_filter($allConsultations, fn ($c) => $c['assignedPersonnel'] === $myName));

// ---------------------------------------------------------------------
// KPI cards
// ---------------------------------------------------------------------
$thisMonthPrefix = date('Y-m');
$completedThisMonth = count(array_filter($myConsultations, fn ($c) => $c['status'] === 'completed' && str_starts_with($c['dateTime'], $thisMonthPrefix)));
$pendingCount = count(array_filter($myConsultations, fn ($c) => $c['status'] === 'pending'));
$followUpsScheduled = count(array_filter($myConsultations, fn ($c) => ($c['nextFollowUp'] ?? '') !== ''));

$kpiCards = [
    ['icon' => 'bi-calendar2-heart-fill', 'iconBg' => '#FCE4E4', 'iconColor' => '#D93025', 'label' => 'My Total Consultations', 'value' => count($myConsultations), 'sub' => 'All-time',       'trend' => 'up'],
    ['icon' => 'bi-check-circle-fill',    'iconBg' => '#E4F7EA', 'iconColor' => '#1E8E3E', 'label' => 'Completed This Month',   'value' => $completedThisMonth,      'sub' => date('F Y'),    'trend' => 'up'],
    ['icon' => 'bi-hourglass-split',      'iconBg' => '#FEF3C7', 'iconColor' => '#B45309', 'label' => 'Pending',                'value' => $pendingCount,             'sub' => 'Awaiting',     'trend' => 'up'],
    ['icon' => 'bi-arrow-repeat',         'iconBg' => '#E8F0FE', 'iconColor' => '#1A73E8', 'label' => 'Follow-ups Scheduled',   'value' => $followUpsScheduled,       'sub' => 'Upcoming',     'trend' => 'up'],
];

// ---------------------------------------------------------------------
// Trend — my consultations over the last 14 days, by dateTime's date.
// ---------------------------------------------------------------------
$trendLabels = [];
$trendCounts = [];
for ($i = 13; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-$i days"));
    $trendLabels[] = date('M j', strtotime($day));
    $trendCounts[] = count(array_filter($myConsultations, fn ($c) => substr($c['dateTime'], 0, 10) === $day));
}

// ---------------------------------------------------------------------
// Consultations by Type (donut)
// ---------------------------------------------------------------------
$byType = [];
foreach ($myConsultations as $c) {
    $byType[$c['consultationType']] = ($byType[$c['consultationType']] ?? 0) + 1;
}
$consultationsByType = [];
foreach ($typeMeta as $key => $meta) {
    if (($byType[$key] ?? 0) > 0) {
        $consultationsByType[] = ['label' => $meta['label'], 'value' => $byType[$key], 'color' => $meta['color']];
    }
}
$consultationsByTypeTotal = array_sum(array_column($consultationsByType, 'value'));

// ---------------------------------------------------------------------
// Consultations by Status (donut)
// ---------------------------------------------------------------------
$byStatus = [];
foreach ($myConsultations as $c) {
    $byStatus[$c['status']] = ($byStatus[$c['status']] ?? 0) + 1;
}
$consultationsByStatus = [];
foreach ($statusMeta as $key => $meta) {
    if (($byStatus[$key] ?? 0) > 0) {
        $consultationsByStatus[] = ['label' => $meta['label'], 'value' => $byStatus[$key], 'color' => $meta['color']];
    }
}
$consultationsByStatusTotal = array_sum(array_column($consultationsByStatus, 'value'));

// ---------------------------------------------------------------------
// Top 5 Diagnoses — real "diagnosis" text api/complete-consultation.php
// writes once a doctor Saves & Ends a consultation. Free text, so
// grouped by exact (trimmed) match rather than a fixed catalog — a
// real distribution over whatever this doctor has actually typed.
// ---------------------------------------------------------------------
$diagnosisCounts = [];
foreach ($myConsultations as $c) {
    $dx = trim((string) ($c['diagnosis'] ?? ''));
    if ($dx === '') {
        continue;
    }
    $diagnosisCounts[$dx] = ($diagnosisCounts[$dx] ?? 0) + 1;
}
arsort($diagnosisCounts);
$topDiagnoses = [];
foreach (array_slice($diagnosisCounts, 0, 5, true) as $label => $value) {
    $topDiagnoses[] = ['label' => $label, 'value' => $value];
}
$topDiagnosesMax = $topDiagnoses !== [] ? max(array_column($topDiagnoses, 'value')) : 1;
