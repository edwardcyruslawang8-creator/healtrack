<?php
/**
 * =====================================================================
 * Consultations (Doctor) — Data
 * ---------------------------------------------------------------------
 * CONNECTED — reads the real "consultations" Firestore collection via
 * includes/firestore/consultations.php, the same collection every other
 * role's consultations page reads. Unlike medical-staff/consultations.php
 * (a facility-wide log), this page is scoped to the signed-in doctor's
 * OWN caseload — every consultation whose real "assignedPersonnel"
 * field matches this doctor's name — same scoping and same
 * name-matching limitation as includes/doctor/doctor_dashboard_firestore.php.
 *
 * "Next to Consult" is this doctor's own earliest still-PENDING
 * consultation (not yet started), independent of the table's own
 * filters below — it's a fixed operational widget, not filtered data.
 * The "Patient Information" card shows that same patient (there's no
 * separate patient-selection step on this page; reaching it is just
 * the sidebar's top-level "Consultations" link), so the page never
 * shows two unrelated patients at once. "Start Consultation" (see
 * assets/js/doctor_consultations.js) flips that consultation's status
 * to "ongoing" via api/update-consultation.php, then reloads so the
 * next-earliest pending consultation (if any) becomes the new "Next to
 * Consult".
 *
 * The search box, type/status filters, and date range (defaulting to
 * TODAY, matching this page's queue-log framing) all really filter the
 * table below — same pattern as medical-staff/consultations.php's
 * toolbar, just scoped to $myConsultations instead of every consultation.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore
require_once __DIR__ . '/../firestore/patients.php';
require_once __DIR__ . '/../firestore/consultations.php';
require_once __DIR__ . '/../shared/fuzzy_search.php'; // hc_fuzzy_match()

$myUid = $_SESSION['uid'] ?? '';
$myDoc = $myUid !== '' ? $firestore->getDocument('users', $myUid) : null;
$myName = $_SESSION['name'] ?? 'Doctor';

$doctorProfile = [
    'name'     => $myName,
    'role'     => 'Doctor',
    'facility' => $myDoc['facility'] ?? 'North Super Health Center',
];

$dashboardDateLabel = date('F j, Y');
$facilityOptions = ['North Super Health Center', 'East Super Health Center', 'West Super Health Center'];

// ---------------------------------------------------------------------
// Filter toolbar option lists — display label "In Progress" for the
// "ongoing" status on THIS page only (matching this page's own design
// reference), without touching hc_consultation_status_meta()'s
// "Ongoing" wording used everywhere else in the app.
// ---------------------------------------------------------------------
$consultationTypeMeta = hc_consultation_type_meta();
$consultationStatusMeta = hc_consultation_status_meta();
$consultationStatusMeta['ongoing']['label'] = 'In Progress';

$consultationTypeOptions = ['All Types' => 'All Consultation Types'];
foreach ($consultationTypeMeta as $key => $meta) {
    $consultationTypeOptions[$key] = $meta['label'];
}
$consultationStatusOptions = ['All Status' => 'All Status'];
foreach ($consultationStatusMeta as $key => $meta) {
    $consultationStatusOptions[$key] = $meta['label'];
}

$patientOptions = hc_get_all_patients_summary();
$patientsById = array_column($patientOptions, null, 'id');

// ---------------------------------------------------------------------
// "Next to Consult" (priority-ordered — Senior/PWD/Pregnant ahead of
// unflagged, then soonest scheduled time), "Resume Consultation"
// (this doctor's own already-STARTED consultation, if any — the only
// way back into active_consultation.php after navigating away or
// reloading mid-consultation), and "Patient Information" (the same
// patient as "Next to Consult") — all derived by the shared
// hc_get_doctor_queue_status() (includes/firestore/consultations.php)
// so this page and api/doctor-queue-status.php (polled by
// assets/js/doctor_consultations.js so this page updates itself once
// medical staff sets a new consultation, without a manual reload)
// compute the exact same snapshot.
// ---------------------------------------------------------------------
$queueStatus = hc_get_doctor_queue_status($myName);
$myConsultations = $queueStatus['myConsultations'];
$nextConsult = $queueStatus['nextConsult'];
$nextPatient = $queueStatus['nextPatient'];
$ongoingConsult = $queueStatus['ongoingConsult'];
$ongoingPatient = $queueStatus['ongoingPatient'];
$ongoingAwaitingLab = $queueStatus['ongoingAwaitingLab'];
$patient = $queueStatus['patient'];

// ---------------------------------------------------------------------
// Search (?q= matches patient name, patient id, or reason for visit) +
// type/status filters (?type=, ?status=) + date range (?from=, ?to=,
// defaulting to today) — all real, applied to $myConsultations only.
// ---------------------------------------------------------------------
$searchTerm = trim($_GET['q'] ?? '');
$typeFilter = $_GET['type'] ?? 'All Types';
$statusFilter = $_GET['status'] ?? 'All Status';
$today = date('Y-m-d');
$dateFrom = trim($_GET['from'] ?? $today);
$dateTo = trim($_GET['to'] ?? $today);

$filteredConsultations = array_values(array_filter($myConsultations, function ($c) use ($searchTerm, $typeFilter, $statusFilter, $dateFrom, $dateTo) {
    if ($searchTerm !== '' && !hc_fuzzy_match($c['patientName'] . ' ' . $c['patientId'] . ' ' . $c['chiefComplaint'], $searchTerm)) {
        return false;
    }
    if ($typeFilter !== 'All Types' && $c['consultationType'] !== $typeFilter) {
        return false;
    }
    if ($statusFilter !== 'All Status' && $c['status'] !== $statusFilter) {
        return false;
    }
    $consultDate = substr($c['dateTime'], 0, 10);
    if ($dateFrom !== '' && $consultDate < $dateFrom) {
        return false;
    }
    if ($dateTo !== '' && $consultDate > $dateTo) {
        return false;
    }
    return true;
}));
usort($filteredConsultations, fn ($a, $b) => strcmp($a['dateTime'], $b['dateTime']));

// ---------------------------------------------------------------------
// Pagination
// ---------------------------------------------------------------------
$consultationsTotal = count($filteredConsultations);
$consultationsPerPage = 5;
$consultationsCurrentPage = max(1, (int) ($_GET['page'] ?? 1));
$consultationsTotalPages = max(1, (int) ceil($consultationsTotal / $consultationsPerPage));
$consultationsCurrentPage = min($consultationsCurrentPage, $consultationsTotalPages);

$consultations = array_slice($filteredConsultations, ($consultationsCurrentPage - 1) * $consultationsPerPage, $consultationsPerPage);
