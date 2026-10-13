<?php
/**
 * =====================================================================
 * Predictive & Prescriptive Analytics — Preview (Medical Technologist,
 * facility-wide)
 * ---------------------------------------------------------------------
 * A small preview card for medical_technician/dashboard.php and
 * medical_technician/analytics_reports.php, same shared render partial
 * (includes/admin/predictive_overview_card.php) every other role's card
 * uses. Facility-wide, not "tests I personally performed" — same reason
 * includes/medical_technician/analytics_reports_medtech_firestore.php's
 * own header comment already gives: neither lab_results nor
 * xray_results has ever recorded who completed a given test, so a
 * per-technician split would have to be fabricated.
 *
 * This role's job isn't disease/consultation forecasting (6.3.1) or
 * medicine/vaccine inventory (6.3.2/6.3.3) — those belong to doctor/
 * medical-staff and pharmacist respectively, not this role. What IS
 * genuinely this role's own predictive/prescriptive value, and
 * computable from real fields without inventing anything:
 *   - PREDICTIVE: forecasting incoming lab + x-ray test volume for the
 *     next 7 days, same real OLS method every other forecast in this
 *     app uses (hc_forecast_linear_fit()/hc_forecast_project() over
 *     real daily order counts), so this role can anticipate workload.
 *   - PRESCRIPTIVE: which already-pending tests need a result soonest —
 *     reuses hc_get_pending_lab_xray_notifications() as-is (the exact
 *     same real days-waiting/urgency computation already driving this
 *     role's notification bell), not a separately invented metric.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore
require_once __DIR__ . '/../firestore/lab_results.php'; // hc_get_all_lab_result_docs(), hc_get_pending_lab_xray_notifications()
require_once __DIR__ . '/../firestore/xray_results.php'; // hc_get_all_xray_result_docs()
require_once __DIR__ . '/../shared/forecast_helpers.php'; // hc_last_n_days(), hc_forecast_linear_fit(), hc_forecast_project()

// ---------------------------------------------------------------------
// Volume forecast — real "orderedDate" across both collections is the
// actual demand signal (a test being requested), same role
// hc_get_all_lab_result_docs()/hc_get_all_xray_result_docs() already
// play in analytics_reports_medtech_firestore.php.
// ---------------------------------------------------------------------
$poOrderedDates = array_values(array_filter(array_merge(
    array_column(array_column(hc_get_all_lab_result_docs(), 'fields'), 'orderedDate'),
    array_column(array_column(hc_get_all_xray_result_docs(), 'fields'), 'orderedDate')
), fn ($d) => $d !== ''));

$poDaily30 = hc_last_n_days($poOrderedDates, 30);
$poFit = hc_forecast_linear_fit($poDaily30['values']);
$poForecast7 = hc_forecast_project($poFit, 30, 7);
$poForecastTotal = array_sum($poForecast7);
$poPast7Total = array_sum(array_slice($poDaily30['values'], -7));

$poDaily14 = hc_last_n_days($poOrderedDates, 14);
$predictiveOverviewTrend = [
    'labels' => array_merge($poDaily14['labels'], array_map(fn ($i) => date('M j', strtotime('+' . ($i + 1) . ' days')), range(0, 6))),
    'actual' => array_merge($poDaily14['values'], array_fill(0, 7, null)),
    'forecast' => array_merge(array_fill(0, 13, null), [end($poDaily14['values'])], $poForecast7),
];

// ---------------------------------------------------------------------
// Backlog — real pending lab_results/xray_results docs, reusing the
// exact same computation the notification bell already runs.
// ---------------------------------------------------------------------
$poPending = hc_get_pending_lab_xray_notifications(); // oldest-ordered first, real daysWaiting-derived priority
$poPendingCount = count($poPending);
$poOverdueCount = count(array_filter($poPending, fn ($n) => $n['priority'] === 'high'));

$predictiveOverviewCards = [
    ['icon' => 'bi-graph-up-arrow', 'iconBg' => '#E8F0FE', 'iconColor' => '#1A73E8',
        'label' => 'Lab & X-ray Volume (Next 7 Days)',
        'value' => $poFit['hasSignal'] ? number_format($poForecastTotal) : '—',
        'sub' => $poFit['hasSignal'] ? ('vs ' . number_format($poPast7Total) . ' in the past 7 days') : 'Not enough history yet'],
    ['icon' => 'bi-hourglass-split', 'iconBg' => '#FEF3C7', 'iconColor' => '#B45309',
        'label' => 'Pending Results Backlog',
        'value' => (string) $poPendingCount,
        'sub' => 'Awaiting a result, facility-wide'],
    ['icon' => 'bi-exclamation-octagon-fill', 'iconBg' => '#FCE4E4', 'iconColor' => '#D93025',
        'label' => 'Overdue (2+ Days Waiting)',
        'value' => (string) $poOverdueCount,
        'sub' => 'Needs immediate attention'],
];

$predictiveOverviewTopAlerts = array_map(function ($n) {
    $orderedAt = strtotime($n['date'] . ' ' . $n['time']);
    $daysWaiting = $orderedAt !== false ? max(0, (int) floor((time() - $orderedAt) / 86400)) : 0;

    return [
        'icon' => $n['icon'],
        'iconBg' => $n['iconBg'],
        'iconColor' => $n['iconColor'],
        'label' => $n['title'],
        'desc' => $n['description'],
        'value' => $daysWaiting,
        'valueColor' => $n['iconColor'],
    ];
}, array_slice($poPending, 0, 4));
