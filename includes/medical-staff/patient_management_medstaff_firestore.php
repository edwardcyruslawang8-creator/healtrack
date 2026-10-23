<?php
/**
 * =====================================================================
 * Patient Management (Medical Staff) — Data
 * ---------------------------------------------------------------------
 * Data comes from includes/shared/patient_management_shared.php's
 * hc_compute_patient_management_for_facility() — the same facility-scoped
 * stat cards, search/gender/age-group filters and pagination every
 * role's Patient Management page uses. This file only owns $staffProfile
 * (this medical staff member's own identity, incl. their facility).
 *
 * The search box and gender/age filters really filter $patients. Each
 * row's "View Profile" button links to
 * medical-staff/patient_profile.php?id=<patient id>.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore
require_once __DIR__ . '/../firestore/patients.php';

$myUid = $_SESSION['uid'] ?? '';
$myDoc = $myUid !== '' ? $firestore->getDocument('users', $myUid) : null;

$staffProfile = [
    'name'     => $_SESSION['name'] ?? 'Medical Staff',
    'role'     => 'Medical Staff',
    'facility' => $myDoc['facility'] ?? 'North Super Health Center',
];

$dashboardDateLabel = date('F j, Y');
$facilityOptions = ['North Super Health Center', 'East Super Health Center', 'West Super Health Center'];

require_once __DIR__ . '/../shared/patient_management_shared.php'; // hc_compute_patient_management_for_facility(), hc_pm_query()

// Stat cards, $patients, pagination — all scoped to this staff member's own
// facility, which is the access-control boundary (api/create-patient.php
// stamps the registering staff member's facility on every patient).
// Same indexed queries the sub-admin/admin pages use — see
// includes/shared/patient_management_shared.php.
extract(hc_compute_patient_management_for_facility($staffProfile['facility']));
