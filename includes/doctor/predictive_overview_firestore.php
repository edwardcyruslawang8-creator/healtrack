<?php
/**
 * =====================================================================
 * Predictive & Prescriptive Analytics — Preview (Doctor, own caseload
 * only)
 * ---------------------------------------------------------------------
 * A small preview card for doctor/dashboard.php and
 * doctor/analytics_reports.php — scoped to ONLY this doctor's own
 * caseload (consultations THEY were assigned,
 * "consultations/{id}.assignedPersonnel === their name" — the exact
 * same real field includes/doctor/analytics_reports_doctor_firestore.php's
 * own KPI cards, doctor/consultations.php's "my queue", and every other
 * doctor page already scope by), never facility-wide — same "only show
 * what's genuinely this role's own domain" principle as
 * includes/medical-staff/predictive_overview_firestore.php's header
 * comment (disease/consultation forecasting IS this role's actual job,
 * unlike medical-staff's front-desk intake role, so the framing here is
 * "my own patients' health trends," not "my own intake activity").
 *
 * Only real 6.3.1 Disease & Service Demand Forecast
 * (hc_disease_demand_forecast()) is computed here — same reasoning as
 * medical-staff's own card for why 6.3.2/6.3.3 (inventory) don't
 * belong: no one "personally" owns a medicine stock level, and
 * dispensing/reordering isn't a doctor's job either (that's
 * pharmacist's). medical_records has no per-doctor "assignedPersonnel"-
 * style field of its own (only a free-text "provider" name, not
 * reliably matchable to this doctor's own session name), so this
 * facility's own medical records are narrowed to THIS doctor's own
 * patients via a patientId join instead — same join style
 * includes/medical-staff/predictive_overview_firestore.php already uses
 * for the same reason.
 *
 * Exposes $predictiveOverviewCards (3 tiles — whatever
 * hc_disease_demand_forecast() itself returns) /
 * $predictiveOverviewTrend / $predictiveOverviewTopAlerts, same shape
 * rendered by includes/admin/predictive_overview_card.php.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore
require_once __DIR__ . '/../firestore/consultations.php';
require_once __DIR__ . '/../firestore/medical_records.php';
require_once __DIR__ . '/../shared/forecast_helpers.php'; // hc_last_n_days(), hc_forecast_linear_fit(), hc_forecast_project()
require_once __DIR__ . '/../shared/predictive_analytics_core.php'; // hc_disease_demand_forecast()

$poMyName = $_SESSION['name'] ?? '';

// ---------------------------------------------------------------------
// This doctor's own consultations (real "assignedPersonnel" field) and
// the distinct patients tied to them — patients/{id} has no
// assignedDoctor field of its own, so "my patients" is derived from my
// own consultations, same limitation
// includes/doctor/doctor_dashboard_firestore.php's own header comment
// already documents.
// ---------------------------------------------------------------------
$poMyConsultations = array_values(array_filter(hc_get_all_consultations(), fn ($c) => $c['assignedPersonnel'] === $poMyName));
$poMyPatientIds = array_values(array_unique(array_column($poMyConsultations, 'patientId')));

// medical_records has no per-doctor field of its own (see header
// comment) — narrowed to this doctor's own patients via patientId.
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
// Cases / Barangays Under Watch) — used as-is, same convention
// includes/medical-staff/predictive_overview_firestore.php follows.
// ---------------------------------------------------------------------
$predictiveOverviewCards = $poDisease['predictionCards'];
$predictiveOverviewTrend = $poDisease['forecastTrend'];
$predictiveOverviewTopAlerts = array_slice($poDisease['riskRows'], 0, 4);
