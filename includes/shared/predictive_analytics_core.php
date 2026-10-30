<?php
/**
 * =====================================================================
 * Shared — Predictive Analytics Core (6.3.1–6.3.4)
 * ---------------------------------------------------------------------
 * The 4 tab computations behind includes/admin/facility_analytics_firestore.php's
 * per-facility "Predictive & Prescriptive" tab — extracted into their
 * own functions (rather than left inline in that file) so each one
 * takes its input collections as parameters instead of reading
 * Firestore directly. The caller decides whether those inputs are
 * system-wide or already filtered to one facility — Medicine & Vaccine
 * Shortage is always called with system-wide inventory (no per-facility
 * field exists for it), while Disease & Demand Forecast, Inventory
 * Replenishment's request-volume half, and Consultation Trends by
 * Barangay are called with this facility's own scoped data. Same forecasting
 * method throughout (see includes/shared/forecast_helpers.php's header
 * comment): ordinary least-squares linear regression over real daily
 * counts, degrading honestly to "not enough history yet" instead of a
 * fabricated confident-looking number.
 *
 * 6.3.1 Disease & Service Demand Forecast: keyword-matches medical_records'
 * free-text diagnosis/chiefComplaint (no coded diagnosis field exists)
 * against a documented notifiable/communicable-disease list, joined to
 * each patient's real barangay.
 *
 * 6.3.2 Medicine & Vaccine Shortage: projects days-until-stockout from
 * real current stock against a real order/prescription-FREQUENCY proxy
 * (fuzzy-matched by name, since prescribing is free text) — a
 * frequency proxy, not true units consumed, since no numeric
 * dispensed-quantity field exists anywhere in this app's schema.
 * Inventory itself has no per-facility field anywhere in the schema
 * (system-wide by design — see facility_analytics_firestore.php's own
 * header comment), so a caller scoping this "per facility" is scoping
 * the ORDER data only; the stock numbers are always the same real
 * system-wide figures regardless of which facility asked.
 *
 * 6.3.3 Inventory Replenishment: built on 6.3.2's projections plus the
 * real "stock_requests" collection, which DOES carry a real per-doc
 * "facility" field — a caller can genuinely scope the request-volume/
 * gap-alert half of this tab per facility even though 6.3.2's stock
 * numbers can't be.
 *
 * 6.3.4 Consultation Trends by Barangay: real consultations joined to
 * each patient's real barangay, restricted to the 11 real barangays
 * this clinic covers.
 * =====================================================================
 */

require_once __DIR__ . '/forecast_helpers.php'; // hc_last_n_days(), hc_forecast_linear_fit(), hc_forecast_project()
require_once __DIR__ . '/inventory_match.php'; // hc_normalize_text(), hc_item_names_match()

// The 11 real Lipa City barangays this clinic covers — same list
// includes/medical-staff/add_patient_modal.php:163 offers at patient
// registration.
const HC_COVERED_BARANGAYS = ['Balintawak', 'Bugtong', 'Bulacnin', 'Dagatan', 'Inosluban', 'Lumbang', 'Marawoy', 'Plaridel', 'Pusil', 'San Lucas', 'Talisay'];

if (!function_exists('hc_predictive_severity_meta')) {
    /**
     * severity -> the shared Risk/Alert Indicator row shape
     * (icon/iconBg/iconColor/valueColor/badge/badgeClass), rendered via
     * admin-dashboard.css's .adm-status-list/.adm-status-row (the same
     * component the dashboard's own "Account Status Overview" uses).
     */
    function hc_predictive_severity_meta(string $severity): array
    {
        return match ($severity) {
            'critical' => ['badge' => 'CRITICAL', 'badgeClass' => 'critical', 'icon' => 'bi-exclamation-octagon-fill', 'iconBg' => '#FCE4E4', 'iconColor' => '#D93025', 'valueColor' => '#D93025'],
            'elevated', 'warning' => ['badge' => 'ELEVATED', 'badgeClass' => 'elevated', 'icon' => 'bi-exclamation-triangle-fill', 'iconBg' => '#FDECDD', 'iconColor' => '#E8710A', 'valueColor' => '#E8710A'],
            default => ['badge' => 'WATCH', 'badgeClass' => 'watch', 'icon' => 'bi-eye-fill', 'iconBg' => '#E8F0FE', 'iconColor' => '#1A73E8', 'valueColor' => '#1A73E8'],
        };
    }
}

// =======================================================================
// 6.3.1 — Disease & Service Demand Forecast
// =======================================================================

if (!function_exists('hc_notifiable_disease_categories')) {
    /**
     * Notifiable/communicable-disease keyword categories — Philippine
     * primary-care-relevant, matched case-insensitively as a substring
     * against a medical_records doc's diagnosis + chiefComplaint text
     * combined. Chronic/non-communicable conditions (hypertension,
     * diabetes, etc.) are deliberately NOT here — this is specifically
     * about outbreak-relevant, community-spread conditions. Factored
     * out of hc_disease_demand_forecast() below so
     * hc_gis_seasonal_overview() shares this exact same category list
     * instead of carrying its own copy that could drift out of sync.
     *
     * @return array<string, array<int, string>>
     */
    function hc_notifiable_disease_categories(): array
    {
        return [
            'Dengue' => ['dengue'],
            'Influenza-like Illness' => ['influenza', 'flu-like', ' flu ', 'flu,'],
            'Acute Gastroenteritis / Diarrhea' => ['gastroenteritis', 'diarrhea', 'diarrhoea'],
            'Measles' => ['measles', 'rubeola'],
            'Chickenpox' => ['chickenpox', 'varicella'],
            'Leptospirosis' => ['leptospirosis'],
            'Typhoid Fever' => ['typhoid'],
            'Pneumonia' => ['pneumonia'],
            'COVID-19' => ['covid'],
            'Tuberculosis' => ['tuberculosis', 'pulmonary tb'],
            'Hand-Foot-Mouth Disease' => ['hand-foot-mouth', 'hand, foot, and mouth', 'hfmd'],
            'Conjunctivitis (Sore Eyes)' => ['conjunctivitis', 'sore eyes'],
            'Mumps' => ['mumps'],
            'Pertussis (Whooping Cough)' => ['pertussis', 'whooping cough'],
        ];
    }
}

if (!function_exists('hc_disease_age_bracket')) {
    /** Real age (patients' own stored "age" field) -> a 5-bracket group label, or '' for 0/unknown. */
    function hc_disease_age_bracket(int $age): string
    {
        return match (true) {
            $age <= 0 => '',
            $age <= 9 => '0-9',
            $age <= 19 => '10-19',
            $age <= 39 => '20-39',
            $age <= 59 => '40-59',
            default => '60+',
        };
    }
}

if (!function_exists('hc_disease_causes_narrative')) {
    /**
     * Real, computed "possible causes" sentence for a set of already-
     * matched notifiable-disease cases — NOT a fixed per-category
     * lookup. Looks at the SAME real cases already shown wherever this
     * renders (GIS Mapping's Top Diseases list, Predictive Analytics'
     * risk alert rows), from up to 4 independent real signals, each
     * included only when the data actually says something (a thin or
     * one-sided dataset just produces fewer clauses, never a fabricated-
     * sounding claim):
     *   - Barangay concentration: which real barangay these cases
     *     cluster in. Naturally silent for a risk-alert row, which is
     *     already scoped to one barangay — nothing to say there.
     *   - Seasonal skew: real rainy (Jun-Nov) vs dry (Dec-May) share,
     *     only stated once there's enough of a historical spread to
     *     mean anything.
     *   - Age-group concentration: which real age bracket
     *     (hc_disease_age_bracket()) most of these patients fall in,
     *     via $patientAgeById — patients with no recorded age are
     *     simply excluded rather than counted as a bracket.
     *   - Trend: real recent-7-days vs prior-7-days change WITHIN this
     *     exact case set.
     *
     * @param array $cases A subset of hc_disease_demand_forecast()'s own 'notifiableCases' (each with date/category/barangay/patientId).
     * @param array $patientAgeById patientId => real age (int); 0/missing = unknown.
     * @return string Empty when there isn't enough real signal to say anything.
     */
    function hc_disease_causes_narrative(array $cases, array $patientAgeById): string
    {
        if ($cases === []) {
            return '';
        }

        $clauses = [];

        // --- Barangay concentration ---
        $barangays = array_values(array_filter(array_column($cases, 'barangay'), fn ($b) => $b !== ''));
        $barangayCounts = array_count_values($barangays);
        if (count($barangayCounts) > 1) {
            arsort($barangayCounts);
            $topBarangay = array_key_first($barangayCounts);
            $topShare = round($barangayCounts[$topBarangay] / count($barangays) * 100);
            if ($topShare >= 40) {
                $clauses[] = $topShare . '% of these cases are in Barangay ' . $topBarangay;
            }
        }

        // --- Seasonal skew ---
        $rainyMonths = [6, 7, 8, 9, 10, 11];
        $rainy = 0;
        $dry = 0;
        foreach ($cases as $c) {
            $ts = strtotime($c['date']);
            if ($ts === false) {
                continue;
            }
            if (in_array((int) date('n', $ts), $rainyMonths, true)) {
                $rainy++;
            } else {
                $dry++;
            }
        }
        $seasonTotal = $rainy + $dry;
        if ($seasonTotal >= 4) {
            $rainyShare = round($rainy / $seasonTotal * 100);
            if ($rainyShare >= 65) {
                $clauses[] = $rainyShare . '% occurred during the rainy season (Jun-Nov)';
            } elseif ($rainyShare <= 35) {
                $clauses[] = (100 - $rainyShare) . '% occurred during the dry season (Dec-May)';
            }
        }

        // --- Age-group concentration ---
        $bracketCounts = [];
        $knownAgeTotal = 0;
        foreach ($cases as $c) {
            $bracket = hc_disease_age_bracket((int) ($patientAgeById[$c['patientId'] ?? ''] ?? 0));
            if ($bracket === '') {
                continue;
            }
            $bracketCounts[$bracket] = ($bracketCounts[$bracket] ?? 0) + 1;
            $knownAgeTotal++;
        }
        if ($knownAgeTotal >= 3) {
            arsort($bracketCounts);
            $topBracket = array_key_first($bracketCounts);
            $topBracketShare = round($bracketCounts[$topBracket] / $knownAgeTotal * 100);
            if ($topBracketShare >= 40) {
                $clauses[] = $topBracketShare . '% of patients are age ' . $topBracket;
            }
        }

        // --- Trend (within this exact case set) ---
        $cutoff7 = date('Y-m-d', strtotime('-7 days'));
        $cutoff14 = date('Y-m-d', strtotime('-14 days'));
        $recent7 = count(array_filter($cases, fn ($c) => $c['date'] >= $cutoff7));
        $prior7 = count(array_filter($cases, fn ($c) => $c['date'] >= $cutoff14 && $c['date'] < $cutoff7));
        if ($prior7 > 0 && $recent7 > $prior7) {
            $changePct = round((($recent7 - $prior7) / $prior7) * 100);
            $clauses[] = 'cases rose ' . $changePct . '% in the last 7 days vs the 7 before';
        } elseif ($prior7 === 0 && $recent7 > 0) {
            $clauses[] = 'these are newly emerging cases with no prior-week activity';
        }

        if ($clauses === []) {
            return '';
        }

        return ucfirst(implode('; ', $clauses)) . '.';
    }
}

if (!function_exists('hc_disease_demand_forecast')) {
    /**
     * @param array $medicalRecordDocs Raw medical_records docs (type=consultation only), already scoped by the caller (system-wide or one facility's own).
     * @param array $consultations hc_get_all_consultations()-shaped rows, same scoping as $medicalRecordDocs.
     * @param array $patientBarangayById patientId => real barangay string.
     * @param array $patientAgeById patientId => real age (int) — optional (default []), only needed by a caller that renders hc_disease_causes_narrative()'s age-group clause; every other caller (dashboard preview widgets that never show that narrative) can omit it with no behavior change.
     */
    function hc_disease_demand_forecast(array $medicalRecordDocs, array $consultations, array $patientBarangayById, array $patientAgeById = []): array
    {
        $keywordCategories = hc_notifiable_disease_categories();

        $notifiableCases = []; // [['date' => Y-m-d, 'category' => ..., 'barangay' => ..., 'patientId' => ...], ...]
        foreach ($medicalRecordDocs as $doc) {
            $f = $doc['fields'];
            $text = hc_normalize_text(($f['diagnosis'] ?? '') . ' ' . ($f['chiefComplaint'] ?? ''));
            if ($text === '') {
                continue;
            }
            foreach ($keywordCategories as $category => $keywords) {
                foreach ($keywords as $kw) {
                    if (str_contains($text, $kw)) {
                        $notifiableCases[] = [
                            'date' => substr((string) ($f['date'] ?? ''), 0, 10),
                            'category' => $category,
                            'barangay' => $patientBarangayById[$f['patientId'] ?? ''] ?? '',
                            'patientId' => $f['patientId'] ?? '',
                        ];
                        break 2; // one category per case — first keyword hit wins
                    }
                }
            }
        }

        $cutoff14 = date('Y-m-d', strtotime('-14 days'));
        $cutoff7 = date('Y-m-d', strtotime('-7 days'));
        $casesLast14 = array_values(array_filter($notifiableCases, fn ($c) => $c['date'] >= $cutoff14 && $c['date'] !== ''));

        // Barangay + category risk tiers over the trailing 14 real days —
        // Watch at >=2 cases, Elevated at >=4, Critical at >=6 AND still
        // rising (more cases in the most recent 7 days than the 7 before
        // that). Thresholds are deliberately low given this clinic's real
        // patient volume.
        $barangayCategoryTallies = [];
        foreach ($casesLast14 as $c) {
            if ($c['barangay'] === '') {
                continue;
            }
            $key = $c['barangay'] . '|' . $c['category'];
            $barangayCategoryTallies[$key] ??= ['barangay' => $c['barangay'], 'category' => $c['category'], 'count14' => 0, 'countRecent7' => 0, 'countPrior7' => 0];
            $barangayCategoryTallies[$key]['count14']++;
            if ($c['date'] >= $cutoff7) {
                $barangayCategoryTallies[$key]['countRecent7']++;
            } else {
                $barangayCategoryTallies[$key]['countPrior7']++;
            }
        }

        $riskAlerts = [];
        foreach ($barangayCategoryTallies as $t) {
            $severity = null;
            if ($t['count14'] >= 6 && $t['countRecent7'] > $t['countPrior7']) {
                $severity = 'critical';
            } elseif ($t['count14'] >= 4) {
                $severity = 'elevated';
            } elseif ($t['count14'] >= 2) {
                $severity = 'watch';
            }
            if ($severity === null) {
                continue;
            }
            $riskAlerts[] = ['severity' => $severity, 'barangay' => $t['barangay'], 'category' => $t['category'], 'count14' => $t['count14']];
        }
        usort($riskAlerts, fn ($a, $b) => array_search($a['severity'], ['critical', 'elevated', 'watch']) <=> array_search($b['severity'], ['critical', 'elevated', 'watch']));
        $barangaysUnderWatch = count(array_unique(array_column($riskAlerts, 'barangay')));

        // Real statistical anomaly flag — alongside (not instead of) the
        // fixed-threshold severity tiers above. For every barangay+
        // category pair already risk-flagged, computes that SAME pair's
        // own 30-day historical daily mean + standard deviation (the 30
        // real days BEFORE the most recent 7, plain PHP array math, no
        // library), then flags 'anomaly' => true when the most recent
        // 7-day daily average sits more than ~2 standard deviations above
        // that pair's own baseline — a genuine statistical outlier check,
        // not just "the raw count crossed a fixed number".
        foreach ($riskAlerts as &$alert) {
            $pairCases = array_values(array_filter(
                $notifiableCases,
                fn ($c) => $c['barangay'] === $alert['barangay'] && $c['category'] === $alert['category']
            ));
            $pairDates = array_column($pairCases, 'date');

            $baselineDaily = [];
            for ($i = 37; $i >= 8; $i--) {
                $day = date('Y-m-d', strtotime("-$i days"));
                $baselineDaily[] = count(array_filter($pairDates, fn ($d) => $d === $day));
            }
            $baselineMean = array_sum($baselineDaily) / count($baselineDaily);
            $baselineVariance = array_sum(array_map(fn ($v) => ($v - $baselineMean) ** 2, $baselineDaily)) / count($baselineDaily);
            $baselineSD = sqrt($baselineVariance);

            $recentAvg = count(array_filter($pairDates, fn ($d) => $d >= $cutoff7)) / 7;
            $alert['anomaly'] = $baselineSD > 0
                ? $recentAvg > $baselineMean + (2 * $baselineSD)
                : $recentAvg > $baselineMean;

            // Real, computed "possible causes" (hc_disease_causes_narrative())
            // over this SAME barangay+category pair's own all-time cases —
            // not a fixed per-category lookup. See that function's own
            // header comment for exactly what it looks at.
            $alert['causes'] = hc_disease_causes_narrative($pairCases, $patientAgeById);
        }
        unset($alert);

        // Consultation-volume forecast — the real "consultations"
        // collection (actual visits), not the diagnosis-text mining above.
        $consultDaily30 = hc_last_n_days(array_column($consultations, 'dateTime'), 30);
        $consultFit = hc_forecast_linear_fit($consultDaily30['values']);
        $consultForecast7 = hc_forecast_project($consultFit, 30, 7);
        $consultForecastTotal = array_sum($consultForecast7);
        $consultPast7Total = array_sum(array_slice($consultDaily30['values'], -7));

        // Real statistical anomaly flag on consultation VOLUME itself —
        // same baseline-mean/standard-deviation method as the per-
        // barangay+category check above, applied to the real daily
        // consultation counts already computed just above (the first 23
        // of the last 30 real days as the baseline, the most recent 7 as
        // "recent").
        $consultBaselineDaily = array_slice($consultDaily30['values'], 0, 23);
        $consultBaselineMean = array_sum($consultBaselineDaily) / count($consultBaselineDaily);
        $consultBaselineVariance = array_sum(array_map(fn ($v) => ($v - $consultBaselineMean) ** 2, $consultBaselineDaily)) / count($consultBaselineDaily);
        $consultBaselineSD = sqrt($consultBaselineVariance);
        $consultRecentAvg = $consultPast7Total / 7;
        $consultVolumeAnomaly = $consultBaselineSD > 0
            ? $consultRecentAvg > $consultBaselineMean + (2 * $consultBaselineSD)
            : $consultRecentAvg > $consultBaselineMean;

        $consultDaily14 = hc_last_n_days(array_column($consultations, 'dateTime'), 14);
        $forecastTrend = [
            'labels' => array_merge($consultDaily14['labels'], array_map(fn ($i) => date('M j', strtotime('+' . ($i + 1) . ' days')), range(0, 6))),
            'actual' => array_merge($consultDaily14['values'], array_fill(0, 7, null)),
            'forecast' => array_merge(array_fill(0, 13, null), [end($consultDaily14['values'])], $consultForecast7),
        ];

        // Trend graph — daily counts for whichever notifiable categories
        // actually have at least one real case in the last 90 days (was
        // 30 — widened so this chart reliably has real lines to draw
        // instead of going empty whenever a facility's recent 30-day
        // window happens to be thin; same reasoning as
        // hc_gis_seasonal_overview()'s own all-time window).
        $diseaseTrendWindowDays = 90;
        $cutoffDiseaseTrend = date('Y-m-d', strtotime("-$diseaseTrendWindowDays days"));
        $categoryTotalsTrend = [];
        foreach ($notifiableCases as $c) {
            if ($c['date'] >= $cutoffDiseaseTrend) {
                $categoryTotalsTrend[$c['category']] = ($categoryTotalsTrend[$c['category']] ?? 0) + 1;
            }
        }
        arsort($categoryTotalsTrend);
        $colors = ['#D93025', '#E8710A', '#7C3AED', '#1A73E8', '#0E9CA8'];
        $diseaseTrend = ['labels' => hc_last_n_days([], $diseaseTrendWindowDays)['labels'], 'datasets' => []];
        $colorIdx = 0;
        foreach (array_slice($categoryTotalsTrend, 0, 5, true) as $category => $total) {
            $dates = array_column(array_values(array_filter($notifiableCases, fn ($c) => $c['category'] === $category)), 'date');
            $diseaseTrend['datasets'][] = ['label' => $category, 'data' => hc_last_n_days($dates, $diseaseTrendWindowDays)['values'], 'color' => $colors[$colorIdx % count($colors)]];
            $colorIdx++;
        }

        $predictionCards = [
            ['label' => 'Consultations (Next 7 Days)', 'icon' => 'bi-graph-up-arrow', 'iconBg' => '#E8F0FE', 'iconColor' => '#1A73E8',
                'value' => $consultFit['hasSignal'] ? number_format($consultForecastTotal) : '—',
                'sub' => $consultFit['hasSignal'] ? ('vs ' . number_format($consultPast7Total) . ' in the past 7 days') : 'Not enough history yet',
                'trendUp' => $consultForecastTotal >= $consultPast7Total],
            ['label' => 'Notifiable-Disease Cases (Last 14 Days)', 'icon' => 'bi-virus', 'iconBg' => '#FCE4E4', 'iconColor' => '#D93025',
                'value' => (string) count($casesLast14), 'sub' => 'Across ' . $barangaysUnderWatch . ' barangay(s) under watch', 'trendUp' => null],
            ['label' => 'Barangays Under Watch', 'icon' => 'bi-geo-alt-fill', 'iconBg' => '#FDF3DC', 'iconColor' => '#B7791F',
                'value' => (string) $barangaysUnderWatch, 'sub' => 'Of ' . count(HC_COVERED_BARANGAYS) . ' covered barangays', 'trendUp' => null],
        ];

        $recommendations = [];
        foreach (array_slice($riskAlerts, 0, 6) as $a) {
            $recommendations[] = [
                'type' => $a['severity'] === 'critical' ? 'critical' : ($a['severity'] === 'elevated' ? 'warning' : 'insight'),
                'icon' => 'bi-clipboard2-pulse-fill',
                'title' => 'Rising ' . $a['category'] . ' cases — Barangay ' . $a['barangay'],
                'body' => $a['count14'] . ' reported case(s) in the last 14 days. Coordinate with barangay health workers for proactive surveillance and health advisories.',
            ];
        }

        $riskRows = array_map(function ($a) {
            $meta = hc_predictive_severity_meta($a['severity']);
            return $meta + ['label' => 'Barangay ' . $a['barangay'], 'desc' => $a['category'], 'value' => $a['count14'], 'anomaly' => $a['anomaly'], 'causes' => $a['causes']];
        }, $riskAlerts);

        return compact('notifiableCases', 'casesLast14', 'riskAlerts', 'barangaysUnderWatch', 'consultFit', 'consultForecastTotal', 'consultPast7Total', 'forecastTrend', 'diseaseTrend', 'predictionCards', 'recommendations', 'riskRows', 'consultVolumeAnomaly');
    }
}

if (!function_exists('hc_gis_seasonal_overview')) {
    /**
     * True calendar-seasonality view — NOT another rolling window like
     * every other date-range control in this app (see
     * includes/public/gis_data_firestore.php's hc_gis_date_range_spec()
     * header comment). Buckets every real notifiable-disease case by its
     * CALENDAR month (Jan-Dec, every available year merged into the same
     * 12 buckets) and by Philippine rainy season (Jun-Nov) vs dry season
     * (Dec-May), so a facility can see e.g. "Dengue cases consistently
     * peak in Aug-Sep" instead of only ever seeing a trailing N-day
     * count.
     *
     * @param array $notifiableCases hc_disease_demand_forecast()'s own
     *              all-time 'notifiableCases' return value (every real
     *              matched case, every year on record) — re-used rather
     *              than re-matched here, so this always agrees exactly
     *              with every other real disease count this app shows.
     * @return array{hasData: bool, monthlyLabels: array, monthlyTotals: array, topCategory: ?string, topCategoryMonthly: array, categoryMonthly: array, categoryOptions: array, seasonCompare: array}
     */
    function hc_gis_seasonal_overview(array $notifiableCases): array
    {
        $rainyMonths = [6, 7, 8, 9, 10, 11]; // Jun-Nov, the standard Philippine rainy-season split

        $monthTotals = array_fill(1, 12, 0);
        $categoryMonthTotals = [];
        $categoryTotals = [];
        $rainyCounts = [];
        $dryCounts = [];

        foreach ($notifiableCases as $c) {
            if ($c['date'] === '') {
                continue;
            }
            $ts = strtotime($c['date']);
            if ($ts === false) {
                continue;
            }
            $month = (int) date('n', $ts);
            $category = $c['category'];

            $monthTotals[$month]++;
            $categoryMonthTotals[$category] ??= array_fill(1, 12, 0);
            $categoryMonthTotals[$category][$month]++;
            $categoryTotals[$category] = ($categoryTotals[$category] ?? 0) + 1;

            if (in_array($month, $rainyMonths, true)) {
                $rainyCounts[$category] = ($rainyCounts[$category] ?? 0) + 1;
            } else {
                $dryCounts[$category] = ($dryCounts[$category] ?? 0) + 1;
            }
        }

        arsort($categoryTotals);
        $topCategory = array_key_first($categoryTotals);

        $monthlyLabels = [];
        for ($m = 1; $m <= 12; $m++) {
            $monthlyLabels[] = date('M', mktime(0, 0, 0, $m, 1));
        }
        $topCategoryMonthly = $topCategory !== null ? array_values($categoryMonthTotals[$topCategory]) : array_fill(0, 12, 0);

        // Every real category's own 12-month breakdown (not just the top
        // one) — lets the chart's own "View" dropdown switch between
        // diseases client-side, with no extra request, instead of only
        // ever showing the single highest-volume category.
        $categoryMonthly = [];
        foreach ($categoryTotals as $category => $total) {
            $categoryMonthly[$category] = array_values($categoryMonthTotals[$category]);
        }
        $categoryOptions = array_keys($categoryTotals); // already arsort()-ed by real total case count, highest first

        // Rainy-vs-dry is a raw case-count comparison (not a per-day
        // rate) — both halves of the year are an equal 6 months long, so
        // a raw count is already an honest comparison.
        $seasonCompare = [];
        foreach ($categoryTotals as $category => $total) {
            $rainy = $rainyCounts[$category] ?? 0;
            $dry = $dryCounts[$category] ?? 0;
            $seasonCompare[] = [
                'category' => $category,
                'rainy' => $rainy,
                'dry' => $dry,
                'moreIn' => $rainy === $dry ? 'even' : ($rainy > $dry ? 'rainy' : 'dry'),
            ];
        }

        return [
            'hasData' => count($notifiableCases) > 0,
            'monthlyLabels' => $monthlyLabels,
            'monthlyTotals' => array_values($monthTotals),
            'topCategory' => $topCategory,
            'topCategoryMonthly' => $topCategoryMonthly,
            'categoryMonthly' => $categoryMonthly,
            'categoryOptions' => $categoryOptions,
            'seasonCompare' => array_slice($seasonCompare, 0, 5),
        ];
    }
}

// =======================================================================
// 6.3.2 — Medicine & Vaccine Shortage Prediction
// =======================================================================

if (!function_exists('hc_predictive_order_dates_for_item')) {
    /**
     * Real order/prescription-event dates for $itemName across BOTH
     * pharmacy_orders (orderedDate) and medications (startDate) —
     * fuzzy-matched by name (see hc_item_names_match()'s own comment
     * for why an exact match would miss almost everything).
     */
    function hc_predictive_order_dates_for_item(string $itemName, array $pharmacyOrderDocs, array $medicationDocs): array
    {
        $dates = [];
        foreach ($pharmacyOrderDocs as $doc) {
            $name = $doc['fields']['itemName'] ?? '';
            if ($name !== '' && hc_item_names_match($itemName, $name)) {
                $dates[] = $doc['fields']['orderedDate'] ?? '';
            }
        }
        foreach ($medicationDocs as $doc) {
            $name = $doc['fields']['name'] ?? '';
            if ($name !== '' && hc_item_names_match($itemName, $name)) {
                $dates[] = $doc['fields']['startDate'] ?? '';
            }
        }

        return array_values(array_filter($dates, fn ($d) => $d !== ''));
    }
}

if (!function_exists('hc_medicine_vaccine_shortage')) {
    /**
     * @param array $allInventoryItems hc_get_inventory_items() rows for BOTH types, each tagged with 'itemType' — always system-wide (see this file's header comment).
     * @param array $pharmacyOrderDocs Raw pharmacy_orders docs, already scoped by the caller (system-wide or one facility's own).
     * @param array $medicationDocs Raw medications docs, same scoping as $pharmacyOrderDocs.
     */
    function hc_medicine_vaccine_shortage(array $allInventoryItems, array $pharmacyOrderDocs, array $medicationDocs): array
    {
        $items = [];
        foreach ($allInventoryItems as $item) {
            $orderDates = hc_predictive_order_dates_for_item($item['name'], $pharmacyOrderDocs, $medicationDocs);
            $daily30 = hc_last_n_days($orderDates, 30);
            $ordersLast30 = array_sum($daily30['values']);
            $ratePerDay = $ordersLast30 / 30; // orders/prescriptions per day — a real frequency proxy, not true units consumed

            $daysUntilStockout = $ratePerDay > 0 ? (int) floor($item['currentStock'] / $ratePerDay) : null;
            $alreadyOut = $item['currentStock'] <= 0;

            $severity = null;
            if ($alreadyOut) {
                $severity = 'critical';
            } elseif ($daysUntilStockout !== null && $daysUntilStockout <= 7) {
                $severity = 'critical';
            } elseif ($daysUntilStockout !== null && $daysUntilStockout <= 30) {
                $severity = 'warning';
            }

            $items[] = $item + [
                'ordersLast30' => $ordersLast30,
                'ratePerDay' => $ratePerDay,
                'daysUntilStockout' => $daysUntilStockout,
                'alreadyOut' => $alreadyOut,
                'severity' => $severity,
                'suggestedReorderQty' => $ratePerDay > 0
                    ? max(0, (int) ceil(($ratePerDay * 30) - $item['currentStock']))
                    : ($alreadyOut ? max(1, $item['reorderLevel'] * 2) : 0),
            ];
        }
        usort($items, function ($a, $b) {
            $aUrgency = $a['alreadyOut'] ? -1 : ($a['daysUntilStockout'] ?? PHP_INT_MAX);
            $bUrgency = $b['alreadyOut'] ? -1 : ($b['daysUntilStockout'] ?? PHP_INT_MAX);

            return $aUrgency <=> $bUrgency;
        });

        $atRisk = array_values(array_filter($items, fn ($i) => $i['severity'] !== null));
        $medicinesAtRisk = count(array_filter($atRisk, fn ($i) => $i['itemType'] === 'medicine' && !$i['alreadyOut']));
        $vaccinesAtRisk = count(array_filter($atRisk, fn ($i) => $i['itemType'] === 'vaccine' && !$i['alreadyOut']));
        $alreadyOutCount = count(array_filter($items, fn ($i) => $i['alreadyOut']));

        // "Which item is this chart about" used to be hardcoded to
        // whichever one is most urgent — the user wanted to browse any
        // medicine/vaccine, not just that one. $stockProjections now
        // covers EVERY real item (one entry each, same fixed 30-day
        // horizon so the x-axis doesn't jump around as the dropdown
        // selection changes); $mostUrgent still picks which one is
        // selected by default when the page first loads.
        $stillStocked = array_values(array_filter($items, fn ($i) => !$i['alreadyOut'] && $i['ratePerDay'] > 0));
        usort($stillStocked, fn ($a, $b) => $a['daysUntilStockout'] <=> $b['daysUntilStockout']);
        $mostUrgent = $stillStocked[0] ?? ($atRisk[0] ?? null);

        $stockHorizon = 30;
        $stockProjections = [];
        foreach ($items as $item) {
            $labels = [];
            $forecast = [];
            for ($i = 0; $i <= $stockHorizon; $i++) {
                $labels[] = date('M j', strtotime("+$i days"));
                $forecast[] = max(0, round($item['currentStock'] - ($item['ratePerDay'] * $i), 1));
            }
            $stockProjections[] = [
                'name' => $item['name'],
                'itemType' => $item['itemType'],
                'labels' => $labels,
                'forecast' => $forecast,
                'reorderLevel' => $item['minLevel'],
                'ratePerDay' => round($item['ratePerDay'], 3),
                'currentStock' => $item['currentStock'],
            ];
        }
        usort($stockProjections, fn ($a, $b) => strcasecmp($a['name'], $b['name']));

        $stockProjection = ['labels' => [], 'forecast' => [], 'reorderLevel' => 0, 'itemName' => null];
        if ($mostUrgent !== null) {
            foreach ($stockProjections as $p) {
                if ($p['name'] === $mostUrgent['name']) {
                    $stockProjection = ['labels' => $p['labels'], 'forecast' => $p['forecast'], 'reorderLevel' => $p['reorderLevel'], 'itemName' => $p['name']];
                    break;
                }
            }
        }

        // Order/prescription frequency — EVERY real medicine/vaccine
        // currently in the system's own inventory (not just whichever
        // few happen to have the most order history), one real bar per
        // item. A multi-line daily-trend chart (the old shape) stops
        // being readable past a handful of lines — this facility's own
        // real inventory already has 20-30+ real items, which would be
        // an unreadable tangle of overlapping lines, so this is a real
        // bar chart instead: each item's own real ordersLast30 count,
        // sorted highest-first, zero-order items included (a real 0,
        // not hidden) so the chart honestly reflects the full real
        // inventory rather than a thin historical subset.
        $byVolume = $items;
        usort($byVolume, fn ($a, $b) => $b['ordersLast30'] <=> $a['ordersLast30']);
        $orderFrequency = [
            'labels' => array_column($byVolume, 'name'),
            'values' => array_column($byVolume, 'ordersLast30'),
            'types' => array_column($byVolume, 'itemType'),
        ];

        // Shortage trend — projected stock decline for every at-risk item
        // at once (up to 5, same urgency ordering $stockProjection above
        // already sorted $stillStocked by), not just the single most
        // urgent one — same reasoning as 6.3.1's own multi-category trend
        // chart: a side-by-side comparison across items is a different,
        // complementary view from drilling into one item's own decline +
        // reorder-level line above. A fixed 30-day horizon (vs. that
        // single-item chart's own item-specific horizon) so every line
        // shares one consistent, comparable x-axis. Already-out items are
        // excluded here for the same reason $mostUrgent skips them above
        // — a flat zero line adds no trend information (see
        // "Already Out of Stock" tile/Risk & Alert list for those).
        $shortageTrendLabels = [];
        for ($i = 0; $i <= 30; $i++) {
            $shortageTrendLabels[] = date('M j', strtotime("+$i days"));
        }
        $shortageTrend = ['labels' => $shortageTrendLabels, 'datasets' => []];
        $colorIdx = 0;
        $stillStockedAtRisk = array_values(array_filter($stillStocked, fn ($i) => $i['severity'] !== null));
        foreach (array_slice($stillStockedAtRisk, 0, 5) as $item) {
            $data = [];
            for ($i = 0; $i <= 30; $i++) {
                $data[] = max(0, round($item['currentStock'] - ($item['ratePerDay'] * $i), 1));
            }
            $shortageTrend['datasets'][] = ['label' => $item['name'], 'data' => $data, 'color' => $colors[$colorIdx % count($colors)]];
            $colorIdx++;
        }

        $predictionCards = [
            ['label' => 'Medicines Projected to Stock Out (≤30 Days)', 'icon' => 'bi-capsule', 'iconBg' => '#FDECDD', 'iconColor' => '#E8710A',
                'value' => (string) $medicinesAtRisk, 'sub' => 'Based on recent order pace', 'trendUp' => null],
            ['label' => 'Vaccines Projected to Stock Out (≤30 Days)', 'icon' => 'bi-droplet-half', 'iconBg' => '#E3F6FB', 'iconColor' => '#12A6C6',
                'value' => (string) $vaccinesAtRisk, 'sub' => 'Based on recent order pace', 'trendUp' => null],
            ['label' => 'Already Out of Stock', 'icon' => 'bi-exclamation-octagon-fill', 'iconBg' => '#FCE4E4', 'iconColor' => '#D93025',
                'value' => (string) $alreadyOutCount, 'sub' => 'Needs immediate reorder', 'trendUp' => null],
        ];

        $recommendations = [];
        foreach (array_slice($atRisk, 0, 6) as $item) {
            $when = $item['alreadyOut'] ? 'is already out of stock' : ('is projected to run out around ' . date('M j, Y', strtotime("+{$item['daysUntilStockout']} days")));
            $recommendations[] = [
                'type' => $item['severity'],
                'icon' => $item['itemType'] === 'vaccine' ? 'bi-droplet-half' : 'bi-capsule',
                'title' => 'Reorder ' . $item['name'],
                'body' => ucfirst($item['name']) . ' ' . $when . ' at the current order pace (~' . round($item['ratePerDay'] * 7, 1) . '/week). Current stock: ' . number_format($item['currentStock']) . '. Suggested reorder: ~' . number_format($item['suggestedReorderQty']) . ' ' . $item['unit'] . '.',
            ];
        }

        $riskRows = array_map(function ($i) {
            $meta = hc_predictive_severity_meta($i['severity']);
            return $meta + ['label' => $i['name'], 'desc' => $i['alreadyOut'] ? 'Out of stock now' : ($i['daysUntilStockout'] . ' day(s) to projected stockout'), 'value' => $i['alreadyOut'] ? 0 : $i['daysUntilStockout']];
        }, $atRisk);

        return compact('items', 'atRisk', 'medicinesAtRisk', 'vaccinesAtRisk', 'alreadyOutCount', 'stockProjection', 'stockProjections', 'orderFrequency', 'shortageTrend', 'predictionCards', 'recommendations', 'riskRows');
    }
}

// =======================================================================
// 6.3.3 — Inventory Replenishment Schedule
// =======================================================================

if (!function_exists('hc_predictive_has_pending_request')) {
    /** Does any pending/approved stock_requests doc already cover $itemName? */
    function hc_predictive_has_pending_request(string $itemName, array $stockRequests): bool
    {
        foreach ($stockRequests as $req) {
            if (!in_array($req['status'], ['pending', 'approved'], true)) {
                continue;
            }
            foreach ($req['items'] as $line) {
                if (hc_item_names_match($itemName, (string) ($line['name'] ?? ''))) {
                    return true;
                }
            }
        }

        return false;
    }
}

if (!function_exists('hc_inventory_replenishment')) {
    /**
     * @param array $shortageItems The 'items' array from hc_medicine_vaccine_shortage() — always the same system-wide inventory regardless of caller.
     * @param array $stockRequests hc_get_all_stock_requests()-shaped rows — the ONE genuinely facility-scopeable input here (real 'facility' field), already filtered by the caller.
     */
    function hc_inventory_replenishment(array $shortageItems, array $stockRequests): array
    {
        $needingReorder = array_values(array_filter($shortageItems, fn ($i) => $i['alreadyOut'] || ($i['daysUntilStockout'] !== null && $i['daysUntilStockout'] <= 30)));
        $earliestStockout = null;
        foreach ($needingReorder as $i) {
            if ($i['alreadyOut']) {
                continue;
            }
            $date = date('Y-m-d', strtotime("+{$i['daysUntilStockout']} days"));
            if ($earliestStockout === null || $date < $earliestStockout) {
                $earliestStockout = $date;
            }
        }
        $pendingRequestsCount = count(array_filter($stockRequests, fn ($r) => $r['status'] === 'pending'));

        $rankedItems = array_slice($needingReorder, 0, 8);
        $rankedMax = $rankedItems === [] ? 1 : (max(array_column($rankedItems, 'currentStock')) ?: 1);

        $gapAlerts = [];
        foreach ($needingReorder as $i) {
            $daysLeft = $i['alreadyOut'] ? 0 : $i['daysUntilStockout'];
            if ($daysLeft > 14) {
                continue;
            }
            if (!hc_predictive_has_pending_request($i['name'], $stockRequests)) {
                $gapAlerts[] = $i;
            }
        }

        // Real weekly stock_request volume by status, last 8 real weeks
        // present in the data (not a fixed calendar window).
        $weekBuckets = [];
        foreach ($stockRequests as $r) {
            if ($r['requestedAtRaw'] === '') {
                continue;
            }
            $weekLabel = date('M j', strtotime('monday this week', strtotime($r['requestedAtRaw'])));
            $weekBuckets[$weekLabel] ??= ['pending' => 0, 'approved' => 0, 'released' => 0, 'rejected' => 0, 'sortKey' => date('Y-m-d', strtotime('monday this week', strtotime($r['requestedAtRaw'])))];
            $weekBuckets[$weekLabel][$r['status']] = ($weekBuckets[$weekLabel][$r['status']] ?? 0) + 1;
        }
        uasort($weekBuckets, fn ($a, $b) => strcmp($a['sortKey'], $b['sortKey']));
        $weekBuckets = array_slice($weekBuckets, -8, null, true);
        $requestVolume = [
            'labels' => array_keys($weekBuckets),
            'datasets' => [
                ['label' => 'Pending', 'data' => array_column($weekBuckets, 'pending'), 'color' => '#E8710A'],
                ['label' => 'Approved', 'data' => array_column($weekBuckets, 'approved'), 'color' => '#1E8E3E'],
                ['label' => 'Released', 'data' => array_column($weekBuckets, 'released'), 'color' => '#8B5CF6'],
                ['label' => 'Rejected', 'data' => array_column($weekBuckets, 'rejected'), 'color' => '#D93025'],
            ],
        ];

        $predictionCards = [
            ['label' => 'Items Needing Reorder This Month', 'icon' => 'bi-box-seam-fill', 'iconBg' => '#FDECDD', 'iconColor' => '#E8710A',
                'value' => (string) count($needingReorder), 'sub' => 'Medicines + vaccines combined', 'trendUp' => null],
            ['label' => 'Next Projected Stockout Date', 'icon' => 'bi-calendar-event-fill', 'iconBg' => '#FCE4E4', 'iconColor' => '#D93025',
                'value' => $earliestStockout !== null ? date('M j, Y', strtotime($earliestStockout)) : '—',
                'sub' => $earliestStockout !== null ? 'Earliest across all items' : 'Nothing imminent', 'trendUp' => null],
            ['label' => 'Pending Requests Awaiting Approval', 'icon' => 'bi-hourglass-split', 'iconBg' => '#F1E8FD', 'iconColor' => '#8B5CF6',
                'value' => (string) $pendingRequestsCount, 'sub' => 'Real stock requests', 'trendUp' => null],
        ];

        $recommendations = [];
        foreach (array_slice($gapAlerts, 0, 6) as $item) {
            $target = $item['alreadyOut'] ? 'as soon as possible' : ('by ' . date('M j, Y', strtotime("+{$item['daysUntilStockout']} days")));
            $recommendations[] = [
                'type' => 'critical',
                'icon' => 'bi-clipboard2-x-fill',
                'title' => 'No replenishment in the pipeline — ' . $item['name'],
                'body' => 'Projected to run low ' . $target . ' with no pending or approved stock request currently covering it. Submit a request for ~' . number_format($item['suggestedReorderQty']) . ' ' . $item['unit'] . '.',
            ];
        }

        $riskRows = array_map(function ($i) {
            $meta = hc_predictive_severity_meta('critical');
            return $meta + ['label' => $i['name'], 'desc' => 'No pending/approved reorder on file', 'value' => $i['alreadyOut'] ? 0 : $i['daysUntilStockout']];
        }, $gapAlerts);

        return compact('needingReorder', 'earliestStockout', 'pendingRequestsCount', 'rankedItems', 'rankedMax', 'gapAlerts', 'requestVolume', 'predictionCards', 'recommendations', 'riskRows');
    }
}

// =======================================================================
// 6.3.4 — Patient Consultation Trends by Barangay
// =======================================================================

if (!function_exists('hc_barangay_consultation_trends')) {
    /**
     * @param array $consultations hc_get_all_consultations()-shaped rows, already scoped by the caller.
     * @param array $patientBarangayById patientId => real barangay string.
     */
    function hc_barangay_consultation_trends(array $consultations, array $patientBarangayById): array
    {
        $barangayDates = array_fill_keys(HC_COVERED_BARANGAYS, []);
        foreach ($consultations as $c) {
            $bgy = $patientBarangayById[$c['patientId']] ?? '';
            if (isset($barangayDates[$bgy])) {
                $barangayDates[$bgy][] = $c['dateTime'];
            }
        }

        $barangays = [];
        foreach ($barangayDates as $barangay => $dates) {
            $daily30 = hc_last_n_days($dates, 30);
            $total30 = array_sum($daily30['values']);
            $firstHalf = array_sum(array_slice($daily30['values'], 0, 15));
            $secondHalf = array_sum(array_slice($daily30['values'], 15));
            $arrow = $secondHalf > $firstHalf ? 'up' : ($secondHalf < $firstHalf ? 'down' : 'flat');

            $fit = hc_forecast_linear_fit($daily30['values']);
            $forecast7 = hc_forecast_project($fit, 30, 7);
            $forecastTotal = array_sum($forecast7);
            $past7Total = array_sum(array_slice($daily30['values'], -7));
            $growthPct = ($fit['hasSignal'] && $past7Total > 0) ? round((($forecastTotal - $past7Total) / $past7Total) * 100) : null;

            $barangays[] = ['barangay' => $barangay, 'total30' => $total30, 'arrow' => $arrow, 'hasSignal' => $fit['hasSignal'], 'forecastTotal' => $forecastTotal, 'past7Total' => $past7Total, 'growthPct' => $growthPct];
        }
        usort($barangays, fn ($a, $b) => $b['total30'] <=> $a['total30']);
        $max = $barangays === [] ? 1 : (max(array_column($barangays, 'total30')) ?: 1);

        $rising = array_values(array_filter($barangays, fn ($b) => $b['growthPct'] !== null && $b['growthPct'] >= 20));
        usort($rising, fn ($a, $b) => $b['growthPct'] <=> $a['growthPct']);
        $fastestGrowing = $rising[0] ?? null;

        // Combined actual-vs-forecast across all 11 covered barangays.
        $allDates = array_merge(...array_values($barangayDates));
        $daily30 = hc_last_n_days($allDates, 30);
        $fit = hc_forecast_linear_fit($daily30['values']);
        $forecast7 = hc_forecast_project($fit, 30, 7);
        $forecastTotal = array_sum($forecast7);
        $daily14 = hc_last_n_days($allDates, 14);
        $forecastTrend = [
            'labels' => array_merge($daily14['labels'], array_map(fn ($i) => date('M j', strtotime('+' . ($i + 1) . ' days')), range(0, 6))),
            'actual' => array_merge($daily14['values'], array_fill(0, 7, null)),
            'forecast' => array_merge(array_fill(0, 13, null), [end($daily14['values'])], $forecast7),
        ];

        $predictionCards = [
            ['label' => 'Fastest-Growing Barangay', 'icon' => 'bi-graph-up-arrow', 'iconBg' => '#E4F7EA', 'iconColor' => '#1E8E3E',
                'value' => $fastestGrowing !== null ? $fastestGrowing['barangay'] : '—',
                'sub' => $fastestGrowing !== null ? ('+' . $fastestGrowing['growthPct'] . '% projected') : 'Not enough history yet', 'trendUp' => true],
            ['label' => 'Total Consultations (Next 7 Days)', 'icon' => 'bi-people-fill', 'iconBg' => '#E8F0FE', 'iconColor' => '#1A73E8',
                'value' => $fit['hasSignal'] ? number_format($forecastTotal) : '—', 'sub' => 'All 11 covered barangays combined', 'trendUp' => null],
        ];

        $recommendations = [];
        foreach (array_slice($rising, 0, 6) as $b) {
            $recommendations[] = [
                'type' => 'insight',
                'icon' => 'bi-graph-up-arrow',
                'title' => 'Rising consultation demand — Barangay ' . $b['barangay'],
                'body' => 'Consultations are trending up ~' . $b['growthPct'] . '% (from ' . $b['past7Total'] . ' in the past week to a projected ' . $b['forecastTotal'] . ' next week). Consider additional outreach or scheduling capacity for this barangay.',
            ];
        }

        $riskRows = array_map(function ($b) {
            $meta = hc_predictive_severity_meta('watch');
            return $meta + ['label' => 'Barangay ' . $b['barangay'], 'desc' => 'Rising consultation demand', 'value' => $b['growthPct'] . '%'];
        }, $rising);

        return compact('barangays', 'max', 'rising', 'fastestGrowing', 'forecastTrend', 'predictionCards', 'recommendations', 'riskRows');
    }
}
