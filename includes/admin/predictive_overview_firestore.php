<?php
/**
 * =====================================================================
 * Predictive & Prescriptive Analytics — System-Wide Preview
 * ---------------------------------------------------------------------
 * A small preview card shared by admin/admin_dashboard.php and
 * admin/facility_management.php's own Overview — NOT a duplicate of
 * admin/facility_analytics.php's full per-facility "Predictive &
 * Prescriptive" tab (reached via that page's "View Analytics" button
 * per facility), but the same real computation
 * (includes/shared/predictive_analytics_core.php's 4 functions, 6.3.1–
 * 6.3.4) run with SYSTEM-WIDE inputs (every facility combined) instead
 * of one facility's own scoped data — an admin lands on these two pages
 * before picking a specific facility, so "all facilities at a glance"
 * is the right scope here, with the full per-facility breakdown always
 * one click away.
 *
 * Same real collections, same honest exceptions
 * includes/admin/facility_analytics_firestore.php's own header comment
 * documents (medicines/vaccines have no per-facility field anywhere in
 * this schema, so shortage/reorder numbers are already system-wide by
 * definition — no scoping difference from the per-facility tab there).
 *
 * Exposes:
 *   $predictiveOverviewCards    — 4 tiles, one per predictive category.
 *   $predictiveOverviewTopAlerts — up to 4 pooled risk rows, most severe
 *                                  first, same severity meta/markup as
 *                                  admin_dashboard.php's own "Account
 *                                  Status Overview" widget
 *                                  (.adm-status-list/.adm-status-row).
 * Rendered by includes/admin/predictive_overview_card.php.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore
require_once __DIR__ . '/../firestore/consultations.php';
require_once __DIR__ . '/../firestore/medical_records.php';
require_once __DIR__ . '/../firestore/medicines.php';
require_once __DIR__ . '/../firestore/pharmacy_orders.php';
require_once __DIR__ . '/../firestore/stock_requests.php';
require_once __DIR__ . '/../shared/forecast_helpers.php'; // hc_last_n_days(), hc_forecast_linear_fit(), hc_forecast_project()
require_once __DIR__ . '/../shared/predictive_analytics_core.php'; // hc_disease_demand_forecast()/hc_medicine_vaccine_shortage()/hc_inventory_replenishment()

// ---------------------------------------------------------------------
// System-wide inputs — every facility combined, same real collections
// facility_analytics_firestore.php filters per facility.
// ---------------------------------------------------------------------
$poPatientBarangayById = [];
foreach ($firestore->listDocuments('patients') as $doc) {
    $poPatientBarangayById[$doc['id']] = trim((string) ($doc['fields']['barangay'] ?? ''));
}
$poAllConsultations = hc_get_all_consultations();
$poAllConsultationRecordDocs = array_values(array_filter(hc_get_all_medical_record_docs(), fn ($d) => ($d['fields']['type'] ?? '') === 'consultation'));
$poAllStockRequests = hc_get_all_stock_requests();

$poDisease = hc_disease_demand_forecast($poAllConsultationRecordDocs, $poAllConsultations, $poPatientBarangayById);

$poAllPharmacyOrderDocs = hc_get_all_pharmacy_order_docs();
$poAllMedicationDocs = $firestore->listDocuments('medications');
$poAllInventoryItems = array_merge(
    array_map(fn ($m) => $m + ['itemType' => 'medicine'], hc_get_inventory_items('medicine')),
    array_map(fn ($v) => $v + ['itemType' => 'vaccine'], hc_get_inventory_items('vaccine'))
);
$poShortage = hc_medicine_vaccine_shortage($poAllInventoryItems, $poAllPharmacyOrderDocs, $poAllMedicationDocs);

$poReplenishment = hc_inventory_replenishment($poShortage['items'], $poAllStockRequests);

// ---------------------------------------------------------------------
// 4 preview tiles — one per real predictive category (6.3.1/6.3.2/6.3.3),
// same numbers the full per-facility tab computes, just system-wide.
// ---------------------------------------------------------------------
$predictiveOverviewCards = [
    [
        'icon' => 'bi-graph-up-arrow', 'iconBg' => '#E8F0FE', 'iconColor' => '#1A73E8',
        'label' => 'Consultations (Next 7 Days)',
        'value' => $poDisease['predictionCards'][0]['value'],
        'sub' => $poDisease['predictionCards'][0]['sub'],
    ],
    [
        'icon' => 'bi-virus', 'iconBg' => '#FCE4E4', 'iconColor' => '#D93025',
        'label' => 'Notifiable-Disease Cases (14d)',
        'value' => (string) count($poDisease['casesLast14']),
        'sub' => 'Across ' . $poDisease['barangaysUnderWatch'] . ' barangay(s) under watch',
    ],
    [
        'icon' => 'bi-capsule', 'iconBg' => '#FDECDD', 'iconColor' => '#E8710A',
        'label' => 'Medicines/Vaccines At Risk',
        'value' => (string) ($poShortage['medicinesAtRisk'] + $poShortage['vaccinesAtRisk']),
        'sub' => 'Projected to stock out within 30 days',
    ],
    [
        'icon' => 'bi-box-seam-fill', 'iconBg' => '#F1E8FD', 'iconColor' => '#8B5CF6',
        'label' => 'Items Needing Reorder',
        'value' => (string) count($poReplenishment['needingReorder']),
        'sub' => 'Medicines + vaccines combined',
    ],
];

// ---------------------------------------------------------------------
// Forecast trend chart — real actual-vs-forecast consultation counts
// (hc_disease_demand_forecast()'s own 'forecastTrend'), the one real
// trend/forecast CHART behind the prediction-card tiles above — see
// includes/admin/predictive_overview_card.php's own header comment.
// ---------------------------------------------------------------------
$predictiveOverviewTrend = $poDisease['forecastTrend'];

// ---------------------------------------------------------------------
// Top risk alerts — pooled across all 3 categories that produce risk
// rows, most severe first (critical > elevated > watch).
// ---------------------------------------------------------------------
$poSeverityOrder = ['critical' => 0, 'elevated' => 1, 'watch' => 2];
$poPooledRiskRows = array_merge($poDisease['riskRows'], $poShortage['riskRows'], $poReplenishment['riskRows']);
usort($poPooledRiskRows, fn ($a, $b) => ($poSeverityOrder[$a['badgeClass']] ?? 3) <=> ($poSeverityOrder[$b['badgeClass']] ?? 3));
$predictiveOverviewTopAlerts = array_slice($poPooledRiskRows, 0, 4);
