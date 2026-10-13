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
 * This used to split into two real Firestore access paths (a cheap
 * paginated query + cheap aggregation counts when no search/filter was
 * active, vs. a full scan when one was) to avoid reading the whole
 * "patients" collection on every page load. Adding the facility filter
 * broke that split for real: Firestore requires a composite index for
 * "WHERE facility == X ORDER BY dateRegistered" (confirmed live — a
 * 400 FAILED_PRECONDITION asking for one to be created), and this app's
 * service account has no permission to deploy one itself (confirmed
 * live too — a 403 on the index-creation API). Rather than ship a page
 * that 400s until someone manually creates that index in the Firebase
 * console, this reads the collection once (same full scan the
 * search/filter path already did) and does everything — stat cards,
 * search, gender/age filters, AND pagination — in PHP over that one
 * facility-scoped list. $patientsUsingRealQuery is kept only to choose
 * which of the two pagination UIs renders (Previous/Next-only vs.
 * numbered) — both now paginate the same in-memory list, just windowed
 * differently for the template.
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
