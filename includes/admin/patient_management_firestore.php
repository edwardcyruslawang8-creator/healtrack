<?php
/**
 * =====================================================================
 * Patient Management — Data (admin/patient_management.php)
 * ---------------------------------------------------------------------
 * UNLIKE includes/subadmin/patient_management_firestore.php (locked to
 * the signed-in sub-admin's own real facility, resolved from their own
 * users/{uid} doc), a System Administrator oversees every facility —
 * $selectedFacility is picked from the topbar's own facility dropdown
 * (?facility=), defaulting to the first entry in $facilityOptions when
 * absent or not a real facility name. The actual stat-card/search/
 * filter/pagination computation is the exact same
 * hc_compute_patient_management_for_facility() both files call — see
 * includes/shared/patient_management_shared.php's header comment for
 * why, and for why extract() is used here.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore
require_once __DIR__ . '/../shared/patient_management_shared.php'; // hc_compute_patient_management_for_facility(), hc_pm_query()

$adminProfile = [
    'name' => $_SESSION['name'] ?? 'Administrator',
    'role' => match ($_SESSION['role'] ?? null) {
        'admin' => 'System Administrator',
        default => 'Administrator',
    },
];

$dashboardDateLabel = date('F j, Y');
$facilityOptions = ['North Super Health Center', 'East Super Health Center', 'West Super Health Center'];
$selectedFacility = in_array($_GET['facility'] ?? '', $facilityOptions, true) ? $_GET['facility'] : $facilityOptions[0];

// Stat cards, $patients, pagination — see includes/shared/patient_management_shared.php.
extract(hc_compute_patient_management_for_facility($selectedFacility));
