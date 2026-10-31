<?php
/**
 * =====================================================================
 * Predictive & Prescriptive Analytics — Preview (Pharmacist)
 * ---------------------------------------------------------------------
 * A small preview card for pharmacist/dashboard.php and
 * pharmacist/analytics_reports.php — unlike the medical-staff version
 * (which keeps only 6.3.1 Disease & Demand Forecast, personally
 * scoped), this one keeps only 6.3.2 Medicine & Vaccine Shortage and
 * 6.3.3 Inventory Replenishment — the two categories that are actually
 * a pharmacist's own job (stock/reorder decisions), not disease/
 * consultation forecasting, which isn't part of this role's work at
 * all. Same "only show what's genuinely this role's own domain"
 * principle as includes/medical-staff/predictive_overview_firestore.php's
 * own header comment.
 *
 * 6.3.2's numbers stay SYSTEM-WIDE on purpose, same as
 * includes/pharmacist/analytics_reports_pharmacist_firestore.php's own
 * header comment already establishes for this role's Inventory Status
 * Summary: "stock levels aren't owned by any one pharmacist, so scoping
 * that to 'mine' would just be wrong" — a medicine physically runs out
 * regardless of who dispensed it, so narrowing the shortage/order-
 * frequency math to just this pharmacist's own dispensing would produce
 * a WRONG (misleadingly slow) depletion projection, not just a smaller
 * one.
 *
 * 6.3.3's request-volume/gap-alert half DOES get personally scoped —
 * to stock_requests this pharmacist themselves submitted
 * ("stock_requests/{id}.pharmacistUid === their uid", the exact same
 * real field that same file's own "My Stock Requests"/"Pending
 * Requests" KPI cards already use) — that part genuinely is this
 * pharmacist's own activity, the same distinction that file already
 * draws between "my requests" (personal) and "inventory levels"
 * (system-wide).
 *
 * Exposes $predictiveOverviewCards / $predictiveOverviewTopAlerts, same
 * shape rendered by includes/admin/predictive_overview_card.php. No
 * $predictiveOverviewTrend — the old multi-line "all at-risk items"
 * chart was removed (see below, where it used to be built).
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore
require_once __DIR__ . '/../firestore/medicines.php';
require_once __DIR__ . '/../firestore/pharmacy_orders.php';
require_once __DIR__ . '/../firestore/stock_requests.php';
require_once __DIR__ . '/../shared/forecast_helpers.php'; // hc_last_n_days(), hc_forecast_linear_fit(), hc_forecast_project()
require_once __DIR__ . '/../shared/predictive_analytics_core.php'; // hc_medicine_vaccine_shortage()/hc_inventory_replenishment()

$poMyUid = $_SESSION['uid'] ?? '';

// Medicine/vaccine inventory has no per-facility OR per-pharmacist field
// anywhere in this app's schema — always system-wide (see header comment).
$poAllPharmacyOrderDocs = hc_get_all_pharmacy_order_docs();
$poAllMedicationDocs = $firestore->listDocuments('medications');
$poAllInventoryItems = array_merge(
    array_map(fn ($m) => $m + ['itemType' => 'medicine'], hc_get_inventory_items('medicine')),
    array_map(fn ($v) => $v + ['itemType' => 'vaccine'], hc_get_inventory_items('vaccine'))
);
$poShortage = hc_medicine_vaccine_shortage($poAllInventoryItems, $poAllPharmacyOrderDocs, $poAllMedicationDocs);

// This pharmacist's OWN stock requests only — real "pharmacistUid"
// field, same one this role's other KPI cards already scope "My Stock
// Requests"/"Pending Requests" by (see header comment).
$poMyStockRequests = array_values(array_filter(hc_get_all_stock_requests(), fn ($r) => $r['pharmacistUid'] === $poMyUid));

$poReplenishment = hc_inventory_replenishment($poShortage['items'], $poMyStockRequests);

// ---------------------------------------------------------------------
// 4 preview tiles — medicine/vaccine shortage split (system-wide, this
// role's actual job) + this pharmacist's own pending reorder requests.
// ---------------------------------------------------------------------
$poMyPendingRequestsCount = count(array_filter($poMyStockRequests, fn ($r) => $r['status'] === 'pending'));

$predictiveOverviewCards = [
    [
        'icon' => 'bi-capsule', 'iconBg' => '#FDECDD', 'iconColor' => '#E8710A',
        'label' => 'Medicines At Risk (≤30 Days)',
        'value' => (string) $poShortage['medicinesAtRisk'],
        'sub' => 'Based on recent order pace',
    ],
    [
        'icon' => 'bi-droplet-half', 'iconBg' => '#E3F6FB', 'iconColor' => '#12A6C6',
        'label' => 'Vaccines At Risk (≤30 Days)',
        'value' => (string) $poShortage['vaccinesAtRisk'],
        'sub' => 'Based on recent order pace',
    ],
    [
        'icon' => 'bi-exclamation-octagon-fill', 'iconBg' => '#FCE4E4', 'iconColor' => '#D93025',
        'label' => 'Already Out of Stock',
        'value' => (string) $poShortage['alreadyOutCount'],
        'sub' => 'Needs immediate reorder',
    ],
    [
        'icon' => 'bi-hourglass-split', 'iconBg' => '#F1E8FD', 'iconColor' => '#8B5CF6',
        'label' => 'My Pending Stock Requests',
        'value' => (string) $poMyPendingRequestsCount,
        'sub' => 'Awaiting approval',
    ],
];

// ---------------------------------------------------------------------
// No trend chart here anymore — the old 'shortageTrend' multi-line "all
// at-risk items at once" chart mostly drew flat, uninformative lines
// once an item could be flagged at-risk purely for being below its own
// reorder level (no real order-rate history required), and was removed
// from the full Predictive Analytics page for the same reason (see
// assets/js/predictive-analytics.js's own comment). Leaving
// $predictiveOverviewTrend unset skips that section of
// includes/admin/predictive_overview_card.php entirely (its own header
// comment documents this as the supported way to omit the chart) —
// this card now shows only the real prediction tiles + risk alerts.
// ---------------------------------------------------------------------

// ---------------------------------------------------------------------
// Top risk alerts — pooled across both categories, most severe first.
// ---------------------------------------------------------------------
$poSeverityOrder = ['critical' => 0, 'elevated' => 1, 'watch' => 2];
$poPooledRiskRows = array_merge($poShortage['riskRows'], $poReplenishment['riskRows']);
usort($poPooledRiskRows, fn ($a, $b) => ($poSeverityOrder[$a['badgeClass']] ?? 3) <=> ($poSeverityOrder[$b['badgeClass']] ?? 3));
$predictiveOverviewTopAlerts = array_slice($poPooledRiskRows, 0, 4);
