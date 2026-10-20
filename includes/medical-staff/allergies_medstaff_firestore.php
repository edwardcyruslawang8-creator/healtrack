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
