<?php
/**
 * =====================================================================
 * Patient Allergies (Doctor) — Data
 * ---------------------------------------------------------------------
 * Reuses includes/doctor/patient_profile_doctor_firestore.php for
 * everything about the patient themselves ($doctorProfile, $patient,
 * $profileTabs) — re-marks the "allergies" tab active here. The actual
 * Firestore query lives in includes/firestore/allergies.php's
 * hc_get_allergies_for_patient() — shared with every other role's
 * identical page.
 * =====================================================================
 */

require_once __DIR__ . '/patient_profile_doctor_firestore.php';
// $doctorProfile, $dashboardDateLabel, $facilityOptions, $patient, $profileTabs
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

// The record shown in the "Allergy Details" panel below the table —
// whichever row's "Select" action was clicked (?recordId=), falling
// back to the most recently added record.
$selectedAllergy = hc_resolve_selected_allergy($allergyRecords, $_GET['recordId'] ?? null);

// ---------------------------------------------------------------------
// Allergy Summary — real counts over $allergyRecords.
// ---------------------------------------------------------------------
$allergySummary = hc_allergy_summary($allergyRecords);
