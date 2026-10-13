<?php
/**
 * =====================================================================
 * Patient Management (Doctor) — Data
 * ---------------------------------------------------------------------
 * The actual Firestore query lives in includes/firestore/patients.php's
 * hc_get_all_patients_summary() — the SAME real "patients" collection
 * every other role's Patient Management page reads, not a
 * doctor-specific copy. A patient isn't owned by any one clinician (the
 * "patients" collection has no assignedDoctor field — see
 * includes/doctor/doctor_dashboard_firestore.php's header comment for
 * why the dashboard's own widgets scope differently, via consultations
 * instead), but it IS owned by exactly one facility (the "facility"
 * field api/create-patient.php always stamps from the registering staff
 * member's own real facility) — so $allPatients is filtered down to
 * $doctorProfile['facility'] right after the read below, same as
 * medical-staff/patient_management.php's own copy of this filter. This
 * file only owns $doctorProfile (this doctor's own identity) and the
 * search/gender/age-group filters + pagination, which are page-
 * interaction concerns, not data-access ones.
 *
 * The search box and gender/age filters really filter $patients. Each
 * row's "View Profile" button links to
 * doctor/patient_profile.php?id=<patient id>.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore
require_once __DIR__ . '/../firestore/patients.php';
require_once __DIR__ . '/../shared/fuzzy_search.php'; // hc_fuzzy_match()
require_once __DIR__ . '/../shared/patient_access_requests.php'; // hc_resolve_patient_owning_doctor(), hc_doctor_has_patient_access(), hc_get_pending_owner_requests()

$myUid = $_SESSION['uid'] ?? '';
$myDoc = $myUid !== '' ? $firestore->getDocument('users', $myUid) : null;

$doctorProfile = [
    'name'     => $_SESSION['name'] ?? 'Doctor',
    'role'     => 'Doctor',
    'facility' => $myDoc['facility'] ?? 'North Super Health Center',
];

$dashboardDateLabel = date('F j, Y');
$facilityOptions = ['North Super Health Center', 'East Super Health Center', 'West Super Health Center'];

$genderOptions = ['All Genders', 'Male', 'Female'];
$ageGroupOptions = ['All Age Groups', '0-12', '13-17', '18-30', '31-45', '46-60', '60+'];

// ---------------------------------------------------------------------
// Real patient list. hc_get_all_patients_summary() returns [] both when
// there's genuinely no data AND when the underlying Firestore call
// failed (network issue, or the free-tier daily read quota running
// out — a real, observed failure mode) — $firestore->lastRequestFailed
// is how those two get told apart below, so a quota outage shows a
// real "couldn't load" message instead of the misleading "No patients
// registered yet" empty state.
// ---------------------------------------------------------------------
$allPatients = hc_get_all_patients_summary();
$patientsLoadFailed = $firestore->lastRequestFailed;

// Facility isolation: a patient registered by North's own staff (via
// api/create-patient.php, which always stamps the registering staff
// member's own real facility onto the new patient doc) must stay
// invisible to every other facility's clinicians — this is the actual
// access-control boundary, not just a display filter, since nothing
// below this line ever sees a row outside $doctorProfile['facility'].
$allPatients = array_values(array_filter($allPatients, fn ($p) => $p['facility'] === $doctorProfile['facility']));

// ---------------------------------------------------------------------
// Summary stat cards — real counts/averages over $allPatients.
// ---------------------------------------------------------------------
$thisMonthPrefix = date('Y-m');
$newThisMonthCount = count(array_filter($allPatients, fn ($p) => str_starts_with($p['dateRegisteredRaw'], $thisMonthPrefix)));
$totalPatientsCount = count($allPatients);
$averageAge = $totalPatientsCount > 0 ? (int) round(array_sum(array_column($allPatients, 'age')) / $totalPatientsCount) : 0;

$statCards = [
    ['icon' => 'bi-people-fill',          'iconBg' => '#FCE4E4', 'iconColor' => '#D93025', 'label' => 'Total Patients',                   'value' => $totalPatientsCount, 'staticSub' => 'All registered patients'],
    ['icon' => 'bi-person-plus-fill',     'iconBg' => '#FDECDD', 'iconColor' => '#E8710A', 'label' => 'New Patients (This Month)',        'value' => $newThisMonthCount,  'staticSub' => date('F Y')],
    ['icon' => 'bi-calendar2-check-fill', 'iconBg' => '#EFE7FB', 'iconColor' => '#7C3AED', 'label' => 'Patients Registered (This Month)', 'value' => $newThisMonthCount,  'staticSub' => date('F Y')],
    ['icon' => 'bi-person-fill',          'iconBg' => '#FCE4E4', 'iconColor' => '#D93025', 'label' => 'Average Age',                      'value' => $averageAge,         'staticSub' => 'Years Old'],
];

// ---------------------------------------------------------------------
// Apply the search box (?q=) and gender/age-group filters (?gender=,
// ?ageGroup=) for real.
// ---------------------------------------------------------------------
$searchTerm = trim($_GET['q'] ?? '');
$genderFilter = $_GET['gender'] ?? 'All Genders';
$ageGroupFilter = $_GET['ageGroup'] ?? 'All Age Groups';

$filteredPatients = array_values(array_filter($allPatients, function ($p) use ($searchTerm, $genderFilter, $ageGroupFilter) {
    if ($searchTerm !== '' && !hc_fuzzy_match($p['name'] . ' ' . $p['contact'], $searchTerm)) {
        return false;
    }
    if ($genderFilter !== 'All Genders' && $p['gender'] !== $genderFilter) {
        return false;
    }
    if ($ageGroupFilter !== 'All Age Groups' && hc_patient_age_group($p['age']) !== $ageGroupFilter) {
        return false;
    }
    return true;
}));

// ---------------------------------------------------------------------
// Pagination — real total/page-count from the filtered result above.
// ---------------------------------------------------------------------
$patientsTotal = count($filteredPatients);
$patientsPerPage = 8;
$patientsCurrentPage = max(1, (int) ($_GET['page'] ?? 1));
$patientsTotalPages = max(1, (int) ceil($patientsTotal / $patientsPerPage));
$patientsCurrentPage = min($patientsCurrentPage, $patientsTotalPages);

$patients = array_slice($filteredPatients, ($patientsCurrentPage - 1) * $patientsPerPage, $patientsPerPage);

// ---------------------------------------------------------------------
// Per-doctor ownership (see includes/shared/patient_access_requests.php's
// header comment for the full workflow) — only computed for the current
// page's rows, not every filtered patient. A row this doctor doesn't
// have access to gets a locked state + "Request Access" action instead
// of "View Profile" (see doctor/patient_management.php).
// ---------------------------------------------------------------------
foreach ($patients as &$p) {
    $p['hasAccess'] = hc_doctor_has_patient_access($p, $myUid);
    $owner = $p['hasAccess'] ? null : hc_resolve_patient_owning_doctor($p);
    $p['ownerName'] = $owner['name'] ?? null;
}
unset($p);

// Real requests waiting on THIS doctor (as the owner) to decide —
// surfaced as a panel above the table when non-empty.
$pendingOwnerRequests = hc_get_pending_owner_requests($myUid);

// Centered pagination window (1 … current-1, current, current+1 … last)
// — at 1,500+ patients / 8 per page that's 190+ pages, so a fixed
// "always show 1, 2, 3" window would strand anyone past page 3 with no
// numbered link anywhere near where they actually are.
$patientsPaginationWindow = [];
if ($patientsTotalPages <= 7) {
    for ($p = 1; $p <= $patientsTotalPages; $p++) {
        $patientsPaginationWindow[] = $p;
    }
} else {
    $patientsPaginationWindow[] = 1;
    $windowStart = max(2, $patientsCurrentPage - 1);
    $windowEnd = min($patientsTotalPages - 1, $patientsCurrentPage + 1);
    if ($windowStart > 2) {
        $patientsPaginationWindow[] = '...';
    }
    for ($p = $windowStart; $p <= $windowEnd; $p++) {
        $patientsPaginationWindow[] = $p;
    }
    if ($windowEnd < $patientsTotalPages - 1) {
        $patientsPaginationWindow[] = '...';
    }
    $patientsPaginationWindow[] = $patientsTotalPages;
}

if (!function_exists('hc_pm_query')) {
    /** Builds a ?query string carrying every current filter except the ones in $overrides — used by the pagination links below so Next/Previous/a page number never drops the active search/gender/age-group filter. */
    function hc_pm_query(array $overrides = []): string
    {
        $params = array_merge([
            'q' => $_GET['q'] ?? '',
            'gender' => $_GET['gender'] ?? 'All Genders',
            'ageGroup' => $_GET['ageGroup'] ?? 'All Age Groups',
            'page' => $_GET['page'] ?? 1,
        ], $overrides);

        return http_build_query(array_filter($params, fn ($v) => $v !== '' && $v !== null && $v !== 'All Genders' && $v !== 'All Age Groups'));
    }
}
