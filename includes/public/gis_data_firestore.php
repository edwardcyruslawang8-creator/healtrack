<?php
/**
 * =====================================================================
 * GIS Mapping — Real Data (patient/gis.php, api/gis-data.php,
 * index.php's home preview widget)
 * ---------------------------------------------------------------------
 * Real notifiable-disease case counts per barangay, over whichever real
 * window patient/gis.php's date-range dropdown selects (Last Week/3/6/12
 * months, default 3 months — see hc_gis_date_range_spec() below and
 * includes/public/gis-data.php) — reuses hc_disease_demand_forecast()'s
 * own real keyword-matched case list
 * (includes/shared/predictive_analytics_core.php, the same real
 * detection every other predictive feature in this app is built on)
 * rather than duplicating that matching logic here, then re-aggregates
 * it into what THIS page needs: a total over the selected window instead
 * of that function's own 14-day window, per-barangay sums instead of
 * per-barangay-per-category risk tiers, and a "vs previous [window]"
 * trend instead of a 7-vs-prior-7-day one.
 *
 * System-wide (every real patient/consultation/medical record), not
 * scoped to one facility — this is a public patient-facing page, not a
 * staff one.
 *
 * The map's polygon SHAPES are the official PSA/NAMRIA barangay
 * boundaries — see includes/shared/gis_geo.php's header comment. The
 * case counts/intensity/trend attached to each shape are computed here.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore
require_once __DIR__ . '/../firestore/medical_records.php';
require_once __DIR__ . '/../firestore/consultations.php';
require_once __DIR__ . '/../shared/predictive_analytics_core.php'; // hc_disease_demand_forecast(), HC_COVERED_BARANGAYS
require_once __DIR__ . '/../shared/gis_geo.php'; // hc_gis_barangay_shapes()

if (!function_exists('hc_gis_intensity_meta')) {
    /**
     * Same 5-tier scale patient/gis.php's own legend documents (Very High
     * 76-100 ... No Cases 0) — a single-hue maroon ramp that gets darker
     * as cases rise (choropleth), with a neutral gray for 0 cases.
     */
    function hc_gis_intensity_meta(int $cases): array
    {
        return match (true) {
            $cases >= 76 => ['label' => 'Very High', 'color' => '#6B0F0F'],
            $cases >= 51 => ['label' => 'High', 'color' => '#A31D1D'],
            $cases >= 26 => ['label' => 'Moderate', 'color' => '#D9534F'],
            $cases >= 1 => ['label' => 'Low', 'color' => '#F2A7A3'],
            default => ['label' => 'No Cases', 'color' => '#9CA3AF'],
        };
    }
}

if (!function_exists('hc_gis_date_range_spec')) {
    /**
     * Every real window patient/gis.php's date-range dropdown can select.
     * "Last Week" needs DAY-level cutoffs/buckets, not month-level —
     * $months alone couldn't express a 7-day window at all, which is the
     * whole reason this used to only offer 3/6/12-month options.
     *
     * @return array{key: string, label: string, periodLabel: string, cutoffCurrent: string, cutoffPrior: string, bucketUnit: 'day'|'month', bucketCount: int}
     */
    function hc_gis_date_range_spec(string $range): array
    {
        $specs = [
            '1w' => ['label' => 'Last Week', 'periodLabel' => 'week', 'cutoffCurrentRaw' => '-7 days', 'cutoffPriorRaw' => '-14 days', 'bucketUnit' => 'day', 'bucketCount' => 7],
            '3m' => ['label' => 'Last 3 Months', 'periodLabel' => '3 months', 'cutoffCurrentRaw' => '-3 months', 'cutoffPriorRaw' => '-6 months', 'bucketUnit' => 'month', 'bucketCount' => 3],
            '6m' => ['label' => 'Last 6 Months', 'periodLabel' => '6 months', 'cutoffCurrentRaw' => '-6 months', 'cutoffPriorRaw' => '-12 months', 'bucketUnit' => 'month', 'bucketCount' => 6],
            '1y' => ['label' => 'Last Year', 'periodLabel' => '12 months', 'cutoffCurrentRaw' => '-12 months', 'cutoffPriorRaw' => '-24 months', 'bucketUnit' => 'month', 'bucketCount' => 12],
        ];
        $spec = $specs[$range] ?? $specs['1w'];

        // Deliberately separate keys (cutoffCurrentRaw/cutoffPriorRaw
        // above vs. the resolved cutoffCurrent/cutoffPrior returned
        // below) rather than overwriting in place — `$spec + [...]`
        // would have looked equivalent but silently keeps $spec's own
        // value on any colliding key (array union favors the LEFT
        // operand), which would have left cutoffCurrent as the literal
        // string "-7 days" instead of a real resolved date.
        return [
            'key' => $range,
            'label' => $spec['label'],
            'periodLabel' => $spec['periodLabel'],
            'cutoffCurrent' => date('Y-m-d', strtotime($spec['cutoffCurrentRaw'])),
            'cutoffPrior' => date('Y-m-d', strtotime($spec['cutoffPriorRaw'])),
            'bucketUnit' => $spec['bucketUnit'],
            'bucketCount' => $spec['bucketCount'],
        ];
    }
}

if (!function_exists('hc_gis_disease_overview')) {
    /**
     * @param string $range The selected window (patient/gis.php's
     *                       date-range dropdown: '1w'/'3m'/'6m'/'1y' —
     *                       see hc_gis_date_range_spec()) — everything
     *                       below (summary, top diseases, per-barangay
     *                       map counts, the health-trend chart's
     *                       buckets) is computed over this same real
     *                       window, with "prior" comparisons using an
     *                       equal-length window immediately before it.
     *                       Defaults to '1w' — the shortest, most-present
     *                       window — so any caller that omits this shows
     *                       exactly what the system currently has, not a
     *                       picture diluted by older months. Real case
     *                       volume here is thin enough that this often
     *                       renders "No Data"/empty — that's still an
     *                       honest, real view, not a bug.
     * @return array{gisSummary: array, topDiseases: array, healthTrend: array, geojson: array, seasonalOverview: array}
     */
    function hc_gis_disease_overview(string $range = '1w'): array
    {
        global $firestore;

        $spec = hc_gis_date_range_spec($range);

        $medicalRecordDocs = array_values(array_filter(
            hc_get_all_medical_record_docs(),
            fn ($doc) => ($doc['fields']['type'] ?? '') === 'consultation'
        ));
        $consultations = hc_get_all_consultations();

        $patientBarangayById = [];
        $patientAgeById = [];
        foreach ($firestore->listDocuments('patients') as $doc) {
            $patientBarangayById[$doc['id']] = trim((string) ($doc['fields']['barangay'] ?? ''));
            $patientAgeById[$doc['id']] = (int) ($doc['fields']['age'] ?? 0);
        }

        $forecast = hc_disease_demand_forecast($medicalRecordDocs, $consultations, $patientBarangayById, $patientAgeById);
        $notifiableCases = $forecast['notifiableCases']; // every real matched case, all time — see that function's own header comment for the keyword list

        $cutoffCurrent = $spec['cutoffCurrent'];
        $cutoffPrior = $spec['cutoffPrior'];
        $current = array_values(array_filter($notifiableCases, fn ($c) => $c['date'] >= $cutoffCurrent));
        $prior = array_values(array_filter($notifiableCases, fn ($c) => $c['date'] >= $cutoffPrior && $c['date'] < $cutoffCurrent));

        // ---------------------------------------------------------------
        // Per-barangay totals (current window) — restricted to the 11
        // real covered barangays, same as every other real barangay-
        // scoped feature in this app.
        // ---------------------------------------------------------------
        $byBarangay = array_fill_keys(HC_COVERED_BARANGAYS, 0);
        // Which real diseases make up that barangay's count — "7 cases"
        // alone doesn't say of WHAT, so the map hover/popup breaks it
        // down by the same notifiable-disease category used everywhere
        // else on this page (hc_disease_demand_forecast()'s own keyword
        // matching, not a separate guess).
        $byBarangayDisease = array_fill_keys(HC_COVERED_BARANGAYS, []);
        foreach ($current as $c) {
            if (isset($byBarangay[$c['barangay']])) {
                $byBarangay[$c['barangay']]++;
                $byBarangayDisease[$c['barangay']][$c['category']] = ($byBarangayDisease[$c['barangay']][$c['category']] ?? 0) + 1;
            }
        }
        $affectedCount = count(array_filter($byBarangay, fn ($n) => $n > 0));
        $totalCovered = count(HC_COVERED_BARANGAYS);

        // ---------------------------------------------------------------
        // Summary card
        // ---------------------------------------------------------------
        $totalCases = count($current);
        $totalPrior = count($prior);
        $trendPct = $totalPrior > 0 ? round((($totalCases - $totalPrior) / $totalPrior) * 100) : ($totalCases > 0 ? 100 : 0);

        $gisSummary = [
            'totalCases' => number_format($totalCases),
            'totalTrend' => abs($trendPct) . '%',
            'totalTrendDir' => $trendPct >= 0 ? 'up' : 'down',
            'affectedCount' => (string) $affectedCount,
            'affectedOfTotal' => (string) $totalCovered,
            'affectedPercent' => ($totalCovered > 0 ? round(($affectedCount / $totalCovered) * 100) : 0) . '% of total',
            'periodLabel' => $spec['periodLabel'],
        ];

        // ---------------------------------------------------------------
        // Top 5 diseases (by notifiable-disease category), current window
        // ---------------------------------------------------------------
        $byCategory = [];
        $byCategoryPrior = [];
        foreach ($current as $c) {
            $byCategory[$c['category']] = ($byCategory[$c['category']] ?? 0) + 1;
        }
        foreach ($prior as $c) {
            $byCategoryPrior[$c['category']] = ($byCategoryPrior[$c['category']] ?? 0) + 1;
        }
        arsort($byCategory);

        $topDiseases = [];
        foreach (array_slice($byCategory, 0, 5, true) as $name => $cases) {
            $priorCases = $byCategoryPrior[$name] ?? 0;
            $changePct = $priorCases > 0 ? round((($cases - $priorCases) / $priorCases) * 100) : ($cases > 0 ? 100 : 0);
            // Real, computed "possible causes" (hc_disease_causes_narrative())
            // over this SAME category's own all-time cases — not a fixed
            // per-category lookup. See that function's own header comment
            // for exactly what it looks at (barangay concentration, season,
            // age group, trend).
            $categoryCasesAllTime = array_values(array_filter($notifiableCases, fn ($c) => $c['category'] === $name));
            $topDiseases[] = [
                'name' => $name,
                'cases' => $cases,
                'percent' => $totalCases > 0 ? round(($cases / $totalCases) * 100, 1) . '%' : '0%',
                'trend' => $changePct >= 0 ? 'up' : 'down',
                'change' => abs($changePct) . '%',
                'causes' => hc_disease_causes_narrative($categoryCasesAllTime, $patientAgeById),
            ];
        }

        // Seasonal Patterns — true calendar-month/rainy-vs-dry view, all
        // real years merged, independent of $range (see
        // hc_gis_seasonal_overview()'s own header comment for why a
        // rolling window wouldn't make sense here).
        $seasonalOverview = hc_gis_seasonal_overview($notifiableCases);

        // ---------------------------------------------------------------
        // Health Trend — real notifiable-disease case totals over the
        // selected window, split into rolling buckets counting back from
        // today (patient/gis.php's left-panel "Health Trend" card) — one
        // real calendar DAY per bucket for "Last Week" (a month-sized
        // bucket would be meaningless over a 7-day span), one rolling
        // ~30-day bucket per bucket otherwise. Deliberately NOT bucketed
        // by calendar month (Jan/Feb/Mar boundaries) for the month-scale
        // ranges — that would silently disagree with $current/$gisSummary
        // above, which use a rolling "today minus N" window. Bucket
        // boundaries are the exact same ones $current/$prior use, so
        // summing every bucket here always equals $totalCases exactly.
        // ---------------------------------------------------------------
        $trendLabels = [];
        $trendValues = [];
        if ($spec['bucketUnit'] === 'day') {
            for ($i = $spec['bucketCount'] - 1; $i >= 0; $i--) {
                $day = date('Y-m-d', strtotime("-$i days"));
                $trendLabels[] = date('M j', strtotime($day));
                $trendValues[] = count(array_filter($notifiableCases, fn ($c) => $c['date'] === $day));
            }
        } else {
            for ($i = $spec['bucketCount'] - 1; $i >= 0; $i--) {
                $bucketEnd = date('Y-m-d', strtotime("-$i months"));
                $bucketStart = date('Y-m-d', strtotime('-' . ($i + 1) . ' months'));
                $trendLabels[] = date('M j', strtotime($bucketEnd));
                $trendValues[] = count(array_filter(
                    $notifiableCases,
                    fn ($c) => $c['date'] >= $bucketStart && ($i === 0 ? true : $c['date'] < $bucketEnd)
                ));
            }
        }
        // Direction reflects the most recent real change (last bucket vs.
        // the one before it) rather than a first-half-vs-second-half sum —
        // a half-vs-half sum can read as "Increasing" even when the line
        // has clearly turned downward right at the end (e.g. a spike the
        // month before last, then a drop last month), which looked wrong
        // next to the chart itself.
        $lastValue = end($trendValues);
        $prevValue = $trendValues[count($trendValues) - 2] ?? $lastValue;
        $healthTrend = [
            'labels' => $trendLabels,
            'values' => $trendValues,
            'direction' => $lastValue > $prevValue ? 'up' : ($lastValue < $prevValue ? 'down' : 'stable'),
        ];

        // ---------------------------------------------------------------
        // Map GeoJSON — real counts/intensity on official boundaries (see
        // includes/shared/gis_geo.php's header comment).
        // ---------------------------------------------------------------
        $features = [];
        foreach (hc_gis_barangay_shapes() as $name => $shape) {
            $cases = $byBarangay[$name];
            $meta = hc_gis_intensity_meta($cases);
            $diseaseCounts = $byBarangayDisease[$name];
            arsort($diseaseCounts);
            $diseases = [];
            foreach ($diseaseCounts as $category => $count) {
                $diseases[] = ['name' => $category, 'cases' => $count];
            }
            $features[] = [
                'type' => 'Feature',
                'properties' => [
                    'name' => $name,
                    'officialName' => $shape['officialName'],
                    'psgc' => $shape['psgc'],
                    'district' => $shape['district'],
                    'cases' => $cases,
                    'diseases' => $diseases,
                    'intensity' => $meta['label'],
                    'color' => $meta['color'],
                    'centroid' => $shape['centroid'],
                ],
                'geometry' => $shape['geometry'],
            ];
        }
        $geojson = ['type' => 'FeatureCollection', 'features' => $features];

        return compact('gisSummary', 'topDiseases', 'healthTrend', 'geojson', 'seasonalOverview');
    }
}
