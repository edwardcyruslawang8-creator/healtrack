<?php
/**
 * =====================================================================
 * Shared — Disease Outbreak Monitoring & Forecasting
 * ---------------------------------------------------------------------
 * Backs subadmin/predictive_analytics.php's "Disease Outbreak
 * Monitoring & Forecasting" card (a new addition to the existing
 * "Disease & Service Demand Forecast" tab, alongside — not replacing —
 * the "Potential Notifiable Disease Cases" card). Built entirely on top
 * of already-existing, already-verified pieces rather than new
 * detection/dedup logic of its own:
 *   - Case extraction + per-patient episode dedup:
 *     hc_notifiable_cases_from_records() (predictive_analytics_core.php).
 *   - GIS eligibility + confirmed-only filtering:
 *     hc_gis_eligible_confirmed_cases() (clinical_classification.php) —
 *     the EXACT SAME rule patient/gis.php's own public map uses, so
 *     this card and GIS Mapping can never disagree about which diseases
 *     or cases count.
 *   - Weekly/monthly/daily bucketing:
 *     hc_notifiable_disease_trend_aggregated() (predictive_analytics_core.php).
 *   - OLS linear-regression forecasting with an honesty gate:
 *     hc_forecast_linear_fit()/hc_forecast_project() (forecast_helpers.php).
 *
 * This file only adds what didn't already exist: a per-disease
 * Normal/Elevated/Potential-Outbreak status (never an auto-declared
 * "official" outbreak — see hc_disease_outbreak_status()'s own header
 * comment), a forecast with an uncertainty band on top of the existing
 * linear fit, and the one orchestration function
 * (hc_disease_outbreak_monitoring()) api/get-disease-outbreak-forecast.php
 * calls.
 * =====================================================================
 */

require_once __DIR__ . '/predictive_analytics_core.php'; // hc_notifiable_disease_trend_aggregated(), HC_COVERED_BARANGAYS
require_once __DIR__ . '/forecast_helpers.php'; // hc_forecast_linear_fit(), hc_forecast_project()
require_once __DIR__ . '/clinical_classification.php'; // hc_gis_eligible_confirmed_cases(), hc_condition_registry_meta_by_name(), hc_is_publicly_visible()

// Fixed, no-filter timeline — no Date Range or Forecast Horizon picker
// any more (the only live filters left on this card are Disease and
// Barangay): the chart always shows the real last 14 days of actual
// cases (solid) immediately followed by a real 7-day-ahead forecast
// (dashed), continuously shifting forward as today's real date changes.
// The forecast's own OLS fit is trained on a WIDER real history
// (HC_DISEASE_OUTBREAK_TRAINING_DAYS) than what's actually drawn on the
// chart — a better-informed fit without a cluttered 60-point display.
if (!defined('HC_DISEASE_OUTBREAK_DISPLAY_DAYS')) {
    define('HC_DISEASE_OUTBREAK_DISPLAY_DAYS', 14);
}
if (!defined('HC_DISEASE_OUTBREAK_FORECAST_DAYS')) {
    define('HC_DISEASE_OUTBREAK_FORECAST_DAYS', 7);
}
if (!defined('HC_DISEASE_OUTBREAK_TRAINING_DAYS')) {
    define('HC_DISEASE_OUTBREAK_TRAINING_DAYS', 60);
}

if (!function_exists('hc_disease_outbreak_monitored_diseases')) {
    /**
     * Every real disease-registry {id, name} pair currently GIS-eligible
     * (same hc_is_publicly_visible() rule GIS Mapping itself uses),
     * regardless of whether it has any real cases in the current window
     * — so a newly-classified condition appears in the Disease filter
     * the moment an admin classifies it (requirement: "automatically
     * appear in the chart once it has valid consultation records"),
     * rather than only once it happens to have a case inside whatever
     * date range is currently selected. The real registry doc ID is the
     * STABLE identifier the filter/color-assignment/selection logic is
     * keyed on everywhere else in this file — the 'name' here is
     * display text only, safe to change (a rename) without losing a
     * disease's identity anywhere downstream. Alphabetical by name —
     * not a case-count ranking, since this is a filter option list, not
     * a ranked display.
     *
     * @return array<int, array{id: string, name: string}>
     */
    function hc_disease_outbreak_monitored_diseases(): array
    {
        $diseases = [];
        foreach (hc_condition_registry_meta_by_name() as $name => $entry) {
            if (hc_is_publicly_visible($entry)) {
                $diseases[] = ['id' => $entry['id'] ?? '', 'name' => $name];
            }
        }
        usort($diseases, fn ($a, $b) => strnatcasecmp($a['name'], $b['name']));

        return $diseases;
    }
}

if (!function_exists('hc_disease_outbreak_weekly_counts')) {
    /**
     * @internal Shared by hc_disease_outbreak_status() and
     * hc_disease_outbreak_forecast_series() — real ISO-week buckets
     * (oldest first) for one already-filtered (disease[+barangay],
     * GIS-eligible/confirmed-only) case list, covering from this set's
     * own earliest real case through the most recent complete week.
     * Weeks with zero real cases are included (not skipped), since both
     * the baseline mean/SD and the OLS fit need the true zero, not a
     * gap.
     *
     * @return array<int, int> oldest week first.
     */
    function hc_disease_outbreak_weekly_counts(array $cases): array
    {
        $dates = array_values(array_filter(array_column($cases, 'date'), fn ($d) => $d !== ''));
        if ($dates === []) {
            return [];
        }
        sort($dates);
        $minDate = $dates[0];
        $maxDate = date('Y-m-d'); // through today, not just this case list's own last case — a disease that's gone silent recently should show real trailing zero-weeks, not stop early

        $weekKey = fn (string $date) => date('o-\WW', strtotime($date));

        $weekKeys = [];
        $cursor = $minDate;
        while (strtotime($cursor) <= strtotime($maxDate)) {
            $key = $weekKey($cursor);
            if (!in_array($key, $weekKeys, true)) {
                $weekKeys[] = $key;
            }
            $cursor = date('Y-m-d', strtotime($cursor . ' +1 day'));
        }

        $counts = [];
        foreach ($weekKeys as $key) {
            $counts[] = count(array_filter($dates, fn ($d) => $weekKey($d) === $key));
        }

        return $counts;
    }
}

if (!function_exists('hc_disease_outbreak_daily_counts')) {
    /**
     * Real daily buckets (oldest first) for one already-filtered
     * (disease[+barangay], GIS-eligible/confirmed-only) case list,
     * covering today back to whichever is LATER: this set's own
     * earliest real case, or $maxLookbackDays ago — capped so a disease
     * with years of real history doesn't blow up into thousands of
     * daily data points for the short daily forecast this backs; 180
     * days is already generous context for a 7-day-ahead OLS fit (the
     * monitoring/forecast caller itself passes the narrower
     * HC_DISEASE_OUTBREAK_TRAINING_DAYS window). Days with zero real cases are included
     * (not skipped), same reasoning as hc_disease_outbreak_weekly_counts()'s
     * own copy of this comment — the fit needs the true zero, not a gap.
     *
     * @return array<int, int>
     */
    function hc_disease_outbreak_daily_counts(array $cases, int $maxLookbackDays = 180): array
    {
        $dates = array_values(array_filter(array_column($cases, 'date'), fn ($d) => $d !== ''));
        $today = date('Y-m-d');
        $earliestAllowed = date('Y-m-d', strtotime("-$maxLookbackDays days"));
        $minDate = $earliestAllowed;
        if ($dates !== []) {
            sort($dates);
            $minDate = max($dates[0], $earliestAllowed);
        }

        $counts = [];
        $cursor = $minDate;
        while (strtotime($cursor) <= strtotime($today)) {
            $counts[] = count(array_filter($dates, fn ($d) => $d === $cursor));
            $cursor = date('Y-m-d', strtotime($cursor . ' +1 day'));
        }

        return $counts;
    }
}

if (!function_exists('hc_disease_outbreak_status')) {
    /**
     * Normal / Elevated / Potential Outbreak — deliberately never
     * "Outbreak" or "Confirmed Outbreak": this is a monitoring SIGNAL for
     * a human to investigate, never an automatic official declaration
     * (the app has no mechanism, and should have none, for declaring one).
     *
     * Method: the most recent real week's case count vs. a mean+SD
     * baseline over the $minBaselineWeeks real weeks before it — the
     * exact same baseline-mean/SD arithmetic hc_disease_demand_forecast()
     * already uses for its own per-barangay+category anomaly flag, just
     * applied at weekly (not daily) granularity to match this feature's
     * own default time grouping, and gated by two explicit safeguards
     * against false alerts on thin data:
     *   - 'insufficient_data' when this disease(+barangay)'s total real
     *     case count is under $minBaselineCases, OR there's under
     *     $minBaselineWeeks of real history — a baseline computed from 2
     *     weeks of history isn't a baseline, and a single historical
     *     case makes "3x the mean" meaningless.
     *   - 'potential_outbreak' additionally requires the trend to still
     *     be RISING (this week's count > the week before it) — a single
     *     already-declining spike-week is 'elevated' at most, mirroring
     *     hc_disease_demand_forecast()'s own "still rising" gate on its
     *     'critical' tier.
     *
     * @param array $cases Already GIS-eligible/confirmed-only, already narrowed to one disease (and optionally one barangay).
     * @return array{status: string, recentCount: int, previousCount: int, baselineMean: float, baselineSD: float}
     */
    function hc_disease_outbreak_status(array $cases, int $minBaselineWeeks = 8, int $minBaselineCases = 5): array
    {
        $totalCases = count($cases);
        $weekly = hc_disease_outbreak_weekly_counts($cases);

        if ($totalCases < $minBaselineCases || count($weekly) < $minBaselineWeeks + 1) {
            return [
                'status' => 'insufficient_data',
                'recentCount' => $weekly === [] ? 0 : (int) end($weekly),
                'previousCount' => count($weekly) >= 2 ? (int) $weekly[count($weekly) - 2] : 0,
                'baselineMean' => 0.0,
                'baselineSD' => 0.0,
            ];
        }

        $recentCount = (int) end($weekly);
        $previousCount = (int) $weekly[count($weekly) - 2];
        $baselineWeeks = array_slice($weekly, -($minBaselineWeeks + 1), $minBaselineWeeks);
        $baselineMean = array_sum($baselineWeeks) / count($baselineWeeks);
        $baselineVariance = array_sum(array_map(fn ($v) => ($v - $baselineMean) ** 2, $baselineWeeks)) / count($baselineWeeks);
        $baselineSD = sqrt($baselineVariance);

        $rising = $recentCount > $previousCount;
        $elevatedThreshold = $baselineMean + (1.5 * $baselineSD);
        $outbreakThreshold = $baselineSD > 0 ? ($baselineMean + (3 * $baselineSD)) : max(3, $baselineMean * 3);

        $status = 'normal';
        if ($recentCount > $outbreakThreshold && $rising) {
            $status = 'potential_outbreak';
        } elseif ($recentCount > $elevatedThreshold) {
            $status = 'elevated';
        }

        return [
            'status' => $status,
            'recentCount' => $recentCount,
            'previousCount' => $previousCount,
            'baselineMean' => round($baselineMean, 2),
            'baselineSD' => round($baselineSD, 2),
        ];
    }
}

if (!function_exists('hc_disease_outbreak_status_meta')) {
    /** status key -> {label, color, bg} for the monitoring table's badge and the chart's own status note. */
    function hc_disease_outbreak_status_meta(): array
    {
        return [
            'normal' => ['label' => 'Normal', 'color' => '#1E8E3E', 'bg' => '#E4F7EA'],
            'elevated' => ['label' => 'Elevated', 'color' => '#E8710A', 'bg' => '#FDECDD'],
            'potential_outbreak' => ['label' => 'Potential Outbreak', 'color' => '#D93025', 'bg' => '#FCE4E4'],
            'insufficient_data' => ['label' => 'Baseline Pending', 'color' => '#6B7280', 'bg' => '#EEF0F3'],
        ];
    }
}

if (!function_exists('hc_disease_outbreak_forecast_series')) {
    /**
     * A $periodsAhead-period-ahead forecast over real case counts
     * (oldest first — daily or weekly, the math is identical either
     * way, only what each entry represents differs), built on the exact
     * same hc_forecast_linear_fit()/hc_forecast_project() OLS method
     * every other forecast in this app already uses — same honesty gate
     * (hasSignal=false, no projected numbers at all, under 7 real
     * periods of history or under 5 non-zero ones), so a thin-data
     * disease never gets a fabricated-looking trend line just to fill
     * the chart — "If a disease has insufficient historical data,
     * display Insufficient Data instead of generating unreliable
     * forecasts."
     *
     * Adds one real thing hc_forecast_linear_fit() doesn't already
     * return: the OLS residual standard error, so the chart can draw a
     * real statistical uncertainty band (±1.96×SE, ~95%) around the
     * projected line — "uncertainty ranges when the forecasting method
     * supports them" (plain OLS always supports this; it's standard
     * regression output, not a separate guess).
     *
     * @return array{hasSignal: bool, values: array<int,int>, upper: array<int,int>, lower: array<int,int>}
     */
    function hc_disease_outbreak_forecast_series(array $counts, int $periodsAhead): array
    {
        $fit = hc_forecast_linear_fit($counts);
        if (!$fit['hasSignal']) {
            return ['hasSignal' => false, 'values' => [], 'upper' => [], 'lower' => []];
        }

        $n = count($counts);
        $residualSumSquares = 0.0;
        foreach ($counts as $x => $actual) {
            $fitted = ($fit['slope'] * $x) + $fit['intercept'];
            $residualSumSquares += ($actual - $fitted) ** 2;
        }
        // n-2 degrees of freedom (slope + intercept both estimated from
        // the data) — the standard OLS residual-standard-error formula.
        $residualSE = $n > 2 ? sqrt($residualSumSquares / ($n - 2)) : 0.0;

        $values = hc_forecast_project($fit, $n, $periodsAhead);
        $margin = 1.96 * $residualSE; // ~95% band
        $upper = array_map(fn ($v) => max(0, (int) round($v + $margin)), $values);
        $lower = array_map(fn ($v) => max(0, (int) round($v - $margin)), $values);

        return ['hasSignal' => true, 'values' => $values, 'upper' => $upper, 'lower' => $lower];
    }
}

if (!function_exists('hc_disease_outbreak_forecast_labels')) {
    /**
     * Real future calendar day labels for the forecast segment of the
     * chart's X-axis — e.g. "Nov 12", "Nov 13", ... — continuing forward
     * from TODAY (Asia/Manila), never a generic placeholder like "Wk +1".
     *
     * @return array<int, string>
     */
    function hc_disease_outbreak_forecast_labels(int $daysAhead): array
    {
        $labels = [];
        for ($i = 1; $i <= $daysAhead; $i++) {
            $labels[] = date('M j', strtotime("+$i days"));
        }

        return $labels;
    }
}

if (!function_exists('hc_disease_outbreak_monitoring')) {
    /**
     * The one orchestration function api/get-disease-outbreak-forecast.php
     * calls — everything subadmin/predictive_analytics.php's Disease
     * Outbreak Monitoring & Forecasting card needs in one real,
     * internally-consistent payload (chart, summary tiles, and
     * monitoring table all computed from the exact same underlying case
     * list, never 3 separate queries that could disagree).
     *
     * Always anchored to the real current Philippine date (Asia/Manila
     * — this app's own default timezone, config/constants.php): the
     * chart always shows the real last HC_DISEASE_OUTBREAK_DISPLAY_DAYS
     * days of actual cases followed by a real HC_DISEASE_OUTBREAK_FORECAST_DAYS-
     * day-ahead forecast, continuously shifting forward as today's real
     * date changes — there is no Date Range or Forecast Horizon picker
     * any more; only Disease and Barangay stay as live filters.
     *
     * @param array $facilityNotifiableCases This sub-admin's own facility-scoped
     *        hc_disease_demand_forecast()['notifiableCases'] — NOT yet
     *        GIS-eligibility-filtered (that happens here, step 1 below).
     * @param array $filters {disease, barangay} — disease is 'all' or a
     *        comma-separated list of real registry doc IDs (never
     *        trusted as names — resolved against
     *        hc_disease_outbreak_monitored_diseases()'s own real id set
     *        below, so an unknown/stale id is simply dropped, never
     *        crashes or silently matches the wrong disease); barangay is
     *        a real HC_COVERED_BARANGAYS entry or 'all'.
     * @return array{summary: array, chart: array, table: array, monitoredDiseases: array<int,array{id:string,name:string}>, currentPeriodLabel: string}
     */
    function hc_disease_outbreak_monitoring(array $facilityNotifiableCases, array $filters): array
    {
        $diseaseFilterRaw = ($filters['disease'] ?? 'all') !== '' ? $filters['disease'] : 'all';
        $barangayFilter = ($filters['barangay'] ?? 'all') !== '' ? $filters['barangay'] : 'all';

        $today = date('Y-m-d');
        $displayDays = HC_DISEASE_OUTBREAK_DISPLAY_DAYS;
        $forecastDays = HC_DISEASE_OUTBREAK_FORECAST_DAYS;
        $trainingDays = HC_DISEASE_OUTBREAK_TRAINING_DAYS;

        // What the chart actually DRAWS as its solid historical segment
        // — the real last 14 days, always. "Current period" / "previous
        // period" (the table's own comparison, and "latest disease
        // activity" ranking for the default relevance filter) use this
        // exact same window, so a viewer never sees a table number that
        // doesn't match what the chart itself shows.
        // -($displayDays - 1), not -$displayDays, so the displayed
        // window is exactly $displayDays real calendar days INCLUDING
        // today (e.g. 14 days = today and the 13 days before it) —
        // $previousPeriodStart then marks off an equal-length prior
        // window ending right where the displayed one begins.
        $displayStart = date('Y-m-d', strtotime('-' . ($displayDays - 1) . ' days'));
        $previousPeriodStart = date('Y-m-d', strtotime($displayStart . " -$displayDays days"));
        // Wider than the displayed window on purpose — "historical data
        // used to train the forecasting algorithm may extend beyond the
        // 14 days displayed" — only ever used below for each shown
        // disease's own OLS fit, never for what's drawn on the chart.
        $trainingStart = date('Y-m-d', strtotime("-$trainingDays days"));

        // Step 1 — the one real GIS-eligibility filter, identical to
        // what the public GIS map itself applies (hc_gis_eligible_confirmed_cases(),
        // clinical_classification.php). Every case this function ever
        // looks at from here on has already passed that same rule.
        $eligible = hc_gis_eligible_confirmed_cases($facilityNotifiableCases);

        $monitoredDiseases = hc_disease_outbreak_monitored_diseases();
        $monitoredIds = array_column($monitoredDiseases, 'id');
        $nameById = array_combine(array_column($monitoredDiseases, 'id'), array_column($monitoredDiseases, 'name'));

        // Requested disease ids — only real, currently-monitored ids
        // ever survive this (a stale id for a since-archived/deleted
        // disease, or any other garbage a client could send, is simply
        // dropped here, not matched against anything).
        $requestedIds = [];
        if ($diseaseFilterRaw !== 'all') {
            $requestedIds = array_values(array_intersect(array_map('trim', explode(',', $diseaseFilterRaw)), $monitoredIds));
        }

        // Step 2 — the displayed window + (optional) one-barangay
        // narrowing every downstream computation (chart/tiles/table)
        // shares, so they can never disagree with each other about
        // which real cases are "in scope." Disease selection is NOT
        // applied yet here — the default relevance filter ('all', when
        // no disease is explicitly picked) needs real recent case
        // counts BEFORE it can decide which diseases qualify, so disease
        // narrowing happens in a second pass just below.
        $inDisplayWindow = array_values(array_filter($eligible, function ($c) use ($displayStart, $today, $barangayFilter) {
            if ($c['date'] === '' || $c['date'] < $displayStart || $c['date'] > $today) {
                return false;
            }
            if ($barangayFilter !== 'all' && ($c['barangay'] ?? '') !== $barangayFilter) {
                return false;
            }

            return true;
        }));

        // "Has real recent activity" (the default relevance filter below)
        // is based on these same real displayed-window counts.
        $displayCountsById = [];
        foreach ($inDisplayWindow as $c) {
            $id = $c['categoryId'] ?? '';
            if ($id === '') {
                continue;
            }
            $displayCountsById[$id] = ($displayCountsById[$id] ?? 0) + 1;
        }
        arsort($displayCountsById);

        if ($requestedIds !== []) {
            $shownIds = $requestedIds;
        } else {
            // 'all' (or an unrecognized/empty selection, which resolves
            // the same honest way "all" does) — every disease with real
            // activity: a real case somewhere in the displayed 14-day
            // window, OR an active outbreak-monitoring signal (elevated/
            // potential outbreak) from its own full real history even
            // if that activity falls just outside the displayed window.
            // Never an arbitrary top-N cut (that silently hid a real,
            // currently-relevant disease once there were too many), and
            // never literally every GIS-eligible disease regardless of
            // activity either (a long flat list of permanently zero-case
            // diseases just buried the ones that actually matter). A
            // viewer who wants one specific disease regardless of its
            // own activity already has the Disease filter's explicit
            // pick for that.
            $shownIds = [];
            foreach ($monitoredIds as $id) {
                if (($displayCountsById[$id] ?? 0) > 0) {
                    $shownIds[] = $id;
                    continue;
                }
                $casesAllTimeForStatus = array_values(array_filter($eligible, fn ($c) => ($c['categoryId'] ?? '') === $id
                    && ($barangayFilter === 'all' || ($c['barangay'] ?? '') === $barangayFilter)));
                $statusForId = hc_disease_outbreak_status($casesAllTimeForStatus);
                if (in_array($statusForId['status'], ['elevated', 'potential_outbreak'], true)) {
                    $shownIds[] = $id;
                }
            }
        }

        $inWindow = $shownIds === []
            ? $inDisplayWindow
            : array_values(array_filter($inDisplayWindow, fn ($c) => in_array($c['categoryId'] ?? '', $shownIds, true)));

        $shownDiseases = array_values(array_filter(array_map(
            fn ($id) => isset($nameById[$id]) ? ['id' => $id, 'name' => $nameById[$id]] : null,
            $shownIds
        )));

        // ------------------------------------------------------------
        // Chart — real last-14-real-days actual cases (solid) via the
        // shared bucketing helper, plus each shown disease's own 7-day-
        // ahead forecast (dashed) + uncertainty band continuing forward
        // from TODAY, connected to the last real actual value with no
        // visual gap — real calendar dates throughout, never a generic
        // placeholder.
        //
        // $forceCategories is always passed explicitly — $shownDiseases
        // is already the definitive, single-source-of-truth disease
        // list (explicit selection, Top 5, or the top-6-by-count
        // default), so the trend aggregator's own internal top-N
        // ranking never needs to re-derive it (and critically, never
        // silently drops an explicitly-selected disease that happens to
        // have zero real cases in this window — see
        // hc_notifiable_disease_trend_aggregated()'s own header comment
        // on $forceCategories for why that mattered).
        // ------------------------------------------------------------
        $trend = hc_notifiable_disease_trend_aggregated(
            $inWindow,
            $displayStart,
            $today,
            'day',
            6,
            $barangayFilter !== 'all' ? $barangayFilter : null,
            array_column($shownDiseases, 'name'),
            true // $fullRange — always draw the full fixed 14-day window, zero-filled, never trimmed to wherever real cases happen to fall
        );

        // Backfill a real id for a forced-in, zero-case disease — the
        // aggregator can only derive 'id' from cases actually present in
        // $filtered, which a genuinely zero-case disease never has any
        // of, but $shownDiseases (this function's own, already-resolved
        // name->id source of truth) always knows it regardless. Without
        // this, such a disease's dataset would fall back to coloring by
        // display name instead of its real stable id client-side.
        $idByShownName = array_column($shownDiseases, 'id', 'name');
        foreach ($trend['datasets'] as &$dataset) {
            if (($dataset['id'] ?? '') === '') {
                $dataset['id'] = $idByShownName[$dataset['label']] ?? '';
            }
        }
        unset($dataset);

        // Real future calendar day labels (hc_disease_outbreak_forecast_labels()),
        // continuing forward from TODAY — never a generic placeholder.
        $forecastLabels = hc_disease_outbreak_forecast_labels($forecastDays);

        foreach ($trend['datasets'] as &$dataset) {
            $dataset['forecast'] = null;
            $dataset['forecastUpper'] = null;
            $dataset['forecastLower'] = null;
            // One disease's own bad/incomplete data must never take the
            // whole chart down — isolated here per disease, logged, and
            // skipped (that dataset simply keeps its real actual-case
            // line with no forecast segment) rather than throwing.
            try {
                $diseaseId = $dataset['id'] ?? '';
                if ($diseaseId === '') {
                    continue;
                }
                // Forecasting is trained on this disease(+barangay)'s own
                // real series over the WIDER training window
                // ($trainingDays, e.g. 60 real days) — deliberately more
                // history than the 14 days actually drawn on the chart,
                // for a better-informed real OLS fit. Matched by ID, not
                // display name, so a mid-session rename can never
                // silently orphan a disease's own forecast.
                $diseaseCasesForTraining = array_values(array_filter($eligible, fn ($c) => ($c['categoryId'] ?? '') === $diseaseId
                    && $c['date'] >= $trainingStart && $c['date'] <= $today
                    && ($barangayFilter === 'all' || ($c['barangay'] ?? '') === $barangayFilter)));
                $counts = hc_disease_outbreak_daily_counts($diseaseCasesForTraining, $trainingDays);
                $forecast = hc_disease_outbreak_forecast_series($counts, $forecastDays);
                if ($forecast['hasSignal']) {
                    $dataset['forecast'] = $forecast['values'];
                    $dataset['forecastUpper'] = $forecast['upper'];
                    $dataset['forecastLower'] = $forecast['lower'];
                }
            } catch (\Throwable $e) {
                error_log('hc_disease_outbreak_monitoring(): forecast failed for disease "' . ($dataset['label'] ?? '?') . '": ' . $e->getMessage());
            }
        }
        unset($dataset);

        $chart = [
            'labels' => $trend['labels'],
            'forecastLabels' => $forecastLabels,
            'datasets' => $trend['datasets'],
            // The real index (0-based, within the combined labels+forecastLabels
            // axis) where history ends and the forecast begins — the
            // chart draws its own clearly-marked "Forecast Begins"
            // boundary there (assets/js/disease-outbreak-forecast.js),
            // computed from real data rather than assumed.
            'boundaryIndex' => count($trend['labels']) - 1,
        ];

        // ------------------------------------------------------------
        // Monitoring table + "Diseases with Elevated Activity" /
        // "Potential Outbreak Signals" tiles — "current" / "previous"
        // are the real last-14-days vs. the 14 real days before that,
        // answering "which diseases are showing unusual increases RIGHT
        // NOW."
        // ------------------------------------------------------------
        $statusMeta = hc_disease_outbreak_status_meta();
        $table = [];
        $elevatedCount = 0;
        foreach ($shownDiseases as $disease) {
            // Same isolation as the forecast loop above — one disease's
            // bad data is skipped (and logged) rather than failing the
            // whole monitoring table.
            try {
                $diseaseCasesInDisplayWindow = array_values(array_filter($inDisplayWindow, fn ($c) => ($c['categoryId'] ?? '') === $disease['id']));
                $diseaseCasesPreviousPeriod = array_values(array_filter($eligible, fn ($c) => ($c['categoryId'] ?? '') === $disease['id']
                    && $c['date'] > $previousPeriodStart && $c['date'] <= $displayStart
                    && ($barangayFilter === 'all' || ($c['barangay'] ?? '') === $barangayFilter)));
                // Status/baseline always looks at ALL real history for
                // this disease(+barangay) — not just the displayed
                // window — the most statistically stable real baseline
                // available.
                $diseaseCasesAllTime = array_values(array_filter($eligible, fn ($c) => ($c['categoryId'] ?? '') === $disease['id']
                    && ($barangayFilter === 'all' || ($c['barangay'] ?? '') === $barangayFilter)));

                $currentCases = count($diseaseCasesInDisplayWindow);
                $previousCases = count($diseaseCasesPreviousPeriod);

                $status = hc_disease_outbreak_status($diseaseCasesAllTime);
                if (in_array($status['status'], ['elevated', 'potential_outbreak'], true)) {
                    $elevatedCount++;
                }

                $table[] = [
                    'disease' => $disease['name'],
                    'diseaseId' => $disease['id'],
                    'currentCases' => $currentCases,
                    'previousCases' => $previousCases,
                    'trend' => $currentCases > $previousCases ? 'increasing' : ($currentCases < $previousCases ? 'decreasing' : 'stable'),
                    'status' => $status['status'],
                    'statusMeta' => $statusMeta[$status['status']],
                ];
            } catch (\Throwable $e) {
                error_log('hc_disease_outbreak_monitoring(): monitoring row failed for disease "' . ($disease['name'] ?? '?') . '": ' . $e->getMessage());
            }
        }
        usort($table, fn ($a, $b) => $b['currentCases'] <=> $a['currentCases']);

        // "Potential Outbreak Signals" — ALWAYS scanned across every one
        // of the 11 real covered barangays, regardless of the barangay
        // filter the user currently has selected (that filter narrows
        // the chart/table/other tiles, not this one) — same per-
        // barangay+category granularity hc_disease_demand_forecast()'s
        // own $riskAlerts already use elsewhere on this page, just
        // re-scoped to GIS-eligible/confirmed cases only. Scans every
        // MONITORED disease (not just the ones currently shown on the
        // chart) — a disease a user hasn't selected can still have a
        // real potential-outbreak signal worth surfacing in the tile.
        $outbreakSignalCount = 0;
        $diseasesForSignalScan = $requestedIds !== [] ? $requestedIds : $monitoredIds;
        foreach ($diseasesForSignalScan as $diseaseId) {
            try {
                foreach (HC_COVERED_BARANGAYS as $barangay) {
                    $pairCases = array_values(array_filter($eligible, fn ($c) => ($c['categoryId'] ?? '') === $diseaseId && ($c['barangay'] ?? '') === $barangay));
                    if (hc_disease_outbreak_status($pairCases)['status'] === 'potential_outbreak') {
                        $outbreakSignalCount++;
                    }
                }
            } catch (\Throwable $e) {
                error_log('hc_disease_outbreak_monitoring(): outbreak-signal scan failed for disease id "' . $diseaseId . '": ' . $e->getMessage());
            }
        }

        $diseasesMonitoredCount = count(array_unique(array_column($inWindow, 'categoryId')));

        $summary = [
            'totalRecordedCases' => count($inWindow),
            'diseasesMonitored' => $diseasesMonitoredCount,
            'elevatedActivity' => $elevatedCount,
            'outbreakSignals' => $outbreakSignalCount,
        ];

        return [
            'summary' => $summary,
            'chart' => $chart,
            'table' => $table,
            'monitoredDiseases' => $monitoredDiseases,
            'currentPeriodLabel' => 'Last ' . $displayDays . ' Days',
        ];
    }
}
