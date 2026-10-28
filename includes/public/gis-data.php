<?php
/**
 * =====================================================================
 * GIS Mapping — Data (patient/gis.php)
 * ---------------------------------------------------------------------
 * $gisSummary, $topDiseases, $healthTrend, and $seasonalOverview are real, computed from
 * Firestore — see includes/public/gis_data_firestore.php's header
 * comment for exactly how (reuses hc_disease_demand_forecast()'s own
 * real notifiable-disease keyword matching). The date-range dropdown
 * genuinely drives this: $_GET['date_range'] (one of
 * $dateRangeToRangeKey's own labels) is resolved to a range key
 * ('1w'/'3m'/'6m'/'1y' — see hc_gis_date_range_spec()) and passed
 * straight into hc_gis_disease_overview(), so picking a different
 * option re-runs the same real Firestore aggregation over that window —
 * api/gis-data.php (the map's own GeoJSON endpoint, fetched separately
 * client-side) reads and applies the same param, kept in sync via
 * window.GIS_RANGE (patient/gis.php).
 * =====================================================================
 */

require_once __DIR__ . '/gis_data_firestore.php'; // hc_gis_disease_overview(), hc_gis_date_range_spec()
require_once __DIR__ . '/../firestore/facilities.php'; // hc_get_all_facilities_summary()

// label -> range key. Keys are exactly what the <select> posts back
// (its own visible option text — see patient/gis.php), so this doubles
// as the allowlist: anything else falls back to the default below
// rather than trusting an arbitrary client-supplied value. Defaults to
// "Last Week" — the shortest, most-present window — so a visitor lands
// on exactly what the system currently has, not a picture diluted by
// older months; they can still widen it themselves via the dropdown.
$dateRangeToRangeKey = [
    'Last Week' => '1w',
    'Last 3 Months' => '3m',
    'Last 6 Months' => '6m',
    'Last Year' => '1y',
];
$dateRangeFilterOptions = array_keys($dateRangeToRangeKey);

$selectedDateRange = $_GET['date_range'] ?? 'Last Week';
if (!isset($dateRangeToRangeKey[$selectedDateRange])) {
    $selectedDateRange = 'Last Week';
}
$gisRange = $dateRangeToRangeKey[$selectedDateRange];

$gisOverview = hc_gis_disease_overview($gisRange);
$gisSummary = $gisOverview['gisSummary'];
$topDiseases = $gisOverview['topDiseases'];
$healthTrend = $gisOverview['healthTrend'];
$seasonalOverview = $gisOverview['seasonalOverview'];

// Real, admin-pinned facility locations (admin/facility_management.php's
// Add Facility map picker) — see includes/firestore/facilities.php's
// hc_get_all_facilities_summary() header comment.
$facilityMarkers = hc_get_all_facilities_summary();
