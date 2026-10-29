<?php
/**
 * =====================================================================
 * Patient Profile (Doctor) — Data
 * ---------------------------------------------------------------------
 * Role-owned wrapper around the shared includes/firestore/ layer:
 * derives $doctorProfile (this doctor's own name/role/facility) and
 * resolves $patient from the real ?id= querystring, then calls
 * hc_get_patient_overview() for the Overview tab — the exact same
 * contract includes/medical-staff/patient_profile_medstaff_firestore.php
 * and includes/subadmin/patient_profile_firestore.php follow, so all
 * three stay in lockstep by construction. A patient looks identical
 * regardless of which role views them — this is intentionally the SAME
 * data, not a doctor-specific copy.
 *
 * "Overview" is the only tab with real in-page content here — Medical
 * Records, Laboratory & X-ray, Vaccination Records, Allergies,
 * Medications, Documents, and History each now have their own real
 * doctor/*.php page (medical_records.php, patient_laboratory_xray.php,
 * vaccination_records.php, allergies.php, medications.php,
 * documents.php, history.php), reached via real links in the tab strip
 * — same pattern medical-staff's/subadmin's patient_profile.php already
 * use.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore
require_once __DIR__ . '/../firestore/patients.php';
require_once __DIR__ . '/../firestore/patient_overview.php';
require_once __DIR__ . '/../shared/patient_consent.php'; // hc_patient_consent_status()

$myUid = $_SESSION['uid'] ?? '';
$myDoc = $myUid !== '' ? $firestore->getDocument('users', $myUid) : null;

$doctorProfile = [
    'name'     => $_SESSION['name'] ?? 'Doctor',
    'role'     => 'Doctor',
    'facility' => $myDoc['facility'] ?? 'North Super Health Center',
];

$dashboardDateLabel = date('F j, Y');
$facilityOptions = ['North Super Health Center', 'East Super Health Center', 'West Super Health Center'];

// ---------------------------------------------------------------------
// Patient — real, resolved from ?id= against the "patients" collection
// (same collection/shape every role's Patient Profile page reads).
// ---------------------------------------------------------------------
$requestedPatientId = $_GET['id'] ?? '';
$patient = hc_get_patient_profile($requestedPatientId);
// Facility isolation: a real patient record from another facility must
// render exactly like an invalid id — never leak that it exists at all
// to clinicians outside the facility that registered them (see
// includes/doctor/patient_management_doctor_firestore.php's header
// comment for the registration/listing side of this same rule). Every
// doctor at a facility can SEE this same-facility patient exists (e.g.
// in Patient Management's own list) — whether they can open the real
// PROFILE is a separate, real per-patient consent check now (see
// hc_patient_consent_status() below), not blanket facility-wide access
// like it used to be. A patient from a DIFFERENT facility is also
// resolvable here when their own real "Share My Information" consent
// (Add Patient wizard, api/create-patient.php's 'consentShareInfo') is
// true — same as api/quick-search-patients.php's own matching now
// allows — but still goes through that exact same consent gate either
// way.
if ($patient !== null && $patient['facility'] !== $doctorProfile['facility'] && !$patient['consentShareInfo']) {
    $patient = null;
    // Every per-tab page (allergies.php, medications.php, medical_records.php,
    // ...) requires this file first for $patient, then independently
    // fetches more per-patient data keyed off $patient['id'] — blanking
    // the id here (not just the identity card above) is what actually
    // stops those from leaking another facility's real records too,
    // since hc_not_found_patient_profile() otherwise echoes the
    // requested id straight back.
    $requestedPatientId = '';
}
$patient = $patient ?? hc_not_found_patient_profile($requestedPatientId);

// ---------------------------------------------------------------------
// Patient-consent gate — real, see includes/shared/patient_consent.php's
// own header comment for the 3 ways in (same-day registrant, same-day
// assigned consultation, or real accepted patient consent). Every
// per-tab page requires THIS file first, same as patient_profile.php
// itself — gating here, once, covers all of them; every page but
// patient_profile.php itself just bounces back to it instead of
// duplicating the restricted-access UI in each one.
// ---------------------------------------------------------------------
$accessRestricted = false;
$consentStatus = null;
if ($requestedPatientId !== '') {
    $consentStatus = hc_patient_consent_status($requestedPatientId, $myUid, $_SESSION['role'] ?? '', $_SESSION['name'] ?? '');
    if (!$consentStatus['access']) {
        $accessRestricted = true;
        if (basename($_SERVER['SCRIPT_NAME']) !== 'patient_profile.php') {
            header('Location: patient_profile.php?id=' . urlencode($requestedPatientId));
            exit;
        }
    }
}

// ---------------------------------------------------------------------
// Profile tabs — only Overview is real; see this file's header comment
// for why every other tab stays an inert placeholder pane for now.
// ---------------------------------------------------------------------
$profileTabs = [
    ['key' => 'overview',        'icon' => 'bi-person-fill',            'label' => 'Overview', 'active' => true],
    ['key' => 'medical_records', 'icon' => 'bi-folder2-open',           'label' => 'Medical History'],
    ['key' => 'medications',     'icon' => 'bi-capsule',                'label' => 'Medications'],
    ['key' => 'allergies',       'icon' => 'bi-shield-fill-check',      'label' => 'Allergies'],
    ['key' => 'lab_xray',        'icon' => 'bi-droplet-fill',           'label' => 'Laboratory & X-ray'],
    ['key' => 'vaccination',     'icon' => 'bi-clipboard2-pulse-fill',  'label' => 'Vaccination Records'],
    ['key' => 'documents',       'icon' => 'bi-file-earmark-text-fill', 'label' => 'Documents'],
    ['key' => 'history',         'icon' => 'bi-clock-history',          'label' => 'History'],
];

// ---------------------------------------------------------------------
// Overview tab — real, all scoped to $patient['id']. Skipped entirely
// when access is restricted.
// ---------------------------------------------------------------------
if (!$accessRestricted) {
    $overview = hc_get_patient_overview($patient['id']);
    $summary = $overview['summary'];
    $activeMedicalConditions = $overview['activeMedicalConditions'];
    $latestConsultation = $overview['latestConsultation'];
    $recentLabResults = $overview['recentLabResults'];
    $recentXray = $overview['recentXray'];
    $vaccinationHistory = $overview['vaccinationHistory'];
} else {
    $summary = ['totalConsultations' => 0, 'activeMedicalConditions' => 0, 'allergies' => 0, 'currentMedications' => 0];
    $activeMedicalConditions = [];
    $latestConsultation = null;
    $recentLabResults = [];
    $recentXray = [];
    $vaccinationHistory = [];
}

$labStatusMeta = [
    'normal'   => ['label' => 'Normal',   'bg' => '#E4F7EA', 'color' => '#1E8E3E'],
    'abnormal' => ['label' => 'Abnormal', 'bg' => '#FCE4E4', 'color' => '#D93025'],
];

$vaccineStatusMeta = [
    'completed' => ['label' => 'Completed', 'bg' => '#E4F7EA', 'color' => '#1E8E3E'],
    'pending'   => ['label' => 'Pending',   'bg' => '#FDECDD', 'color' => '#E8710A'],
];
