<?php
/**
 * =====================================================================
 * Patient Medications (Doctor) — Data
 * ---------------------------------------------------------------------
 * Reuses includes/doctor/patient_profile_doctor_firestore.php for
 * everything about the patient themselves ($doctorProfile, $patient,
 * $profileTabs) — re-marks the "medications" tab active here. The
 * actual Firestore queries live in includes/firestore/medications.php —
 * shared with every other role's identical page. Each row's View
 * button still links to medication_details.php?id=<id> — that page was
 * never built on subadmin or medical-staff either, and building it is
 * a separate undertaking, so that link stays a dead end, matching
 * those pages exactly; the Edit button (shown only for active
 * medications) still isn't wired up.
 * =====================================================================
 */

require_once __DIR__ . '/patient_profile_doctor_firestore.php';
// $doctorProfile, $dashboardDateLabel, $facilityOptions, $patient, $profileTabs
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

// Current vs Past — hc_medication_classify() (includes/firestore/medications.php)
// is the single rule, recalculated from each prescription's own dates on
// every view: a course moves to Past by itself the day after its end
// date; discontinued/completed ones are Past; maintenance medications
// with no end date stay Current.
$currentMedications = array_values(array_filter($medications, fn ($m) => $m['isCurrent']));
$pastMedications = array_values(array_filter($medications, fn ($m) => !$m['isCurrent']));

// ---------------------------------------------------------------------
// Medications Summary — real counts over $medications.
// ---------------------------------------------------------------------
$hasAllergies = count(hc_get_allergies_for_patient($patient['id'])) > 0;
$medicationsSummary = hc_medications_summary($medications, $hasAllergies);

// ---------------------------------------------------------------------
// Medication History — real, vertical timeline.
// ---------------------------------------------------------------------
$medicationHistory = hc_get_medication_history_for_patient($patient['id'], $medications);

// Only a doctor may discontinue / extend / confirm a medication
// (api/update-medication.php re-checks this server-side).
$canManageMedications = true;
