<?php
/**
 * =====================================================================
 * Quick Search — Patients (api/quick-search-patients.php)
 * ---------------------------------------------------------------------
 * Backs the live "Quick Search Results" dropdown under the Patient
 * Management search box in subadmin/, medical-staff/, doctor/, and
 * admin/ (assets/js/quick-search.js) — all four read the same real
 * "patients" Firestore collection (includes/firestore/patients.php),
 * so one shared, role-agnostic endpoint serves all four rather than
 * near-identical copies. Also backs pharmacist/medicine_vaccine_inventory.php's
 * "Dispense Without Prescription" modal (quick-search.js's SELECT mode
 * — see that file's header comment) so a pharmacist can pick a real
 * patient without leaving the modal.
 *
 * Fuzzy-matches ?q= against name/contact/address via
 * includes/shared/fuzzy_search.php's hc_fuzzy_match() — the exact same
 * matching used server-side by each role's Patient Management page
 * filter, so what the dropdown suggests and what "View all results"
 * actually shows always agree. Returns
 * only the top 5 matches (a quick-glance list, not the full result
 * set) plus the total match count, for the "View all results for
 * ..." footer row.
 *
 * Facility isolation: every non-admin role is hard-scoped here to their
 * own real facility, resolved server-side from their own users/{uid}
 * doc — never optional, never client-supplied, so this dropdown can
 * never suggest (or leak the id/name/contact of) a patient from another
 * facility. Optional ?facility= only applies to admin/patient_management.php,
 * the one caller that's ever genuinely multi-facility (a System
 * Administrator can view any of them via its own topbar dropdown);
 * left blank there, admin searches across every facility at once.
 *
 * 'meta' is preformatted server-side ("28 years &bull; Female &bull;
 * 0921 765 4321") — assets/js/quick-search.js just prints it as-is, so
 * it stays domain-agnostic instead of knowing about patient-specific
 * fields like age/gender/contact.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php'; // $firestore
require_once __DIR__ . '/../includes/firestore/patients.php';
require_once __DIR__ . '/../includes/shared/fuzzy_search.php'; // hc_fuzzy_match()

header('Content-Type: application/json');

if (!in_array($_SESSION['role'] ?? null, ['subadmin', 'healthcare_staff', 'doctor', 'pharmacist', 'admin'], true)) {
    http_response_code(403);
    echo json_encode(['error' => 'Not authorized.']);
    exit;
}

$query = trim($_GET['q'] ?? '');
if ($query === '') {
    echo json_encode(['results' => [], 'total' => 0]);
    exit;
}

if ($_SESSION['role'] === 'admin') {
    $facility = trim($_GET['facility'] ?? '');
} else {
    $myUid = $_SESSION['uid'] ?? '';
    $myDoc = $myUid !== '' ? $firestore->getDocument('users', $myUid) : null;
    $facility = $myDoc['facility'] ?? '__none__'; // no real facility on record -> matches nothing, never falls through to "all facilities"
}

$matches = array_values(array_filter(
    hc_get_all_patients_summary(),
    fn ($p) => ($facility === '' || $p['facility'] === $facility) && hc_fuzzy_match($p['name'] . ' ' . $p['contact'] . ' ' . $p['address'], $query)
));

$results = array_map(fn ($p) => [
    'id' => $p['id'],
    'name' => $p['name'],
    'meta' => implode(' &bull; ', array_filter([
        $p['age'] ? $p['age'] . ' years' : '',
        $p['gender'],
        $p['contact'],
    ])),
], array_slice($matches, 0, 5));

echo json_encode(['results' => $results, 'total' => count($matches)]);
