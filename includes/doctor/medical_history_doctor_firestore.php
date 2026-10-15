<?php
/**
 * =====================================================================
 * Patient Medical History (Doctor) — Data
 * ---------------------------------------------------------------------
 * Reuses includes/doctor/patient_profile_doctor_firestore.php for
 * everything about the patient themselves ($doctorProfile, $patient,
 * $profileTabs) — this page IS that same patient's profile, just
 * viewing the "Medical History" tab (was "Medical Records") instead of
 * "Overview". See subadmin/medical_history_firestore.php's header
 * comment for the full reasoning — identical data layer, just a
 * different $*Profile source for the facility/access gate.
 * =====================================================================
 */

require_once __DIR__ . '/patient_profile_doctor_firestore.php';
// $doctorProfile, $dashboardDateLabel, $facilityOptions, $patient, $profileTabs
require_once __DIR__ . '/../firestore/medical_records.php'; // hc_get_medical_conditions_for_patient(), hc_get_surgical_history_for_patient()
require_once __DIR__ . '/../firestore/family_history.php'; // hc_get_family_history_for_patient()

// Re-mark which tab is active — this page shows "Medical History"
// instead of patient_profile.php's "Overview". Tab key stays
// 'medical_records' internally — see subadmin's version of this file.
foreach ($profileTabs as &$tab) {
    $tab['active'] = ($tab['key'] === 'medical_records');
}
unset($tab);

$chronicConditions = hc_get_medical_conditions_for_patient($patient['id']);
$surgicalHistory = hc_get_surgical_history_for_patient($patient['id']);
$familyHistory = hc_get_family_history_for_patient($patient['id']);
