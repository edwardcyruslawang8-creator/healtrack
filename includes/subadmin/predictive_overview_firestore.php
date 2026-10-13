<?php
/**
 * =====================================================================
 * Predictive & Prescriptive Analytics — Preview (Sub-Admin Dashboard)
 * ---------------------------------------------------------------------
 * A small preview card for subadmin/dashboard.php, locked to THIS
 * sub-admin's own real assigned facility (never system-wide — a
 * sub-admin only ever sees their own facility everywhere else in this
 * app). Resolves it via hc_resolve_own_facility_id()
 * (includes/shared/facility_resolver.php — the same lock every other
 * subadmin predictive/analytics entry point already uses).
 *
 * Deliberately a STANDALONE computation rather than reusing
 * includes/subadmin/predictive_analytics_firestore.php (which pulls in
 * the entire admin/facility_analytics_firestore.php pipeline — dozens
 * of unrelated variables) — subadmin/dashboard.php already computes its
 * OWN large set of real variables
 * (includes/subadmin/subadmin_dashboard_firestore.php), and chaining
 * that heavy pipeline in here risked silently colliding with (and
 * overwriting) some of them. This file only ever sets its own
 * $predictiveOverviewCards/$predictiveOverviewTopAlerts, computed the
 * same way admin/facility_analytics_firestore.php does for its own
 * per-facility Predictive & Prescriptive tab (same real collections,
 * same joins), just as a standalone block instead of borrowing that
 * whole file.
 *
 * See includes/admin/predictive_overview_firestore.php's header comment
 * for why the system-wide sibling on the admin side is ALSO a
 * standalone computation rather than reused from elsewhere — same
 * reasoning, same convention.
 * =====================================================================
 */

require_once __DIR__ . '/../shared/facility_resolver.php'; // hc_resolve_own_facility_id(), $facilities
require_once __DIR__ . '/../firestore/patients.php';
require_once __DIR__ . '/../firestore/consultations.php';
require_once __DIR__ . '/../firestore/medical_records.php';
require_once __DIR__ . '/../firestore/medicines.php';
require_once __DIR__ . '/../firestore/pharmacy_orders.php';
require_once __DIR__ . '/../firestore/stock_requests.php';
require_once __DIR__ . '/../shared/forecast_helpers.php'; // hc_last_n_days(), hc_forecast_linear_fit(), hc_forecast_project()
require_once __DIR__ . '/../shared/predictive_analytics_core.php'; // hc_disease_demand_forecast()/hc_medicine_vaccine_shortage()/hc_inventory_replenishment()

$poFacilityId = hc_resolve_own_facility_id();
$poFacilityName = '';
foreach ($facilities as $f) {
    if ($f['id'] === $poFacilityId) {
        $poFacilityName = $f['name'];
        break;
    }
}
$predictiveOverviewFacilityUnresolved = $poFacilityName === '';

// ---------------------------------------------------------------------
// This facility's own scoped inputs — same real joins
// admin/facility_analytics_firestore.php uses for its own per-facility
// Predictive & Prescriptive tab. Every filter below explicitly requires
// $poFacilityName !== '' — without that, an unresolved facility (empty
// string) would otherwise match any patient/record/request whose own
// "facility" field also happens to be blank, showing this sub-admin
// someone else's (unassigned) data instead of an honest empty state.
// ---------------------------------------------------------------------
$poFacilityPatients = $predictiveOverviewFacilityUnresolved ? [] : array_values(array_filter(hc_get_all_patients_summary(), fn ($p) => $p['facility'] === $poFacilityName));
$poFacilityPatientIds = array_column($poFacilityPatients, 'id');

$poFacilityConsultations = $predictiveOverviewFacilityUnresolved ? [] : array_values(array_filter(hc_get_all_consultations(), fn ($c) => in_array($c['patientId'], $poFacilityPatientIds, true)));
$poFacilityConsultationRecordDocs = $predictiveOverviewFacilityUnresolved ? [] : array_values(array_filter(
    hc_get_all_medical_record_docs(),
    fn ($doc) => ($doc['fields']['facility'] ?? '') === $poFacilityName && ($doc['fields']['type'] ?? '') === 'consultation'
));
$poFacilityStockRequests = $predictiveOverviewFacilityUnresolved ? [] : array_values(array_filter(hc_get_all_stock_requests(), fn ($r) => $r['facility'] === $poFacilityName));

$poPatientBarangayById = [];
foreach ($firestore->listDocuments('patients') as $doc) {
    $poPatientBarangayById[$doc['id']] = trim((string) ($doc['fields']['barangay'] ?? ''));
}

$poDisease = hc_disease_demand_forecast($poFacilityConsultationRecordDocs, $poFacilityConsultations, $poPatientBarangayById);

// Medicine/vaccine inventory has no per-facility field anywhere in this
// app's schema — always system-wide, same honest exception every other
// inventory section in this app already notes.
$poAllPharmacyOrderDocs = hc_get_all_pharmacy_order_docs();
$poAllMedicationDocs = $firestore->listDocuments('medications');
$poAllInventoryItems = array_merge(
    array_map(fn ($m) => $m + ['itemType' => 'medicine'], hc_get_inventory_items('medicine')),
    array_map(fn ($v) => $v + ['itemType' => 'vaccine'], hc_get_inventory_items('vaccine'))
);
$poShortage = hc_medicine_vaccine_shortage($poAllInventoryItems, $poAllPharmacyOrderDocs, $poAllMedicationDocs);

$poReplenishment = hc_inventory_replenishment($poShortage['items'], $poFacilityStockRequests);

// ---------------------------------------------------------------------
// 4 preview tiles — one per real predictive category (6.3.1/6.3.2/6.3.3),
// same numbers the full per-facility tab computes, scoped to this
// sub-admin's own facility.
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

// ---------------------------------------------------------------------
// Restore $adminProfile to this app's real sub-admin shape
// (name/role/facility). The facility_resolver.php require above pulls
// in includes/admin/facility_management_firestore.php, which sets its
// OWN $adminProfile (just name/role, no facility field at all — the
// admin-side shape) — silently overwriting whatever
// subadmin/dashboard.php's own
// includes/subadmin/subadmin_dashboard_firestore.php had already built
// moments earlier, since this file is required right after that one.
// Without this, subadmin/dashboard.php's topbar facility badge, which
// prints $adminProfile's own facility entry, hits an undefined array
// key — same clobbering bug
// includes/subadmin/predictive_analytics_firestore.php's own
// rebuild-after-require already guards against for its own pages.
// ---------------------------------------------------------------------
$adminProfile = [
    'name' => $_SESSION['name'] ?? 'Sub-Administrator',
    'role' => match ($_SESSION['role'] ?? null) {
        'subadmin' => 'Sub-Admin',
        default => 'Sub-Administrator',
    },
    'facility' => $predictiveOverviewFacilityUnresolved ? 'Unassigned Facility' : $poFacilityName,
];
