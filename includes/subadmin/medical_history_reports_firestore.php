<?php
/**
 * =====================================================================
 * Medical History Reports — Data
 * ---------------------------------------------------------------------
 * The "Medical History" tab of the Analytics & Reports section
 * (subadmin/medical_history_reports.php) — same $adminProfile/
 * $analyticsTabs shape as every sibling report page.
 *
 * CONNECTED to real Firestore data, scoped to THIS sub-admin's own
 * assigned facility (hc_resolve_own_facility_id()): every number comes
 * from includes/shared/medical_history_summary.php, which reads the
 * real medical_conditions / allergies / surgical_history /
 * family_history records of this facility's own patients — see that
 * file's header comment for the unique-patient counting rules.
 * =====================================================================
 */

require_once __DIR__ . '/../shared/query_helpers.php';
require_once __DIR__ . '/../shared/facility_resolver.php'; // hc_resolve_own_facility_id(), $facilities
require_once __DIR__ . '/../shared/medical_history_summary.php';

$mhFacilityId = hc_resolve_own_facility_id();
$facilityName = '';
foreach ($facilities as $f) {
    if ($f['id'] === $mhFacilityId) {
        $facilityName = $f['name'];
    }
}

$adminProfile = [
    'name' => $_SESSION['name'] ?? 'Sub-Administrator',
    'role' => 'Sub-Admin',
    'facility' => $facilityName !== '' ? $facilityName : 'No facility assigned',
];

$analyticsDateRangeLabel = date('F j, Y');

$analyticsTabs = [
    ['key' => 'overview',              'label' => 'Overview',              'href' => 'analytics_reports.php'],
    ['key' => 'patient_reports',       'label' => 'Patient Reports',       'href' => 'patient_reports.php'],
    ['key' => 'medical_history_reports', 'label' => 'Medical History',       'href' => 'medical_history_reports.php', 'active' => true],
    ['key' => 'inventory_reports',     'label' => 'Inventory Reports',     'href' => 'inventory_reports.php'],
    ['key' => 'stock_request_reports', 'label' => 'Stock Request Reports', 'href' => 'stock_request_reports.php'],
    ['key' => 'consultation_reports',  'label' => 'Consultation Reports',  'href' => 'consultation_reports.php'],
    ['key' => 'vaccine_reports',       'label' => 'Vaccine Reports',       'href' => 'vaccine_reports.php'],
    ['key' => 'lab_xray_reports',      'label' => 'Lab & X-ray Reports',   'href' => 'lab_xray_reports.php'],
    ['key' => 'predictive_analytics',  'label' => 'Predictive & Prescriptive', 'href' => 'predictive_analytics.php'],
];

// No facility on this account -> no patients, never another facility's.
$mhPatients = $facilityName !== '' ? hc_mh_facility_patients($facilityName) : [];
$mhFilters = hc_mh_filters_from_get();
$mhFilterOptions = hc_mh_filter_options($mhPatients);
$mh = hc_medical_history_summary($mhPatients, $mhFilters);
$mhStatCards = hc_mh_stat_cards($mh);
