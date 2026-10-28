<?php
/**
 * =====================================================================
 * Facility Analytics & Reports — Data
 * ---------------------------------------------------------------------
 * CONNECTED — every number on the Overview tab is computed live and
 * scoped to whichever facility was selected (?facility_id=...), using
 * whatever real "facility" field each underlying collection actually
 * carries:
 *   - patients, medical_records, vaccination_records all have their
 *     own real "facility" field — filtered directly.
 *   - consultations has NO facility field of its own, so it's scoped
 *     by joining through patientId to this facility's real patients.
 *   - medicines/vaccines (inventory) have NO facility field anywhere
 *     in this app's schema — that collection is genuinely system-wide,
 *     not scoped per facility. Rather than fabricate a per-facility
 *     split that doesn't exist, the Inventory sections below show the
 *     REAL global inventory (same figures regardless of which facility
 *     is selected) with that limitation noted in each section's own
 *     comment — honest about what the data model does/doesn't support
 *     yet, same principle as every other "front-end only" admission
 *     elsewhere in this app.
 *
 * $facility is looked up from includes/admin/facility_management_firestore.php's
 * $facilities array by the "facility_id" query param, so both pages
 * stay in sync off one source of facility records.
 *
 * Every $analyticsTabs entry is real now — Patients, Records, Services,
 * Staff, Medicines, and Vaccines each add their own genuinely new real
 * breakdown on top of whatever Overview already computed (e.g.
 * Patients by Barangay, Immunizations Given by vaccine — see each
 * section's own comment below), rather than just re-showing the same
 * Overview numbers in another shape. Reports is a real month-over-month
 * comparison table built entirely from numbers already computed above.
 *
 * The Predictive tab (hc_forecast_linear_fit()/hc_forecast_project()
 * below) is genuinely facility-scoped — it forecasts over
 * $facilityPatients/$facilityConsultations, the same real per-facility
 * data the Overview tab already computed above, not a shared/global
 * model — so two different facilities with different real activity
 * produce different forecasts and different recommendations, not the
 * same numbers relabeled. It uses ordinary least-squares linear
 * regression over each facility's own last-30-real-days daily counts —
 * a standard, explainable forecasting method appropriate to this app's
 * actual data volume (no ML framework available in this PHP
 * environment, and nothing here has enough history to need one).
 * Degrades honestly to "not enough data yet" rather than projecting a
 * confident-looking number from a couple of data points — see
 * hc_forecast_linear_fit()'s own comment for the exact bar.
 * =====================================================================
 */

require_once __DIR__ . '/facility_management_firestore.php'; // $adminProfile, $facilities (also gives us the shared facility list)
require_once __DIR__ . '/../firestore/patients.php';
require_once __DIR__ . '/../firestore/consultations.php';
require_once __DIR__ . '/../firestore/medical_records.php';
require_once __DIR__ . '/../firestore/vaccination_records.php';
require_once __DIR__ . '/../firestore/medicines.php';
require_once __DIR__ . '/../firestore/pharmacy_orders.php';
require_once __DIR__ . '/../firestore/stock_requests.php';
require_once __DIR__ . '/../shared/forecast_helpers.php'; // hc_last_n_days(), hc_forecast_linear_fit(), hc_forecast_project()
require_once __DIR__ . '/../shared/predictive_analytics_core.php'; // hc_disease_demand_forecast()/hc_medicine_vaccine_shortage()/hc_inventory_replenishment()/hc_barangay_consultation_trends() — the 4 real predictive categories (6.3.1–6.3.4), called below with THIS facility's own scoped inputs
require_once __DIR__ . '/../shared/analytics_comparison.php'; // hc_facility_growth_pct()

// ---------------------------------------------------------------------
// Resolve the selected facility from ?facility_id=...
// ---------------------------------------------------------------------
// $facilities can legitimately come back [] two different ways:
// Firestore genuinely has no facility docs (shouldn't happen — 3 were
// seeded once, see facility_management_firestore.php's header comment),
// or the read failed (the free-tier daily quota running out mid-page is
// a real, observed failure mode — see firebase/FirestoreRestClient.php's
// $lastRequestFailed). $facilitiesLoadFailed is how the page tells those
// apart, same convention as *_management.php's own $patientsLoadFailed.
$facilitiesLoadFailed = $facilities === [] && $firestore->lastRequestFailed;

$requestedFacilityId = $_GET['facility_id'] ?? '';
$facility = null;
foreach ($facilities as $f) {
    if ($f['id'] === $requestedFacilityId) {
        $facility = $f;
        break;
    }
}
// Unknown/missing id — fall back to the first facility rather than a
// blank page; if there's no facility at all to fall back to (empty
// list, whichever reason), fall back to a safe empty shape instead of
// crashing on $facilities[0] — every computation below that filters by
// $facilityName simply matches nothing against '', which is honest
// (real zeros, not fabricated ones) rather than fatal.
if ($facility === null) {
    $facility = $facilities[0] ?? [
        'id' => $requestedFacilityId, 'name' => '', 'type' => '—', 'location' => '—',
        'icon' => 'bi-building', 'patients' => 0, 'records' => 0, 'staff' => 0,
        'status' => 'active', 'dateAdded' => '',
    ];
}
$facility['status'] = 'Active';

$facilityName = $facility['name'];
$todayRaw = date('Y-m-d');
$thisMonth = date('Y-m');
$lastMonth = date('Y-m', strtotime('first day of last month'));

// Patient Trend card's Daily/Weekly/Monthly/Yearly range selector
// (an-range-btn dropdown) — a real query param, not a client-side-only
// toggle, so the chart genuinely re-renders from freshly bucketed
// server data at the requested granularity. Falls back to 'daily' on
// anything unrecognized rather than erroring.
$patientTrendRangeOptions = ['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly', 'yearly' => 'Yearly'];
$patientTrendRange = $_GET['range'] ?? 'daily';
if (!array_key_exists($patientTrendRange, $patientTrendRangeOptions)) {
    $patientTrendRange = 'daily';
}

/** Real daily counts for the last 8 days (oldest first), from a list of raw ISO date strings. */
function hc_facility_last8_days(array $rawDates): array
{
    $counts = array_fill(0, 8, 0);
    $labels = [];
    for ($i = 7; $i >= 0; $i--) {
        $labels[] = date('M j', strtotime("-$i days"));
    }
    $dayIndex = [];
    for ($i = 7; $i >= 0; $i--) {
        $dayIndex[date('Y-m-d', strtotime("-$i days"))] = 7 - $i;
    }
    foreach ($rawDates as $raw) {
        $day = substr((string) $raw, 0, 10);
        if (isset($dayIndex[$day])) {
            $counts[$dayIndex[$day]]++;
        }
    }

    return ['labels' => $labels, 'values' => $counts];
}

// hc_facility_trend_by_range() moved to includes/shared/forecast_helpers.php
// (already required below) so other roles' own range-switching trend
// cards can reuse it without pulling in this whole per-facility file.

// ---------------------------------------------------------------------
// Real patients at this facility.
// ---------------------------------------------------------------------
$facilityPatients = array_values(array_filter(
    hc_get_all_patients_summary(),
    fn ($p) => $p['facility'] === $facilityName
));
$facilityPatientIds = array_column($facilityPatients, 'id');

$totalPatients = count($facilityPatients);
$newPatientsThisMonth = count(array_filter($facilityPatients, fn ($p) => str_starts_with($p['dateRegisteredRaw'], $thisMonth)));
$newPatientsLastMonth = count(array_filter($facilityPatients, fn ($p) => str_starts_with($p['dateRegisteredRaw'], $lastMonth)));

// ---------------------------------------------------------------------
// Real consultations at this facility (joined via patientId — see
// header comment for why this one needs a join instead of a direct
// facility field).
// ---------------------------------------------------------------------
$facilityConsultations = array_values(array_filter(
    hc_get_all_consultations(),
    fn ($c) => in_array($c['patientId'], $facilityPatientIds, true)
));
$consultationsThisMonth = count(array_filter($facilityConsultations, fn ($c) => str_starts_with($c['dateTime'], $thisMonth)));
$consultationsLastMonth = count(array_filter($facilityConsultations, fn ($c) => str_starts_with($c['dateTime'], $lastMonth)));

// ---------------------------------------------------------------------
// Real medical records at this facility (real "facility" field on the
// doc itself, no join needed).
// ---------------------------------------------------------------------
$facilityRecordDocs = array_values(array_filter(
    hc_get_all_medical_record_docs(),
    fn ($doc) => ($doc['fields']['facility'] ?? '') === $facilityName
));
$recordsThisMonth = count(array_filter($facilityRecordDocs, fn ($doc) => str_starts_with($doc['fields']['date'] ?? '', $thisMonth)));
$recordsLastMonth = count(array_filter($facilityRecordDocs, fn ($doc) => str_starts_with($doc['fields']['date'] ?? '', $lastMonth)));

// ---------------------------------------------------------------------
// Real immunizations at this facility (real "facility" field, only
// "completed" ones count as administered).
// ---------------------------------------------------------------------
$facilityVaccineDocs = array_values(array_filter(
    hc_get_all_vaccination_record_docs(),
    fn ($doc) => ($doc['fields']['facility'] ?? '') === $facilityName && ($doc['fields']['status'] ?? '') === 'completed'
));
$immunizationsThisMonth = count(array_filter($facilityVaccineDocs, fn ($doc) => str_starts_with($doc['fields']['dateAdministered'] ?? '', $thisMonth)));
$immunizationsLastMonth = count(array_filter($facilityVaccineDocs, fn ($doc) => str_starts_with($doc['fields']['dateAdministered'] ?? '', $lastMonth)));

// Real staff headcount at this facility (users/{uid} docs with a
// matching "facility" field — same field admin/subadmin_management.php's
// own staff directory already relies on).
$facilityStaffCount = count(array_filter(
    $firestore->listDocuments('users'),
    fn ($doc) => ($doc['fields']['facility'] ?? '') === $facilityName
));

// Overwrite the header card's patients/records/staff with the real,
// live-computed counts above instead of the static fields the
// "facilities" doc itself stores (those go stale the moment a patient
// or staff record changes without someone remembering to also bump
// this doc — same anti-pattern the admin dashboard stats had, fixed
// earlier this session in firebase/firestore.php's getDashboardStats()).
$facility['patients'] = $totalPatients;
$facility['records'] = count($facilityRecordDocs);
$facility['staff'] = $facilityStaffCount;

$facility['patientsGrowth'] = hc_facility_growth_pct($newPatientsThisMonth, $newPatientsLastMonth);
$facility['recordsGrowth'] = hc_facility_growth_pct($recordsThisMonth, $recordsLastMonth);
$facility['staffGrowth'] = 0; // no historical staff-count snapshots to compare against

// ---------------------------------------------------------------------
// Overview tab — five summary cards, each with a real Chart.js
// sparkline (last 8 real days — flat/zero where nothing genuinely
// happened in that window, which is expected given this app's current
// seed data volume, not a bug).
// ---------------------------------------------------------------------
$patientRegSparkline = hc_facility_last8_days(array_column($facilityPatients, 'dateRegisteredRaw'));
$consultSparkline = hc_facility_last8_days(array_column($facilityConsultations, 'dateTime'));
$recordsSparkline = hc_facility_last8_days(array_column(array_map(fn ($d) => $d['fields']['date'] ?? '', $facilityRecordDocs), 0));
$immunizationSparkline = hc_facility_last8_days(array_column(array_map(fn ($d) => $d['fields']['dateAdministered'] ?? '', $facilityVaccineDocs), 0));

$summaryCards = [
    [
        'key' => 'totalPatients', 'icon' => 'bi-people-fill',
        'iconBg' => '#E8F0FE', 'iconColor' => '#1A73E8', 'sparklineColor' => '#1A73E8',
        'label' => 'Total Patients', 'value' => $totalPatients, 'sub' => 'All time', 'growth' => $facility['patientsGrowth'],
        'sparkline' => $patientRegSparkline['values'],
    ],
    [
        'key' => 'newPatients', 'icon' => 'bi-person-plus-fill',
        'iconBg' => '#E4F7EA', 'iconColor' => '#1E8E3E', 'sparklineColor' => '#1E8E3E',
        'label' => 'New Patients', 'value' => $newPatientsThisMonth, 'sub' => 'This month', 'growth' => $facility['patientsGrowth'],
        'sparkline' => $patientRegSparkline['values'],
    ],
    [
        'key' => 'totalConsultations', 'icon' => 'bi-clipboard2-pulse-fill',
        'iconBg' => '#FDECDD', 'iconColor' => '#E8710A', 'sparklineColor' => '#E8710A',
        'label' => 'Total Consultations', 'value' => $consultationsThisMonth, 'sub' => 'This month', 'growth' => hc_facility_growth_pct($consultationsThisMonth, $consultationsLastMonth),
        'sparkline' => $consultSparkline['values'],
    ],
    [
        'key' => 'totalRecords', 'icon' => 'bi-file-earmark-text-fill',
        'iconBg' => '#EFE7FB', 'iconColor' => '#7C3AED', 'sparklineColor' => '#7C3AED',
        'label' => 'Total Records', 'value' => $recordsThisMonth, 'sub' => 'This month', 'growth' => $facility['recordsGrowth'],
        'sparkline' => $recordsSparkline['values'],
    ],
    [
        'key' => 'immunizations', 'icon' => 'bi-droplet-half', // "bi-syringe" isn't a real Bootstrap Icons 1.11.3 class
        'iconBg' => '#E4F7FA', 'iconColor' => '#0E9CA8', 'sparklineColor' => '#0E9CA8',
        'label' => 'Immunizations', 'value' => $immunizationsThisMonth, 'sub' => 'This month', 'growth' => hc_facility_growth_pct($immunizationsThisMonth, $immunizationsLastMonth),
        'sparkline' => $immunizationSparkline['values'],
    ],
];

// ---------------------------------------------------------------------
// Patient Trend chart — real, bucketed at whichever granularity the
// "an-range-btn" dropdown requested ($patientTrendRange, above:
// Daily=last 8 days / Weekly=last 8 weeks / Monthly=last 8 months /
// Yearly=last 5 years). "New" = registered in that bucket; "Returning"
// = had a consultation in that bucket but was registered before it
// (both facility-scoped). With this app's current data volume this
// will mostly be flat/zero at finer granularities — honest given how
// little history exists yet, not a bug.
// ---------------------------------------------------------------------
$patientsById = array_column($facilityPatients, null, 'id');
$returningDates = [];
foreach ($facilityConsultations as $c) {
    $p = $patientsById[$c['patientId']] ?? null;
    if ($p !== null && $p['dateRegisteredRaw'] !== '' && substr($c['dateTime'], 0, 10) > $p['dateRegisteredRaw']) {
        $returningDates[] = $c['dateTime'];
    }
}
$newByRange = hc_facility_trend_by_range(array_column($facilityPatients, 'dateRegisteredRaw'), $patientTrendRange);
$returningByRange = hc_facility_trend_by_range($returningDates, $patientTrendRange);

$patientTrend = [
    'labels' => $newByRange['labels'],
    'newPatients' => $newByRange['values'],
    'returningPatients' => $returningByRange['values'],
];

// ---------------------------------------------------------------------
// Patient Demographics doughnut — real age buckets over this
// facility's real patients.
// ---------------------------------------------------------------------
$demographicsBuckets = [
    '0 - 17 years'       => ['count' => 0, 'color' => '#7C3AED'],
    '18 - 34 years'      => ['count' => 0, 'color' => '#1E8E3E'],
    '35 - 54 years'      => ['count' => 0, 'color' => '#E8710A'],
    '55 years and above' => ['count' => 0, 'color' => '#1A73E8'],
];
foreach ($facilityPatients as $p) {
    $age = $p['age'];
    $bucket = match (true) {
        $age <= 17 => '0 - 17 years',
        $age <= 34 => '18 - 34 years',
        $age <= 54 => '35 - 54 years',
        default => '55 years and above',
    };
    $demographicsBuckets[$bucket]['count']++;
}
$demographicsTotal = count($facilityPatients);
$demographics = [];
foreach ($demographicsBuckets as $label => $b) {
    $demographics[] = [
        'label' => $label,
        'value' => $b['count'],
        'pct' => $demographicsTotal > 0 ? round($b['count'] / $demographicsTotal * 100, 1) : 0,
        'color' => $b['color'],
    ];
}

// ---------------------------------------------------------------------
// Top Services — real consultation types tallied over this facility's
// real consultations.
// ---------------------------------------------------------------------
$consultationTypeMeta = hc_consultation_type_meta();
$serviceIconMeta = [
    'walk_in'   => ['icon' => 'bi-clipboard2-pulse-fill', 'iconBg' => '#E8F0FE', 'iconColor' => '#1A73E8'],
    'follow_up' => ['icon' => 'bi-arrow-repeat',           'iconBg' => '#E4F7EA', 'iconColor' => '#1E8E3E'],
    'emergency' => ['icon' => 'bi-heart-pulse-fill',        'iconBg' => '#FCE4E4', 'iconColor' => '#D93025'],
    'scheduled' => ['icon' => 'bi-calendar2-check-fill',    'iconBg' => '#F1E8FD', 'iconColor' => '#8B5CF6'],
    'tele'      => ['icon' => 'bi-camera-video-fill',       'iconBg' => '#E3F6FB', 'iconColor' => '#12A6C6'],
];
$serviceTallies = [];
foreach ($facilityConsultations as $c) {
    $type = $c['consultationType'];
    $serviceTallies[$type] = ($serviceTallies[$type] ?? 0) + 1;
}
arsort($serviceTallies);
$topServices = [];
foreach (array_slice($serviceTallies, 0, 5, true) as $type => $count) {
    $meta = $consultationTypeMeta[$type] ?? ['label' => ucfirst($type), 'color' => '#6B7280'];
    $iconMeta = $serviceIconMeta[$type] ?? ['icon' => 'bi-clipboard2-pulse-fill', 'iconBg' => '#EEF0F3', 'iconColor' => '#6B7280'];
    $topServices[] = [
        'icon' => $iconMeta['icon'], 'iconBg' => $iconMeta['iconBg'], 'iconColor' => $iconMeta['iconColor'],
        'label' => $meta['label'], 'value' => $count, 'color' => $meta['color'],
    ];
}
$topServicesMax = $topServices === [] ? 1 : (max(array_column($topServices, 'value')) ?: 1);

// ---------------------------------------------------------------------
// Medicine Inventory — REAL data, but system-wide (see header
// comment: medicines/vaccines have no per-facility field in this
// app's schema, so this is the same for every facility right now).
// ---------------------------------------------------------------------
$allMedicineItems = hc_get_inventory_items('medicine');
$medicineInventory = [
    'total' => count($allMedicineItems),
    'inStock' => count(array_filter($allMedicineItems, fn ($m) => $m['status'] === 'available')),
    'lowStock' => count(array_filter($allMedicineItems, fn ($m) => $m['status'] === 'low')),
    'outOfStock' => count(array_filter($allMedicineItems, fn ($m) => $m['status'] === 'unavailable')),
    'overstocked' => count(array_filter($allMedicineItems, fn ($m) => $m['status'] === 'overstocked')),
];
// 'low'/'unavailable' only — "!== 'available'" would also sweep in
// 'overstocked' items, which don't belong in a shortage-focused list.
$lowMedicines = array_values(array_filter($allMedicineItems, fn ($m) => in_array($m['status'], ['low', 'unavailable'], true)));
usort($lowMedicines, fn ($a, $b) => $a['currentStock'] <=> $b['currentStock']);
$topLowStockMedicines = array_map(fn ($m) => ['name' => $m['name'], 'stock' => $m['currentStock']], array_slice($lowMedicines, 0, 5));
$lowStockMedicinesMax = $topLowStockMedicines === [] ? 1 : (max(array_column($topLowStockMedicines, 'stock')) ?: 1);
$medicineStockDistribution = [
    ['label' => 'In Stock',     'pct' => $medicineInventory['total'] > 0 ? round($medicineInventory['inStock'] / $medicineInventory['total'] * 100, 1) : 0, 'color' => '#1E8E3E'],
    ['label' => 'Low Stock',    'pct' => $medicineInventory['total'] > 0 ? round($medicineInventory['lowStock'] / $medicineInventory['total'] * 100, 1) : 0, 'color' => '#E8710A'],
    ['label' => 'Out of Stock', 'pct' => $medicineInventory['total'] > 0 ? round($medicineInventory['outOfStock'] / $medicineInventory['total'] * 100, 1) : 0, 'color' => '#D93025'],
    ['label' => 'Overstocked',  'pct' => $medicineInventory['total'] > 0 ? round($medicineInventory['overstocked'] / $medicineInventory['total'] * 100, 1) : 0, 'color' => '#8B5CF6'],
];

// ---------------------------------------------------------------------
// Vaccine Inventory — same system-wide caveat as Medicine Inventory above.
// ---------------------------------------------------------------------
$allVaccineItems = hc_get_inventory_items('vaccine');
$vaccineInventory = [
    'total' => count($allVaccineItems),
    'inStock' => count(array_filter($allVaccineItems, fn ($v) => $v['status'] === 'available')),
    'lowStock' => count(array_filter($allVaccineItems, fn ($v) => $v['status'] === 'low')),
    'outOfStock' => count(array_filter($allVaccineItems, fn ($v) => $v['status'] === 'unavailable')),
    'overstocked' => count(array_filter($allVaccineItems, fn ($v) => $v['status'] === 'overstocked')),
];
$vaccineStock = array_map(fn ($v) => ['name' => $v['name'], 'stock' => $v['currentStock']], array_slice($allVaccineItems, 0, 5));
$vaccineStockMax = $vaccineStock === [] ? 1 : (max(array_column($vaccineStock, 'stock')) ?: 1);
$vaccineStockDistribution = [
    ['label' => 'In Stock',     'pct' => $vaccineInventory['total'] > 0 ? round($vaccineInventory['inStock'] / $vaccineInventory['total'] * 100, 1) : 0, 'color' => '#1E8E3E'],
    ['label' => 'Low Stock',    'pct' => $vaccineInventory['total'] > 0 ? round($vaccineInventory['lowStock'] / $vaccineInventory['total'] * 100, 1) : 0, 'color' => '#E8710A'],
    ['label' => 'Out of Stock', 'pct' => $vaccineInventory['total'] > 0 ? round($vaccineInventory['outOfStock'] / $vaccineInventory['total'] * 100, 1) : 0, 'color' => '#D93025'],
    ['label' => 'Overstocked',  'pct' => $vaccineInventory['total'] > 0 ? round($vaccineInventory['overstocked'] / $vaccineInventory['total'] * 100, 1) : 0, 'color' => '#8B5CF6'],
];

// ---------------------------------------------------------------------
// Medicine/Vaccine Inventory cards' OWN real category filter
// (?medCategory=/?vacCategory=, an-range-btn-style dropdown per card,
// assets/js/facility_analytics.js fetches api/get-inventory-card.php
// to refresh just that one card). $medicineInventory/$vaccineInventory/
// $topLowStockMedicines/etc. above stay system-wide, UNFILTERED — the
// Inventory Summary panel and the "Medicines need reordering"
// recommendation both need the true system-wide picture regardless of
// what category either card happens to be filtered to on screen right
// now, so this is a second, separate "card view" computed from the
// same real $allMedicineItems/$allVaccineItems rather than replacing
// the totals above.
// ---------------------------------------------------------------------
$medicineCategories = hc_medicine_categories($allMedicineItems);
$medCategoryFilter = $_GET['medCategory'] ?? 'All';
if ($medCategoryFilter !== 'All' && !in_array($medCategoryFilter, $medicineCategories, true)) {
    $medCategoryFilter = 'All';
}
$medicineItemsCard = hc_medicine_filter_by_category($allMedicineItems, $medCategoryFilter);
$medicineInventoryCard = hc_medicine_status_pills($medicineItemsCard);
$lowMedicinesCard = array_values(array_filter($medicineItemsCard, fn ($m) => in_array($m['status'], ['low', 'unavailable'], true)));
usort($lowMedicinesCard, fn ($a, $b) => $a['currentStock'] <=> $b['currentStock']);
$topLowStockMedicinesCard = array_map(fn ($m) => ['name' => $m['name'], 'stock' => $m['currentStock']], array_slice($lowMedicinesCard, 0, 5));
$lowStockMedicinesMaxCard = $topLowStockMedicinesCard === [] ? 1 : (max(array_column($topLowStockMedicinesCard, 'stock')) ?: 1);
$medicineStockDistributionCard = hc_medicine_stock_distribution($medicineInventoryCard);

$vaccineCategories = hc_medicine_categories($allVaccineItems);
$vacCategoryFilter = $_GET['vacCategory'] ?? 'All';
if ($vacCategoryFilter !== 'All' && !in_array($vacCategoryFilter, $vaccineCategories, true)) {
    $vacCategoryFilter = 'All';
}
$vaccineItemsCard = hc_medicine_filter_by_category($allVaccineItems, $vacCategoryFilter);
$vaccineInventoryCard = hc_medicine_status_pills($vaccineItemsCard);
$vaccineStockCard = array_map(fn ($v) => ['name' => $v['name'], 'stock' => $v['currentStock']], array_slice($vaccineItemsCard, 0, 5));
$vaccineStockMaxCard = $vaccineStockCard === [] ? 1 : (max(array_column($vaccineStockCard, 'stock')) ?: 1);
$vaccineStockDistributionCard = hc_medicine_stock_distribution($vaccineInventoryCard);

// ---------------------------------------------------------------------
// Inventory Summary panel (medicines + vaccines combined) — real,
// system-wide (see above). No price field exists anywhere in the
// schema, so there's no real "Total Inventory Value" to show (removed
// rather than displaying a fabricated ₱0.00). "expiringSoonCount" has
// a real settled "soon" definition (90 days) already.
// ---------------------------------------------------------------------
$inventorySummary = [
    'totalItems' => $medicineInventory['total'] + $vaccineInventory['total'],
    'inStock' => $medicineInventory['inStock'] + $vaccineInventory['inStock'],
    'lowStock' => $medicineInventory['lowStock'] + $vaccineInventory['lowStock'],
    'outOfStock' => $medicineInventory['outOfStock'] + $vaccineInventory['outOfStock'],
    'overstocked' => $medicineInventory['overstocked'] + $vaccineInventory['overstocked'],
    'expiringSoonCount' => count(array_filter(array_merge($allMedicineItems, $allVaccineItems), fn ($m) => $m['expiryDateRaw'] !== '' && $m['expiryDateRaw'] <= date('Y-m-d', strtotime('+90 days')))),
    'expiringSoonWindowDays' => 90,
];

// ---------------------------------------------------------------------
// PATIENTS TAB — this facility's own real patients, deeper than the
// Overview tab's demographics-only donut.
// ---------------------------------------------------------------------
$patientsMaleCount = count(array_filter($facilityPatients, fn ($p) => $p['gender'] === 'Male'));
$patientsFemaleCount = count(array_filter($facilityPatients, fn ($p) => $p['gender'] === 'Female'));
$patientsAverageAge = $totalPatients > 0 ? (int) round(array_sum(array_column($facilityPatients, 'age')) / $totalPatients) : 0;

// Barangay is real per-patient data (not system-wide like inventory) —
// a genuinely facility-specific geographic breakdown.
$barangayTallies = [];
foreach ($facilityPatients as $p) {
    $bgy = trim((string) ($p['barangay'] ?? ''));
    if ($bgy === '') {
        continue;
    }
    $barangayTallies[$bgy] = ($barangayTallies[$bgy] ?? 0) + 1;
}
arsort($barangayTallies);
$patientsByBarangay = [];
foreach (array_slice($barangayTallies, 0, 8, true) as $label => $count) {
    $patientsByBarangay[] = ['label' => $label, 'value' => $count];
}
$patientsByBarangayMax = $patientsByBarangay === [] ? 1 : max(array_column($patientsByBarangay, 'value'));

// Most recently registered 10, newest first (hc_get_all_patients_summary() already sorts that way).
$recentPatientsTable = array_slice($facilityPatients, 0, 10);

// ---------------------------------------------------------------------
// RECORDS TAB — this facility's own real medical_records docs.
// ---------------------------------------------------------------------
$recordsConsultationCount = count(array_filter($facilityRecordDocs, fn ($d) => ($d['fields']['type'] ?? '') === 'consultation'));
$recordsLaboratoryCount = count(array_filter($facilityRecordDocs, fn ($d) => ($d['fields']['type'] ?? '') === 'laboratory'));
$recordsTotal = count($facilityRecordDocs);
$recordsByType = array_values(array_filter([
    ['label' => 'Consultation', 'value' => $recordsConsultationCount, 'color' => '#1A73E8'],
    ['label' => 'Laboratory',   'value' => $recordsLaboratoryCount,   'color' => '#7C3AED'],
], fn ($r) => $r['value'] > 0));
$recordsByTypeTotal = array_sum(array_column($recordsByType, 'value'));

$facilityRecordsSorted = $facilityRecordDocs;
usort($facilityRecordsSorted, fn ($a, $b) => strcmp($b['fields']['date'] ?? '', $a['fields']['date'] ?? ''));
$recentRecordsTable = array_map(fn ($d) => [
    'date' => isset($d['fields']['date']) && $d['fields']['date'] !== '' ? date('M j, Y', strtotime($d['fields']['date'])) : '—',
    'patientName' => $patientsById[$d['fields']['patientId'] ?? '']['name'] ?? '(unknown patient)',
    'type' => ucfirst($d['fields']['type'] ?? '—'),
    'title' => $d['fields']['title'] ?? '—',
    'provider' => $d['fields']['provider'] ?? '—',
], array_slice($facilityRecordsSorted, 0, 10));

// ---------------------------------------------------------------------
// SERVICES TAB — this facility's own real consultations, by type AND
// by status (Overview only shows Top Services by type).
// ---------------------------------------------------------------------
$consultStatusMeta = hc_consultation_status_meta();
$servicesStatusTallies = [];
foreach ($facilityConsultations as $c) {
    $servicesStatusTallies[$c['status']] = ($servicesStatusTallies[$c['status']] ?? 0) + 1;
}
$servicesByStatus = [];
foreach ($consultStatusMeta as $key => $meta) {
    if (($servicesStatusTallies[$key] ?? 0) > 0) {
        $servicesByStatus[] = ['label' => $meta['label'], 'value' => $servicesStatusTallies[$key], 'color' => $meta['color']];
    }
}
$servicesByStatusTotal = array_sum(array_column($servicesByStatus, 'value'));

$consultationsTotal = count($facilityConsultations);
$consultationsCompleted = $servicesStatusTallies['completed'] ?? 0;
$consultationsPending = $servicesStatusTallies['pending'] ?? 0;

// Full type breakdown (not just top 5 like Overview's Top Services).
$allServiceTallies = [];
foreach ($facilityConsultations as $c) {
    $allServiceTallies[$c['consultationType']] = ($allServiceTallies[$c['consultationType']] ?? 0) + 1;
}
arsort($allServiceTallies);
$servicesTable = [];
foreach ($allServiceTallies as $type => $count) {
    $meta = $consultationTypeMeta[$type] ?? ['label' => ucfirst($type)];
    $servicesTable[] = [
        'label' => $meta['label'],
        'value' => $count,
        'pct' => $consultationsTotal > 0 ? round($count / $consultationsTotal * 100, 1) : 0,
    ];
}

// ---------------------------------------------------------------------
// STAFF TAB — real users/{uid} docs whose "facility" matches this one
// (same field $facilityStaffCount above already counts by).
// ---------------------------------------------------------------------
$staffRoleMeta = [
    'doctor'             => ['label' => 'Doctor',            'color' => '#B45309'],
    'healthcare_staff'   => ['label' => 'Medical Staff',     'color' => '#1E8E3E'],
    'pharmacist'         => ['label' => 'Pharmacist',        'color' => '#4F46E5'],
    'medical_technician' => ['label' => 'Medical Technologist','color' => '#1A73E8'],
    'radio_technologist' => ['label' => 'Radiologic Technologist', 'color' => '#8B5CF6'],
    'subadmin'           => ['label' => 'Sub-Admin',         'color' => '#D93025'],
];
$facilityStaffDocs = array_values(array_filter(
    $firestore->listDocuments('users'),
    fn ($doc) => ($doc['fields']['facility'] ?? '') === $facilityName
));
$staffRoleTallies = [];
foreach ($facilityStaffDocs as $doc) {
    $role = $doc['fields']['role'] ?? 'other';
    $staffRoleTallies[$role] = ($staffRoleTallies[$role] ?? 0) + 1;
}
$staffByRole = [];
foreach ($staffRoleMeta as $key => $meta) {
    if (($staffRoleTallies[$key] ?? 0) > 0) {
        $staffByRole[] = ['label' => $meta['label'], 'value' => $staffRoleTallies[$key], 'color' => $meta['color']];
    }
}
$staffByRoleTotal = array_sum(array_column($staffByRole, 'value'));

$staffTable = array_map(fn ($doc) => [
    'name' => $doc['fields']['name'] ?? '(unnamed)',
    'role' => $staffRoleMeta[$doc['fields']['role'] ?? '']['label'] ?? ucfirst($doc['fields']['role'] ?? '—'),
    'email' => $doc['fields']['email'] ?? '—',
    'status' => ($doc['fields']['status'] ?? 'active') === 'pending' ? 'Pending' : (($doc['fields']['status'] ?? 'active') === 'inactive' ? 'Inactive' : 'Active'),
], $facilityStaffDocs);
usort($staffTable, fn ($a, $b) => strcasecmp($a['name'], $b['name']));

// ---------------------------------------------------------------------
// MEDICINES TAB — real inventory, system-wide (see this file's header
// comment — no per-facility field exists on "medicines" docs).
// ---------------------------------------------------------------------
$medicineCategoryTallies = [];
foreach ($allMedicineItems as $m) {
    $medicineCategoryTallies[$m['category']] = ($medicineCategoryTallies[$m['category']] ?? 0) + 1;
}
arsort($medicineCategoryTallies);
$medicinesByCategory = [];
foreach (array_slice($medicineCategoryTallies, 0, 8, true) as $label => $count) {
    $medicinesByCategory[] = ['label' => $label, 'value' => $count];
}
$medicinesByCategoryMax = $medicinesByCategory === [] ? 1 : max(array_column($medicinesByCategory, 'value'));

$medicineStatusMetaTab = hc_medicine_status_meta();
$medicinesTable = array_map(fn ($m) => [
    'name' => $m['name'],
    'category' => $m['category'],
    'stock' => $m['currentStock'],
    'status' => $medicineStatusMetaTab[$m['status']] ?? ['label' => ucfirst($m['status']), 'bg' => '#EEF0F3', 'color' => '#6B7280'],
    'expiry' => $m['expiryDate'],
], array_slice($allMedicineItems, 0, 20));

// ---------------------------------------------------------------------
// VACCINES TAB — inventory is system-wide (same caveat as Medicines),
// but immunizations actually GIVEN are real, per-patient,
// facility-scoped data ($facilityVaccineDocs, already read for
// Overview's Immunizations sparkline) — the genuinely facility-specific
// angle this tab adds on top of the shared inventory table.
// ---------------------------------------------------------------------
$vaccineCategoryTallies = [];
foreach ($allVaccineItems as $v) {
    $vaccineCategoryTallies[$v['category']] = ($vaccineCategoryTallies[$v['category']] ?? 0) + 1;
}
arsort($vaccineCategoryTallies);
$vaccinesByCategory = [];
foreach (array_slice($vaccineCategoryTallies, 0, 8, true) as $label => $count) {
    $vaccinesByCategory[] = ['label' => $label, 'value' => $count];
}
$vaccinesByCategoryMax = $vaccinesByCategory === [] ? 1 : max(array_column($vaccinesByCategory, 'value'));

$immunizationsByVaccine = [];
foreach ($facilityVaccineDocs as $doc) {
    $name = trim((string) ($doc['fields']['name'] ?? ''));
    if ($name === '') {
        continue;
    }
    $immunizationsByVaccine[$name] = ($immunizationsByVaccine[$name] ?? 0) + 1;
}
arsort($immunizationsByVaccine);
$immunizationsByVaccineTable = [];
foreach (array_slice($immunizationsByVaccine, 0, 8, true) as $label => $count) {
    $immunizationsByVaccineTable[] = ['label' => $label, 'value' => $count];
}
$immunizationsByVaccineMax = $immunizationsByVaccineTable === [] ? 1 : max(array_column($immunizationsByVaccineTable, 'value'));
$immunizationsTotal = count($facilityVaccineDocs);

$vaccinesTable = array_map(fn ($v) => [
    'name' => $v['name'],
    'category' => $v['category'],
    'stock' => $v['currentStock'],
    'status' => $medicineStatusMetaTab[$v['status']] ?? ['label' => ucfirst($v['status']), 'bg' => '#EEF0F3', 'color' => '#6B7280'],
    'expiry' => $v['expiryDate'],
], array_slice($allVaccineItems, 0, 20));

// ---------------------------------------------------------------------
// REPORTS TAB — a real month-over-month summary, reusing every number
// already computed above rather than re-querying anything.
// ---------------------------------------------------------------------
$monthlyReport = [
    ['metric' => 'New Patients',   'thisMonth' => $newPatientsThisMonth,   'lastMonth' => $newPatientsLastMonth,   'changePct' => $facility['patientsGrowth']],
    ['metric' => 'Consultations',  'thisMonth' => $consultationsThisMonth, 'lastMonth' => $consultationsLastMonth, 'changePct' => hc_facility_growth_pct($consultationsThisMonth, $consultationsLastMonth)],
    ['metric' => 'Medical Records','thisMonth' => $recordsThisMonth,       'lastMonth' => $recordsLastMonth,       'changePct' => $facility['recordsGrowth']],
    ['metric' => 'Immunizations',  'thisMonth' => $immunizationsThisMonth, 'lastMonth' => $immunizationsLastMonth, 'changePct' => hc_facility_growth_pct($immunizationsThisMonth, $immunizationsLastMonth)],
];

// ---------------------------------------------------------------------
// PREDICTIVE ANALYTICS — real forecasts over this facility's own
// historical daily counts, via ordinary least-squares linear
// regression (see this file's header comment for why this method).
// hc_last_n_days()/hc_forecast_linear_fit()/hc_forecast_project() live
// in includes/shared/forecast_helpers.php (required above).
// ---------------------------------------------------------------------

// Fit over the last 30 real days (better statistical signal than the
// 8-day sparkline window); the visual chart below only shows the last
// 14 of those plus the 7-day projection, for legibility in a small card.
$patientDaily30 = hc_last_n_days(array_column($facilityPatients, 'dateRegisteredRaw'), 30);
$consultDaily30 = hc_last_n_days(array_column($facilityConsultations, 'dateTime'), 30);

$patientFit = hc_forecast_linear_fit($patientDaily30['values']);
$consultFit = hc_forecast_linear_fit($consultDaily30['values']);

$patientForecast7 = hc_forecast_project($patientFit, 30, 7);
$consultForecast7 = hc_forecast_project($consultFit, 30, 7);

$patientForecastTotal = array_sum($patientForecast7);
$consultForecastTotal = array_sum($consultForecast7);
$patientPast7Total = array_sum(array_slice($patientDaily30['values'], -7));
$consultPast7Total = array_sum(array_slice($consultDaily30['values'], -7));

// Projected Monthly Patients — pace-based (days elapsed so far this
// month, extrapolated to a full month), a simpler technique than the
// day-by-day regression above, better suited to a single running total.
$daysElapsedThisMonth = (int) date('j');
$daysInThisMonth = (int) date('t');
$projectedMonthlyPatients = $daysElapsedThisMonth > 0
    ? (int) round(($newPatientsThisMonth / $daysElapsedThisMonth) * $daysInThisMonth)
    : 0;
$projectedMonthlyGrowthPct = hc_facility_growth_pct($projectedMonthlyPatients, $newPatientsLastMonth);

// Forecast trend chart — last 14 real days (actual) then 7 projected
// days (forecast), sharing one label axis. The two datasets overlap by
// exactly one point (today) so the line reads as continuous instead of
// leaving a visible gap between "actual" and "forecast".
$patientDaily14 = hc_last_n_days(array_column($facilityPatients, 'dateRegisteredRaw'), 14);
$forecastLabels = array_merge($patientDaily14['labels'], array_map(fn ($i) => date('M j', strtotime('+' . ($i + 1) . ' days')), range(0, 6)));
$forecastActual = array_merge($patientDaily14['values'], array_fill(0, 7, null));
$forecastProjected = array_merge(array_fill(0, 13, null), [end($patientDaily14['values'])], $patientForecast7);
$forecastTrend = ['labels' => $forecastLabels, 'actual' => $forecastActual, 'forecast' => $forecastProjected];

// ---------------------------------------------------------------------
// PRESCRIPTIVE ANALYTICS — real, rule-based recommendations, each only
// appearing when its underlying number actually crosses a meaningful
// threshold. An empty list below means genuinely "nothing to flag
// right now" at this facility, not a missing feature.
// ---------------------------------------------------------------------
$recommendations = [];

if ($patientFit['hasSignal'] && $patientPast7Total > 0) {
    $patientChangePct = (int) round((($patientForecastTotal - $patientPast7Total) / $patientPast7Total) * 100);
    if ($patientChangePct >= 20) {
        $recommendations[] = [
            'type' => 'opportunity', 'icon' => 'bi-graph-up-arrow',
            'title' => 'Patient volume is trending up',
            'body' => "Projected new patients next week ({$patientForecastTotal}) are {$patientChangePct}% above the past week ({$patientPast7Total}) at {$facilityName}. Consider preparing extra walk-in capacity and front-desk coverage.",
        ];
    } elseif ($patientChangePct <= -20) {
        $recommendations[] = [
            'type' => 'warning', 'icon' => 'bi-graph-down-arrow',
            'title' => 'Patient volume is trending down',
            'body' => 'Projected new patients next week (' . $patientForecastTotal . ') are ' . abs($patientChangePct) . "% below the past week ({$patientPast7Total}) at {$facilityName}. Worth reviewing outreach or scheduling.",
        ];
    }
}

if ($consultFit['hasSignal'] && $consultForecastTotal > 0 && $facilityStaffCount > 0) {
    $projectedPerStaff = $consultForecastTotal / $facilityStaffCount;
    if ($projectedPerStaff >= 15) { // sustained 2+ consultations/day/staff member
        $recommendations[] = [
            'type' => 'warning', 'icon' => 'bi-people-fill',
            'title' => 'Consultation load may outpace current staffing',
            'body' => "Projected consultations next week ({$consultForecastTotal}) average " . round($projectedPerStaff, 1) . " per staff member across {$facilityStaffCount} staff at {$facilityName} — worth evaluating whether coverage needs adjusting.",
        ];
    }
}

if ($immunizationsLastMonth >= 5) {
    $immunizationChangePct = hc_facility_growth_pct($immunizationsThisMonth, $immunizationsLastMonth);
    if ($immunizationChangePct <= -30) {
        $recommendations[] = [
            'type' => 'warning', 'icon' => 'bi-droplet-half', // "bi-syringe" isn't a real Bootstrap Icons 1.11.3 class
            'title' => 'Immunizations have dropped this month',
            'body' => 'Completed immunizations are down ' . abs($immunizationChangePct) . "% vs last month ({$immunizationsThisMonth} vs {$immunizationsLastMonth}) at {$facilityName}. A local vaccination drive may help close the gap.",
        ];
    }
}

if ($demographicsTotal >= 10) {
    foreach ($demographics as $bucket) {
        if ($bucket['label'] === '55 years and above' && $bucket['pct'] >= 40) {
            $recommendations[] = [
                'type' => 'insight', 'icon' => 'bi-person-heart',
                'title' => 'A large share of patients are senior citizens',
                'body' => round($bucket['pct']) . "% of {$facilityName}'s patients are 55 or older — prioritizing senior-friendly scheduling and stocking senior-relevant medicines may improve care here.",
            ];
        }
        if ($bucket['label'] === '0 - 17 years' && $bucket['pct'] >= 40) {
            $recommendations[] = [
                'type' => 'insight', 'icon' => 'bi-person-heart',
                'title' => 'A large share of patients are children',
                'body' => round($bucket['pct']) . "% of {$facilityName}'s patients are under 18 — ensuring pediatric vaccine and medicine stock stays adequate is worth prioritizing.",
            ];
        }
    }
}

if ($medicineInventory['lowStock'] + $medicineInventory['outOfStock'] > 0) {
    $recommendations[] = [
        'type' => 'warning', 'icon' => 'bi-capsule',
        'title' => 'Medicines need reordering',
        // Medicine inventory is system-wide, not per-facility (see this
        // file's header comment) — worded accordingly rather than
        // implying these specific items belong only to this facility.
        'body' => ($medicineInventory['lowStock'] + $medicineInventory['outOfStock']) . ' medicine(s) are low or out of stock system-wide, including ' .
            implode(', ', array_slice(array_column($topLowStockMedicines, 'name'), 0, 3)) .
            '. Review Stock Requests to reorder before this facility runs out.',
    ];
}

// ---------------------------------------------------------------------
// PREDICTIVE ANALYTICS — the 4 additional categories (6.3.1–6.3.4),
// scoped to THIS facility wherever the data model actually supports
// it, via includes/shared/predictive_analytics_core.php's shared tab
// functions.
//   - Disease & Demand + Consultation Trends by Barangay: genuinely
//     facility-scoped, using $facilityRecordDocs/$facilityConsultations
//     already computed above (real, now that patients carry a real
//     "facility" field — api/create-patient.php).
//   - Medicine & Vaccine Shortage: system-wide inventory/orders, same
//     honest exception the rest of this page's Overview tab already
//     documents (medicines/vaccines have no per-facility field at all).
//   - Inventory Replenishment: stock_requests DOES carry a real
//     "facility" field, so its request-volume/gap-alert half is scoped
//     to this facility even though the underlying stock numbers can't be.
// ---------------------------------------------------------------------
$patientBarangayById = [];
$patientAgeById = [];
foreach ($firestore->listDocuments('patients') as $doc) {
    $patientBarangayById[$doc['id']] = trim((string) ($doc['fields']['barangay'] ?? ''));
    $patientAgeById[$doc['id']] = (int) ($doc['fields']['age'] ?? 0);
}
$facilityConsultationRecordDocs = array_values(array_filter($facilityRecordDocs, fn ($d) => ($d['fields']['type'] ?? '') === 'consultation'));
$facilityStockRequests = array_values(array_filter(hc_get_all_stock_requests(), fn ($r) => $r['facility'] === $facilityName));

$pt1 = hc_disease_demand_forecast($facilityConsultationRecordDocs, $facilityConsultations, $patientBarangayById, $patientAgeById);
$pt1PredictionCards = $pt1['predictionCards'];
$pt1ForecastTrend = $pt1['forecastTrend'];
$pt1DiseaseTrend = $pt1['diseaseTrend'];
$pt1RiskRows = $pt1['riskRows'];
$pt1Recommendations = $pt1['recommendations'];
$pt1ConsultVolumeAnomaly = $pt1['consultVolumeAnomaly'];

$allPharmacyOrderDocsForShortage = hc_get_all_pharmacy_order_docs(); // system-wide — see comment above
$allMedicationDocsForShortage = $firestore->listDocuments('medications');
$allInventoryItemsForShortage = array_merge(
    array_map(fn ($m) => $m + ['itemType' => 'medicine'], hc_get_inventory_items('medicine')),
    array_map(fn ($v) => $v + ['itemType' => 'vaccine'], hc_get_inventory_items('vaccine'))
);
$pt2 = hc_medicine_vaccine_shortage($allInventoryItemsForShortage, $allPharmacyOrderDocsForShortage, $allMedicationDocsForShortage);
$pt2PredictionCards = $pt2['predictionCards'];
$pt2StockProjection = $pt2['stockProjection'];
$pt2OrderFrequency = $pt2['orderFrequency'];
$pt2ShortageTrend = $pt2['shortageTrend'];
$pt2RiskRows = $pt2['riskRows'];
$pt2Recommendations = $pt2['recommendations'];

$pt3 = hc_inventory_replenishment($pt2['items'], $facilityStockRequests);
$pt3PredictionCards = $pt3['predictionCards'];
$pt3RankedItems = $pt3['rankedItems'];
$pt3RankedMax = $pt3['rankedMax'];
$pt3RequestVolume = $pt3['requestVolume'];
$pt3RiskRows = $pt3['riskRows'];
$pt3Recommendations = $pt3['recommendations'];

$pt4 = hc_barangay_consultation_trends($facilityConsultations, $patientBarangayById);
$pt4PredictionCards = $pt4['predictionCards'];
$pt4Barangays = $pt4['barangays'];
$pt4Max = $pt4['max'];
$pt4ForecastTrend = $pt4['forecastTrend'];
$pt4RiskRows = $pt4['riskRows'];
$pt4Recommendations = $pt4['recommendations'];

$predictiveSubTabs = [
    ['key' => 'disease', 'icon' => 'bi-virus', 'label' => 'Disease & Demand Forecast', 'active' => true],
    ['key' => 'shortage', 'icon' => 'bi-capsule', 'label' => 'Medicine & Vaccine Shortage'],
    ['key' => 'replenishment', 'icon' => 'bi-box-seam-fill', 'label' => 'Inventory Replenishment'],
    ['key' => 'barangay', 'icon' => 'bi-geo-alt-fill', 'label' => 'Consultation Trends by Barangay'],
];

// ---------------------------------------------------------------------
// Predictive & Prescriptive Analytics — small preview section on the
// Overview tab (see admin/facility_analytics.php's own #tab-overview
// block) — 4 tiles + top risk alerts for THIS facility, reusing pt1-pt4
// above rather than a second computation. Same shape/markup as
// includes/admin/predictive_overview_card.php's admin-dashboard/
// facility-management/sub-admin-dashboard cards, just scoped to this
// one facility instead of system-wide or "my own facility" — a link on
// the card jumps straight to this same page's own "Predictive &
// Prescriptive" tab (#tab-predictive), not a different page.
// ---------------------------------------------------------------------
$predictiveOverviewCards = [
    [
        'icon' => 'bi-graph-up-arrow', 'iconBg' => '#E8F0FE', 'iconColor' => '#1A73E8',
        'label' => 'Consultations (Next 7 Days)',
        'value' => $pt1PredictionCards[0]['value'],
        'sub' => $pt1PredictionCards[0]['sub'],
    ],
    [
        'icon' => 'bi-virus', 'iconBg' => '#FCE4E4', 'iconColor' => '#D93025',
        'label' => 'Notifiable-Disease Cases (14d)',
        'value' => (string) count($pt1['casesLast14']),
        'sub' => 'Across ' . $pt1['barangaysUnderWatch'] . ' barangay(s) under watch',
    ],
    [
        'icon' => 'bi-capsule', 'iconBg' => '#FDECDD', 'iconColor' => '#E8710A',
        'label' => 'Medicines/Vaccines At Risk',
        'value' => (string) ($pt2['medicinesAtRisk'] + $pt2['vaccinesAtRisk']),
        'sub' => 'Projected to stock out within 30 days',
    ],
    [
        'icon' => 'bi-box-seam-fill', 'iconBg' => '#F1E8FD', 'iconColor' => '#8B5CF6',
        'label' => 'Items Needing Reorder',
        'value' => (string) count($pt3['needingReorder']),
        'sub' => 'Medicines + vaccines combined',
    ],
];

// Forecast trend chart for the preview — same real actual-vs-forecast
// consultation counts $pt1ForecastTrend already is, just exposed under
// the name includes/admin/predictive_overview_card.php's shared partial
// expects (see that file's own header comment).
$predictiveOverviewTrend = $pt1ForecastTrend;

$poSeverityOrder = ['critical' => 0, 'elevated' => 1, 'watch' => 2];
$poPooledRiskRows = array_merge($pt1RiskRows, $pt2RiskRows, $pt3RiskRows);
usort($poPooledRiskRows, fn ($a, $b) => ($poSeverityOrder[$a['badgeClass']] ?? 3) <=> ($poSeverityOrder[$b['badgeClass']] ?? 3));
$predictiveOverviewTopAlerts = array_slice($poPooledRiskRows, 0, 4);

// ---------------------------------------------------------------------
// Facility Comparison (6.2.4) — same 4 real metrics as the Overview tab
// above (Patients/Consultations/Medical Records/Immunizations), just
// computed once per REAL facility instead of once for the single
// ?facility_id= selection — same underlying collections/join logic
// (consultations joined via patientId, the other 3 via their own real
// "facility" field), read once here rather than once per facility to
// avoid N redundant Firestore reads.
// ---------------------------------------------------------------------
$allPatientsForComparison = hc_get_all_patients_summary();
$allConsultationsForComparison = hc_get_all_consultations();
$allRecordDocsForComparison = hc_get_all_medical_record_docs();
$allVaccineDocsForComparison = array_values(array_filter(
    hc_get_all_vaccination_record_docs(),
    fn ($doc) => ($doc['fields']['status'] ?? '') === 'completed'
));

$facilityComparisonRows = [];
foreach ($facilities as $f) {
    $fName = $f['name'];
    $fPatientIds = array_column(array_filter($allPatientsForComparison, fn ($p) => $p['facility'] === $fName), 'id');
    $facilityComparisonRows[] = [
        'name' => $fName,
        'patients' => count($fPatientIds),
        'consultations' => count(array_filter($allConsultationsForComparison, fn ($c) => in_array($c['patientId'], $fPatientIds, true))),
        'records' => count(array_filter($allRecordDocsForComparison, fn ($doc) => ($doc['fields']['facility'] ?? '') === $fName)),
        'immunizations' => count(array_filter($allVaccineDocsForComparison, fn ($doc) => ($doc['fields']['facility'] ?? '') === $fName)),
    ];
}
$facilityComparisonMetrics = [
    ['key' => 'patients', 'label' => 'Patients'],
    ['key' => 'consultations', 'label' => 'Consultations'],
    ['key' => 'records', 'label' => 'Medical Records'],
    ['key' => 'immunizations', 'label' => 'Immunizations'],
];

// ---------------------------------------------------------------------
// Analytics tabs — Overview and Predictive have real content for now.
// ---------------------------------------------------------------------
$analyticsTabs = [
    ['key' => 'overview', 'icon' => 'bi-grid-1x2-fill',      'label' => 'Overview', 'active' => true],
    ['key' => 'predictive', 'icon' => 'bi-graph-up-arrow',   'label' => 'Predictive & Prescriptive'],
    ['key' => 'patients',  'icon' => 'bi-people-fill',       'label' => 'Patients'],
    ['key' => 'records',   'icon' => 'bi-file-earmark-text-fill', 'label' => 'Records'],
    ['key' => 'services',  'icon' => 'bi-clipboard2-pulse-fill',  'label' => 'Services'],
    ['key' => 'staff',     'icon' => 'bi-person-badge-fill', 'label' => 'Staff'],
    ['key' => 'medicines', 'icon' => 'bi-capsule',           'label' => 'Medicines'],
    ['key' => 'vaccines',  'icon' => 'bi-droplet-half',      'label' => 'Vaccines'],
    ['key' => 'comparison', 'icon' => 'bi-bar-chart-steps',  'label' => 'Facility Comparison'],
    ['key' => 'reports',   'icon' => 'bi-bar-chart-fill',    'label' => 'Reports'],
];
