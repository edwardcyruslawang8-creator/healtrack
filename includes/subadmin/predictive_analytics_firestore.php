<?php
/**
 * =====================================================================
 * Predictive & Prescriptive Analytics (Sub-Admin) — Data
 * ---------------------------------------------------------------------
 * The "Predictive & Prescriptive" tab of the Analytics & Reports
 * section — a sibling page to subadmin/analytics_reports.php
 * ("Overview"), same as subadmin/patient_reports.php etc. Real
 * forecasting for THIS sub-admin's own assigned facility only (never a
 * picker): resolves it via hc_resolve_own_facility_id()
 * (includes/shared/facility_resolver.php — the same lock
 * api/get-patient-trend.php's own subadmin branch already uses) and
 * forces $_GET['facility_id'] to that value before requiring the real
 * admin data file (includes/admin/facility_analytics_firestore.php),
 * which has no admin-specific gating of its own (the role check lives
 * in the page, not there) — so this reuses the EXACT same computation
 * admin/facility_analytics.php's own "Predictive & Prescriptive" tab
 * uses for this same facility, never a second copy that could drift.
 *
 * Only the predictive-related variables that file computes are
 * actually used by subadmin/predictive_analytics.php's template
 * (patient/consultation forecast + the 4 categories, 6.3.1–6.3.4) —
 * every OTHER variable that pipeline also computes (Overview tab
 * summary cards, demographics, medicine/vaccine inventory sections,
 * etc.) is simply unused here, not exposed on this page.
 * =====================================================================
 */

require_once __DIR__ . '/../shared/facility_resolver.php'; // hc_resolve_own_facility_id()

$mySubadminFacilityId = hc_resolve_own_facility_id();
// Real, honest failure state — a subadmin account with no (or a
// mismatched) "facility" field on their own users/{uid} doc. Without
// this flag, admin/facility_analytics_firestore.php's own "no real
// facility_id matched -> fall back to $facilities[0]" behavior (meant
// for admin mistyping a URL) would silently show THIS subadmin some
// OTHER facility's data instead of admitting their account isn't set
// up correctly yet.
$subadminFacilityUnresolved = $mySubadminFacilityId === null;
$_GET['facility_id'] = $mySubadminFacilityId ?? '';

require __DIR__ . '/../admin/facility_analytics_firestore.php';
// $facility, $facilityName, $patientFit, $consultFit, $patientForecastTotal,
// $consultForecastTotal, $patientPast7Total, $consultPast7Total, $forecastTrend,
// $recommendations, $pt1PredictionCards..$pt4Recommendations (the 4 categories)

// Rebuilt AFTER the require above (which overwrites $adminProfile with
// the plain admin shape — no "facility" key) into this app's real
// sub-admin $adminProfile convention (name/role/facility), same as
// every sibling report page's own -firestore.php — 'facility' here is
// $facilityName itself rather than a second independent users/{uid}
// lookup, so it's guaranteed to agree with every number on this page.
$adminProfile = [
    'name' => $_SESSION['name'] ?? 'Sub-Administrator',
    'role' => match ($_SESSION['role'] ?? null) {
        'subadmin' => 'Sub-Admin',
        default => 'Sub-Administrator',
    },
    'facility' => $facilityName,
];

$analyticsDateRangeLabel = date('F j, Y'); // same real, up-to-date single date every sibling report page now shows
$facilityOptions = ['North Super Health Center', 'East Super Health Center', 'West Super Health Center'];

// ---------------------------------------------------------------------
// Analytics tabs — same real sibling-page list every other report page
// in this family carries (see includes/subadmin/analytics_reports_firestore.php's
// own copy) — Predictive & Prescriptive stays active (this IS that page).
// ---------------------------------------------------------------------
$analyticsTabs = [
    ['key' => 'overview',              'label' => 'Overview',              'href' => 'analytics_reports.php'],
    ['key' => 'patient_reports',       'label' => 'Patient Reports',       'href' => 'patient_reports.php'],
    ['key' => 'inventory_reports',     'label' => 'Inventory Reports',     'href' => 'inventory_reports.php'],
    ['key' => 'stock_request_reports', 'label' => 'Stock Request Reports', 'href' => 'stock_request_reports.php'],
    ['key' => 'consultation_reports',  'label' => 'Consultation Reports',  'href' => 'consultation_reports.php'],
    ['key' => 'vaccine_reports',       'label' => 'Vaccine Reports',       'href' => 'vaccine_reports.php'],
    ['key' => 'predictive_analytics',  'label' => 'Predictive & Prescriptive', 'href' => 'predictive_analytics.php', 'active' => true],
];
