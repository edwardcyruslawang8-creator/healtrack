<?php
/**
 * =====================================================================
 * Laboratory Consultation (Medical Technologist) — Data
 * ---------------------------------------------------------------------
 * The referral INBOX, not the full processing queue
 * (medical_technician/laboratory_xray.php's "Laboratory") — every real
 * lab_results doc that is still "pending": a doctor completed a
 * consultation, routed it to Laboratory (see
 * api/complete-consultation.php), and no result has been entered yet.
 * X-ray referrals are NOT shown here — those route to
 * radio_technologist's own referral inbox
 * (includes/radio_technologist/xray_consultation_firestore.php)
 * instead, so the same exam is never claimable from two different
 * roles' screens. That file is the ONLY place lab_results is ever
 * written to (confirmed — no other create path exists anywhere in this
 * app), so every doc here genuinely is "a patient sent by a doctor for
 * laboratory testing", no separate "source" field needed to prove
 * that.
 *
 * Once a technician enters a result and its status flips to
 * "completed", the doc naturally drops off this inbox and continues to
 * show up only in the full Laboratory & X-ray queue/history.
 *
 * Shares the same patient join, deterministic placeholder Priority
 * (see laboratory_xray_firestore.php's header comment for why it's a
 * stand-in), and pagination pattern as that page — this file just
 * pre-filters to status === 'pending' and swaps the tab strip for a
 * lighter type filter (?type=all|lab|xray) since a status filter would
 * be meaningless here (everything on this page is pending, by design).
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore
require_once __DIR__ . '/../firestore/patients.php';
require_once __DIR__ . '/../firestore/lab_results.php';

$myUid = $_SESSION['uid'] ?? '';
$myDoc = $myUid !== '' ? $firestore->getDocument('users', $myUid) : null;

$staffProfile = [
    'name'     => $_SESSION['name'] ?? 'Medical Technologist',
    'role'     => 'Medical Technologist',
    'facility' => $myDoc['facility'] ?? 'North Super Health Center',
];

$dashboardDateLabel = date('F j, Y');

if (!function_exists('hc_medtech_lxc_query')) {
    /**
     * Builds a ?query string carrying every current filter except the
     * ones in $overrides — used by both this page's own pagination
     * links and includes/medical_technician/_lab_xray_referrals.php
     * (shared verbatim with api/medtech-referral-queue-status.php, so
     * the live-polled fragment's pagination links match exactly).
     */
    function hc_medtech_lxc_query(array $overrides = []): string
    {
        $params = array_merge([
            'type' => $_GET['type'] ?? 'all',
            'q' => $_GET['q'] ?? '',
            'from' => $_GET['from'] ?? '',
            'to' => $_GET['to'] ?? '',
            'page' => $_GET['page'] ?? 1,
        ], $overrides);

        return http_build_query(array_filter($params, fn ($v) => $v !== '' && $v !== null));
    }
}

$patientsById = [];
foreach (hc_get_all_patients_summary() as $p) {
    $patientsById[$p['id']] = $p;
}

/** Same deterministic Routine/High/Emergency placeholder as laboratory_xray_firestore.php — see that file's header comment. */
function hc_medtech_lxc_priority(string $docId): array
{
    $options = [
        ['label' => 'Routine',   'bg' => '#E4F7EA', 'color' => '#1E8E3E'],
        ['label' => 'High',      'bg' => '#FCE4E4', 'color' => '#D93025'],
        ['label' => 'Emergency', 'bg' => '#FDECDD', 'color' => '#E8710A'],
    ];

    return $options[crc32($docId) % count($options)];
}

// ---------------------------------------------------------------------
// Build the referral inbox — real lab_results docs still pending,
// across every patient.
// ---------------------------------------------------------------------
$referrals = [];

foreach (hc_get_all_lab_result_docs() as $doc) {
    $f = $doc['fields'];
    if (($f['status'] ?? '') !== 'pending') {
        continue;
    }
    $patient = $patientsById[$f['patientId'] ?? ''] ?? null;
    $referrals[] = [
        'id' => $doc['id'],
        'type' => 'lab',
        'typeLabel' => 'Laboratory',
        'patientId' => $f['patientId'] ?? '',
        'patientName' => $patient['name'] ?? 'Unknown Patient',
        'patientAge' => $patient['age'] ?? 0,
        'examination' => $f['testName'] ?? '—',
        'requestedBy' => $f['orderedBy'] ?? '—',
        'facility' => $f['facility'] ?? '—',
        'orderedDateRaw' => $f['orderedDate'] ?? '',
        'date' => isset($f['orderedDate']) && $f['orderedDate'] !== '' ? date('M j, Y', strtotime($f['orderedDate'])) : '—',
        'time' => $f['orderedTime'] ?? '—',
        'priority' => hc_medtech_lxc_priority($doc['id']),
        // Flags rows whose test is one of the 3 real Lipa City Health
        // Office "SEROLOGY / RAPID IMMUNOCHROMATOGRAPHIC TESTS" paper
        // form's tests (hc_serology_rapid_test_catalog()) — drives the
        // "Fill Serology Form" action link below.
        'isSerologyRapid' => array_key_exists($f['testName'] ?? '', hc_serology_rapid_test_catalog()),
        // Same idea for the real "URINALYSIS" paper form — drives the
        // "Fill Urinalysis Form" action link below.
        'isUrinalysis' => ($f['testName'] ?? '') === 'Urinalysis',
        // Same idea for the real "CD4 COUNT" paper form — drives the
        // "Fill CD4 Count Form" action link below.
        'isCD4Count' => ($f['testName'] ?? '') === 'CD4',
        // Same idea for the real "GRAM STAIN" paper form — drives the
        // "Fill Gram Stain Form" action link below.
        'isGramStain' => ($f['testName'] ?? '') === 'Gram Stain',
        // Real "who the doctor sent this to" — api/complete-consultation.php's
        // Laboratory staff picker. Null for every referral sent before
        // that existed (and for one nobody was assigned, by choice or
        // because no active medical technologist was on staff at the
        // time) — still shows up in this shared queue either way, this
        // just surfaces who it's actually meant for.
        'assignedToName' => $f['assignedToName'] ?? null,
    ];
}

usort($referrals, fn ($a, $b) => strcmp($b['orderedDateRaw'] . $b['time'], $a['orderedDateRaw'] . $a['time']));

// ---------------------------------------------------------------------
// Summary cards — real counts over $referrals.
// ---------------------------------------------------------------------
$referralsSummary = [
    'total' => count($referrals),
    'lab' => count(array_filter($referrals, fn ($r) => $r['type'] === 'lab')),
];

// ---------------------------------------------------------------------
// Type filter (?type=all|lab) + search (?q=) + date range (?from=,
// ?to=) — all real over $referrals. No status filter — every row here
// is already "pending" by definition (see header comment). X-ray
// dropped from this filter — radio_technologist has its own referral
// inbox for that now.
// ---------------------------------------------------------------------
$typeFilter = $_GET['type'] ?? 'all';
if (!in_array($typeFilter, ['all', 'lab'], true)) {
    $typeFilter = 'all';
}

$searchTerm = trim($_GET['q'] ?? '');
$dateFrom = $_GET['from'] ?? '';
$dateTo = $_GET['to'] ?? '';

$filteredReferrals = array_values(array_filter($referrals, function ($r) use ($typeFilter, $searchTerm, $dateFrom, $dateTo) {
    if ($typeFilter !== 'all' && $r['type'] !== $typeFilter) {
        return false;
    }
    if ($searchTerm !== '' && stripos($r['patientName'] . ' ' . $r['patientId'] . ' ' . $r['id'], $searchTerm) === false) {
        return false;
    }
    if ($dateFrom !== '' && $r['orderedDateRaw'] !== '' && $r['orderedDateRaw'] < $dateFrom) {
        return false;
    }
    if ($dateTo !== '' && $r['orderedDateRaw'] !== '' && $r['orderedDateRaw'] > $dateTo) {
        return false;
    }
    return true;
}));

// ---------------------------------------------------------------------
// Pagination — real total/page-count from the filtered result above.
// ---------------------------------------------------------------------
$referralsTotal = count($filteredReferrals);
$referralsPerPage = 8;
$referralsCurrentPage = max(1, (int) ($_GET['page'] ?? 1));
$referralsTotalPages = max(1, (int) ceil($referralsTotal / $referralsPerPage));
$referralsCurrentPage = min($referralsCurrentPage, $referralsTotalPages);

$referralRows = array_slice($filteredReferrals, ($referralsCurrentPage - 1) * $referralsPerPage, $referralsPerPage);

$referralsPaginationWindow = [];
if ($referralsTotalPages <= 5) {
    for ($p = 1; $p <= $referralsTotalPages; $p++) {
        $referralsPaginationWindow[] = $p;
    }
} else {
    $referralsPaginationWindow = [1, 2, 3, '...', $referralsTotalPages];
}

// Cheap "did anything actually change" signals for
// assets/js/medtech_lab_xray_consultation.js's live poll
// (api/medtech-referral-queue-status.php) — kept as two SEPARATE
// signatures (not one combined value) so a poll only swaps whichever
// half genuinely changed, never both at once: the stat cards
// (unaffected by the current type/search/date filter, so a brand new
// referral that doesn't even match the current filter still trips
// this one) and the table (the current page's own visible row ids, so
// a referral being picked up/completed elsewhere — which changes
// what's visible without changing the grand total — is caught too).
$referralsStatsSignature = $referralsSummary['total'] . ':' . $referralsSummary['lab'];
$referralsTableSignature = $referralsTotal . ':' . implode(',', array_column($referralRows, 'id'));
