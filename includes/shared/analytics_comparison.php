<?php
/**
 * =====================================================================
 * Shared — Analytics Month-over-Month Comparison
 * ---------------------------------------------------------------------
 * hc_facility_growth_pct() used to live as a plain, unguarded top-level
 * function inside includes/admin/facility_analytics_firestore.php —
 * only admin's own Facility Analytics page could reuse it. Moved here
 * (with the standard if (!function_exists(...)) guard every other
 * shared helper in this app uses) so every other role's own
 * includes/*\/analytics_reports_firestore.php can compute the exact
 * same real "vs last month" percentage against their own already-
 * scoped this-month/last-month counts, instead of each writing its own
 * copy that could drift out of sync with admin's.
 * =====================================================================
 */

if (!function_exists('hc_facility_growth_pct')) {
    /** Real (this-month vs last-month) percent change — 0 when there's nothing to compare against. */
    function hc_facility_growth_pct(int $thisMonthCount, int $lastMonthCount): float
    {
        if ($lastMonthCount === 0) {
            return $thisMonthCount > 0 ? 100.0 : 0.0;
        }

        return round((($thisMonthCount - $lastMonthCount) / $lastMonthCount) * 100, 1);
    }
}
