<?php
/**
 * =====================================================================
 * Query Helpers
 * ---------------------------------------------------------------------
 * hc_url_with_params(): builds a link to the CURRENT page with one or
 * more query params overridden, preserving every other param already on
 * the URL (other active filters, other tables' own page numbers, etc.)
 * — used by every real filter-dropdown link and pagination link across
 * the subadmin Analytics & Reports report pages, so switching one
 * control never silently resets another.
 * =====================================================================
 */

if (!function_exists('hc_url_with_params')) {
    function hc_url_with_params(array $overrides): string
    {
        $params = array_merge($_GET, $overrides);
        return '?' . http_build_query($params);
    }
}

if (!function_exists('hc_matches_date_range_key')) {
    /**
     * True if $dateStr (any strtotime()-parseable date, e.g. medical
     * records' own "M j, Y" formatted dates) falls within $rangeKey
     * ('today'/'this_week'/'this_month'), or $rangeKey is anything else
     * (no filter) — for the several tables across the app whose date
     * filters still use this preset Today/This Week/This Month/All Time
     * shape (Audit Logs moved to a real From/To range instead — see
     * includes/shared/audit_log_writer.php's hc_audit_log_matches_date_from_to()).
     */
    function hc_matches_date_range_key(string $dateStr, string $rangeKey): bool
    {
        if ($dateStr === '' || !in_array($rangeKey, ['today', 'this_week', 'this_month'], true)) {
            return true;
        }
        $ts = strtotime($dateStr);
        if ($ts === false) {
            return false;
        }

        return match ($rangeKey) {
            'today' => date('Y-m-d', $ts) === date('Y-m-d'),
            'this_week' => $ts >= strtotime('monday this week'),
            'this_month' => date('Y-m', $ts) === date('Y-m'),
            default => true,
        };
    }
}
