<?php
/**
 * =====================================================================
 * GIS Mapping (Admin) — Data
 * ---------------------------------------------------------------------
 * Thin admin-shell wrapper around the SAME real GIS pipeline the public
 * homepage (index.php's preview widget) and patient/gis.php's full map
 * already use — includes/public/gis-data.php (-> gis_data_firestore.php's
 * hc_gis_disease_overview()) is required directly, unmodified, so this
 * page is never a second copy of that logic that could drift out of
 * sync: $gisSummary/$topDiseases/$healthTrend and the map's own
 * api/gis-data.php GeoJSON endpoint are the exact same real
 * medical_records/consultations/patients-derived numbers everywhere
 * they appear, city-wide across every facility (this data was never
 * facility-scoped to begin with — a disease case density map is a
 * public-health concern spanning the whole area, not one site), which
 * is exactly the scope a System Administrator (not tied to a single
 * facility, unlike a sub-admin) should see.
 *
 * $adminProfile mirrors every other admin/*.php page's own copy of this
 * (see includes/admin/facility_management_firestore.php's own comment
 * for why this isn't shared instead).
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore
require_once __DIR__ . '/../public/gis-data.php'; // $dateRangeFilterOptions, $selectedDateRange, $gisRange, $gisSummary, $topDiseases, $healthTrend, $seasonalOverview
require_once __DIR__ . '/../firestore/medical_records.php'; // hc_get_all_medical_record_docs()
require_once __DIR__ . '/../shared/clinical_classification.php'; // hc_unclassified_diagnoses_report()

// ---------------------------------------------------------------------
// Data-quality monitoring ONLY (point 11 of the automated-classification
// design) — real, recurring diagnosis text that matches no entry in
// includes/shared/clinical_classification.php's catalog yet. No action
// is attached to this anywhere; it's visibility for whoever maintains
// the catalog, never a queue to approve/classify.
// ---------------------------------------------------------------------
$unclassifiedDiagnoses = hc_unclassified_diagnoses_report(array_values(array_filter(
    hc_get_all_medical_record_docs(),
    fn ($doc) => ($doc['fields']['type'] ?? '') === 'consultation'
)));

// Read from the PHP session api/session-login.php populated after Firebase sign-in
// (admin/gis_mapping.php calls session_start() before requiring this file).
$adminProfile = [
    'name' => $_SESSION['name'] ?? 'Administrator',
    'role' => match ($_SESSION['role'] ?? null) {
        'admin' => 'System Administrator',
        'subadmin' => 'Sub-Administrator',
        'healthcare_staff' => 'Healthcare Staff',
        default => 'Administrator',
    },
];

if (!function_exists('hc_gis_admin_query')) {
    /**
     * Builds a ?query string carrying the current ?location= (barangay
     * search) and ?date_range= except whatever's in $overrides — used
     * by the topbar's Date Range dropdown links so picking a range
     * never drops an active barangay search.
     */
    function hc_gis_admin_query(array $overrides = []): string
    {
        $params = array_merge([
            'location' => $_GET['location'] ?? '',
            'date_range' => $_GET['date_range'] ?? 'Last Week',
        ], $overrides);

        return http_build_query(array_filter($params, fn ($v) => $v !== '' && $v !== null));
    }
}
