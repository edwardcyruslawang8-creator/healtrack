<?php
/**
 * =====================================================================
 * Patient Management — Data
 * ---------------------------------------------------------------------
 * FACILITY ISOLATION: a patient registered by North's own staff (via
 * api/create-patient.php, which always stamps the registering staff
 * member's own real facility onto the new patient doc) must stay
 * invisible to every other facility's sub-admin — this is the actual
 * access-control boundary, not just a display filter. $allPatients is
 * read once below and immediately filtered to $adminProfile['facility']
 * (never a client-supplied value — resolved server-side from this
 * sub-admin's own real users/{uid} doc, same as every other facility-
 * scoped read in this app), before anything else — stat cards, search,
 * gender/age filters, and pagination all operate on that already-scoped
 * list, so nothing below this file can leak another facility's patient.
 *
 * Two Firestore access paths ($patientsUsingRealQuery): with no
 * search/filter active, server-side counts plus one indexed page query
 * ("WHERE facility == X ORDER BY dateRegistered DESC" — composite index
 * patients (facility ASC, dateRegistered DESC), created manually in the
 * Firebase console since this app's service account can't deploy
 * indexes; Average Age also needs patients (facility ASC, age ASC)).
 * With a search/filter active, just this facility's patients are read
 * (single-field filter, no index) and searched/filtered in PHP.
 *
 * $patientsLoadFailed still distinguishes "genuinely no data" from "the
 * read itself failed" (quota, network) the same way it did before —
 * see firebase/FirestoreRestClient.php's $lastRequestFailed.
 *
 * The actual stat-card/search/filter/pagination computation below now
 * lives in includes/shared/patient_management_shared.php's
 * hc_compute_patient_management_for_facility() — this file just
 * resolves the sub-admin's own real facility and hands it off, so the
 * same logic isn't duplicated a second time for
 * includes/admin/patient_management_firestore.php (which lets a System
 * Administrator pick any facility). See that shared file's own header
 * comment for why extract() is used here.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore
require_once __DIR__ . '/../shared/patient_management_shared.php'; // hc_compute_patient_management_for_facility(), hc_pm_query()

$myUid = $_SESSION['uid'] ?? '';
$myDoc = $myUid !== '' ? $firestore->getDocument('users', $myUid) : null;

$adminProfile = [
    'name' => $_SESSION['name'] ?? 'Sub-Administrator',
    'role' => match ($_SESSION['role'] ?? null) {
        'subadmin' => 'Sub-Admin',
        'healthcare_staff' => 'Healthcare Staff',
        default => 'Sub-Administrator',
    },
    'facility' => $myDoc['facility'] ?? 'Unassigned Facility',
];

$dashboardDateLabel = date('F j, Y');
$facilityOptions = ['North Super Health Center', 'East Super Health Center', 'West Super Health Center'];

// Stat cards, $patients, pagination — see includes/shared/patient_management_shared.php.
extract(hc_compute_patient_management_for_facility($adminProfile['facility']));
