<?php
/**
 * =====================================================================
 * Patient Allergies (Medical Staff) — Data
 * ---------------------------------------------------------------------
 * Reuses includes/patient_profile_medstaff_firestore.php for everything
 * about the patient themselves ($staffProfile, $patient, $profileTabs)
 * — re-marks the "allergies" tab active here. The actual Firestore
 * query lives in includes/firestore/allergies.php's
 * hc_get_allergies_for_patient() — shared with subadmin's identical
 * page.
 * =====================================================================
 */

require_once __DIR__ . '/patient_profile_medstaff_firestore.php';
// $staffProfile, $dashboardDateLabel, $facilityOptions, $patient, $profileTabs
require_once __DIR__ . '/../firestore/allergies.php';

foreach ($profileTabs as &$tab) {
    $tab['active'] = ($tab['key'] === 'allergies');
}
unset($tab);

// Severity -> badge color meta.
$severityMeta = [
    'severe'   => ['label' => 'Severe',   'bg' => '#FCE4E4', 'color' => '#D93025'],
    'moderate' => ['label' => 'Moderate', 'bg' => '#FDECDD', 'color' => '#E8710A'],
    'mild'     => ['label' => 'Mild',     'bg' => '#E8F0FE', 'color' => '#1A73E8'],
];

$allergyStatusMeta = [
    'active'   => ['label' => 'Active',   'bg' => '#E4F7EA', 'color' => '#1E8E3E'],
    'inactive' => ['label' => 'Inactive', 'bg' => '#FDECDD', 'color' => '#E8710A'],
    'resolved' => ['label' => 'Resolved', 'bg' => '#EEF0F3', 'color' => '#6B7280'],
];

// Verification status (HL7 FHIR AllergyIntolerance.verificationStatus
// concept) — independent axis from clinical status; set/changed by
// api/update-allergy-clinical-status.php.
$verificationStatusMeta = [
    'unconfirmed'      => ['label' => 'Unconfirmed',      'bg' => '#EEF0F3', 'color' => '#6B7280'],
    'presumed'         => ['label' => 'Presumed',         'bg' => '#E8F0FE', 'color' => '#1A73E8'],
    'confirmed'        => ['label' => 'Confirmed',        'bg' => '#E4F7EA', 'color' => '#1E8E3E'],
    'refuted'          => ['label' => 'Refuted',          'bg' => '#FCE4E4', 'color' => '#D93025'],
    'entered-in-error' => ['label' => 'Entered in Error', 'bg' => '#FCE4E4', 'color' => '#D93025'],
];

// ---------------------------------------------------------------------
// Real allergy records for this patient.
// ---------------------------------------------------------------------
$allergyRecords = hc_get_allergies_for_patient($patient['id']);
$allergyRecordsTotal = count($allergyRecords);

// Active vs Inactive/Resolved — keyed off the real 3-value
// clinicalStatus field (api/update-allergy-clinical-status.php); the
// Inactive/Resolved table holds both 'inactive' and 'resolved' records.
$activeAllergies = array_values(array_filter($allergyRecords, fn ($a) => $a['clinicalStatus'] === 'active'));
$inactiveAllergies = array_values(array_filter($allergyRecords, fn ($a) => $a['clinicalStatus'] !== 'active'));

// ---------------------------------------------------------------------
// Allergy Summary — real counts over $allergyRecords.
// ---------------------------------------------------------------------
$allergySummary = hc_allergy_summary($allergyRecords);

// ---------------------------------------------------------------------
// "Add Allergy Record" modal (includes/medical-staff/add_allergy_modal.php)
// ---------------------------------------------------------------------
$allergyTypeOptions = array_keys(hc_allergy_type_meta()); // Environmental, Food, Medication, Other
// Onset Date can't predate birth. $patient only has the display-formatted
// birth date ("Mar 4, 1990"), which strtotime() parses.
$algBirthDateRaw = $patient['birthDate'] !== '—' && strtotime($patient['birthDate']) ? date('Y-m-d', strtotime($patient['birthDate'])) : '';
