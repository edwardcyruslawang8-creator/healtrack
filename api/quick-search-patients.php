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
 * Fuzzy-matches ?q= against name/contact via includes/shared/fuzzy_search.php's
 * hc_fuzzy_match() — the exact same matching used server-side by each
 * role's Patient Management page filter, so what the dropdown suggests
 * and what "View all results" actually shows always agree. Returns
 * only the top 5 matches (a quick-glance list, not the full result
 * set) plus the total match count, for the "View all results for
 * ..." footer row.
 *
 * Optional ?facility= — admin/patient_management.php is the only
 * caller that's ever multi-facility (a System Administrator can view
 * any of them via its own topbar dropdown, unlike subadmin/medical-
 * staff/doctor/pharmacist, each permanently scoped to their own one
 * real facility already), so this is the only role this actually
 * narrows: picking a quick-search result that belongs to a DIFFERENT
 * facility than the one currently selected would otherwise fill the
 * search box with a name the full-page search (which IS facility-
 * scoped) then finds zero matches for, once submitted.
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

$facility = $_SESSION['role'] === 'admin' ? trim($_GET['facility'] ?? '') : '';

$matches = array_values(array_filter(
    hc_get_all_patients_summary(),
    fn ($p) => ($facility === '' || $p['facility'] === $facility) && hc_fuzzy_match($p['name'] . ' ' . $p['contact'], $query)
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
