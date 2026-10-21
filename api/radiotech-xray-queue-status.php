<?php
/**
 * =====================================================================
 * Radio Technologist X-ray Queue Status (api/radiotech-xray-queue-status.php)
 * ---------------------------------------------------------------------
 * Polled every few seconds by assets/js/radiotech_xray_consultation.js
 * on radio_technologist/xray_consultation.php so the stat card
 * (#xcReferralStats) and the referrals table (#xcReferralTable) refresh
 * themselves in place — e.g. the moment a doctor completes a
 * consultation routed to X-ray (api/complete-consultation.php), a new
 * pending xray_results doc appears here — without the technologist
 * needing to manually reload the page.
 *
 * Reads the SAME ?q=/?from=/?to=/?page= querystring the page itself
 * would (the client passes window.location.search through unchanged),
 * so the polled snapshot always matches whatever filter/page the
 * technologist currently has open. Same pattern as
 * api/medtech-referral-queue-status.php.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php';

header('Content-Type: application/json');

if (($_SESSION['role'] ?? null) !== 'radio_technologist') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Not authorized.']);
    exit;
}

require_once __DIR__ . '/../includes/radio_technologist/xray_consultation_firestore.php';
// $referralsSummary, $referralRows, $referralsTotal, $referralsPerPage,
// $referralsCurrentPage, $referralsTotalPages, $referralsPaginationWindow,
// $referralsStatsSignature, $referralsTableSignature

ob_start();
require __DIR__ . '/../includes/radio_technologist/_xray_referral_stats.php';
$statsHtml = ob_get_clean();

ob_start();
require __DIR__ . '/../includes/radio_technologist/_xray_referral_table.php';
$tableHtml = ob_get_clean();

echo json_encode([
    'success' => true,
    'statsHtml' => $statsHtml,
    'statsSignature' => $referralsStatsSignature,
    'tableHtml' => $tableHtml,
    'tableSignature' => $referralsTableSignature,
]);
