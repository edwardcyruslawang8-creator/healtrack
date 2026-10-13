<?php
/**
 * =====================================================================
 * Get Consultations by Barangay Table (api/consultation-reports-barangay-table.php)
 * ---------------------------------------------------------------------
 * Called by assets/js/cr-barangay-pagination.js whenever a First/
 * Previous/page-number/Next/Last link inside
 * subadmin/consultation_reports.php's "Consultations by Barangay
 * (Top 5)" card is clicked — returns just that card's HTML fragment
 * (same markup includes/subadmin/_cr_barangay_card.php renders on a
 * normal full page load) so only this one card re-renders, instead of
 * a full page reload that would also needlessly re-fetch every stat
 * card, both donuts, the trend chart, and the Consultations by
 * Physician table next to it.
 *
 * Re-requires the SAME data file subadmin/consultation_reports.php
 * does, so every query param this page already understands (brgyPage,
 * plus the filter toolbar's range/type/status/physician/barangay) is
 * read exactly the same way — this file just runs that one data file,
 * then echoes one fragment instead of the whole page.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/constants.php'; // BASE_URL, asset_url()

if (($_SESSION['role'] ?? null) !== 'subadmin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Not authorized.']);
    exit;
}

require_once __DIR__ . '/../includes/subadmin/consultation_reports_firestore.php';
// $consultationsByBarangay, $consultationsByBarangayTotal,
// $consultationsByBarangayCurrentPage, $consultationsByBarangayTotalPages

header('Content-Type: application/json');

ob_start();
require __DIR__ . '/../includes/subadmin/_cr_barangay_card.php';
$html = ob_get_clean();

echo json_encode(['success' => true, 'html' => $html]);
