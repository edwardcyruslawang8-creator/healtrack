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

require_once __DIR__ . '/forecast_helpers.php'; // hc_last_n_days() — used by hc_operational_trends_overview() below

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

if (!function_exists('hc_operational_trends_overview')) {
    /**
     * Real "operational issue" comparative/anomaly view — same mean +
     * standard-deviation method hc_disease_demand_forecast() already
     * uses to flag unusual DISEASE case clusters and unusual
     * CONSULTATION volume, applied here to two real operational
     * signals this app has actual timestamped data for: how many real
     * stock requests come in per day, and how long a real request sits
     * before a sub-admin decides on it (approve/reject/release).
     *
     * @param array $stockRequests hc_get_all_stock_requests()-shaped rows (needs 'requestedAtRaw' and 'reviewedAtRaw'), already scoped by the caller — one facility's own requests, or one pharmacist's own.
     * @return array{hasData: bool, volumeLabels: array, volumeDaily: array, volumeAnomaly: bool, volumeRecentAvg: float, volumeBaselineAvg: float, hasTurnaroundData: bool, turnaroundAnomaly: bool, turnaroundRecentAvgHours: float, turnaroundBaselineAvgHours: float, decidedCount: int}
     */
    function hc_operational_trends_overview(array $stockRequests): array
    {
        $empty = [
            'hasData' => false,
            'volumeLabels' => [], 'volumeDaily' => [], 'volumeAnomaly' => false,
            'volumeRecentAvg' => 0.0, 'volumeBaselineAvg' => 0.0,
            'hasTurnaroundData' => false, 'turnaroundAnomaly' => false,
            'turnaroundRecentAvgHours' => 0.0, 'turnaroundBaselineAvgHours' => 0.0,
            'decidedCount' => 0,
        ];
        if ($stockRequests === []) {
            return $empty;
        }

        // --- Real daily request VOLUME, last 30 days (hc_last_n_days() —
        // same zero-filled daily bucketing hc_disease_demand_forecast()
        // already uses for consultation volume, so there are no gaps to
        // worry about: a day with no requests is a real 0, not missing
        // data). ---
        $requestDates = array_column($stockRequests, 'requestedAtRaw');
        $volumeDaily30 = hc_last_n_days($requestDates, 30);

        $volumeBaselineDaily = array_slice($volumeDaily30['values'], 0, 23);
        $volumeBaselineAvg = array_sum($volumeBaselineDaily) / count($volumeBaselineDaily);
        $volumeBaselineVariance = array_sum(array_map(fn ($v) => ($v - $volumeBaselineAvg) ** 2, $volumeBaselineDaily)) / count($volumeBaselineDaily);
        $volumeBaselineSD = sqrt($volumeBaselineVariance);
        $volumeRecentAvg = array_sum(array_slice($volumeDaily30['values'], -7)) / 7;
        // Two-sided — an unusual DROP in requests (e.g. a broken
        // submission flow) is as much an operational issue as an
        // unusual spike.
        $volumeAnomaly = $volumeBaselineSD > 0
            ? abs($volumeRecentAvg - $volumeBaselineAvg) > (2 * $volumeBaselineSD)
            : $volumeRecentAvg > $volumeBaselineAvg;

        // --- Real average PROCESSING TIME (hours from request to
        // decision) — individual per-request turnaround hours, not a
        // daily average, since most days have zero or one decided
        // request here and a daily-bucketed series would be mostly
        // gaps (dishonest to zero-fill — "no request decided that day"
        // is not "0-hour turnaround"). ---
        $turnaroundHours = [];
        foreach ($stockRequests as $r) {
            if (($r['reviewedAtRaw'] ?? '') === '' || ($r['requestedAtRaw'] ?? '') === '') {
                continue;
            }
            $requestedTs = strtotime($r['requestedAtRaw']);
            $reviewedTs = strtotime($r['reviewedAtRaw']);
            if ($requestedTs === false || $reviewedTs === false || $reviewedTs < $requestedTs) {
                continue;
            }
            $turnaroundHours[] = [
                'hours' => ($reviewedTs - $requestedTs) / 3600,
                'reviewedDate' => date('Y-m-d', $reviewedTs),
            ];
        }
        $decidedCount = count($turnaroundHours);
        $hasTurnaroundData = $decidedCount >= 5;

        $turnaroundAnomaly = false;
        $turnaroundRecentAvgHours = 0.0;
        $turnaroundBaselineAvgHours = 0.0;
        if ($hasTurnaroundData) {
            $cutoff7 = date('Y-m-d', strtotime('-7 days'));
            $recent = array_values(array_filter($turnaroundHours, fn ($t) => $t['reviewedDate'] >= $cutoff7));
            $baseline = array_values(array_filter($turnaroundHours, fn ($t) => $t['reviewedDate'] < $cutoff7));

            if ($recent !== [] && count($baseline) >= 3) {
                $baselineValues = array_column($baseline, 'hours');
                $turnaroundBaselineAvgHours = round(array_sum($baselineValues) / count($baselineValues), 1);
                $turnaroundBaselineVariance = array_sum(array_map(fn ($v) => ($v - $turnaroundBaselineAvgHours) ** 2, $baselineValues)) / count($baselineValues);
                $turnaroundBaselineSD = sqrt($turnaroundBaselineVariance);

                $recentValues = array_column($recent, 'hours');
                $turnaroundRecentAvgHours = round(array_sum($recentValues) / count($recentValues), 1);

                // One-sided — only a SLOWER-than-normal turnaround is the
                // real operational concern; unusually fast isn't a problem.
                $turnaroundAnomaly = $turnaroundBaselineSD > 0
                    ? $turnaroundRecentAvgHours > $turnaroundBaselineAvgHours + (2 * $turnaroundBaselineSD)
                    : $turnaroundRecentAvgHours > $turnaroundBaselineAvgHours;
            } else {
                $hasTurnaroundData = false; // not enough real split to compare recent-vs-baseline honestly
            }
        }

        return [
            'hasData' => true,
            'volumeLabels' => $volumeDaily30['labels'],
            'volumeDaily' => $volumeDaily30['values'],
            'volumeAnomaly' => $volumeAnomaly,
            'volumeRecentAvg' => round($volumeRecentAvg, 1),
            'volumeBaselineAvg' => round($volumeBaselineAvg, 1),
            'hasTurnaroundData' => $hasTurnaroundData,
            'turnaroundAnomaly' => $turnaroundAnomaly,
            'turnaroundRecentAvgHours' => $turnaroundRecentAvgHours,
            'turnaroundBaselineAvgHours' => $turnaroundBaselineAvgHours,
            'decidedCount' => $decidedCount,
        ];
    }
}
