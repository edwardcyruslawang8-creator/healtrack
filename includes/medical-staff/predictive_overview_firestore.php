<?php
/**
 * =====================================================================
 * Predictive & Prescriptive Analytics — Preview (Medical Staff, own
 * activity only)
 * ---------------------------------------------------------------------
 * A small preview card for medical-staff/dashboard.php and
 * medical-staff/analytics_reports.php — scoped to ONLY this staff
 * member's own intake activity (patients THEY personally registered,
 * "patients/{id}.registeredBy === their uid"; consultations THEY
 * personally set, "consultations/{id}.createdBy === their uid" — the
 * exact same two real fields
 * includes/medical-staff/analytics_reports_medstaff_firestore.php's own
 * KPI cards already use), never facility-wide — a front-desk/intake
 * staff member's own dashboard should reflect their own work, not the
 * whole facility's picture (that's subadmin/admin's job — see
 * includes/subadmin/predictive_overview_firestore.php /
 * includes/admin/predictive_overview_firestore.php for those).
 *
 * Only real 6.3.1 Disease & Service Demand Forecast
 * (hc_disease_demand_forecast()) is computed here — 6.3.2 Medicine &
 * Vaccine Shortage and 6.3.3 Inventory Replenishment are deliberately
 * NOT included: inventory has no per-staff-member meaning at all (no
 * one "personally" owns a medicine stock level), so there is no honest
 * "only for the medical staff" version of those two categories to show
 * — showing them here would just be the same facility/system-wide
 * numbers relabeled as if they were personal, which they aren't.
 * medical_records has no per-staff "createdBy"-style field of its own
 * (only a free-text "provider" name), so this facility's own medical
 * records are narrowed to this staff member's own patients via a
 * patientId join instead — same join style
 * admin/facility_analytics_firestore.php already uses for consultations
 * (which also has no facility field of its own).
 *
 * Exposes $predictiveOverviewCards (3 tiles — whatever
 * hc_disease_demand_forecast() itself returns) /
 * $predictiveOverviewTopAlerts, same shape rendered by
 * includes/admin/predictive_overview_card.php.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore
require_once __DIR__ . '/../firestore/patients.php';
require_once __DIR__ . '/../firestore/consultations.php';
require_once __DIR__ . '/../firestore/medical_records.php';
require_once __DIR__ . '/../shared/forecast_helpers.php'; // hc_last_n_days(), hc_forecast_linear_fit(), hc_forecast_project()
require_once __DIR__ . '/../shared/predictive_analytics_core.php'; // hc_disease_demand_forecast()

$poMyUid = $_SESSION['uid'] ?? '';

// ---------------------------------------------------------------------
// This staff member's own patients/consultations — same real fields and
// same join style includes/medical-staff/analytics_reports_medstaff_firestore.php
// already uses for its own "Patients I Registered"/"Consultations I Set"
// KPI cards.
// ---------------------------------------------------------------------
$poMyPatientIds = [];
foreach ($firestore->listDocuments('patients') as $doc) {
    if (($doc['fields']['registeredBy'] ?? '') === $poMyUid) {
        $poMyPatientIds[] = $doc['id'];
    }
}

$poMyConsultations = array_values(array_filter(hc_get_all_consultations(), fn ($c) => $c['createdBy'] === $poMyUid));

// medical_records has no per-staff "createdBy" field of its own (only a
// free-text "provider" name — see header comment), so it's narrowed to
// this staff member's own patients via patientId instead.
$poMyConsultationRecordDocs = array_values(array_filter(
    hc_get_all_medical_record_docs(),
    fn ($doc) => in_array($doc['fields']['patientId'] ?? '', $poMyPatientIds, true) && ($doc['fields']['type'] ?? '') === 'consultation'
));

$poPatientBarangayById = [];
foreach ($firestore->listDocuments('patients') as $doc) {
    $poPatientBarangayById[$doc['id']] = trim((string) ($doc['fields']['barangay'] ?? ''));
}

$poDisease = hc_disease_demand_forecast($poMyConsultationRecordDocs, $poMyConsultations, $poPatientBarangayById);

// ---------------------------------------------------------------------
// hc_disease_demand_forecast() already returns exactly the right
// predictionCards shape (Consultations Next 7 Days / Notifiable-Disease
// Cases / Barangays Under Watch) — used as-is rather than hand-picking
// a subset, since every one of those 3 IS a genuinely personal number
// once the inputs above are scoped to this staff member's own work.
// ---------------------------------------------------------------------
$predictiveOverviewCards = $poDisease['predictionCards'];
$predictiveOverviewTrend = $poDisease['forecastTrend'];
$predictiveOverviewTopAlerts = array_slice($poDisease['riskRows'], 0, 4);
