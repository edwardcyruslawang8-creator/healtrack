<?php
/**
 * =====================================================================
 * Patient Medications — Data
 * ---------------------------------------------------------------------
 * Reuses includes/patient_profile_firestore.php for everything about the
 * patient themselves ($adminProfile, $patient, $profileTabs) — re-marks
 * the "medications" tab active here. The actual Firestore queries live
 * in includes/firestore/medications.php — shared with medical-staff's
 * identical page. Each row's View button still links to
 * subadmin/medication_details.php?id=<id> — that page was never built
 * (confirmed absent from the codebase) and building it is a separate
 * undertaking, so that link stays a dead end; the Edit button (shown
 * only for active medications) still isn't wired up.
 * =====================================================================
 */

require_once __DIR__ . '/patient_profile_firestore.php';
// $adminProfile, $dashboardDateLabel, $facilityOptions, $patient, $profileTabs
require_once __DIR__ . '/../firestore/medications.php';
require_once __DIR__ . '/../firestore/allergies.php';

foreach ($profileTabs as &$tab) {
    $tab['active'] = ($tab['key'] === 'medications');
}
unset($tab);

// ---------------------------------------------------------------------
// Real medications for this patient.
// ---------------------------------------------------------------------
$medications = hc_get_medications_for_patient($patient['id']);
$medicationsTotal = count($medications);

// Current vs Past — same real 'active' status the Overview tab's
// "Active Medications" summary and hc_medications_summary() below
// already key off; everything else (e.g. 'completed') is Past.
$currentMedications = array_values(array_filter($medications, fn ($m) => $m['status'] === 'active'));
$pastMedications = array_values(array_filter($medications, fn ($m) => $m['status'] !== 'active'));

// ---------------------------------------------------------------------
// Medications Summary — real counts over $medications.
// ---------------------------------------------------------------------
$hasAllergies = count(hc_get_allergies_for_patient($patient['id'])) > 0;
$medicationsSummary = hc_medications_summary($medications, $hasAllergies);

// ---------------------------------------------------------------------
// Medication History — real, vertical timeline. $indicatorColor
// ('green' = added/current, 'blue' = added/past, 'gray' = discontinued)
// is stored per-entry in Firestore rather than derived, since it
// reflects an editorial choice made at seed time, not a computable fact.
// ---------------------------------------------------------------------
$medicationHistory = hc_get_medication_history_for_patient($patient['id']);
