<?php
/**
 * =====================================================================
 * Facility Management — Data
 * ---------------------------------------------------------------------
 * $facilities is read live from Firestore's "facilities" collection.
 * The 3 original facilities (North/East/West Super Health Center) were
 * one-time seeded there with the same ids this app already links to
 * (e.g. facility_analytics.php?facility_id=north-super-health-center),
 * so nothing downstream needed to change. New facilities are added via
 * admin/facility_management.php's "Add New Facility" modal ->
 * assets/js/add-facility.js -> api/create-facility.php, which writes
 * here the same way api/create-subadmin.php writes to "users".
 *
 * $adminProfile mirrors includes/admin_dashboard_firestore.php's version rather than
 * sharing it, since that file is scoped to admin_dashboard.php's own
 * dashboard arrays — this stays a small, self-contained duplicate.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore
require_once __DIR__ . '/../firestore/patients.php';
require_once __DIR__ . '/../firestore/medical_records.php';

if (!function_exists('hc_format_date_added')) {
    /**
     * api/create-facility.php stores this as a raw date('c') ISO 8601
     * string (e.g. "2026-07-15T06:20:01+02:00") — shown unformatted
     * before, which rendered as that literal ISO string on
     * admin/admin_dashboard.php's Facility Overview table instead of a
     * real date. DateTime parses the offset actually embedded in the
     * string (not today's default timezone), so this is correct even
     * for facilities added back when this server's PHP default
     * timezone was still wrong (see config/constants.php's own
     * date_default_timezone_set() comment) — it's converted to
     * Asia/Manila here regardless of what offset it was originally
     * stored with.
     */
    function hc_format_date_added(string $iso): string
    {
        if ($iso === '') {
            return '—';
        }

        try {
            return (new DateTime($iso))->setTimezone(new DateTimeZone('Asia/Manila'))->format('M j, Y');
        } catch (\Exception $e) {
            return '—';
        }
    }
}

// Read from the PHP session api/session-login.php populated after Firebase sign-in
// (facility_management.php calls session_start() before requiring this file).
$adminProfile = [
    'name' => $_SESSION['name'] ?? 'Administrator',
    'role' => match ($_SESSION['role'] ?? null) {
        'admin' => 'System Administrator',
        'subadmin' => 'Sub-Administrator',
        'healthcare_staff' => 'Healthcare Staff',
        default => 'Administrator',
    },
];

// patients/records/staff below are computed LIVE from each collection's
// real "facility" field rather than trusted from static fields stored
// on the facility doc itself (those go stale the moment a patient/
// record/staff account changes without someone remembering to also
// bump this doc — same anti-pattern the admin dashboard stats had,
// fixed earlier this session in firebase/firestore.php's
// getDashboardStats()). Loaded once here and tallied per facility name
// rather than re-querying per facility card.
$allPatientsForCounts = hc_get_all_patients_summary();
$allRecordDocsForCounts = hc_get_all_medical_record_docs();
$allUsersForCounts = $firestore->listDocuments('users');

// Firestore's REST API (via firebase/FirestoreRestClient.php) doesn't
// support server-side sorting here, so this sorts by name in PHP —
// fine at this app's scale.
$facilities = [];
foreach ($firestore->listDocuments('facilities') as $doc) {
    $fields = $doc['fields'];
    $facilityName = $fields['name'] ?? $doc['id'];
    $facilities[] = [
        'id' => $doc['id'],
        'name' => $facilityName,
        'type' => $fields['type'] ?? 'Super Health Center',
        'location' => $fields['location'] ?? '',
        'icon' => $fields['icon'] ?? 'bi-building',
        'lat' => $fields['lat'] ?? null,
        'lng' => $fields['lng'] ?? null,
        'isIntegrated' => !empty($fields['isIntegrated']),
        'patients' => count(array_filter($allPatientsForCounts, fn ($p) => $p['facility'] === $facilityName)),
        'records' => count(array_filter($allRecordDocsForCounts, fn ($d) => ($d['fields']['facility'] ?? '') === $facilityName)),
        'staff' => count(array_filter($allUsersForCounts, fn ($u) => ($u['fields']['facility'] ?? '') === $facilityName)),
        'status' => $fields['status'] ?? 'active',
        'dateAdded' => hc_format_date_added($fields['dateAdded'] ?? ''),
    ];
}
usort($facilities, fn ($a, $b) => strcmp($a['name'], $b['name']));

$facilitiesTotal = count($facilities);
$facilitiesPerPage = 6;
// Was hardcoded to 1 with $facilities never actually sliced below it —
// the Previous/page-number/Next links in admin/facility_management.php
// were all real dead links (href="#") pointing at nothing, since there
// was no ?page= for them to carry in the first place. Real now, same
// as every other paginated admin list this session.
$facilitiesCurrentPage = max(1, (int) ($_GET['page'] ?? 1));
$facilitiesTotalPages = (int) max(1, ceil($facilitiesTotal / $facilitiesPerPage));
$facilitiesCurrentPage = min($facilitiesCurrentPage, $facilitiesTotalPages);

$facilities = array_slice($facilities, ($facilitiesCurrentPage - 1) * $facilitiesPerPage, $facilitiesPerPage);

if (!function_exists('hc_fac_query')) {
    /** Builds a ?page=N query string — this page has no search/filter of its own, just pagination. */
    function hc_fac_query(int $page): string
    {
        return http_build_query(['page' => $page]);
    }
}
