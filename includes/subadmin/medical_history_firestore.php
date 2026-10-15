<?php
/**
 * =====================================================================
 * Patient Medical History — Data
 * ---------------------------------------------------------------------
 * Reuses includes/subadmin/patient_profile_firestore.php for everything
 * about the patient themselves ($adminProfile, $patient, $profileTabs)
 * — this page IS that same patient's profile, just viewing the
 * "Medical History" tab (was "Medical Records") instead of "Overview".
 *
 * Replaces the old consultation/lab-type "medical_records" collection
 * view (decorative View/Download buttons, never wired up) with three
 * real, editable sections — Chronic Conditions, Past Surgeries/
 * Procedures, Family History — see includes/firestore/medical_records.php's
 * hc_get_medical_conditions_for_patient()/hc_get_surgical_history_for_patient()
 * and includes/firestore/family_history.php's hc_get_family_history_for_patient().
 * Add/Edit/Delete for all three go through api/add-condition.php /
 * api/add-surgery.php / api/add-family-history.php (and their
 * update-/delete- counterparts), wired by assets/js/medical-history.js.
 * =====================================================================
 */

require_once __DIR__ . '/patient_profile_firestore.php';
// $adminProfile, $dashboardDateLabel, $facilityOptions, $patient, $profileTabs
require_once __DIR__ . '/../firestore/medical_records.php'; // hc_get_medical_conditions_for_patient(), hc_get_surgical_history_for_patient()
require_once __DIR__ . '/../firestore/family_history.php'; // hc_get_family_history_for_patient()

// Re-mark which tab is active — this page shows "Medical History"
// instead of patient_profile.php's "Overview". Tab key stays
// 'medical_records' internally (only the label/file changed) so every
// other profile page's tab-strip branch doesn't need its key touched.
foreach ($profileTabs as &$tab) {
    $tab['active'] = ($tab['key'] === 'medical_records');
}
unset($tab);

$chronicConditions = hc_get_medical_conditions_for_patient($patient['id']);
$surgicalHistory = hc_get_surgical_history_for_patient($patient['id']);
$familyHistory = hc_get_family_history_for_patient($patient['id']);
