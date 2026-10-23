<?php
/**
 * =====================================================================
 * Patient Management (Doctor) — Data
 * ---------------------------------------------------------------------
 * Data comes from includes/shared/patient_management_shared.php's
 * hc_compute_patient_management_for_facility() — the same stat cards,
 * filters and pagination every role's Patient Management page uses. A patient isn't owned by any one clinician (the
 * "patients" collection has no assignedDoctor field — see
 * includes/doctor/doctor_dashboard_firestore.php's header comment for
 * why the dashboard's own widgets scope differently, via consultations
 * instead), but it IS owned by exactly one facility (the "facility"
 * field api/create-patient.php always stamps from the registering staff
 * member's own real facility) — so everything is scoped to
 * $doctorProfile['facility']. This file only owns $doctorProfile (this
 * doctor's own identity).
 *
 * The search box and gender/age filters really filter $patients. Each
 * row's "View Profile" button links to
 * doctor/patient_profile.php?id=<patient id>.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore
require_once __DIR__ . '/../firestore/patients.php';

$myUid = $_SESSION['uid'] ?? '';
$myDoc = $myUid !== '' ? $firestore->getDocument('users', $myUid) : null;

$doctorProfile = [
    'name'     => $_SESSION['name'] ?? 'Doctor',
    'role'     => 'Doctor',
    'facility' => $myDoc['facility'] ?? 'North Super Health Center',
];

$dashboardDateLabel = date('F j, Y');
$facilityOptions = ['North Super Health Center', 'East Super Health Center', 'West Super Health Center'];

require_once __DIR__ . '/../shared/patient_management_shared.php'; // hc_compute_patient_management_for_facility(), hc_pm_query()

// Stat cards, $patients, pagination — all scoped to this clinician's own
// facility, which is the access-control boundary (api/create-patient.php
// stamps the registering staff member's facility on every patient).
// Same indexed queries the sub-admin/admin pages use — see
// includes/shared/patient_management_shared.php.
extract(hc_compute_patient_management_for_facility($doctorProfile['facility']));
