<?php
/**
 * =====================================================================
 * Analytics & Reports (Medical Staff) — Data
 * ---------------------------------------------------------------------
 * Scoped to the signed-in staff member's OWN intake work: patients THEY
 * registered (patients/{id}.registeredBy === their uid, the same real
 * field api/create-patient.php already stamps) and consultations THEY
 * set (consultations/{id}.createdBy === their uid, same real field
 * api/create-consultation.php already stamps) — a front-desk staff
 * member's analytics are about their own intake activity, not the
 * whole facility's (that's subadmin/analytics_reports.php's job). Real
 * Firestore data throughout: hc_get_all_patients_summary() and
 * hc_get_all_consultations() are the same shared readers
 * patient_management.php/consultations.php already use.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore
require_once __DIR__ . '/../firestore/patients.php';
require_once __DIR__ . '/../firestore/consultations.php';
require_once __DIR__ . '/../shared/analytics_comparison.php'; // hc_facility_growth_pct()

$myUid = $_SESSION['uid'] ?? '';
$myDoc = $myUid !== '' ? $firestore->getDocument('users', $myUid) : null;

$staffProfile = [
    'name'     => $_SESSION['name'] ?? 'Medical Staff',
    'role'     => 'Medical Staff',
    'facility' => $myDoc['facility'] ?? 'North Super Health Center',
];

$dashboardDateLabel = date('F j, Y');
$analyticsDateRangeLabel = 'Last 14 Days';

$typeMeta = hc_consultation_type_meta();
$priorityMeta = hc_consultation_priority_meta();

// hc_get_all_patients_summary() doesn't carry registeredBy — read raw
// docs directly here rather than widening that shared summary shape
// for every other caller that doesn't need it.
$myPatients = [];
foreach ($firestore->listDocuments('patients') as $doc) {
    $f = $doc['fields'];
    if (($f['registeredBy'] ?? '') === $myUid) {
        $myPatients[] = ['id' => $doc['id'], 'dateRegistered' => $f['dateRegistered'] ?? ''];
    }
}

$allConsultations = hc_get_all_consultations();
$myConsultations = array_values(array_filter($allConsultations, fn ($c) => $c['createdBy'] === $myUid));

// ---------------------------------------------------------------------
// KPI cards
// ---------------------------------------------------------------------
$thisMonthPrefix = date('Y-m');
$lastMonthPrefix = date('Y-m', strtotime('first day of last month'));
$newPatientsThisMonth = count(array_filter($myPatients, fn ($p) => str_starts_with($p['dateRegistered'], $thisMonthPrefix)));
$newPatientsLastMonth = count(array_filter($myPatients, fn ($p) => str_starts_with($p['dateRegistered'], $lastMonthPrefix)));
$newPatientsGrowthPct = hc_facility_growth_pct($newPatientsThisMonth, $newPatientsLastMonth);
$priorityConsultCount = count(array_filter($myConsultations, fn ($c) => $c['priority'] !== ''));

$kpiCards = [
    ['icon' => 'bi-person-plus-fill',     'iconBg' => '#FCE4E4', 'iconColor' => '#D93025', 'label' => 'Patients I Registered',  'value' => count($myPatients),      'sub' => 'All-time',    'trend' => 'up'],
    ['icon' => 'bi-calendar2-check-fill', 'iconBg' => '#FDECDD', 'iconColor' => '#E8710A', 'label' => 'New This Month',         'value' => $newPatientsThisMonth,   'sub' => abs($newPatientsGrowthPct) . '% vs last month', 'trend' => $newPatientsGrowthPct < 0 ? 'down' : 'up'],
    ['icon' => 'bi-calendar2-heart-fill', 'iconBg' => '#E8F0FE', 'iconColor' => '#1A73E8', 'label' => 'Consultations I Set',    'value' => count($myConsultations), 'sub' => 'All-time',    'trend' => 'up'],
    ['icon' => 'bi-exclamation-diamond-fill', 'iconBg' => '#FEF3C7', 'iconColor' => '#B45309', 'label' => 'Priority Consultations', 'value' => $priorityConsultCount, 'sub' => 'Senior / PWD / Pregnant', 'trend' => 'up'],
];

// ---------------------------------------------------------------------
// Trend — patients I registered over the last 14 days.
// ---------------------------------------------------------------------
$trendLabels = [];
$trendCounts = [];
for ($i = 13; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-$i days"));
    $trendLabels[] = date('M j', strtotime($day));
    $trendCounts[] = count(array_filter($myPatients, fn ($p) => substr($p['dateRegistered'], 0, 10) === $day));
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
// Consultations by Priority (donut) — RA 9994/RA 10754 priority lanes.
// ---------------------------------------------------------------------
$byPriority = ['none' => 0];
foreach ($myConsultations as $c) {
    $key = $c['priority'] !== '' ? $c['priority'] : 'none';
    $byPriority[$key] = ($byPriority[$key] ?? 0) + 1;
}
$consultationsByPriority = [];
foreach ($priorityMeta as $key => $meta) {
    if (($byPriority[$key] ?? 0) > 0) {
        $consultationsByPriority[] = ['label' => $meta['label'], 'value' => $byPriority[$key], 'color' => $meta['color']];
    }
}
if ($byPriority['none'] > 0) {
    $consultationsByPriority[] = ['label' => 'None', 'value' => $byPriority['none'], 'color' => '#9CA3AF'];
}
$consultationsByPriorityTotal = array_sum(array_column($consultationsByPriority, 'value'));

// ---------------------------------------------------------------------
// Top 5 Chief Complaints / Reasons for Visit — free text, so grouped by
// exact (trimmed) match rather than a fixed catalog.
// ---------------------------------------------------------------------
$reasonCounts = [];
foreach ($myConsultations as $c) {
    $reason = trim((string) ($c['chiefComplaint'] ?? ''));
    if ($reason === '' || $reason === '—') {
        continue;
    }
    $reasonCounts[$reason] = ($reasonCounts[$reason] ?? 0) + 1;
}
arsort($reasonCounts);
$topReasons = [];
foreach (array_slice($reasonCounts, 0, 5, true) as $label => $value) {
    $topReasons[] = ['label' => $label, 'value' => $value];
}
$topReasonsMax = $topReasons !== [] ? max(array_column($topReasons, 'value')) : 1;
