<?php
/**
 * =====================================================================
 * Sub-Admin Dashboard — Data
 * ---------------------------------------------------------------------
 * PARTIALLY connected to real Firestore data. $adminProfile/$facility
 * were already real (read from the signed-in sub-admin's own
 * users/{uid} document). This pass adds:
 *   - Total Patients, Patients by Age Group, Patients by Gender — real,
 *     from includes/firestore/patients.php's shared hc_get_all_patients_summary()
 *     — the SAME query includes/patient_management_firestore.php uses.
 *   - Active Medical Staff — real, counted from the "users" collection
 *     (role healthcare_staff/pharmacist), same source as
 *     includes/user_management_firestore.php.
 *   - Low Stock Alerts — real, from the "medicines" collection's
 *     status field (see includes/medicine_vaccine_inventory_firestore.php).
 *   - Recent Stock Requests — real, latest 3 from the "stock_requests"
 *     collection (see includes/stock_requests_firestore.php).
 *
 * Today's Consultations and the Consultations Trend line chart now
 * read the real "consultations" Firestore collection too, via
 * includes/firestore/consultations.php's hc_get_all_consultations() — the
 * same collection medical-staff/consultations.php and its dashboard
 * read, so all three pages agree with each other instead of each
 * showing a different invented number. (The Consultations by Type and
 * Monthly Consultation Comparison cards that used to also read this
 * collection were removed from the page — see subadmin/dashboard.php.)
 *
 * Total Medical Records, Top 5 Common Health Conditions, and
 * Laboratory & X-ray Summary are ALSO now real, aggregated straight
 * from the real per-patient "medical_records"/"medical_conditions"/
 * "lab_results"/"xray_results" collections (the same ones
 * includes/firestore/medical_records.php, lab_results.php, and
 * xray_results.php already read per-patient for the Patient Profile
 * tabs) — just counted/grouped, scoped to this facility, instead of one
 * patient.
 *
 * FACILITY ISOLATION: every widget here is scoped to $adminProfile['facility']
 * (resolved server-side from this sub-admin's own real users/{uid} doc,
 * never a client-supplied value) — a sub-admin's dashboard must show
 * THEIR facility's numbers, not every facility's combined. "patients",
 * "medical_records", "lab_results", "xray_results", "stock_requests",
 * and "users" (staff) each carry a real "facility" field directly, so
 * those filter on it directly; "consultations" and "medical_conditions"
 * carry no such field (only patientId), so those are scoped by joining
 * against the already facility-filtered patient list instead. The one
 * genuine exception is "medicines" (Low Stock Alerts) — inventory has
 * no facility field at all, a system-wide shared pharmacy rather than
 * per-facility stock (same as api/get-inventory-card.php already
 * treats it).
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore
require_once __DIR__ . '/../firestore/patients.php';
require_once __DIR__ . '/../firestore/consultations.php';
require_once __DIR__ . '/../shared/query_helpers.php'; // hc_url_with_params(), hc_matches_date_range_key()

$myUid = $_SESSION['uid'] ?? '';
$myDoc = $myUid !== '' ? $firestore->getDocument('users', $myUid) : null;

$adminProfile = [
    'name' => $_SESSION['name'] ?? 'Sub-Administrator',
    'role' => match ($_SESSION['role'] ?? null) {
        'subadmin' => 'Sub-Admin',
        'healthcare_staff' => 'Healthcare Staff',
        default => 'Sub-Administrator',
    },
    'facility' => $myDoc['facility'] ?? 'Unassigned Facility',
];

$dashboardDateLabel = date('F j, Y');
$facilityOptions = ['North Super Health Center', 'East Super Health Center', 'West Super Health Center'];

// ---------------------------------------------------------------------
// Real patients — used for Total Patients, Patients by Age Group, and
// Patients by Gender below. Scoped to this sub-admin's own facility.
// ---------------------------------------------------------------------
$realPatients = array_values(array_filter(hc_get_all_patients_summary(), fn ($p) => $p['facility'] === $adminProfile['facility']));
$totalPatientsCount = count($realPatients);
$facilityPatientIds = array_column($realPatients, 'id');

// ---------------------------------------------------------------------
// Real staff — Active Medical Staff, broken down by role. Scoped to
// this sub-admin's own facility (a "users" doc's real "facility" field).
// ---------------------------------------------------------------------
$staffCounts = ['healthcare_staff' => 0, 'pharmacist' => 0, 'other' => 0];
foreach ($firestore->listDocuments('users') as $doc) {
    $f = $doc['fields'];
    if (($f['facility'] ?? '') !== $adminProfile['facility']) {
        continue;
    }
    $role = $f['role'] ?? '';
    if ($role === 'healthcare_staff') {
        $staffCounts['healthcare_staff']++;
    } elseif ($role === 'pharmacist') {
        $staffCounts['pharmacist']++;
    }
}
$activeStaffTotal = $staffCounts['healthcare_staff'] + $staffCounts['pharmacist'];

// ---------------------------------------------------------------------
// Real medicines — Low Stock Alerts.
// ---------------------------------------------------------------------
$lowStockAlertsCount = 0;
foreach ($firestore->listDocuments('medicines') as $doc) {
    $status = $doc['fields']['status'] ?? 'available';
    if ($status === 'low' || $status === 'unavailable') {
        $lowStockAlertsCount++;
    }
}

// ---------------------------------------------------------------------
// Real consultations — backs "Today's Consultations" and the trend
// chart below. Same collection medical-staff/consultations.php reads
// (includes/firestore/consultations.php), so every page agrees. No
// "facility" field of its own (see header comment) — scoped by joining
// on patientId against $facilityPatientIds instead.
// ---------------------------------------------------------------------
$allConsultations = array_values(array_filter(hc_get_all_consultations(), fn ($c) => in_array($c['patientId'], $facilityPatientIds, true)));

$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));
$todaysConsultationsCount = count(array_filter($allConsultations, fn ($c) => str_starts_with($c['dateTime'], $today)));
$yesterdaysConsultationsCount = count(array_filter($allConsultations, fn ($c) => str_starts_with($c['dateTime'], $yesterday)));

// Trend vs yesterday only shown when there's a real yesterday count to
// compare against — an honest omission beats a fabricated percentage
// when the collection has little history yet.
$todaysConsultationsSub = 'No data for yesterday yet';
$todaysConsultationsTrend = 'up';
$todaysConsultationsHasTrend = false;
if ($yesterdaysConsultationsCount > 0) {
    $pct = round((($todaysConsultationsCount - $yesterdaysConsultationsCount) / $yesterdaysConsultationsCount) * 100);
    $todaysConsultationsSub = abs($pct) . '% from yesterday';
    $todaysConsultationsTrend = $pct < 0 ? 'down' : 'up';
    $todaysConsultationsHasTrend = true;
}

// ---------------------------------------------------------------------
// Real medical records — every patient's "medical_records" doc (same
// collection includes/firestore/medical_records.php reads per-patient
// for the Patient Profile's Medical Records tab), scoped to this
// facility via each doc's own real "facility" field.
// ---------------------------------------------------------------------
$rawMedicalRecords = array_values(array_filter(
    $firestore->listDocuments('medical_records'),
    fn ($doc) => ($doc['fields']['facility'] ?? '') === $adminProfile['facility']
));
$totalMedicalRecordsCount = count($rawMedicalRecords);
$thisMonthPrefix = date('Y-m');
$newMedicalRecordsThisMonth = count(array_filter($rawMedicalRecords, fn ($doc) => str_starts_with($doc['fields']['date'] ?? '', $thisMonthPrefix)));

// ---------------------------------------------------------------------
// Summary stat cards — every card here is real now.
// ---------------------------------------------------------------------
$statCards = [
    ['icon' => 'bi-people-fill',            'iconBg' => '#FCE4E4', 'iconColor' => '#D93025', 'label' => 'Total Patients',          'value' => $totalPatientsCount, 'staticSub' => 'All registered patients'],
    ['icon' => 'bi-heart-pulse-fill',       'iconBg' => '#E4F7EA', 'iconColor' => '#1E8E3E', 'label' => "Today's Consultations",   'value' => $todaysConsultationsCount] + ($todaysConsultationsHasTrend ? ['sub' => $todaysConsultationsSub, 'trend' => $todaysConsultationsTrend] : ['staticSub' => $todaysConsultationsSub]),
    ['icon' => 'bi-file-earmark-text-fill', 'iconBg' => '#EFE7FB', 'iconColor' => '#7C3AED', 'label' => 'Total Medical Records',   'value' => $totalMedicalRecordsCount, 'staticSub' => $newMedicalRecordsThisMonth . ' new ' . date('F Y')],
    ['icon' => 'bi-person-badge-fill',      'iconBg' => '#E8F0FE', 'iconColor' => '#1A73E8', 'label' => 'Active Medical Staff',    'value' => $activeStaffTotal, 'breakdown' => [['label' => 'Medical Staff', 'value' => $staffCounts['healthcare_staff']], ['label' => 'Pharmacists', 'value' => $staffCounts['pharmacist']]]],
    ['icon' => 'bi-exclamation-triangle-fill', 'iconBg' => '#FDECDD', 'iconColor' => '#E8710A', 'label' => 'Low Stock Alerts',    'value' => $lowStockAlertsCount, 'link' => ['label' => 'View inventory', 'href' => 'medicine_vaccine_inventory.php']],
];

// ---------------------------------------------------------------------
// Consultations Trend — real counts from each consultation's real
// dateTime, bucketed to match the selected range: daily points for
// This Week (last 7 days)/This Month (month-to-date), monthly points
// for This Year (Jan through the current month).
// ---------------------------------------------------------------------
$consultationsTrendRangeOptions = ['this_week' => 'This Week', 'this_month' => 'This Month', 'this_year' => 'This Year'];
$consultationsTrendRangeKey = array_key_exists($_GET['trendRange'] ?? '', $consultationsTrendRangeOptions) ? $_GET['trendRange'] : 'this_week';
$consultationsTrendRange = $consultationsTrendRangeOptions[$consultationsTrendRangeKey];

$trendLabelsSub = [];
$trendValuesSub = [];
if ($consultationsTrendRangeKey === 'this_year') {
    $monthsSoFar = (int) date('n');
    for ($m = 1; $m <= $monthsSoFar; $m++) {
        $monthStart = mktime(0, 0, 0, $m, 1, (int) date('Y'));
        $trendLabelsSub[] = date('M', $monthStart);
        $monthPrefix = date('Y-m', $monthStart);
        $trendValuesSub[] = count(array_filter($allConsultations, fn ($c) => str_starts_with($c['dateTime'], $monthPrefix)));
    }
} else {
    $daysBack = $consultationsTrendRangeKey === 'this_month' ? ((int) date('j') - 1) : 6;
    for ($i = $daysBack; $i >= 0; $i--) {
        $day = date('Y-m-d', strtotime("-{$i} day"));
        $trendLabelsSub[] = date('M j', strtotime($day));
        $trendValuesSub[] = count(array_filter($allConsultations, fn ($c) => str_starts_with($c['dateTime'], $day)));
    }
}
$consultationsTrend = [
    'labels' => $trendLabelsSub,
    'values' => $trendValuesSub,
];

// ---------------------------------------------------------------------
// Patients by Age Group — real, computed from $realPatients, scoped by
// each patient's own real registration date (dateRegisteredRaw) when a
// range other than All Time is selected.
// ---------------------------------------------------------------------
$patientsByAgeRangeOptions = ['all' => 'All Time', 'today' => 'Today', 'this_week' => 'This Week', 'this_month' => 'This Month'];
$patientsByAgeRangeKey = array_key_exists($_GET['ageRange'] ?? '', $patientsByAgeRangeOptions) ? $_GET['ageRange'] : 'all';
$patientsByAgeRange = $patientsByAgeRangeOptions[$patientsByAgeRangeKey];

$ageGroupOrder = ['0-12', '13-17', '18-30', '31-45', '46-60', '60+'];
$ageGroupCounts = array_fill_keys($ageGroupOrder, 0);
foreach ($realPatients as $p) {
    if (!hc_matches_date_range_key($p['dateRegisteredRaw'] ?? '', $patientsByAgeRangeKey)) {
        continue;
    }
    $ageGroupCounts[hc_patient_age_group((int) ($p['age'] ?? 0))]++;
}
$patientsByAge = [
    'labels' => $ageGroupOrder,
    'values' => array_values($ageGroupCounts),
];

// ---------------------------------------------------------------------
// Patients by Gender — real, computed from $realPatients.
// ---------------------------------------------------------------------
$patientsByGenderTotal = $totalPatientsCount;
$femaleCount = count(array_filter($realPatients, fn ($p) => ($p['gender'] ?? '') === 'Female'));
$maleCount = count(array_filter($realPatients, fn ($p) => ($p['gender'] ?? '') === 'Male'));
$patientsByGender = [
    ['label' => 'Female', 'value' => $femaleCount, 'pct' => $patientsByGenderTotal > 0 ? ($femaleCount / $patientsByGenderTotal) * 100 : 0, 'color' => '#D93025'],
    ['label' => 'Male',   'value' => $maleCount,   'pct' => $patientsByGenderTotal > 0 ? ($maleCount / $patientsByGenderTotal) * 100 : 0,   'color' => '#F4A6B0'],
];

// ---------------------------------------------------------------------
// Top 5 Common Health Conditions — real, grouped by label across every
// patient's "medical_conditions" docs (same collection
// includes/firestore/medical_records.php's hc_get_medical_conditions_for_patient()
// reads per-patient). No "facility" field of its own (see header
// comment) — scoped by joining on patientId against $facilityPatientIds
// instead. Fewer than 5 real conditions on record shows fewer than 5
// rows — that's accurate, not a bug.
// ---------------------------------------------------------------------
$healthConditionsRangeOptions = ['all' => 'All Time', 'today' => 'Today', 'this_week' => 'This Week', 'this_month' => 'This Month'];
$healthConditionsRangeKey = array_key_exists($_GET['conditionsRange'] ?? '', $healthConditionsRangeOptions) ? $_GET['conditionsRange'] : 'all';
$healthConditionsRange = $healthConditionsRangeOptions[$healthConditionsRangeKey];

$conditionCounts = [];
foreach ($firestore->listDocuments('medical_conditions') as $doc) {
    if (!in_array($doc['fields']['patientId'] ?? null, $facilityPatientIds, true)) {
        continue;
    }
    if (!hc_matches_date_range_key($doc['fields']['diagnosedOn'] ?? '', $healthConditionsRangeKey)) {
        continue;
    }
    $label = $doc['fields']['label'] ?? null;
    if ($label === null || $label === '') {
        continue;
    }
    $conditionCounts[$label] = ($conditionCounts[$label] ?? 0) + 1;
}
arsort($conditionCounts);
$topHealthConditions = [];
foreach (array_slice($conditionCounts, 0, 5, true) as $label => $value) {
    $topHealthConditions[] = ['label' => $label, 'value' => $value];
}
$topHealthConditionsMax = $topHealthConditions ? max(array_column($topHealthConditions, 'value')) : 1;

// A hardcoded 0/100/200/300/400 axis (sized for the old placeholder's
// hundreds-of-cases numbers) would misrepresent real counts that are
// currently much smaller — round the real max up to a "nice" number
// and split it into 4 even ticks instead.
$niceAxisMax = 1;
foreach ([1, 2, 5, 10, 20, 50, 100, 200, 500, 1000, 2000, 5000, 10000] as $candidate) {
    if ($candidate >= $topHealthConditionsMax) {
        $niceAxisMax = $candidate;
        break;
    }
}
$topHealthConditionsAxisMax = $niceAxisMax;
$topHealthConditionsAxis = [];
for ($i = 0; $i <= 4; $i++) {
    $topHealthConditionsAxis[] = (int) round($niceAxisMax * $i / 4);
}
