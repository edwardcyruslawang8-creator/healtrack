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
const HC_COVERED_BARANGAYS = ['Balintawak', 'Bugtong', 'Buclanin', 'Dagatan', 'Inosluban', 'Lumbang', 'Marawoy', 'Plaridel', 'Pusil', 'San Lucas', 'Talisay'];

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

if (!function_exists('hc_disease_demand_forecast')) {
    /**
     * @param array $medicalRecordDocs Raw medical_records docs (type=consultation only), already scoped by the caller (system-wide or one facility's own).
     * @param array $consultations hc_get_all_consultations()-shaped rows, same scoping as $medicalRecordDocs.
     * @param array $patientBarangayById patientId => real barangay string.
     */
    function hc_disease_demand_forecast(array $medicalRecordDocs, array $consultations, array $patientBarangayById): array
    {
        // Notifiable/communicable-disease keyword categories — Philippine
        // primary-care-relevant, matched case-insensitively as a substring
        // against each doc's diagnosis + chiefComplaint text combined.
        // Chronic/non-communicable conditions (hypertension, diabetes, etc.)
        // are deliberately NOT here — this is specifically about
        // outbreak-relevant, community-spread conditions.
        $keywordCategories = [
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

        $notifiableCases = []; // [['date' => Y-m-d, 'category' => ..., 'barangay' => ...], ...]
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

        // Consultation-volume forecast — the real "consultations"
        // collection (actual visits), not the diagnosis-text mining above.
        $consultDaily30 = hc_last_n_days(array_column($consultations, 'dateTime'), 30);
        $consultFit = hc_forecast_linear_fit($consultDaily30['values']);
        $consultForecast7 = hc_forecast_project($consultFit, 30, 7);
        $consultForecastTotal = array_sum($consultForecast7);
        $consultPast7Total = array_sum(array_slice($consultDaily30['values'], -7));

        $consultDaily14 = hc_last_n_days(array_column($consultations, 'dateTime'), 14);
        $forecastTrend = [
            'labels' => array_merge($consultDaily14['labels'], array_map(fn ($i) => date('M j', strtotime('+' . ($i + 1) . ' days')), range(0, 6))),
            'actual' => array_merge($consultDaily14['values'], array_fill(0, 7, null)),
            'forecast' => array_merge(array_fill(0, 13, null), [end($consultDaily14['values'])], $consultForecast7),
        ];

        // Trend graph — daily counts for whichever notifiable categories
        // actually have at least one real case in the last 30 days.
        $cutoff30 = date('Y-m-d', strtotime('-30 days'));
        $categoryTotals30 = [];
        foreach ($notifiableCases as $c) {
            if ($c['date'] >= $cutoff30) {
                $categoryTotals30[$c['category']] = ($categoryTotals30[$c['category']] ?? 0) + 1;
            }
        }
        arsort($categoryTotals30);
        $colors = ['#D93025', '#E8710A', '#7C3AED', '#1A73E8', '#0E9CA8'];
        $diseaseTrend = ['labels' => hc_last_n_days([], 30)['labels'], 'datasets' => []];
        $colorIdx = 0;
        foreach (array_slice($categoryTotals30, 0, 5, true) as $category => $total) {
            $dates = array_column(array_values(array_filter($notifiableCases, fn ($c) => $c['category'] === $category)), 'date');
            $diseaseTrend['datasets'][] = ['label' => $category, 'data' => hc_last_n_days($dates, 30)['values'], 'color' => $colors[$colorIdx % count($colors)]];
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
            return $meta + ['label' => 'Barangay ' . $a['barangay'], 'desc' => $a['category'], 'value' => $a['count14']];
        }, $riskAlerts);

        return compact('notifiableCases', 'casesLast14', 'riskAlerts', 'barangaysUnderWatch', 'consultFit', 'consultForecastTotal', 'consultPast7Total', 'forecastTrend', 'diseaseTrend', 'predictionCards', 'recommendations', 'riskRows');
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

        // Chart needs an item that's actually still declining — an
        // already-out item would just draw a flat zero line.
        $stillStocked = array_values(array_filter($items, fn ($i) => !$i['alreadyOut'] && $i['ratePerDay'] > 0));
        usort($stillStocked, fn ($a, $b) => $a['daysUntilStockout'] <=> $b['daysUntilStockout']);
        $mostUrgent = $stillStocked[0] ?? ($atRisk[0] ?? null);
        $stockProjection = ['labels' => [], 'forecast' => [], 'reorderLevel' => 0, 'itemName' => null];
        if ($mostUrgent !== null && $mostUrgent['ratePerDay'] > 0) {
            $horizon = min(30, max(7, ($mostUrgent['daysUntilStockout'] ?? 14) + 3));
            $labels = [];
            $forecast = [];
            for ($i = 0; $i <= $horizon; $i++) {
                $labels[] = date('M j', strtotime("+$i days"));
                $forecast[] = max(0, round($mostUrgent['currentStock'] - ($mostUrgent['ratePerDay'] * $i), 1));
            }
            $stockProjection = ['labels' => $labels, 'forecast' => $forecast, 'reorderLevel' => $mostUrgent['minLevel'], 'itemName' => $mostUrgent['name']];
        }

        // Trend graph — real order/prescription frequency, top 5 items by
        // volume in the last 30 days.
        $byVolume = $items;
        usort($byVolume, fn ($a, $b) => $b['ordersLast30'] <=> $a['ordersLast30']);
        $colors = ['#1A73E8', '#1E8E3E', '#E8710A', '#7C3AED', '#0E9CA8'];
        $orderFrequency = ['labels' => hc_last_n_days([], 30)['labels'], 'datasets' => []];
        $colorIdx = 0;
        foreach (array_slice($byVolume, 0, 5) as $item) {
            if ($item['ordersLast30'] <= 0) {
                continue;
            }
            $orderDates = hc_predictive_order_dates_for_item($item['name'], $pharmacyOrderDocs, $medicationDocs);
            $orderFrequency['datasets'][] = ['label' => $item['name'], 'data' => hc_last_n_days($orderDates, 30)['values'], 'color' => $colors[$colorIdx % count($colors)]];
            $colorIdx++;
        }

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

        return compact('items', 'atRisk', 'medicinesAtRisk', 'vaccinesAtRisk', 'alreadyOutCount', 'stockProjection', 'orderFrequency', 'shortageTrend', 'predictionCards', 'recommendations', 'riskRows');
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
