<?php
/**
 * =====================================================================
 * Patient Profile (Medical Staff) — Data
 * ---------------------------------------------------------------------
 * Role-owned wrapper around the shared includes/data/ layer: derives
 * $staffProfile (this medical staff member's own name/role/facility)
 * and resolves $patient from the real ?id= querystring, then calls
 * hc_get_patient_overview() for the Overview tab. All the actual
 * Firestore querying lives in includes/firestore/patients.php and
 * includes/firestore/patient_overview.php — this file just supplies this
 * page's role-specific identity and hands off to it, the same
 * contract includes/patient_profile_firestore.php (subadmin's equivalent)
 * follows too, so the two stay in lockstep by construction. A patient
 * looks identical whether a sub-admin or a medical staff member views
 * them — this is intentionally the SAME data, not a
 * medical-staff-specific copy.
 *
 * medical_records.php, patient_laboratory_xray.php,
 * vaccination_records.php, allergies.php, medications.php, and
 * history.php now exist under medical-staff/ too (mirroring their
 * subadmin/ counterparts) and are linked to from the tab strip. Only
 * "Documents" has no medical-staff page yet, so it stays an inert
 * placeholder tab.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore
require_once __DIR__ . '/../firestore/patients.php';
require_once __DIR__ . '/../firestore/patient_overview.php';
require_once __DIR__ . '/../shared/patient_consent.php'; // hc_patient_consent_status()

$myUid = $_SESSION['uid'] ?? '';
$myDoc = $myUid !== '' ? $firestore->getDocument('users', $myUid) : null;

$staffProfile = [
    'name'     => $_SESSION['name'] ?? 'Medical Staff',
    'role'     => 'Medical Staff',
    'facility' => $myDoc['facility'] ?? 'North Super Health Center',
];

$dashboardDateLabel = date('F j, Y');
$facilityOptions = ['North Super Health Center', 'East Super Health Center', 'West Super Health Center'];

// ---------------------------------------------------------------------
// Patient — real, resolved from ?id= against the "patients" collection
// (same collection/shape as includes/patient_profile_firestore.php).
// ---------------------------------------------------------------------
$requestedPatientId = $_GET['id'] ?? '';
$patient = hc_get_patient_profile($requestedPatientId);
// Facility isolation: a real patient record from another facility must
// render exactly like an invalid id — never leak that it exists at all
// to staff outside the facility that registered them (see
// includes/medical-staff/patient_management_medstaff_firestore.php's
// header comment for the registration/listing side of this same rule).
if ($patient !== null && $patient['facility'] !== $staffProfile['facility']) {
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
// own header comment for the 3 ways in. Every per-tab page requires
// THIS file first, same as patient_profile.php itself — gating here,
// once, covers all of them; every page but patient_profile.php itself
// just bounces back to it instead of duplicating the restricted-access
// UI in each one.
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
// Profile tabs — Documents has no medical-staff page yet, so it stays
// an inert placeholder pane; every other tab now links to a real page.
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
