<?php
/**
 * =====================================================================
 * Patient Profile — Data
 * ---------------------------------------------------------------------
 * Role-owned wrapper around the shared includes/data/ layer: derives
 * $adminProfile (sub-admin's own name/role/facility) and resolves
 * $patient from the real ?id= querystring, then calls
 * hc_get_patient_overview() for the Overview tab. All the actual
 * Firestore querying lives in includes/firestore/patients.php and
 * includes/firestore/patient_overview.php — this file just supplies this
 * page's role-specific identity and hands off to it, the same
 * contract includes/patient_profile_medstaff_firestore.php (medical-staff's
 * equivalent) follows too, so the two stay in lockstep by construction
 * instead of by two people remembering to update both files whenever
 * the query logic changes.
 *
 * The 7 other tabs (medical_records.php, patient_laboratory_xray.php,
 * vaccination_records.php, allergies.php, medications.php,
 * documents.php, history.php) each follow the same pattern — see their
 * own includes/*_data.php header comments.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore
require_once __DIR__ . '/../firestore/patients.php';
require_once __DIR__ . '/../firestore/patient_overview.php';
require_once __DIR__ . '/../shared/patient_consent.php'; // hc_patient_consent_status()

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
// Patient — real, resolved from ?id= against the "patients" collection.
// ---------------------------------------------------------------------
$requestedPatientId = $_GET['id'] ?? '';
$patient = hc_get_patient_profile($requestedPatientId);
// Facility isolation: a real patient record from another facility must
// render exactly like an invalid id — never leak that it exists at all
// to a sub-admin outside the facility that registered them (see
// includes/subadmin/patient_management_firestore.php's header comment
// for the registration/listing side of this same rule) — UNLESS this
// patient's own real "Share My Information" consent (Add Patient
// wizard, api/create-patient.php's 'consentShareInfo') is true, in
// which case they're real and findable from any facility, same as
// api/quick-search-patients.php's own matching now allows. Opening the
// full profile from here still goes through the exact same
// hc_patient_consent_status() gate below either way — this check only
// decides whether the patient is visible to resolve at all.
if ($patient !== null && $patient['facility'] !== $adminProfile['facility'] && !$patient['consentShareInfo']) {
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
// own header comment for the 3 ways in. $requestedPatientId is '' for
// an invalid/other-facility id (handled above already), so this only
// runs for a genuinely real, same-facility patient.
//
// Every per-tab page (allergies.php, medications.php, medical_history.php,
// patient_laboratory_xray.php, vaccination_records.php, documents.php,
// history.php) requires THIS file first, same as patient_profile.php
// itself — so gating here, once, covers all of them. Only
// patient_profile.php itself (identified by its own real script name)
// renders the "Access Restricted" card in place of the profile; every
// other per-tab page just bounces back to it rather than duplicating
// that UI 7 more times — there's nothing for them to show on their own
// once the underlying profile itself isn't allowed to open.
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
// Profile tabs — only Overview has real content for now; the rest
// render as empty panes, ready for content later without changing the
// tab strip itself.
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
// when access is restricted — nothing here should ever be fetched,
// let alone rendered, until real consent exists.
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
