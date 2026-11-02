<?php
/**
 * =====================================================================
 * Medical History Summary — Data (admin, per facility)
 * ---------------------------------------------------------------------
 * The admin's view of includes/shared/medical_history_summary.php for
 * the facility picked by ?facility_id= (same lookup and fallback as
 * includes/admin/facility_analytics_firestore.php). Used by
 * admin/facility_analytics.php's Medical History tab and by the report
 * export endpoint when an admin prints/exports that tab.
 *
 * Sets $mhFacilityName, $mhPatients, $mhFilters, $mhFilterOptions, $mh, $mhStatCards.
 * =====================================================================
 */

require_once __DIR__ . '/../shared/query_helpers.php';
require_once __DIR__ . '/facility_management_firestore.php'; // $facilities
require_once __DIR__ . '/../shared/medical_history_summary.php';

$mhFacilityName = '';
foreach ($facilities as $f) {
    if ($f['id'] === ($_GET['facility_id'] ?? '')) {
        $mhFacilityName = $f['name'];
    }
}
if ($mhFacilityName === '') {
    $mhFacilityName = $facilities[0]['name'] ?? '';
}

$mhPatients = $mhFacilityName !== '' ? hc_mh_facility_patients($mhFacilityName) : [];
$mhFilters = hc_mh_filters_from_get();
$mhFilterOptions = hc_mh_filter_options($mhPatients);
$mh = hc_medical_history_summary($mhPatients, $mhFilters);
$mhStatCards = hc_mh_stat_cards($mh);
