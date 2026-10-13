<?php
/**
 * =====================================================================
 * Shared — Own-Facility Resolver
 * ---------------------------------------------------------------------
 * "What real facility does the signed-in user belong to" — the same
 * question subadmin/dashboard.php's and subadmin/analytics_reports.php's
 * own header comments already answer inline (their users/{uid} doc's
 * real "facility" field is a NAME, e.g. "North Super Health Center",
 * "not a switcher"). hc_resolve_own_facility_id() answers the same
 * question but returns the matching real facility ID instead (e.g.
 * "north-super-health-center") by matching that name against
 * includes/admin/facility_management_firestore.php's real $facilities
 * list — the id includes/admin/facility_analytics_firestore.php's
 * whole computation pipeline is actually keyed on.
 *
 * Used by includes/subadmin/facility_analytics_firestore.php (locks a
 * subadmin to their own facility's analytics, no picker) and by
 * api/get-patient-trend.php (so a subadmin can never fetch another
 * facility's predictive data by editing the query string — see that
 * file's own comment for why this matters).
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore
require_once __DIR__ . '/../admin/facility_management_firestore.php'; // $facilities

if (!function_exists('hc_resolve_own_facility_id')) {
    /** The real facility id matching $_SESSION['uid']'s own users/{uid}.facility name, or null if unresolved. */
    function hc_resolve_own_facility_id(): ?string
    {
        global $firestore, $facilities;

        $myUid = $_SESSION['uid'] ?? '';
        if ($myUid === '') {
            return null;
        }

        $myDoc = $firestore->getDocument('users', $myUid);
        $myFacilityName = $myDoc['facility'] ?? '';
        if ($myFacilityName === '') {
            return null;
        }

        foreach ($facilities as $f) {
            if ($f['name'] === $myFacilityName) {
                return $f['id'];
            }
        }

        return null;
    }
}