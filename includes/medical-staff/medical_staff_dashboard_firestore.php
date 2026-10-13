<?php
/**
 * =====================================================================
 * Medical Staff Dashboard — Data
 * ---------------------------------------------------------------------
 * Static stand-ins for medical-staff/dashboard.php, following the same
 * per-page "-data.php" convention as admin/ and subadmin/.
 *
 * CONNECTED — patient-count widgets ($statCards' "Total Assigned
 * Patients", $patientsByAge, $patientsByGender, $recentlyRegisteredPatients)
 * read the real "patients" Firestore collection via
 * includes/firestore/patients.php's hc_get_all_patients_summary(). Every
 * CONSULTATION-related widget (today's/pending/completed-today stat
 * cards, the 7-day trend line, consultation status donut, today's
 * schedule table, upcoming follow-ups) now reads the real
 * "consultations" collection too, via includes/firestore/consultations.php's
 * hc_get_all_consultations() — the same collection
 * medical-staff/consultations.php itself reads, so the two pages agree
 * with each other. "Unread Notifications" is real too now —
 * hc_medstaff_unread_notification_count() (config/constants.php), the
 * same real follow-up-derived count driving
 * medical-staff/notifications.php's own badges.
 * $staffProfile IS real — read from the signed-in medical staff's own
 * Firestore users/{uid} document (the same one api/session-login.php
 * populates $_SESSION from).
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore
require_once __DIR__ . '/../firestore/patients.php';
require_once __DIR__ . '/../firestore/consultations.php';
require_once __DIR__ . '/../shared/query_helpers.php'; // hc_url_with_params(), hc_matches_date_range_key()

$myUid = $_SESSION['uid'] ?? '';
$myDoc = $myUid !== '' ? $firestore->getDocument('users', $myUid) : null;

$staffProfile = [
    'name'     => $_SESSION['name'] ?? 'Medical Staff',
    'role'     => 'Medical Staff',
    'facility' => $myDoc['facility'] ?? 'North Super Health Center',
];

$dashboardDateLabel = date('F j, Y');

// Facility selector — mirrors includes/subadmin_dashboard_firestore.php's names.
$facilityOptions = ['North Super Health Center', 'East Super Health Center', 'West Super Health Center'];

// ---------------------------------------------------------------------
// Real patient list — backs the "Total Assigned Patients" stat card,
// the age/gender charts, and the Recently Registered Patients table
// below. Everything else on this page (consultations, notifications)
// has no real collection yet — see this file's header comment.
// ---------------------------------------------------------------------
$allPatients = hc_get_all_patients_summary();
$totalPatientsCount = count($allPatients);
$thisMonthPrefix = date('Y-m');
$newThisMonthCount = count(array_filter($allPatients, fn ($p) => str_starts_with($p['dateRegisteredRaw'], $thisMonthPrefix)));

// ---------------------------------------------------------------------
// Real consultation list — backs the consultation-related stat cards,
// the trend chart, the status donut, today's schedule, and upcoming
// follow-ups below. Same collection medical-staff/consultations.php
// itself reads (includes/firestore/consultations.php), so both pages agree.
// ---------------------------------------------------------------------
$allConsultations = hc_get_all_consultations();
$consultationTypeMeta = hc_consultation_type_meta();
$consultationStatusMetaDash = hc_consultation_status_meta();
$consultationPriorityMetaDash = hc_consultation_priority_meta();

$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));
$todaysConsultationsCount = count(array_filter($allConsultations, fn ($c) => str_starts_with($c['dateTime'], $today)));
$yesterdaysConsultationsCount = count(array_filter($allConsultations, fn ($c) => str_starts_with($c['dateTime'], $yesterday)));
$pendingConsultationsCount = count(array_filter($allConsultations, fn ($c) => $c['status'] === 'pending'));
$completedTodayCount = count(array_filter($allConsultations, fn ($c) => str_starts_with($c['dateTime'], $today) && $c['status'] === 'completed'));

// Trend vs yesterday is only shown when there's a real yesterday count
// to compare against — an honest omission beats a fabricated 0%/up
// arrow when the collection is new (same pattern used on
// medical-staff/consultations.php's own stat cards).
$todaysConsultationsTrend = null;
if ($yesterdaysConsultationsCount > 0) {
    $pct = round((($todaysConsultationsCount - $yesterdaysConsultationsCount) / $yesterdaysConsultationsCount) * 100);
    $todaysConsultationsTrend = ['label' => abs($pct) . '% from yesterday', 'dir' => $pct < 0 ? 'down' : 'up'];
}

// ---------------------------------------------------------------------
// Summary stat cards — all real now (see this file's header comment).
// ---------------------------------------------------------------------
$statCards = [
    ['icon' => 'bi-people-fill',       'iconBg' => '#FCE4E4', 'iconColor' => '#D93025', 'label' => "Today's Consultations",   'value' => $todaysConsultationsCount] + ($todaysConsultationsTrend ? ['sub' => $todaysConsultationsTrend['label'], 'trend' => $todaysConsultationsTrend['dir']] : ['staticSub' => 'No data for yesterday yet']),
    ['icon' => 'bi-person-lines-fill', 'iconBg' => '#E4F7EA', 'iconColor' => '#1E8E3E', 'label' => 'Total Assigned Patients',  'value' => $totalPatientsCount, 'staticSub' => $newThisMonthCount . ' new ' . date('F Y')],
    ['icon' => 'bi-calendar2-week-fill','iconBg' => '#EFE7FB', 'iconColor' => '#7C3AED', 'label' => 'Pending Consultations',    'value' => $pendingConsultationsCount,   'staticSub' => 'Across all dates'],
    ['icon' => 'bi-check-circle-fill', 'iconBg' => '#E8F0FE', 'iconColor' => '#1A73E8', 'label' => 'Completed Today',          'value' => $completedTodayCount,   'staticSub' => date('F j, Y')],
    ['icon' => 'bi-bell-fill',         'iconBg' => '#FDECDD', 'iconColor' => '#E8710A', 'label' => 'Unread Notifications',     'value' => hc_medstaff_unread_notification_count(),    'link' => ['label' => 'View all notifications', 'href' => 'notifications.php']],
];

// ---------------------------------------------------------------------
// Consultations Trend — line chart. Real counts from each consultation's
// real dateTime, bucketed to match the selected range: daily points for
// This Week (last 7 days)/This Month (month-to-date), monthly points
// for This Year (Jan through the current month).
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
        $trendValues[] = count(array_filter($allConsultations, fn ($c) => str_starts_with($c['dateTime'], $monthPrefix)));
    }
} else {
    $daysBack = $consultationsTrendRangeKey === 'this_month' ? ((int) date('j') - 1) : 6;
    for ($i = $daysBack; $i >= 0; $i--) {
        $day = date('Y-m-d', strtotime("-{$i} day"));
        $trendLabels[] = date('M j', strtotime($day));
        $trendValues[] = count(array_filter($allConsultations, fn ($c) => str_starts_with($c['dateTime'], $day)));
    }
}
$consultationsTrend = [
    'labels' => $trendLabels,
    'values' => $trendValues,
];

// ---------------------------------------------------------------------
// Patients by Age Group — bar chart. Real distribution over every
// registered patient, scoped by each patient's own real registration
// date (dateRegisteredRaw) when a range other than All Time is
// selected.
// ---------------------------------------------------------------------
$patientsByAgeRangeOptions = ['all' => 'All Time', 'today' => 'Today', 'this_week' => 'This Week', 'this_month' => 'This Month'];
$patientsByAgeRangeKey = array_key_exists($_GET['ageRange'] ?? '', $patientsByAgeRangeOptions) ? $_GET['ageRange'] : 'all';
$patientsByAgeRange = $patientsByAgeRangeOptions[$patientsByAgeRangeKey];

$ageGroupLabels = ['0-12', '13-17', '18-30', '31-45', '46-60', '60+'];
$ageGroupCounts = array_fill_keys($ageGroupLabels, 0);
foreach ($allPatients as $p) {
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
// Patients by Gender — doughnut chart. Real Male/Female counts; any
// other or unspecified value (e.g. a patient record missing gender)
// is grouped under "Other" rather than silently dropped.
// ---------------------------------------------------------------------
$patientsByGenderTotal = count($allPatients);
$genderCounts = ['Male' => 0, 'Female' => 0, 'Other' => 0];
foreach ($allPatients as $p) {
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
// Upcoming Follow-up Patients — real consultations with a real
// nextFollowUp set in the future, soonest first.
// ---------------------------------------------------------------------
$now = date('c');
$upcoming = array_values(array_filter($allConsultations, fn ($c) => $c['nextFollowUp'] !== '' && $c['nextFollowUp'] >= $now));
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
// Consultation Status — doughnut chart. Real status distribution over
// every consultation (same statuses/colors medical-staff/consultations.php
// uses).
// ---------------------------------------------------------------------
$consultationStatusTotal = count($allConsultations);
$statusCounts = array_fill_keys(array_keys($consultationStatusMetaDash), 0);
foreach ($allConsultations as $c) {
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
// Today's Consultation Schedule — real consultations happening today,
// earliest first.
// ---------------------------------------------------------------------
$todaysConsultationsList = array_values(array_filter($allConsultations, fn ($c) => str_starts_with($c['dateTime'], $today)));
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
// Recent Notifications
// ---------------------------------------------------------------------
$recentNotifications = [
    ['icon' => 'bi-person-plus-fill',     'iconBg' => '#FCE4E4', 'iconColor' => '#D93025', 'title' => 'New consultation assigned', 'desc' => 'Juan D. Cruz',       'timeAgo' => '10 minutes ago'],
    ['icon' => 'bi-bell-fill',            'iconBg' => '#FDECDD', 'iconColor' => '#E8710A', 'title' => 'Follow-up reminder',        'desc' => 'Maria S. Reyes',     'timeAgo' => '1 hour ago'],
    ['icon' => 'bi-file-earmark-text-fill','iconBg' => '#E8F0FE', 'iconColor' => '#1A73E8', 'title' => 'Patient record updated',    'desc' => 'Pedro L. Santos',    'timeAgo' => '2 hours ago'],
    ['icon' => 'bi-calendar2-x-fill',     'iconBg' => '#EFE7FB', 'iconColor' => '#7C3AED', 'title' => 'Consultation rescheduled',  'desc' => 'Nina P. Garcia',     'timeAgo' => '3 hours ago'],
    ['icon' => 'bi-person-check-fill',    'iconBg' => '#E4F7EA', 'iconColor' => '#1E8E3E', 'title' => 'New patient added',         'desc' => 'Ramon T. Dela Cruz', 'timeAgo' => '4 hours ago'],
];

// ---------------------------------------------------------------------
// Recently Registered Patients — the 5 most recently registered real
// patients ($allPatients is already sorted newest-first). Renamed from
// the earlier placeholder's "Recently Accessed Patients": there's no
// view/access-tracking anywhere in this app, so claiming "accessed"
// would be a feature that doesn't exist — "registered" is what
// dateRegistered actually is. "Reason for Visit" is the real
// initialReasonForVisit captured at intake (medical-staff/patient_management.php's
// "Add Patient" wizard, Step 5) — '—' for patients registered before
// that field existed.
// ---------------------------------------------------------------------
$recentlyRegisteredPatients = array_slice($allPatients, 0, 5);
