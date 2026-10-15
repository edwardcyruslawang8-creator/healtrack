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
    'resolved' => ['label' => 'Resolved', 'bg' => '#EEF0F3', 'color' => '#6B7280'],
];

// ---------------------------------------------------------------------
// Real allergy records for this patient.
// ---------------------------------------------------------------------
$allergyRecords = hc_get_allergies_for_patient($patient['id']);
$allergyRecordsTotal = count($allergyRecords);

// Active vs Inactive/Resolved — same real 'active' status
// $allergyStatusMeta and hc_allergy_summary() below already key off;
// everything else (e.g. 'resolved') is Inactive.
$activeAllergies = array_values(array_filter($allergyRecords, fn ($a) => $a['status'] === 'active'));
$inactiveAllergies = array_values(array_filter($allergyRecords, fn ($a) => $a['status'] !== 'active'));

// The record shown in the "Allergy Details" panel below the table —
// whichever row's "Select" action was clicked (?recordId=), falling
// back to the most recently added record.
$selectedAllergy = hc_resolve_selected_allergy($allergyRecords, $_GET['recordId'] ?? null);

// ---------------------------------------------------------------------
// Allergy Summary — real counts over $allergyRecords.
// ---------------------------------------------------------------------
$allergySummary = hc_allergy_summary($allergyRecords);
