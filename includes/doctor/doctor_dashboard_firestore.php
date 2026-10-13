<?php
/**
 * =====================================================================
 * Doctor Dashboard — Data
 * ---------------------------------------------------------------------
 * "Doctor" is its own Firestore role ("doctor"), separate from
 * "healthcare_staff" (nurses / general medical staff — see
 * medical-staff/dashboard.php). Both roles share the same real
 * "patients" and "consultations" collections (includes/firestore/patients.php,
 * includes/firestore/consultations.php) — nothing here invents a
 * doctor-only data model. What's different is scope: medical-staff/dashboard.php
 * shows facility-wide numbers, while this page narrows every widget to
 * the SIGNED-IN doctor's own caseload — every consultations/{id} doc
 * whose real "assignedPersonnel" field (set when a consultation is
 * created/updated — see api/create-consultation.php, api/update-consultation.php,
 * hc_get_physician_options()) matches this doctor's own name. "My
 * Patients" is derived from that same filtered set (the distinct
 * patientIds across the doctor's own consultations) — patients/{id}
 * itself has no assignedDoctor field, so there's no way to scope the
 * patient collection directly.
 *
 * A consultation with assignedPersonnel that doesn't exactly match the
 * signed-in doctor's name (e.g. assigned before this doctor's account
 * existed, or a typo'd name) simply won't show up here — same
 * name-string-matching limitation hc_get_physician_options() already
 * has, not something this file can fix on its own.
 *
 * "Unread Notifications" is real now too — hc_doctor_unread_notification_count()
 * (config/constants.php), the same real follow-up-derived count driving
 * doctor/notifications.php's own badges. "Recent Notifications" is real
 * as well — the same hc_get_followup_notifications() feed as that page,
 * capped to 5 (see this file's "Recent Notifications" section below).
 * $doctorProfile IS real — read from the signed-in doctor's own
 * Firestore users/{uid} document.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore
require_once __DIR__ . '/../firestore/patients.php';
require_once __DIR__ . '/../firestore/consultations.php';
require_once __DIR__ . '/../shared/query_helpers.php'; // hc_url_with_params(), hc_matches_date_range_key()

$myUid = $_SESSION['uid'] ?? '';
$myDoc = $myUid !== '' ? $firestore->getDocument('users', $myUid) : null;
$myName = $_SESSION['name'] ?? 'Doctor';

$doctorProfile = [
    'name'     => $myName,
    'role'     => 'Doctor',
    'facility' => $myDoc['facility'] ?? 'North Super Health Center',
];

$dashboardDateLabel = date('F j, Y');

// Facility selector — mirrors includes/medical-staff/medical_staff_dashboard_firestore.php's names.
$facilityOptions = ['North Super Health Center', 'East Super Health Center', 'West Super Health Center'];

// ---------------------------------------------------------------------
// This doctor's own consultations — every real consultations/{id} doc
// assigned to them by name (see this file's header comment).
// ---------------------------------------------------------------------
$allConsultations = hc_get_all_consultations();
$myConsultations = array_values(array_filter($allConsultations, fn ($c) => $c['assignedPersonnel'] === $myName));

$consultationTypeMeta = hc_consultation_type_meta();
$consultationStatusMetaDash = hc_consultation_status_meta();
$consultationPriorityMetaDash = hc_consultation_priority_meta();

// ---------------------------------------------------------------------
// This doctor's own patients — the distinct patients behind their own
// consultations above (patients/{id} has no assignedDoctor field of
// its own to filter on directly).
// ---------------------------------------------------------------------
$myPatientIds = array_values(array_unique(array_filter(array_map(fn ($c) => $c['patientId'], $myConsultations))));
$allPatients = hc_get_all_patients_summary();
$myPatients = array_values(array_filter($allPatients, fn ($p) => in_array($p['id'], $myPatientIds, true)));
$totalMyPatientsCount = count($myPatients);
$thisMonthPrefix = date('Y-m');
$newThisMonthCount = count(array_filter($myPatients, fn ($p) => str_starts_with($p['dateRegisteredRaw'], $thisMonthPrefix)));

$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));
$todaysConsultationsCount = count(array_filter($myConsultations, fn ($c) => str_starts_with($c['dateTime'], $today)));
$yesterdaysConsultationsCount = count(array_filter($myConsultations, fn ($c) => str_starts_with($c['dateTime'], $yesterday)));
$pendingConsultationsCount = count(array_filter($myConsultations, fn ($c) => $c['status'] === 'pending'));
$completedTodayCount = count(array_filter($myConsultations, fn ($c) => str_starts_with($c['dateTime'], $today) && $c['status'] === 'completed'));

// Trend vs yesterday only shown when there's a real yesterday count to
// compare against — same honest-omission pattern as medical-staff/dashboard.php.
$todaysConsultationsTrend = null;
if ($yesterdaysConsultationsCount > 0) {
    $pct = round((($todaysConsultationsCount - $yesterdaysConsultationsCount) / $yesterdaysConsultationsCount) * 100);
    $todaysConsultationsTrend = ['label' => abs($pct) . '% from yesterday', 'dir' => $pct < 0 ? 'down' : 'up'];
}

// ---------------------------------------------------------------------
// Summary stat cards
// ---------------------------------------------------------------------
$statCards = [
    ['icon' => 'bi-people-fill',        'iconBg' => '#FCE4E4', 'iconColor' => '#D93025', 'label' => "Today's Consultations",  'value' => $todaysConsultationsCount] + ($todaysConsultationsTrend ? ['sub' => $todaysConsultationsTrend['label'], 'trend' => $todaysConsultationsTrend['dir']] : ['staticSub' => 'No data for yesterday yet']),
    ['icon' => 'bi-person-lines-fill',  'iconBg' => '#E4F7EA', 'iconColor' => '#1E8E3E', 'label' => 'My Patients',             'value' => $totalMyPatientsCount, 'staticSub' => $newThisMonthCount . ' new ' . date('F Y')],
    ['icon' => 'bi-calendar2-week-fill','iconBg' => '#EFE7FB', 'iconColor' => '#7C3AED', 'label' => 'Pending Consultations',   'value' => $pendingConsultationsCount, 'staticSub' => 'Across all dates'],
    ['icon' => 'bi-check-circle-fill', 'iconBg' => '#E8F0FE',  'iconColor' => '#1A73E8', 'label' => 'Completed Today',         'value' => $completedTodayCount, 'staticSub' => date('F j, Y')],
    ['icon' => 'bi-bell-fill',         'iconBg' => '#FDECDD',  'iconColor' => '#E8710A', 'label' => 'Unread Notifications',    'value' => hc_doctor_unread_notification_count(), 'link' => ['label' => 'View all notifications', 'href' => 'notifications.php']],
];

// ---------------------------------------------------------------------
// Consultations Trend — line chart. Real counts from this doctor's own
// consultations, bucketed to match the selected range: daily points
// for This Week (last 7 days)/This Month (month-to-date), monthly
// points for This Year (Jan through the current month).
// ---------------------------------------------------------------------
$consultationsTrendRangeOptions = ['this_week' => 'This Week', 'this_month' => 'This Month', 'this_year' => 'This Year'];
$consultationsTrendRangeKey = array_key_exists($_GET['trendRange'] ?? '', $consultationsTrendRangeOptions) ? $_GET['trendRange'] : 'this_week';
$consultationsTrendRange = $consultationsTrendRangeOptions[$consultationsTrendRangeKey];

$trendLabels = [];
$trendValues = [];
if ($consultationsTrendRangeKey === 'this_year') {
    $monthsSoFar = (int) date('n');
    for ($m = 1; $m <= $monthsSoFar; $m++) {
        $monthStart = mktime(0, 0, 0, $m, 1, (int) date('Y'));
        $trendLabels[] = date('M', $monthStart);
        $monthPrefix = date('Y-m', $monthStart);
        $trendValues[] = count(array_filter($myConsultations, fn ($c) => str_starts_with($c['dateTime'], $monthPrefix)));
    }
} else {
    $daysBack = $consultationsTrendRangeKey === 'this_month' ? ((int) date('j') - 1) : 6;
    for ($i = $daysBack; $i >= 0; $i--) {
        $day = date('Y-m-d', strtotime("-{$i} day"));
        $trendLabels[] = date('M j', strtotime($day));
        $trendValues[] = count(array_filter($myConsultations, fn ($c) => str_starts_with($c['dateTime'], $day)));
    }
}
$consultationsTrend = [
    'labels' => $trendLabels,
    'values' => $trendValues,
];

// ---------------------------------------------------------------------
// My Patients by Age Group — bar chart, scoped by each patient's own
// real registration date (dateRegisteredRaw) when a range other than
// All Time is selected.
// ---------------------------------------------------------------------
$patientsByAgeRangeOptions = ['all' => 'All Time', 'today' => 'Today', 'this_week' => 'This Week', 'this_month' => 'This Month'];
$patientsByAgeRangeKey = array_key_exists($_GET['ageRange'] ?? '', $patientsByAgeRangeOptions) ? $_GET['ageRange'] : 'all';
$patientsByAgeRange = $patientsByAgeRangeOptions[$patientsByAgeRangeKey];

$ageGroupLabels = ['0-12', '13-17', '18-30', '31-45', '46-60', '60+'];
$ageGroupCounts = array_fill_keys($ageGroupLabels, 0);
foreach ($myPatients as $p) {
    if (!hc_matches_date_range_key($p['dateRegisteredRaw'] ?? '', $patientsByAgeRangeKey)) {
        continue;
    }
    $ageGroupCounts[hc_patient_age_group($p['age'])]++;
}
$patientsByAge = [
    'labels' => $ageGroupLabels,
    'values' => array_values($ageGroupCounts),
];

// ---------------------------------------------------------------------
// My Patients by Gender — doughnut chart.
// ---------------------------------------------------------------------
$patientsByGenderTotal = count($myPatients);
$genderCounts = ['Male' => 0, 'Female' => 0, 'Other' => 0];
foreach ($myPatients as $p) {
    $g = in_array($p['gender'], ['Male', 'Female'], true) ? $p['gender'] : 'Other';
    $genderCounts[$g]++;
}
$genderColors = ['Female' => '#D93025', 'Male' => '#F4A6B0', 'Other' => '#9AA0AC'];
$patientsByGender = [];
foreach ($genderCounts as $label => $value) {
    if ($value === 0) {
        continue;
    }
    $patientsByGender[] = [
        'label' => $label,
        'value' => $value,
        'pct' => $patientsByGenderTotal > 0 ? round($value / $patientsByGenderTotal * 100, 1) : 0,
        'color' => $genderColors[$label],
    ];
}

// ---------------------------------------------------------------------
// Upcoming Follow-up Patients — this doctor's own consultations with a
// real nextFollowUp set in the future, soonest first.
// ---------------------------------------------------------------------
$now = date('c');
$upcoming = array_values(array_filter($myConsultations, fn ($c) => $c['nextFollowUp'] !== '' && $c['nextFollowUp'] >= $now));
usort($upcoming, fn ($a, $b) => strcmp($a['nextFollowUp'], $b['nextFollowUp']));
$upcoming = array_slice($upcoming, 0, 5);

$tomorrow = date('Y-m-d', strtotime('+1 day'));
$upcomingFollowUps = array_map(function ($c) use ($today, $tomorrow) {
    $followUpDate = substr($c['nextFollowUp'], 0, 10);
    $when = $followUpDate === $today ? 'Today' : ($followUpDate === $tomorrow ? 'Tomorrow' : date('M j, Y', strtotime($followUpDate)));
    return [
        'name' => $c['patientName'],
        'when' => $when,
        'time' => date('g:i A', strtotime($c['nextFollowUp'])),
    ];
}, $upcoming);

// ---------------------------------------------------------------------
// Consultation Status — doughnut chart, scoped to this doctor.
// ---------------------------------------------------------------------
$consultationStatusTotal = count($myConsultations);
$statusCounts = array_fill_keys(array_keys($consultationStatusMetaDash), 0);
foreach ($myConsultations as $c) {
    if (isset($statusCounts[$c['status']])) {
        $statusCounts[$c['status']]++;
    }
}
$consultationStatus = [];
foreach ($statusCounts as $key => $value) {
    if ($value === 0) {
        continue;
    }
    $meta = $consultationStatusMetaDash[$key];
    $consultationStatus[] = [
        'label' => $meta['label'],
        'value' => $value,
        'pct' => $consultationStatusTotal > 0 ? round($value / $consultationStatusTotal * 100) : 0,
        'color' => $meta['color'],
    ];
}

// ---------------------------------------------------------------------
// Today's Consultation Schedule — this doctor's own consultations
// happening today, earliest first.
// ---------------------------------------------------------------------
$todaysConsultationsList = array_values(array_filter($myConsultations, fn ($c) => str_starts_with($c['dateTime'], $today)));
usort($todaysConsultationsList, fn ($a, $b) => strcmp($a['dateTime'], $b['dateTime']));

$todaysSchedule = array_map(function ($c) use ($consultationTypeMeta) {
    return [
        'time' => date('g:i A', strtotime($c['dateTime'])),
        'patient' => $c['patientName'],
        'type' => $consultationTypeMeta[$c['consultationType']]['label'] ?? $c['consultationType'],
        'priority' => $c['priority'],
        'status' => $c['status'],
    ];
}, $todaysConsultationsList);
$scheduleStatusMeta = $consultationStatusMetaDash;
$priorityMeta = $consultationPriorityMetaDash;

// ---------------------------------------------------------------------
// Recent Notifications — CONNECTED now, same real source as
// doctor/notifications.php (hc_get_followup_notifications(), scoped to
// this doctor's own name), minus anything they've dismissed there —
// see includes/doctor/notifications_doctor_firestore.php's header
// comment. There's no real "created at" timestamp for a follow-up
// notification (it's derived live from a consultation's nextFollowUp
// date, not a stored event), so this shows the real due date/time
// instead of a fabricated "X minutes ago" string. Capped to 5, same as
// the old placeholder list.
// ---------------------------------------------------------------------
$ntfDismissedIds = $_SESSION['ntfDismissedIds'] ?? [];
$recentNotifications = array_map(function ($n) {
    return [
        'icon' => $n['icon'],
        'iconBg' => $n['iconBg'],
        'iconColor' => $n['iconColor'],
        'title' => $n['title'],
        'desc' => $n['description'],
        'timeAgo' => $n['date'] . ' · ' . $n['time'],
    ];
}, array_slice(array_values(array_filter(
    hc_get_followup_notifications($myName),
    fn ($n) => !in_array($n['id'], $ntfDismissedIds, true)
)), 0, 5));

// ---------------------------------------------------------------------
// My Recently Registered Patients — the 5 most recently registered
// real patients among this doctor's own ($allPatients, and therefore
// $myPatients, is already sorted newest-first).
// ---------------------------------------------------------------------
$recentlyRegisteredPatients = array_slice($myPatients, 0, 5);
