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
     * ('today'/'this_week'/'this_month'/'this_year'/'last_year'/
     * 'custom'), or $rangeKey is anything else (no filter) — for the
     * several tables and dashboard cards across the app whose date
     * filters use this preset shape (Audit Logs use a real From/To range
     * instead — see includes/shared/audit_log_writer.php's
     * hc_audit_log_matches_date_from_to()). 'custom' requires
     * $customFrom/$customTo ('Y-m-d'); with either missing it matches
     * nothing rather than silently falling back to "no filter".
     */
    function hc_matches_date_range_key(string $dateStr, string $rangeKey, ?string $customFrom = null, ?string $customTo = null): bool
    {
        if ($dateStr === '' || !in_array($rangeKey, ['today', 'this_week', 'this_month', 'this_year', 'last_year', 'custom'], true)) {
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
            'this_year' => date('Y', $ts) === date('Y'),
            'last_year' => date('Y', $ts) === date('Y', strtotime('-1 year')),
            'custom' => $customFrom !== null && $customFrom !== '' && $customTo !== null && $customTo !== ''
                && $ts >= strtotime($customFrom . ' 00:00:00') && $ts <= strtotime($customTo . ' 23:59:59'),
            default => true,
        };
    }
}

if (!function_exists('hc_resolve_dashboard_range')) {
    /**
     * Resolves one dashboard card's "?{$param}=..." filter against its
     * own preset option map, adding "Last Year" and "Custom Date"
     * ("?{$param}From=Y-m-d&{$param}To=Y-m-d") on top of whatever
     * presets the card already defines. Shared by every role's
     * dashboard (medical-staff, doctor, subadmin, medical technician) so
     * both behave identically everywhere instead of being hand-rolled
     * per card. Pass $options WITHOUT 'last_year'/'custom' in it — they
     * are added here, always as the last two entries so every card's
     * dropdown lists them in the same place.
     *
     * An invalid/incomplete custom range falls back to $default rather
     * than silently filtering nothing in — same reasoning as
     * includes/shared/date_range_filter.php's hc_resolve_date_range_filter().
     */
    function hc_resolve_dashboard_range(string $param, array $options, string $default): array
    {
        $options['last_year'] = 'Last Year';
        $options['custom'] = 'Custom Date';

        $key = $_GET[$param] ?? $default;
        if (!array_key_exists($key, $options)) {
            $key = $default;
        }

        $customFrom = '';
        $customTo = '';
        if ($key === 'custom') {
            $customFrom = (string) ($_GET[$param . 'From'] ?? '');
            $customTo = (string) ($_GET[$param . 'To'] ?? '');
            $fromDt = DateTimeImmutable::createFromFormat('Y-m-d', $customFrom);
            $toDt = DateTimeImmutable::createFromFormat('Y-m-d', $customTo);
            if ($fromDt === false || $toDt === false || $fromDt > $toDt) {
                $key = $default;
                $customFrom = '';
                $customTo = '';
            }
        }

        $label = $options[$key];
        if ($key === 'custom') {
            $label = DateTimeImmutable::createFromFormat('Y-m-d', $customFrom)->format('M j, Y')
                . ' - ' . DateTimeImmutable::createFromFormat('Y-m-d', $customTo)->format('M j, Y');
        }

        return [
            'key' => $key,
            'label' => $label,
            'options' => $options,
            'customFrom' => $customFrom,
            'customTo' => $customTo,
            'param' => $param,
        ];
    }
}

if (!function_exists('hc_dashboard_range_to_from_to')) {
    /**
     * Converts a hc_resolve_dashboard_range() result's preset/custom key
     * into concrete ['Y-m-d' from, 'Y-m-d' to] day bounds (blank means
     * open-ended on that side) — lets a caller that already filters by a
     * real From/To pair (e.g. Audit Logs' own
     * hc_audit_log_matches_date_from_to()) adopt the shared preset+
     * custom dropdown without changing its own filtering logic at all.
     */
    function hc_dashboard_range_to_from_to(string $rangeKey, string $customFrom = '', string $customTo = ''): array
    {
        $today = date('Y-m-d');

        return match ($rangeKey) {
            'today' => [$today, $today],
            'this_week' => [date('Y-m-d', strtotime('monday this week')), $today],
            'this_month' => [date('Y-m-01'), $today],
            'this_year' => [date('Y-01-01'), $today],
            'last_year' => [date('Y-01-01', strtotime('-1 year')), date('Y-12-31', strtotime('-1 year'))],
            'custom' => [$customFrom, $customTo],
            default => ['', ''], // 'all'
        };
    }
}

if (!function_exists('hc_dashboard_trend_series')) {
    /**
     * Builds a {labels, values} trend-chart series for the 5 preset
     * dashboard trend ranges (this_week/this_month/this_year/last_year/
     * custom) by counting how many $items have $dateGetter($item)
     * starting with each bucket's day/month prefix. Mirrors the
     * bucketing every dashboard's own Consultations/Tests Trend chart
     * already computed inline (daily points for This Week/This Month,
     * monthly points for This Year/Last Year) — This Year/Last Year
     * stay monthly, and a Custom range buckets daily when it spans 31
     * days or less, monthly otherwise, so a multi-year custom range
     * doesn't render hundreds of daily points.
     */
    function hc_dashboard_trend_series(array $items, callable $dateGetter, string $rangeKey, string $customFrom = '', string $customTo = ''): array
    {
        $labels = [];
        $values = [];
        $count = fn (string $prefix) => count(array_filter($items, fn ($it) => str_starts_with($dateGetter($it), $prefix)));

        if ($rangeKey === 'this_year' || $rangeKey === 'last_year') {
            $year = $rangeKey === 'last_year' ? (int) date('Y', strtotime('-1 year')) : (int) date('Y');
            $monthsThrough = $rangeKey === 'last_year' ? 12 : (int) date('n');
            for ($m = 1; $m <= $monthsThrough; $m++) {
                $monthStart = mktime(0, 0, 0, $m, 1, $year);
                $labels[] = date('M', $monthStart);
                $values[] = $count(date('Y-m', $monthStart));
            }
        } elseif ($rangeKey === 'custom' && $customFrom !== '' && $customTo !== '') {
            $fromTs = strtotime($customFrom);
            $toTs = strtotime($customTo);
            $daySpan = max(0, (int) floor(($toTs - $fromTs) / 86400));
            if ($daySpan <= 31) {
                for ($i = 0; $i <= $daySpan; $i++) {
                    $day = date('Y-m-d', strtotime("+{$i} day", $fromTs));
                    $labels[] = date('M j', strtotime($day));
                    $values[] = $count($day);
                }
            } else {
                $cursor = mktime(0, 0, 0, (int) date('n', $fromTs), 1, (int) date('Y', $fromTs));
                $endCursor = mktime(0, 0, 0, (int) date('n', $toTs), 1, (int) date('Y', $toTs));
                while ($cursor <= $endCursor) {
                    $labels[] = date('M Y', $cursor);
                    $values[] = $count(date('Y-m', $cursor));
                    $cursor = strtotime('+1 month', $cursor);
                }
            }
        } else { // this_week / this_month
            $daysBack = $rangeKey === 'this_month' ? ((int) date('j') - 1) : 6;
            for ($i = $daysBack; $i >= 0; $i--) {
                $day = date('Y-m-d', strtotime("-{$i} day"));
                $labels[] = date('M j', strtotime($day));
                $values[] = $count($day);
            }
        }

        return ['labels' => $labels, 'values' => $values];
    }
}
