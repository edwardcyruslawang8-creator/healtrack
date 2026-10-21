<?php
/**
 * =====================================================================
 * Predictive & Prescriptive Analytics — Preview (Radiologic Technologist,
 * facility-wide)
 * ---------------------------------------------------------------------
 * A small preview card for radiologic_technologist/analytics_reports.php,
 * same shared render partial (includes/admin/predictive_overview_card.php)
 * every other role's card uses. Modeled directly on
 * includes/medical_technician/predictive_overview_firestore.php
 * (X-ray's counterpart to that file's Laboratory one), facility-wide
 * for the same reason: forecasting INCOMING exam volume and tracking
 * the shared pending backlog are both about work any radiologic technologist
 * at this facility could pick up next — not "exams I personally
 * completed" (which the dashboard's own "My Completed Exams"/"My
 * Completed This Week" cards already cover separately, scoped by the
 * real completedByUid api/complete-xray-result.php now stamps).
 *
 *   - PREDICTIVE: forecasting incoming X-ray exam volume for the next 7
 *     days, same real OLS method every other forecast in this app uses
 *     (hc_forecast_linear_fit()/hc_forecast_project() over real daily
 *     order counts), so this role can anticipate workload.
 *   - PRESCRIPTIVE: which already-pending exams need a result soonest —
 *     reuses hc_get_pending_xray_notifications() as-is (the exact same
 *     real days-waiting/urgency computation already driving this role's
 *     notification bell), not a separately invented metric.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore
require_once __DIR__ . '/../firestore/xray_results.php'; // hc_get_all_xray_result_docs()
require_once __DIR__ . '/../firestore/lab_results.php'; // hc_get_pending_xray_notifications()
require_once __DIR__ . '/../shared/forecast_helpers.php'; // hc_last_n_days(), hc_forecast_linear_fit(), hc_forecast_project()

// ---------------------------------------------------------------------
// Volume forecast — real "orderedDate" is the actual demand signal (an
// exam being requested), same field analytics_reports_radiotech_firestore.php
// already plays.
// ---------------------------------------------------------------------
$poOrderedDates = array_values(array_filter(
    array_column(array_column(hc_get_all_xray_result_docs(), 'fields'), 'orderedDate'),
    fn ($d) => $d !== ''
));

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
// Backlog — real pending xray_results docs, reusing the exact same
// computation the notification bell already runs.
// ---------------------------------------------------------------------
$poPending = hc_get_pending_xray_notifications(); // oldest-ordered first, real daysWaiting-derived urgency
$poPendingCount = count($poPending);
// There's no 'priority' field on these rows — iconColor is the real
// signal already encoding "2+ days waiting" (hc_get_pending_lab_xray_notifications()'s
// own $daysWaiting >= 2 check).
$poOverdueCount = count(array_filter($poPending, fn ($n) => $n['iconColor'] === '#D93025'));

$predictiveOverviewCards = [
    ['icon' => 'bi-graph-up-arrow', 'iconBg' => '#E8F0FE', 'iconColor' => '#1A73E8',
        'label' => 'X-ray Volume (Next 7 Days)',
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
