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
require_once __DIR__ . '/../public/gis-data.php'; // $dateRangeFilterOptions, $selectedDateRange, $gisRange, $classificationFilterOptions, $gisClassification, $gisSummary, $topDiseases, $healthTrend, $seasonalOverview
require_once __DIR__ . '/../firestore/medical_records.php'; // hc_get_all_medical_record_docs()
require_once __DIR__ . '/../shared/clinical_classification.php'; // hc_ensure_disease_registry_entries()

// ---------------------------------------------------------------------
// Real auto-provisioning (includes/shared/clinical_classification.php's
// header comment) — for diagnosis text matching no existing registry
// synonym, creates a real 'unclassified' doc so it shows up for admin
// review on admin/disease_registry.php. Deliberately triggered HERE
// (authenticated, admin-only, infrequent relative to public traffic)
// and nowhere on the public-facing patient/gis.php/homepage path, so
// anonymous public traffic never causes a Firestore write.
// ---------------------------------------------------------------------
hc_ensure_disease_registry_entries(array_values(array_filter(
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
     * search), ?date_range=, and ?classification= except whatever's in
     * $overrides — used by the topbar's Date Range and Classification
     * dropdown links so picking one never drops the other two.
     */
    function hc_gis_admin_query(array $overrides = []): string
    {
        $params = array_merge([
            'location' => $_GET['location'] ?? '',
            'date_range' => $_GET['date_range'] ?? 'Last Week',
            'classification' => $_GET['classification'] ?? 'all',
        ], $overrides);

        return http_build_query(array_filter($params, fn ($v) => $v !== '' && $v !== null));
    }
}
