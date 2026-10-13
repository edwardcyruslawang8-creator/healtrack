<?php
/**
 * =====================================================================
 * Medical Technologist Referral Queue Status (api/medtech-referral-queue-status.php)
 * ---------------------------------------------------------------------
 * Polled every few seconds by assets/js/medtech_lab_xray_consultation.js
 * on medical_technician/lab_xray_consultation.php so the stat cards
 * (#lxcReferralStats) and the referrals table (#lxcReferralTable)
 * refresh themselves in place — e.g. the moment a doctor completes a
 * consultation routed to Laboratory/X-ray (api/complete-consultation.php),
 * a new pending lab_results/xray_results doc appears here — without the
 * technician needing to manually reload the page.
 *
 * Reads the SAME ?type=/?q=/?from=/?to=/?page= querystring the page
 * itself would (the client passes window.location.search through
 * unchanged), so the polled snapshot always matches whatever filter/
 * page the technician currently has open — never a different view than
 * what's on screen.
 *
 * Returns the exact same includes/medical_technician/_lab_xray_referral_stats.php
 * and _lab_xray_referral_table.php markup the page itself renders (via
 * includes/medical_technician/lab_xray_consultation_firestore.php),
 * plus each section's own signature so the client can cheaply tell
 * whether that section actually changed before touching its DOM — kept
 * as two independent signatures/fragments (not one combined response)
 * so a poll never has to touch the filter toolbar's search input
 * sitting between them, which could hold text the technician is still
 * typing.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php';

header('Content-Type: application/json');

if (($_SESSION['role'] ?? null) !== 'medical_technician') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Not authorized.']);
    exit;
}

require_once __DIR__ . '/../includes/medical_technician/lab_xray_consultation_firestore.php';
// $referralsSummary, $referralRows, $referralsTotal, $referralsPerPage,
// $referralsCurrentPage, $referralsTotalPages, $referralsPaginationWindow,
// $referralsStatsSignature, $referralsTableSignature

ob_start();
require __DIR__ . '/../includes/medical_technician/_lab_xray_referral_stats.php';
$statsHtml = ob_get_clean();

ob_start();
require __DIR__ . '/../includes/medical_technician/_lab_xray_referral_table.php';
$tableHtml = ob_get_clean();

echo json_encode([
    'success' => true,
    'statsHtml' => $statsHtml,
    'statsSignature' => $referralsStatsSignature,
    'tableHtml' => $tableHtml,
    'tableSignature' => $referralsTableSignature,
]);
