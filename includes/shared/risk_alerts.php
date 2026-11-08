<?php
/**
 * =====================================================================
 * Automatic Risk & Alert Monitoring (includes/shared/risk_alerts.php)
 * ---------------------------------------------------------------------
 * Drives every "Risk & Alert Indicators" list (and its Recommendations
 * partner) on the Predictive & Prescriptive tabs of
 * admin/facility_analytics.php and subadmin/predictive_analytics.php.
 * Nothing is dismissed by hand — indicators are created, updated and
 * resolved purely from real Firestore data:
 *
 *   Risk detected -> ACTIVE (WATCH/ALERT/ELEVATED/CRITICAL)
 *     -> re-evaluated (severity, value and recommendation refreshed)
 *     -> condition back to normal -> N consecutive normal DAILY
 *        evaluations (counter resets if the risk returns)
 *     -> RESOLVED (resolutionMethod AUTOMATIC) -> kept in history.
 *
 * Firestore collections (new, nothing existing is modified):
 *   risk_alerts/{alertId}    one doc per alert EPISODE — never deleted;
 *                            resolved ones only leave the active lists.
 *   risk_alert_runs/{runId}  one marker per scope per evaluation date,
 *                            so the daily run is tracked and safe to rerun.
 *
 * Scopes: a facility's own name for facility-scoped data (consultations,
 * lab/X-ray requests, disease cases, stock requests), or
 * HC_RISK_SYSTEM_SCOPE for medicine/vaccine inventory, which has no
 * per-facility field anywhere in this app's schema.
 *
 * Detection reuses the existing analytics in predictive_analytics_core.php
 * (hc_disease_demand_forecast(), hc_medicine_vaccine_shortage(),
 * hc_inventory_replenishment(), hc_barangay_consultation_trends()) so the
 * charts, indicators and recommendations always agree. Thresholds live
 * in config/risk_alerts.php.
 *
 * Schedules:
 *   - Daily at 12:00 AM Asia/Manila: scripts/evaluate-risk-alerts.php
 *     (Windows Task Scheduler / cron — see that file). If the server was
 *     off at midnight, the first dashboard load of the day runs the same
 *     evaluation for that facility (hc_risk_ensure_daily_run()) — same
 *     evaluation date, same result, idempotent.
 *   - Inventory/replenishment alerts are also re-evaluated on every
 *     dashboard load, so a corrected stock level resolves immediately.
 *
 * No patient-identifying data is ever written to these collections —
 * only barangay names, disease categories, item names and aggregate counts.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore
require_once __DIR__ . '/predictive_analytics_core.php'; // hc_predictive_severity_meta(), HC_COVERED_BARANGAYS, the 4 predictive categories

const HC_RISK_ALERTS_COLLECTION = 'risk_alerts';
const HC_RISK_RUNS_COLLECTION = 'risk_alert_runs';
const HC_RISK_SYSTEM_SCOPE = '__system__';

// ---------------------------------------------------------------------
// Config, time, registry
// ---------------------------------------------------------------------

if (!function_exists('hc_risk_config')) {
    function hc_risk_config(): array
    {
        static $cfg = null;

        return $cfg ??= require __DIR__ . '/../../config/risk_alerts.php';
    }
}

if (!function_exists('hc_risk_tz')) {
    function hc_risk_tz(): DateTimeZone
    {
        return new DateTimeZone(hc_risk_config()['timezone'] ?? 'Asia/Manila');
    }
}

if (!function_exists('hc_risk_today')) {
    /** Today's evaluation date (Y-m-d) in Philippine Standard Time. */
    function hc_risk_today(): string
    {
        return (new DateTime('now', hc_risk_tz()))->format('Y-m-d');
    }
}

if (!function_exists('hc_risk_now')) {
    /** Current timestamp, ISO 8601 with the +08:00 offset. */
    function hc_risk_now(): string
    {
        return (new DateTime('now', hc_risk_tz()))->format('c');
    }
}

if (!function_exists('hc_risk_shift_date')) {
    function hc_risk_shift_date(string $ymd, int $days): string
    {
        return (new DateTime($ymd, hc_risk_tz()))->modify(($days >= 0 ? '+' : '') . $days . ' days')->format('Y-m-d');
    }
}

if (!function_exists('hc_risk_format_ts')) {
    /** "Nov 7, 2026 12:00 AM" in Asia/Manila, or '—'. */
    function hc_risk_format_ts(?string $iso): string
    {
        if ($iso === null || $iso === '') {
            return '—';
        }
        try {
            return (new DateTime($iso))->setTimezone(hc_risk_tz())->format('M j, Y g:i A');
        } catch (\Exception $e) {
            return '—';
        }
    }
}

if (!function_exists('hc_risk_barangay_id')) {
    /** Stable barangay id slug, e.g. "San Lucas" -> "san-lucas". */
    function hc_risk_barangay_id(string $name): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', '-', strtolower(trim($name))), '-');
    }
}

if (!function_exists('hc_risk_types')) {
    /**
     * riskType => label / dashboard group (which tab's list it belongs
     * to) / config section holding its resolution rule.
     */
    function hc_risk_types(): array
    {
        return [
            'disease_cluster'       => ['label' => 'Notifiable disease cluster',     'group' => 'disease',       'cfg' => 'disease'],
            'stock_shortage'        => ['label' => 'Medicine/vaccine shortage',      'group' => 'shortage',      'cfg' => 'inventory'],
            'replenishment_gap'     => ['label' => 'No replenishment in pipeline',   'group' => 'replenishment', 'cfg' => 'inventory'],
            'consultation_demand'   => ['label' => 'Consultation demand surge',      'group' => 'barangay',      'cfg' => 'demand'],
            'lab_request_demand'    => ['label' => 'Laboratory request surge',       'group' => 'barangay',      'cfg' => 'demand'],
            'xray_request_demand'   => ['label' => 'X-ray request surge',            'group' => 'barangay',      'cfg' => 'demand'],
            'consultation_forecast' => ['label' => 'Projected consultation demand',  'group' => 'barangay',      'cfg' => 'forecast'],
        ];
    }
}

if (!function_exists('hc_risk_daily_types')) {
    /** Types evaluated once per day (12:00 AM run / first load of the day). */
    function hc_risk_daily_types(): array
    {
        return ['disease_cluster', 'consultation_demand', 'lab_request_demand', 'xray_request_demand', 'consultation_forecast'];
    }
}

if (!function_exists('hc_risk_severity_rank')) {
    function hc_risk_severity_rank(?string $severity): int
    {
        return match ($severity) {
            'CRITICAL', 'ALERT' => 3,
            'ELEVATED' => 2,
            'WATCH' => 1,
            default => 0,
        };
    }
}

if (!function_exists('hc_risk_required_normals')) {
    /** Consecutive normal evaluations needed before $f auto-resolves. */
    function hc_risk_required_normals(string $riskType, ?string $peakSeverity): int
    {
        $cfg = hc_risk_config();
        $section = hc_risk_types()[$riskType]['cfg'] ?? 'demand';
        if ($section === 'disease' && $peakSeverity === 'CRITICAL') {
            return max(1, (int) $cfg['disease']['resolve_after_normal_evaluations_critical']);
        }

        return max(1, (int) ($cfg[$section]['resolve_after_normal_evaluations'] ?? 2));
    }
}

// ---------------------------------------------------------------------
// Observations — one per monitored (risk type, barangay/item) pair.
// Shape: alertKey, riskType, barangayName, barangayId, subject,
// severity (null = within normal thresholds), hold (true = not a risk
// but not yet "normal" either — e.g. a disease pair still getting new
// cases), value, displayValue, label, desc, basis (actual|forecast|
// inventory), monitoringPeriod, details, recommendation, extra.
// ---------------------------------------------------------------------

if (!function_exists('hc_risk_classify_demand')) {
    /**
     * Rolling 7-day classification of one barangay's ACTUAL counts.
     *
     * @return array{severity: ?string, pct: ?float, increase: int, insufficient: bool}
     */
    function hc_risk_classify_demand(int $current, int $previous, bool $hasHistory): array
    {
        $c = hc_risk_config()['demand'];
        $increase = $current - $previous;

        if (!$hasHistory) {
            return ['severity' => null, 'pct' => null, 'increase' => $increase, 'insufficient' => true];
        }

        if ($previous < (int) $c['min_baseline']) {
            // No real baseline to compute a percentage from — only a
            // genuine surge on its own counts.
            $severity = $current >= (int) $c['alert_min_current'] ? 'ALERT' : null;

            return ['severity' => $severity, 'pct' => null, 'increase' => $increase, 'insufficient' => $severity === null];
        }

        $pct = ($increase / $previous) * 100;
        $severity = null;
        if ($pct >= (float) $c['alert_min_pct'] && $current >= (int) $c['alert_min_current']) {
            $severity = 'ALERT';
        } elseif ($pct >= (float) $c['watch_min_pct'] && $increase >= (int) $c['watch_min_increase']) {
            $severity = 'WATCH';
        }

        return ['severity' => $severity, 'pct' => round($pct, 1), 'increase' => $increase, 'insufficient' => false];
    }
}

if (!function_exists('hc_risk_observe_service_demand')) {
    /**
     * @param string $riskType consultation_demand | lab_request_demand | xray_request_demand
     * @param array $datesByBarangay barangay => list of real event dates ('Y-m-d…'), already facility-scoped
     * @param ?string $earliestDate the facility's earliest real event date for this source (history check)
     */
    function hc_risk_observe_service_demand(string $riskType, array $datesByBarangay, ?string $earliestDate, string $evalDate): array
    {
        $cfg = hc_risk_config()['demand'];
        $n = (int) $cfg['window_days'];
        $curStart = hc_risk_shift_date($evalDate, -$n);
        $curEnd = hc_risk_shift_date($evalDate, -1);
        $prevStart = hc_risk_shift_date($evalDate, -2 * $n);
        $prevEnd = hc_risk_shift_date($evalDate, -$n - 1);
        $hasHistory = !$cfg['require_full_history'] || ($earliestDate !== null && $earliestDate !== '' && substr($earliestDate, 0, 10) <= $prevStart);

        $noun = match ($riskType) {
            'lab_request_demand' => ['one' => 'laboratory request', 'many' => 'laboratory requests', 'title' => 'Laboratory request surge'],
            'xray_request_demand' => ['one' => 'X-ray request', 'many' => 'X-ray requests', 'title' => 'X-ray request surge'],
            default => ['one' => 'consultation', 'many' => 'consultations', 'title' => 'Consultation demand surge'],
        };
        $fmt = fn ($d) => date('M j', strtotime($d));
        $period = $fmt($curStart) . '–' . $fmt($curEnd) . ' vs ' . $fmt($prevStart) . '–' . $fmt($prevEnd);

        $obs = [];
        foreach (HC_COVERED_BARANGAYS as $barangay) {
            $current = 0;
            $previous = 0;
            foreach ($datesByBarangay[$barangay] ?? [] as $d) {
                $day = substr((string) $d, 0, 10);
                if ($day >= $curStart && $day <= $curEnd) {
                    $current++;
                } elseif ($day >= $prevStart && $day <= $prevEnd) {
                    $previous++;
                }
            }
            $k = hc_risk_classify_demand($current, $previous, $hasHistory);
            $pctLabel = $k['pct'] === null ? ($current > 0 && $previous === 0 ? 'New' : '0%') : (($k['pct'] >= 0 ? '+' : '') . round($k['pct']) . '%');

            $reco = null;
            if ($k['severity'] !== null) {
                $change = $k['pct'] === null
                    ? "{$current} {$noun['many']} with no {$noun['many']} in the 7 days before"
                    : "{$noun['many']} rose from {$previous} to {$current} ({$pctLabel})";
                $action = match ($riskType) {
                    'lab_request_demand' => $k['severity'] === 'ALERT'
                        ? 'Confirm laboratory staffing, reagent and specimen-supply levels for the coming week, and review whether the increase points to a cluster needing follow-up.'
                        : 'Monitor laboratory workload and supplies closely over the next few days.',
                    'xray_request_demand' => $k['severity'] === 'ALERT'
                        ? 'Confirm radiologic technologist coverage and film/equipment availability for the coming week, and review the referring cases for a common cause.'
                        : 'Monitor X-ray workload and equipment availability over the next few days.',
                    default => $k['severity'] === 'ALERT'
                        ? 'Add consultation slots or staff for this barangay, coordinate with barangay health workers, and review recent diagnoses for a common cause.'
                        : 'Monitor this barangay closely and prepare to add consultation capacity if the increase continues.',
                };
                $reco = [
                    'type' => $k['severity'] === 'ALERT' ? 'critical' : 'warning',
                    'icon' => match ($riskType) { 'lab_request_demand' => 'bi-eyedropper', 'xray_request_demand' => 'bi-radioactive', default => 'bi-people-fill' },
                    'title' => $noun['title'] . ' — Barangay ' . $barangay,
                    'body' => 'Actual recorded ' . $change . ' over the last 7 complete days (' . $period . '). ' . $action,
                ];
            }

            $obs[] = [
                'alertKey' => $riskType . '|' . hc_risk_barangay_id($barangay),
                'riskType' => $riskType,
                'barangayName' => $barangay,
                'barangayId' => hc_risk_barangay_id($barangay),
                'subject' => $noun['many'],
                'severity' => $k['severity'],
                'hold' => false,
                'value' => $k['pct'] ?? (float) $current,
                'displayValue' => $pctLabel,
                'label' => 'Barangay ' . $barangay,
                'desc' => 'Actual: ' . $current . ' ' . ($current === 1 ? $noun['one'] : $noun['many']) . ' vs ' . $previous . ' in the prior 7 days',
                'basis' => 'actual',
                'monitoringPeriod' => $period,
                'details' => ['currentCount' => $current, 'previousCount' => $previous, 'increase' => $k['increase'], 'pct' => $k['pct'], 'insufficientData' => $k['insufficient']],
                'recommendation' => $reco,
                'extra' => [],
            ];
        }

        return $obs;
    }
}

if (!function_exists('hc_risk_observe_consultation_forecast')) {
    /** Forecast-based WATCH from hc_barangay_consultation_trends()'s own projection. */
    function hc_risk_observe_consultation_forecast(array $pt4): array
    {
        $min = (float) hc_risk_config()['forecast']['watch_min_growth_pct'];
        $obs = [];
        foreach ($pt4['barangays'] ?? [] as $b) {
            $flag = $b['hasSignal'] && $b['growthPct'] !== null && $b['growthPct'] >= $min;
            $obs[] = [
                'alertKey' => 'consultation_forecast|' . hc_risk_barangay_id($b['barangay']),
                'riskType' => 'consultation_forecast',
                'barangayName' => $b['barangay'],
                'barangayId' => hc_risk_barangay_id($b['barangay']),
                'subject' => 'projected consultations',
                'severity' => $flag ? 'WATCH' : null,
                'hold' => false,
                'value' => (float) ($b['growthPct'] ?? 0),
                'displayValue' => ($b['growthPct'] ?? 0) . '%',
                'label' => 'Barangay ' . $b['barangay'],
                'desc' => 'Forecast: ~' . $b['forecastTotal'] . ' next 7 days vs ' . $b['past7Total'] . ' in the past 7 days',
                'basis' => 'forecast',
                'monitoringPeriod' => 'Next 7 days (projected)',
                'details' => ['forecastTotal' => $b['forecastTotal'], 'past7Total' => $b['past7Total'], 'growthPct' => $b['growthPct']],
                'recommendation' => $flag ? [
                    'type' => 'insight',
                    'icon' => 'bi-graph-up-arrow',
                    'title' => 'Projected rise in consultations — Barangay ' . $b['barangay'],
                    'body' => 'Forecast only (not yet confirmed by actual records): consultations are projected to rise ~' . $b['growthPct'] . '% (from ' . $b['past7Total'] . ' in the past week to ~' . $b['forecastTotal'] . ' next week). Consider additional outreach or scheduling capacity for this barangay.',
                ] : null,
                'extra' => [],
            ];
        }

        return $obs;
    }
}

if (!function_exists('hc_risk_observe_disease')) {
    /**
     * From hc_disease_demand_forecast()'s own riskAlerts (14-day tiers).
     * Non-flagged pairs that still had a case within quiet_days are
     * emitted as "hold" so an active alert can't count them as normal.
     */
    function hc_risk_observe_disease(array $pt1, string $evalDate): array
    {
        $quietDays = (int) hc_risk_config()['disease']['quiet_days'];
        $quietCutoff = hc_risk_shift_date($evalDate, -$quietDays);
        $obs = [];
        $flagged = [];

        foreach ($pt1['riskAlerts'] ?? [] as $a) {
            $severity = strtoupper($a['severity']); // WATCH | ELEVATED | CRITICAL
            $key = 'disease_cluster|' . hc_risk_barangay_id($a['barangay']) . '|' . strtolower(trim($a['category']));
            $flagged[$key] = true;
            $obs[] = [
                'alertKey' => $key,
                'riskType' => 'disease_cluster',
                'barangayName' => $a['barangay'],
                'barangayId' => hc_risk_barangay_id($a['barangay']),
                'subject' => $a['category'],
                'severity' => $severity,
                'hold' => false,
                'value' => (float) $a['count14'],
                'displayValue' => (string) $a['count14'],
                'label' => 'Barangay ' . $a['barangay'],
                'desc' => $a['category'] . ' — ' . $a['count14'] . ' case(s) in the last 14 days',
                'basis' => 'actual',
                'monitoringPeriod' => 'Rolling 14 days',
                'details' => ['count14' => $a['count14']],
                'recommendation' => [
                    'type' => $severity === 'CRITICAL' ? 'critical' : ($severity === 'ELEVATED' ? 'warning' : 'insight'),
                    'icon' => 'bi-clipboard2-pulse-fill',
                    'title' => 'Rising ' . $a['category'] . ' cases — Barangay ' . $a['barangay'],
                    'body' => $a['count14'] . ' reported case(s) in the last 14 days. Coordinate with barangay health workers for proactive surveillance and health advisories.',
                ],
                'extra' => ['anomaly' => !empty($a['anomaly']), 'causes' => (string) ($a['causes'] ?? '')],
            ];
        }

        // Still-recent cases for pairs below every tier -> hold.
        foreach ($pt1['notifiableCases'] ?? [] as $c) {
            if (($c['date'] ?? '') === '' || $c['date'] < $quietCutoff || ($c['barangay'] ?? '') === '') {
                continue;
            }
            $key = 'disease_cluster|' . hc_risk_barangay_id($c['barangay']) . '|' . strtolower(trim($c['category']));
            if (isset($flagged[$key])) {
                continue;
            }
            $flagged[$key] = true;
            $obs[] = ['alertKey' => $key, 'riskType' => 'disease_cluster', 'severity' => null, 'hold' => true, 'value' => null];
        }

        return $obs;
    }
}

if (!function_exists('hc_risk_observe_inventory')) {
    /** From hc_medicine_vaccine_shortage()'s own 'items' (system-wide). */
    function hc_risk_observe_inventory(array $pt2): array
    {
        $obs = [];
        foreach ($pt2['items'] ?? [] as $i) {
            if ($i['severity'] === null) {
                continue; // not emitted = normal for any active alert on it
            }
            $projectedOnly = !$i['alreadyOut'] && !$i['belowReorderLevel'];
            $severity = ($i['alreadyOut'] || ($i['daysUntilStockout'] !== null && $i['daysUntilStockout'] <= 7)) ? 'CRITICAL' : ($projectedOnly ? 'WATCH' : 'ELEVATED');

            if ($i['alreadyOut']) {
                $desc = 'Out of stock now';
                $when = 'is already out of stock';
                $value = 0;
            } elseif ($i['belowReorderLevel']) {
                $desc = 'Below reorder level (' . number_format($i['currentStock']) . ' / ' . number_format($i['minLevel']) . ' ' . $i['unit'] . ')';
                $when = 'is already below its reorder level (' . number_format($i['currentStock']) . ' / ' . number_format($i['minLevel']) . ' ' . $i['unit'] . ')';
                $value = max(0, $i['minLevel'] - $i['currentStock']);
            } else {
                $desc = $i['daysUntilStockout'] . ' day(s) to projected stockout';
                $when = 'is projected to run out around ' . date('M j, Y', strtotime("+{$i['daysUntilStockout']} days"));
                $value = $i['daysUntilStockout'];
            }

            $obs[] = [
                'alertKey' => 'stock_shortage|' . $i['id'],
                'riskType' => 'stock_shortage',
                'barangayName' => '',
                'barangayId' => '',
                'subject' => $i['name'],
                'severity' => $severity,
                'hold' => false,
                'value' => (float) $i['currentStock'],
                'displayValue' => (string) $value,
                'label' => $i['name'],
                'desc' => $desc,
                'basis' => $projectedOnly ? 'forecast' : 'inventory',
                'monitoringPeriod' => 'Live inventory level',
                'details' => ['currentStock' => $i['currentStock'], 'minLevel' => $i['minLevel'], 'unit' => $i['unit'], 'daysUntilStockout' => $i['daysUntilStockout']],
                'recommendation' => [
                    'type' => $severity === 'CRITICAL' ? 'critical' : 'warning',
                    'icon' => $i['itemType'] === 'vaccine' ? 'bi-droplet-half' : 'bi-capsule',
                    'title' => 'Reorder ' . $i['name'],
                    'body' => ucfirst($i['name']) . ' ' . $when . ' at the current order pace (~' . round($i['ratePerDay'] * 7, 1) . '/week). Current stock: ' . number_format($i['currentStock']) . '. Suggested reorder: ~' . hc_reorder_quantity_label($i['suggestedReorderQty'], $i['unit'], $i['piecesPerBox'] ?? 0) . '.',
                ],
                'extra' => [],
            ];
        }

        return $obs;
    }
}

if (!function_exists('hc_risk_observe_replenishment')) {
    /** From hc_inventory_replenishment()'s own gapAlerts (facility-scoped stock requests). */
    function hc_risk_observe_replenishment(array $pt3): array
    {
        $obs = [];
        foreach ($pt3['gapAlerts'] ?? [] as $i) {
            if ($i['alreadyOut']) {
                $target = 'as soon as possible';
            } elseif ($i['daysUntilStockout'] !== null) {
                $target = 'by ' . date('M j, Y', strtotime("+{$i['daysUntilStockout']} days"));
            } else {
                $target = 'soon — it is already below its reorder level';
            }
            $obs[] = [
                'alertKey' => 'replenishment_gap|' . $i['id'],
                'riskType' => 'replenishment_gap',
                'barangayName' => '',
                'barangayId' => '',
                'subject' => $i['name'],
                'severity' => 'CRITICAL',
                'hold' => false,
                'value' => (float) $i['currentStock'],
                'displayValue' => (string) ($i['alreadyOut'] ? 0 : ($i['daysUntilStockout'] ?? 0)),
                'label' => $i['name'],
                'desc' => 'No pending/approved reorder on file',
                'basis' => 'inventory',
                'monitoringPeriod' => 'Live inventory + stock requests',
                'details' => ['currentStock' => $i['currentStock'], 'daysUntilStockout' => $i['daysUntilStockout']],
                'recommendation' => [
                    'type' => 'critical',
                    'icon' => 'bi-clipboard2-x-fill',
                    'title' => 'No replenishment in the pipeline — ' . $i['name'],
                    'body' => 'Projected to run low ' . $target . ' with no pending or approved stock request currently covering it. Submit a request for ~' . hc_reorder_quantity_label($i['suggestedReorderQty'], $i['unit'], $i['piecesPerBox'] ?? 0) . '.',
                ],
                'extra' => [],
            ];
        }

        return $obs;
    }
}

// ---------------------------------------------------------------------
// Evaluator
// ---------------------------------------------------------------------

if (!function_exists('hc_risk_scope_docs')) {
    /** @return array<string, array> alertId => fields, for one scope */
    function hc_risk_scope_docs(string $scope): array
    {
        global $firestore;

        $out = [];
        foreach ($firestore->listDocumentsWhere(HC_RISK_ALERTS_COLLECTION, 'scope', $scope) as $doc) {
            $out[$doc['id']] = $doc['fields'];
        }

        return $out;
    }
}

if (!function_exists('hc_risk_push_event')) {
    /** One history event per evaluation date — a rerun replaces that date's event instead of duplicating it. */
    function hc_risk_push_event(array $events, array $event): array
    {
        $last = end($events);
        if ($last !== false && ($last['date'] ?? '') === $event['date']) {
            if (($last['kind'] ?? '') === 'detected' && ($event['kind'] ?? '') === 'evaluated') {
                // Re-evaluated on its detection day: keep the single
                // "Risk detected" record, refreshed with the latest reading.
                $events[count($events) - 1] = array_merge($last, ['severity' => $event['severity'], 'value' => $event['value'], 'at' => $event['at']]);

                return array_values($events);
            }
            if (($last['kind'] ?? '') !== 'detected') {
                array_pop($events);
            }
        }
        $events[] = $event;

        return array_values($events);
    }
}

if (!function_exists('hc_risk_display_fields')) {
    function hc_risk_display_fields(array $o): array
    {
        return [
            'barangayName' => $o['barangayName'],
            'barangayId' => $o['barangayId'],
            'subject' => $o['subject'],
            'label' => $o['label'],
            'desc' => $o['desc'],
            'displayValue' => $o['displayValue'],
            'basis' => $o['basis'],
            'monitoringPeriod' => $o['monitoringPeriod'],
            'details' => $o['details'],
            'recommendation' => $o['recommendation'],
            'anomaly' => $o['extra']['anomaly'] ?? false,
            'causes' => $o['extra']['causes'] ?? '',
        ];
    }
}

if (!function_exists('hc_risk_evaluate')) {
    /**
     * Applies one evaluation of $evaluatedTypes for $scope on $evalDate.
     * Idempotent: rerunning the same date recomputes from the state
     * BEFORE that date (consecutiveNormalBefore / previousValue are kept
     * per date), and alert ids are deterministic per scope+key+date, so
     * reruns never double-count a normal day or create duplicates.
     *
     * @return array{created: int, updated: int, resolved: int, failed: int}
     */
    function hc_risk_evaluate(string $scope, array $observations, array $evaluatedTypes, string $evalDate): array
    {
        global $firestore;

        $stats = ['created' => 0, 'updated' => 0, 'resolved' => 0, 'failed' => 0];
        $now = hc_risk_now();
        $types = hc_risk_types();
        $docs = hc_risk_scope_docs($scope);
        if ($firestore->lastRequestFailed) {
            $stats['failed']++;

            return $stats; // never evaluate against a partial read
        }

        // Ignoring timestamps, is $new any different from what's stored?
        // Live (inventory) re-checks run on every dashboard load — an
        // unchanged alert must not cost a Firestore write each time.
        $strip = function (array $f): string {
            unset($f['lastEvaluatedAt']);
            $f['events'] = array_map(function ($e) { unset($e['at']); return $e; }, $f['events'] ?? []);

            return json_encode($f);
        };

        $save = function (string $id, array $fields) use ($firestore, &$stats, &$docs, $strip): void {
            if (isset($docs[$id]) && $strip(json_decode(json_encode($fields), true)) === $strip($docs[$id])) {
                return; // nothing changed
            }
            if ($firestore->setDocument(HC_RISK_ALERTS_COLLECTION, $id, $fields)) {
                $docs[$id] = $fields;
            } else {
                $stats['failed']++;
            }
        };

        // One ACTIVE episode per key. A stray duplicate (e.g. two
        // overlapping first runs) is closed, never deleted.
        $active = [];
        uasort($docs, fn ($a, $b) => strcmp($a['detectedAt'] ?? '', $b['detectedAt'] ?? ''));
        foreach ($docs as $id => $f) {
            if (($f['status'] ?? '') !== 'ACTIVE' || !in_array($f['riskType'] ?? '', $evaluatedTypes, true)) {
                continue;
            }
            if (isset($active[$f['alertKey']])) {
                $save($id, array_merge($f, [
                    'status' => 'RESOLVED', 'resolvedAt' => $now, 'resolvedDate' => $evalDate,
                    'resolutionReason' => 'Duplicate of an already-active indicator for the same barangay, risk type and period.',
                    'resolutionMethod' => 'AUTOMATIC', 'lastEvaluatedAt' => $now, 'lastEvaluationDate' => $evalDate,
                ]));
                $stats['resolved']++;
                continue;
            }
            $active[$f['alertKey']] = $id;
        }

        $byKey = [];
        foreach ($observations as $o) {
            if (in_array($o['riskType'], $evaluatedTypes, true)) {
                $byKey[$o['alertKey']] = $o;
            }
        }

        // --- Risks present now: create or update ---
        foreach ($byKey as $key => $o) {
            if ($o['severity'] === null) {
                continue;
            }

            if (isset($active[$key])) {
                $id = $active[$key];
                $f = $docs[$id];
                if (($f['lastEvaluationDate'] ?? '') > $evalDate) {
                    continue; // never apply an older evaluation over a newer one
                }
                $rerun = ($f['lastEvaluationDate'] ?? '') === $evalDate;
                $base = $rerun ? (int) ($f['consecutiveNormalBefore'] ?? 0) : (int) ($f['consecutiveNormal'] ?? 0);
                $prevValue = $rerun ? ($f['previousValue'] ?? null) : ($f['currentValue'] ?? null);
                $oldSeverity = $rerun ? ($f['severityBefore'] ?? $f['severity']) : $f['severity'];

                $note = 'Risk persists';
                if ($base > 0) {
                    $note = 'Risk increased again — normal-evaluation counter reset';
                } elseif ($o['severity'] !== $oldSeverity) {
                    $note = 'Severity changed from ' . $oldSeverity . ' to ' . $o['severity'];
                }

                $peak = hc_risk_severity_rank($o['severity']) > hc_risk_severity_rank($f['peakSeverity'] ?? null) ? $o['severity'] : ($f['peakSeverity'] ?? $o['severity']);
                $save($id, array_merge($f, hc_risk_display_fields($o), [
                    'severity' => $o['severity'],
                    'severityBefore' => $oldSeverity,
                    'peakSeverity' => $peak,
                    'currentValue' => $o['value'],
                    'previousValue' => $prevValue,
                    'consecutiveNormal' => 0,
                    'consecutiveNormalBefore' => $base,
                    'lastEvaluatedAt' => $now,
                    'lastEvaluationDate' => $evalDate,
                    'events' => hc_risk_push_event($f['events'] ?? [], [
                        'date' => $evalDate, 'at' => $now, 'kind' => 'evaluated', 'status' => 'ACTIVE',
                        'severity' => $o['severity'], 'value' => $o['value'], 'note' => $note,
                    ]),
                ]));
                $stats['updated']++;
                continue;
            }

            // New episode — deterministic id per scope + key + detection date.
            $baseId = substr(sha1($scope . "\0" . $key), 0, 20) . '_' . str_replace('-', '', $evalDate);
            $id = $baseId;
            for ($n = 2; isset($docs[$id]); $n++) {
                $id = $baseId . '_' . $n; // same-day re-detection after a same-day resolution
            }
            $save($id, array_merge(hc_risk_display_fields($o), [
                'alertId' => $id,
                'scope' => $scope,
                'facility' => $scope === HC_RISK_SYSTEM_SCOPE ? '' : $scope,
                'alertKey' => $key,
                'riskType' => $o['riskType'],
                'riskTypeLabel' => $types[$o['riskType']]['label'],
                'group' => $types[$o['riskType']]['group'],
                'severity' => $o['severity'],
                'severityBefore' => $o['severity'],
                'peakSeverity' => $o['severity'],
                'detectedAt' => $now,
                'detectedDate' => $evalDate,
                'lastEvaluatedAt' => $now,
                'lastEvaluationDate' => $evalDate,
                'currentValue' => $o['value'],
                'previousValue' => null,
                'consecutiveNormal' => 0,
                'consecutiveNormalBefore' => 0,
                'requiredNormalEvaluations' => hc_risk_required_normals($o['riskType'], $o['severity']),
                'status' => 'ACTIVE',
                'resolvedAt' => null,
                'resolvedDate' => null,
                'resolutionReason' => null,
                'resolutionMethod' => null,
                'events' => [[
                    'date' => $evalDate, 'at' => $now, 'kind' => 'detected', 'status' => 'ACTIVE',
                    'severity' => $o['severity'], 'value' => $o['value'], 'note' => 'Risk detected',
                ]],
            ]));
            $stats['created']++;
        }

        // --- Active alerts no longer at risk: count toward resolution ---
        foreach ($active as $key => $id) {
            $o = $byKey[$key] ?? null;
            if ($o !== null && $o['severity'] !== null) {
                continue; // handled above
            }
            $f = $docs[$id];
            if (($f['lastEvaluationDate'] ?? '') > $evalDate) {
                continue;
            }
            $rerun = ($f['lastEvaluationDate'] ?? '') === $evalDate;
            if ($rerun && ($f['detectedDate'] ?? '') === $evalDate && (int) ($f['consecutiveNormalBefore'] ?? 0) === 0 && ($f['events'][count($f['events'] ?? []) - 1]['kind'] ?? '') === 'detected') {
                // Detected earlier TODAY: a same-day normal reading counts
                // only for live (inventory) types, never for daily ones.
                if (in_array($f['riskType'], hc_risk_daily_types(), true)) {
                    continue;
                }
            }
            $base = $rerun ? (int) ($f['consecutiveNormalBefore'] ?? 0) : (int) ($f['consecutiveNormal'] ?? 0);
            $prevValue = $rerun ? ($f['previousValue'] ?? null) : ($f['currentValue'] ?? null);
            $hold = $o !== null && !empty($o['hold']);
            $count = $hold ? 0 : $base + 1;
            $required = hc_risk_required_normals($f['riskType'], $f['peakSeverity'] ?? null);
            $value = ($o !== null && $o['value'] !== null) ? $o['value'] : ($f['currentValue'] ?? null);

            $update = [
                'currentValue' => $value,
                'previousValue' => $prevValue,
                'consecutiveNormal' => $count,
                'consecutiveNormalBefore' => $base,
                'requiredNormalEvaluations' => $required,
                'lastEvaluatedAt' => $now,
                'lastEvaluationDate' => $evalDate,
            ];
            if ($o !== null && !$hold && isset($o['desc'])) {
                $update += ['desc' => $o['desc'], 'displayValue' => $o['displayValue'], 'details' => $o['details'], 'monitoringPeriod' => $o['monitoringPeriod']];
            }

            if (!$hold && $count >= $required) {
                $reason = match ($types[$f['riskType']]['cfg'] ?? '') {
                    'inventory' => $f['riskType'] === 'replenishment_gap'
                        ? 'Replenishment covered — a stock request was filed or the stock level was corrected.'
                        : 'Inventory condition corrected — stock is back above its reorder level.',
                    'disease' => 'No longer meets any disease-cluster tier and no new case in the last ' . (int) hc_risk_config()['disease']['quiet_days'] . ' days, for ' . $count . ' consecutive daily evaluations.',
                    default => 'Within normal thresholds for ' . $count . ' consecutive daily evaluations.',
                };
                $save($id, array_merge($f, $update, [
                    'status' => 'RESOLVED',
                    'resolvedAt' => $now,
                    'resolvedDate' => $evalDate,
                    'resolutionReason' => $reason,
                    'resolutionMethod' => 'AUTOMATIC',
                    'events' => hc_risk_push_event($f['events'] ?? [], [
                        'date' => $evalDate, 'at' => $now, 'kind' => 'resolved', 'status' => 'RESOLVED',
                        'severity' => $f['severity'], 'value' => $value, 'note' => 'Automatically resolved',
                    ]),
                ]));
                $stats['resolved']++;
            } else {
                $save($id, array_merge($f, $update, [
                    'events' => hc_risk_push_event($f['events'] ?? [], [
                        'date' => $evalDate, 'at' => $now, 'kind' => 'evaluated', 'status' => 'ACTIVE',
                        'severity' => $f['severity'], 'value' => $value,
                        'note' => $hold ? 'Below alert tiers but new cases still recorded — resolution on hold' : ('Within normal thresholds (' . $count . ' of ' . $required . ' evaluations)'),
                    ]),
                ]));
                $stats['updated']++;
            }
        }

        return $stats;
    }
}

// ---------------------------------------------------------------------
// Daily + live runs
// ---------------------------------------------------------------------

if (!function_exists('hc_risk_run_id')) {
    function hc_risk_run_id(string $scope, string $evalDate): string
    {
        return substr(sha1($scope), 0, 20) . '_' . str_replace('-', '', $evalDate);
    }
}

if (!function_exists('hc_risk_load_facility_inputs')) {
    /**
     * Every real, facility-scoped input the daily evaluation needs —
     * the same filters includes/admin/facility_analytics_firestore.php
     * uses, so the indicators match the page's own charts.
     */
    function hc_risk_load_facility_inputs(string $facilityName): array
    {
        global $firestore;

        require_once __DIR__ . '/../firestore/patients.php';
        require_once __DIR__ . '/../firestore/consultations.php';
        require_once __DIR__ . '/../firestore/medical_records.php';
        require_once __DIR__ . '/../firestore/lab_results.php';
        require_once __DIR__ . '/../firestore/xray_results.php';
        require_once __DIR__ . '/forecast_helpers.php';

        $patientIds = array_column(array_filter(hc_get_all_patients_summary(), fn ($p) => $p['facility'] === $facilityName), 'id');
        $patientIdSet = array_flip($patientIds);

        $barangayById = [];
        $ageById = [];
        foreach ($firestore->listDocuments('patients') as $doc) {
            $barangayById[$doc['id']] = trim((string) ($doc['fields']['barangay'] ?? ''));
            $ageById[$doc['id']] = (int) ($doc['fields']['age'] ?? 0);
        }

        $consultations = array_values(array_filter(hc_get_all_consultations(), fn ($c) => isset($patientIdSet[$c['patientId']])));
        $recordDocs = array_values(array_filter(
            hc_get_all_medical_record_docs(),
            fn ($d) => ($d['fields']['facility'] ?? '') === $facilityName && ($d['fields']['type'] ?? '') === 'consultation'
        ));
        $labDocs = array_values(array_filter(hc_get_all_lab_result_docs(), fn ($d) => ($d['fields']['facility'] ?? '') === $facilityName));
        $xrayDocs = array_values(array_filter(hc_get_all_xray_result_docs(), fn ($d) => ($d['fields']['facility'] ?? '') === $facilityName));

        return compact('consultations', 'recordDocs', 'labDocs', 'xrayDocs', 'barangayById', 'ageById');
    }
}

if (!function_exists('hc_risk_dates_by_barangay')) {
    /** @return array{0: array<string, string[]>, 1: ?string} barangay => dates, earliest date */
    function hc_risk_dates_by_barangay(array $rows, callable $dateOf, callable $patientOf, array $barangayById): array
    {
        $by = [];
        $earliest = null;
        foreach ($rows as $r) {
            $date = substr((string) $dateOf($r), 0, 10);
            if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                continue; // missing/invalid date — never counted
            }
            if ($earliest === null || $date < $earliest) {
                $earliest = $date;
            }
            $bgy = $barangayById[$patientOf($r)] ?? '';
            if ($bgy !== '') {
                $by[$bgy][] = $date;
            }
        }

        return [$by, $earliest];
    }
}

if (!function_exists('hc_risk_run_facility_daily')) {
    /**
     * The daily evaluation for one facility: disease clusters,
     * consultation / laboratory / X-ray demand (actual) and projected
     * consultation demand (forecast). Writes the run marker last.
     */
    function hc_risk_run_facility_daily(string $facilityName, ?string $evalDate = null): array
    {
        global $firestore;

        $evalDate ??= hc_risk_today();
        $in = hc_risk_load_facility_inputs($facilityName);

        $pt1 = hc_disease_demand_forecast($in['recordDocs'], $in['consultations'], $in['barangayById'], $in['ageById']);
        $pt4 = hc_barangay_consultation_trends($in['consultations'], $in['barangayById']);

        [$consultByBgy, $consultEarliest] = hc_risk_dates_by_barangay($in['consultations'], fn ($c) => $c['dateTime'], fn ($c) => $c['patientId'], $in['barangayById']);
        [$labByBgy, $labEarliest] = hc_risk_dates_by_barangay($in['labDocs'], fn ($d) => $d['fields']['orderedDate'] ?? '', fn ($d) => $d['fields']['patientId'] ?? '', $in['barangayById']);
        [$xrayByBgy, $xrayEarliest] = hc_risk_dates_by_barangay($in['xrayDocs'], fn ($d) => $d['fields']['orderedDate'] ?? '', fn ($d) => $d['fields']['patientId'] ?? '', $in['barangayById']);

        $obs = array_merge(
            hc_risk_observe_disease($pt1, $evalDate),
            hc_risk_observe_service_demand('consultation_demand', $consultByBgy, $consultEarliest, $evalDate),
            hc_risk_observe_service_demand('lab_request_demand', $labByBgy, $labEarliest, $evalDate),
            hc_risk_observe_service_demand('xray_request_demand', $xrayByBgy, $xrayEarliest, $evalDate),
            hc_risk_observe_consultation_forecast($pt4)
        );

        $stats = hc_risk_evaluate($facilityName, $obs, hc_risk_daily_types(), $evalDate);
        if ($stats['failed'] === 0) {
            $firestore->setDocument(HC_RISK_RUNS_COLLECTION, hc_risk_run_id($facilityName, $evalDate), [
                'scope' => $facilityName, 'date' => $evalDate, 'kind' => 'daily', 'completedAt' => hc_risk_now(),
                'created' => $stats['created'], 'updated' => $stats['updated'], 'resolved' => $stats['resolved'],
            ]);
        }

        return $stats;
    }
}

if (!function_exists('hc_risk_ensure_daily_run')) {
    /** Catch-up: runs today's daily evaluation for $facilityName only if the 12:00 AM job hasn't. */
    function hc_risk_ensure_daily_run(string $facilityName): void
    {
        global $firestore;

        if ($facilityName === '') {
            return;
        }
        $today = hc_risk_today();
        if ($firestore->getDocument(HC_RISK_RUNS_COLLECTION, hc_risk_run_id($facilityName, $today)) !== null) {
            return;
        }
        if ($firestore->lastRequestFailed) {
            return; // read failed — don't guess, the scheduled job will cover it
        }
        hc_risk_run_facility_daily($facilityName, $today);
    }
}

if (!function_exists('hc_risk_run_live')) {
    /**
     * Inventory-driven indicators, re-evaluated whenever fresh numbers
     * are available (every dashboard load + the daily job) so a
     * corrected stock level resolves right away.
     *
     * @param ?array $pt2 hc_medicine_vaccine_shortage() result (system-wide), or null to skip
     * @param ?array $pt3 hc_inventory_replenishment() result for $facilityName, or null to skip
     */
    function hc_risk_run_live(?array $pt2, string $facilityName, ?array $pt3): void
    {
        $today = hc_risk_today();
        if ($pt2 !== null) {
            hc_risk_evaluate(HC_RISK_SYSTEM_SCOPE, hc_risk_observe_inventory($pt2), ['stock_shortage'], $today);
        }
        if ($pt3 !== null && $facilityName !== '') {
            hc_risk_evaluate($facilityName, hc_risk_observe_replenishment($pt3), ['replenishment_gap'], $today);
        }
    }
}

// ---------------------------------------------------------------------
// Readers — dashboard lists + history
// ---------------------------------------------------------------------

if (!function_exists('hc_risk_severity_display')) {
    function hc_risk_severity_display(string $severity): array
    {
        $meta = hc_predictive_severity_meta(match ($severity) {
            'CRITICAL', 'ALERT' => 'critical',
            'ELEVATED' => 'elevated',
            default => 'watch',
        });
        $meta['badge'] = $severity;

        return $meta;
    }
}

if (!function_exists('hc_risk_dashboard_lists')) {
    /**
     * Active indicators + their recommendations for every dashboard
     * group, read from risk_alerts (the single source of truth).
     *
     * @return array<string, array{rows: array, recommendations: array}> keyed by group
     */
    function hc_risk_dashboard_lists(string $facilityName): array
    {
        $alerts = [];
        foreach ([$facilityName, HC_RISK_SYSTEM_SCOPE] as $scope) {
            if ($scope === '') {
                continue;
            }
            foreach (hc_risk_scope_docs($scope) as $f) {
                if (($f['status'] ?? '') === 'ACTIVE' && isset(hc_risk_types()[$f['riskType'] ?? ''])) {
                    $alerts[] = $f;
                }
            }
        }
        usort($alerts, fn ($a, $b) => [hc_risk_severity_rank($b['severity']), (float) ($b['currentValue'] ?? 0)] <=> [hc_risk_severity_rank($a['severity']), (float) ($a['currentValue'] ?? 0)]);

        $lists = [];
        foreach (['disease', 'shortage', 'replenishment', 'barangay'] as $g) {
            $lists[$g] = ['rows' => [], 'recommendations' => []];
        }
        foreach ($alerts as $f) {
            $group = hc_risk_types()[$f['riskType']]['group'];
            $count = (int) ($f['consecutiveNormal'] ?? 0);
            $lists[$group]['rows'][] = hc_risk_severity_display($f['severity']) + [
                'label' => $f['label'] ?? '',
                'desc' => $f['desc'] ?? '',
                'value' => $f['displayValue'] ?? '',
                'anomaly' => !empty($f['anomaly']),
                'causes' => $f['causes'] ?? '',
                'isForecast' => ($f['basis'] ?? '') === 'forecast',
                // Only the Consultation Trends tab mixes several risk types in one list.
                'typeLabel' => $group === 'barangay' ? ($f['riskTypeLabel'] ?? '') : '',
                'resolvingNote' => $count > 0 ? ('Returning to normal — ' . $count . ' of ' . (int) ($f['requiredNormalEvaluations'] ?? 2) . ' normal evaluations') : '',
                'detected' => hc_risk_format_ts($f['detectedAt'] ?? null),
            ];
            if (!empty($f['recommendation'])) {
                $lists[$group]['recommendations'][] = $f['recommendation'];
            }
        }

        return $lists;
    }
}

if (!function_exists('hc_risk_history')) {
    /**
     * Paginated alert history for $facilityName (+ system-wide inventory).
     * Filters: barangay (id), riskType, severity, status, from/to (detected date, Y-m-d).
     * Returns only non-identifying fields.
     */
    function hc_risk_history(string $facilityName, array $filters, int $page, int $pageSize): array
    {
        $rows = [];
        foreach ([$facilityName, HC_RISK_SYSTEM_SCOPE] as $scope) {
            foreach ($scope === '' ? [] : hc_risk_scope_docs($scope) as $f) {
                if (!isset(hc_risk_types()[$f['riskType'] ?? ''])) {
                    continue;
                }
                $severity = $f['peakSeverity'] ?? $f['severity'] ?? '';
                $detected = $f['detectedDate'] ?? '';
                if (($filters['group'] ?? '') !== '' && hc_risk_types()[$f['riskType']]['group'] !== $filters['group']) continue;
                if (($filters['barangay'] ?? '') !== '' && ($f['barangayId'] ?? '') !== $filters['barangay']) continue;
                if (($filters['riskType'] ?? '') !== '' && $f['riskType'] !== $filters['riskType']) continue;
                if (($filters['severity'] ?? '') !== '' && $severity !== $filters['severity']) continue;
                if (($filters['status'] ?? '') !== '' && ($f['status'] ?? '') !== $filters['status']) continue;
                if (($filters['from'] ?? '') !== '' && $detected < $filters['from']) continue;
                if (($filters['to'] ?? '') !== '' && $detected > $filters['to']) continue;

                $rows[] = [
                    'alertId' => $f['alertId'] ?? '',
                    'barangay' => ($f['barangayName'] ?? '') !== '' ? $f['barangayName'] : 'All (inventory)',
                    'riskIndicator' => ($f['riskTypeLabel'] ?? '') . (($f['subject'] ?? '') !== '' && in_array($f['riskType'], ['disease_cluster', 'stock_shortage', 'replenishment_gap'], true) ? ' — ' . $f['subject'] : ''),
                    'basis' => $f['basis'] ?? '',
                    'severity' => $severity,
                    'currentSeverity' => $f['severity'] ?? '',
                    'detectedAt' => $f['detectedAt'] ?? '',
                    'detected' => hc_risk_format_ts($f['detectedAt'] ?? null),
                    'resolved' => hc_risk_format_ts($f['resolvedAt'] ?? null),
                    'status' => $f['status'] ?? '',
                    // Only what the table shows. The full per-evaluation
                    // trail (events, values, counters) stays in Firestore.
                    'resolutionReason' => $f['resolutionReason'] ?? '',
                    'resolutionMethod' => $f['resolutionMethod'] ?? '',
                ];
            }
        }
        usort($rows, fn ($a, $b) => strcmp($b['detectedAt'], $a['detectedAt']));

        $total = count($rows);
        $pageSize = max(1, min(50, $pageSize));
        $pages = max(1, (int) ceil($total / $pageSize));
        $page = max(1, min($page, $pages));

        return [
            'rows' => array_slice($rows, ($page - 1) * $pageSize, $pageSize),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'pageSize' => $pageSize,
        ];
    }
}
