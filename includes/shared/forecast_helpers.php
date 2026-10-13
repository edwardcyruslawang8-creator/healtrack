<?php
/**
 * =====================================================================
 * Shared — Forecast Helpers
 * ---------------------------------------------------------------------
 * Ordinary least-squares linear regression over real daily counts, plus
 * the small date-bucketing helper it needs — the same standard,
 * explainable forecasting method includes/admin/facility_analytics_firestore.php's
 * per-facility "Predictive & Prescriptive" tab has used since it was
 * first built (no ML framework available in this PHP environment, and
 * nothing in this app has enough history to need one). Extracted here
 * so includes/shared/predictive_analytics_core.php's 4 tab functions
 * (disease/demand, medicine & vaccine shortage, inventory replenishment,
 * barangay consultation trends) can reuse the exact same method instead
 * of a second, drifting copy.
 * =====================================================================
 */

if (!function_exists('hc_last_n_days')) {
    /**
     * Real daily counts for the last $n days (oldest first), from a list
     * of raw ISO date/datetime strings — only the first 10 characters
     * (YYYY-MM-DD) of each are used, so a full datetime works the same
     * as a bare date. General-purpose version of what was previously
     * facility_analytics_firestore.php's own hc_facility_last8_days()/
     * hc_facility_last_n_days() pair (now just one function, any $n).
     *
     * @return array{labels: array<int, string>, values: array<int, int>}
     */
    function hc_last_n_days(array $rawDates, int $n): array
    {
        $counts = array_fill(0, $n, 0);
        $labels = [];
        $dayIndex = [];
        for ($i = $n - 1; $i >= 0; $i--) {
            $labels[] = date('M j', strtotime("-$i days"));
            $dayIndex[date('Y-m-d', strtotime("-$i days"))] = $n - 1 - $i;
        }
        foreach ($rawDates as $raw) {
            $day = substr((string) $raw, 0, 10);
            if (isset($dayIndex[$day])) {
                $counts[$dayIndex[$day]]++;
            }
        }

        return ['labels' => $labels, 'values' => $counts];
    }
}

if (!function_exists('hc_forecast_linear_fit')) {
    /**
     * Ordinary least-squares slope+intercept over $dailyCounts (oldest
     * first, x = day index). Returns hasSignal=false — a flat line, not
     * a confident-looking projection — when there isn't enough real
     * activity to justify one: fewer than 7 days of history, or fewer
     * than 5 of those days had any real activity at all. A trend drawn
     * from 1-2 data points is noise, not a forecast.
     *
     * @return array{slope: float, intercept: float, hasSignal: bool}
     */
    function hc_forecast_linear_fit(array $dailyCounts): array
    {
        $n = count($dailyCounts);
        $daysWithActivity = count(array_filter($dailyCounts, fn ($v) => $v > 0));
        if ($n < 7 || $daysWithActivity < 5) {
            return ['slope' => 0.0, 'intercept' => $n > 0 ? array_sum($dailyCounts) / $n : 0.0, 'hasSignal' => false];
        }

        $sumX = 0;
        $sumY = 0;
        $sumXY = 0;
        $sumXX = 0;
        foreach ($dailyCounts as $x => $y) {
            $sumX += $x;
            $sumY += $y;
            $sumXY += $x * $y;
            $sumXX += $x * $x;
        }
        $denominator = ($n * $sumXX) - ($sumX * $sumX);
        if ($denominator == 0) {
            return ['slope' => 0.0, 'intercept' => $sumY / $n, 'hasSignal' => false];
        }
        $slope = (($n * $sumXY) - ($sumX * $sumY)) / $denominator;
        $intercept = ($sumY - ($slope * $sumX)) / $n;

        return ['slope' => $slope, 'intercept' => $intercept, 'hasSignal' => true];
    }
}

if (!function_exists('hc_forecast_project')) {
    /** Projects $days values forward from a fit starting at day-index $fromIndex — never negative, rounded to whole people/visits/units. */
    function hc_forecast_project(array $fit, int $fromIndex, int $days): array
    {
        $out = [];
        for ($i = 0; $i < $days; $i++) {
            $x = $fromIndex + $i;
            $out[] = max(0, (int) round(($fit['slope'] * $x) + $fit['intercept']));
        }

        return $out;
    }
}

if (!function_exists('hc_facility_trend_by_range')) {
    /**
     * Real counts bucketed by $range ('daily'|'weekly'|'monthly'|'yearly'),
     * oldest bucket first, from a list of raw ISO date/datetime strings —
     * the range-aware generalization of hc_last_n_days() above. Bucket
     * boundaries are computed with DateTime/DateInterval rather than
     * strtotime("-N month") string arithmetic, which has a well-known
     * day-overflow bug across month boundaries (e.g. Jan 31 "-1 month"
     * lands on Mar 3, not Feb 28) — correctness matters here since this
     * is a real, user-selectable analytics view, not a cosmetic default.
     *
     * Originally lived only in includes/admin/facility_analytics_firestore.php
     * (admin/subadmin's Patient Trend card); moved here so any other
     * role's own range-switching trend card (e.g.
     * includes/pharmacist/analytics_reports_pharmacist_firestore.php's
     * "My Stock Requests Over Time") can reuse the exact same bucketing
     * without requiring that whole heavy per-facility pipeline just for
     * this one function.
     *
     * @return array{labels: array<int, string>, values: array<int, int>}
     */
    function hc_facility_trend_by_range(array $rawDates, string $range): array
    {
        $bucketCounts = ['daily' => 8, 'weekly' => 8, 'monthly' => 8, 'yearly' => 5];
        $buckets = $bucketCounts[$range] ?? 8;

        $labels = [];
        $keys = [];
        for ($i = $buckets - 1; $i >= 0; $i--) {
            $d = new DateTime('now');
            switch ($range) {
                case 'weekly':
                    $d->modify('monday this week');
                    $d->sub(new DateInterval("P{$i}W"));
                    $keys[] = $d->format('Y-m-d');
                    $labels[] = $d->format('M j');
                    break;
                case 'monthly':
                    $d->modify('first day of this month');
                    $d->sub(new DateInterval("P{$i}M"));
                    $keys[] = $d->format('Y-m');
                    $labels[] = $d->format("M \\'y");
                    break;
                case 'yearly':
                    $d->modify('first day of january this year');
                    $d->sub(new DateInterval("P{$i}Y"));
                    $keys[] = $d->format('Y');
                    $labels[] = $d->format('Y');
                    break;
                default: // daily
                    $d->sub(new DateInterval("P{$i}D"));
                    $keys[] = $d->format('Y-m-d');
                    $labels[] = $d->format('M j');
                    break;
            }
        }

        $counts = array_fill_keys($keys, 0);
        foreach ($rawDates as $raw) {
            $rawDay = substr((string) $raw, 0, 10);
            if ($rawDay === '') {
                continue;
            }
            $dt = DateTime::createFromFormat('Y-m-d', $rawDay);
            if ($dt === false) {
                continue;
            }
            switch ($range) {
                case 'weekly':
                    $dt->modify('monday this week');
                    $key = $dt->format('Y-m-d');
                    break;
                case 'monthly':
                    $key = $dt->format('Y-m');
                    break;
                case 'yearly':
                    $key = $dt->format('Y');
                    break;
                default:
                    $key = $dt->format('Y-m-d');
                    break;
            }
            if (isset($counts[$key])) {
                $counts[$key]++;
            }
        }

        return ['labels' => $labels, 'values' => array_values($counts)];
    }
}
