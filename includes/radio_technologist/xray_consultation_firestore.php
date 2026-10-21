<?php
/**
 * =====================================================================
 * X-ray Consultation (Radio Technologist) — Data
 * ---------------------------------------------------------------------
 * The referral INBOX — every real xray_results doc still "pending": a
 * doctor completed a consultation, routed it to X-ray (see
 * api/complete-consultation.php), and no result has been entered yet.
 * Modeled directly on
 * includes/medical_technician/lab_xray_consultation_firestore.php,
 * narrowed to the xray_results collection only — this role never sees
 * laboratory referrals, which stay medical_technician's own, so there's
 * no type filter here at all (everything on this page already is X-ray).
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore
require_once __DIR__ . '/../firestore/patients.php';
require_once __DIR__ . '/../firestore/xray_results.php';

$myUid = $_SESSION['uid'] ?? '';
$myDoc = $myUid !== '' ? $firestore->getDocument('users', $myUid) : null;

$staffProfile = [
    'name'     => $_SESSION['name'] ?? 'Radio Technologist',
    'role'     => 'Radio Technologist',
    'facility' => $myDoc['facility'] ?? 'North Super Health Center',
];

$dashboardDateLabel = date('F j, Y');

if (!function_exists('hc_radiotech_xc_query')) {
    /**
     * Builds a ?query string carrying every current filter except the
     * ones in $overrides — used by both this page's own pagination
     * links and includes/radio_technologist/_xray_referral_table.php
     * (shared verbatim with api/radiotech-xray-queue-status.php, so the
     * live-polled fragment's pagination links match exactly).
     */
    function hc_radiotech_xc_query(array $overrides = []): string
    {
        $params = array_merge([
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

/** Same deterministic Routine/High/Emergency placeholder as includes/medical_technician/lab_xray_consultation_firestore.php — see that file's header comment. */
function hc_radiotech_xc_priority(string $docId): array
{
    $options = [
        ['label' => 'Routine',   'bg' => '#E4F7EA', 'color' => '#1E8E3E'],
        ['label' => 'High',      'bg' => '#FCE4E4', 'color' => '#D93025'],
        ['label' => 'Emergency', 'bg' => '#FDECDD', 'color' => '#E8710A'],
    ];

    return $options[crc32($docId) % count($options)];
}

// ---------------------------------------------------------------------
// Build the referral inbox — real xray_results docs still pending,
// across every patient.
// ---------------------------------------------------------------------
$referrals = [];

foreach (hc_get_all_xray_result_docs() as $doc) {
    $f = $doc['fields'];
    if (($f['status'] ?? '') !== 'pending') {
        continue;
    }
    $patient = $patientsById[$f['patientId'] ?? ''] ?? null;
    $referrals[] = [
        'id' => $doc['id'],
        'patientId' => $f['patientId'] ?? '',
        'patientName' => $patient['name'] ?? 'Unknown Patient',
        'patientAge' => $patient['age'] ?? 0,
        'examination' => $f['examName'] ?? '—',
        'bodyPart' => $f['bodyPart'] ?? '—',
        'requestedBy' => $f['orderedBy'] ?? '—',
        'facility' => $f['facility'] ?? '—',
        'orderedDateRaw' => $f['orderedDate'] ?? '',
        'date' => isset($f['orderedDate']) && $f['orderedDate'] !== '' ? date('M j, Y', strtotime($f['orderedDate'])) : '—',
        'time' => $f['orderedTime'] ?? '—',
        'priority' => hc_radiotech_xc_priority($doc['id']),
        // Real "who the doctor sent this to" — api/complete-consultation.php's
        // X-ray staff picker. Null for every referral sent before that
        // existed, or one nobody was assigned — still shows up in this
        // shared queue either way, this just surfaces who it's actually
        // meant for.
        'assignedToName' => $f['assignedToName'] ?? null,
    ];
}

usort($referrals, fn ($a, $b) => strcmp($b['orderedDateRaw'] . $b['time'], $a['orderedDateRaw'] . $a['time']));

// ---------------------------------------------------------------------
// Summary cards — real counts over $referrals.
// ---------------------------------------------------------------------
$referralsSummary = [
    'total' => count($referrals),
];

// ---------------------------------------------------------------------
// Search (?q=) + date range (?from=, ?to=) — real over $referrals. No
// status filter — every row here is already "pending" by definition.
// ---------------------------------------------------------------------
$searchTerm = trim($_GET['q'] ?? '');
$dateFrom = $_GET['from'] ?? '';
$dateTo = $_GET['to'] ?? '';

$filteredReferrals = array_values(array_filter($referrals, function ($r) use ($searchTerm, $dateFrom, $dateTo) {
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

// Cheap "did anything actually change" signals for a live poll
// (api/radiotech-xray-queue-status.php) — same two-separate-signatures
// reasoning as includes/medical_technician/lab_xray_consultation_firestore.php's
// own copy of this comment.
$referralsStatsSignature = (string) $referralsSummary['total'];
$referralsTableSignature = $referralsTotal . ':' . implode(',', array_column($referralRows, 'id'));
