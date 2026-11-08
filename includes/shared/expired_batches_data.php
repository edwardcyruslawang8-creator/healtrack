<?php
/**
 * =====================================================================
 * Expired Batches (monitoring) — Data
 * ---------------------------------------------------------------------
 * Shared by pharmacist/expired_batches.php and subadmin/expired_batches.php.
 * Read-only list of every expired batch archived through
 * api/archive-medicine-batch.php (hc_get_archived_expired_batches()),
 * with search (q — batch number / item name / category), type filter
 * (type — All/Medicines/Vaccines) and pagination (page). No actions —
 * monitoring only, there is deliberately no unarchive for a batch.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore
require_once __DIR__ . '/../firestore/medicines.php'; // hc_qty_unit(), hc_packaging_label()
require_once __DIR__ . '/../firestore/medicine_batches.php'; // hc_get_archived_expired_batches()
require_once __DIR__ . '/fuzzy_search.php'; // hc_fuzzy_match()

$myUid = $_SESSION['uid'] ?? '';
$myDoc = $myUid !== '' ? $firestore->getDocument('users', $myUid) : null;
$dashboardDateLabel = date('F j, Y');

$allExpiredBatches = hc_get_archived_expired_batches();

$ebSearchTerm = trim((string) ($_GET['q'] ?? ''));
$ebTypeOptions = ['all' => 'All Types', 'medicine' => 'Medicines', 'vaccine' => 'Vaccines'];
$ebTypeFilter = array_key_exists($_GET['type'] ?? '', $ebTypeOptions) ? $_GET['type'] : 'all';

$filteredExpiredBatches = array_values(array_filter($allExpiredBatches, function ($b) use ($ebSearchTerm, $ebTypeFilter) {
    if ($ebTypeFilter !== 'all' && $b['itemType'] !== $ebTypeFilter) {
        return false;
    }
    if ($ebSearchTerm !== '' && !hc_fuzzy_match($b['displayBatchNumber'] . ' ' . $b['itemName'] . ' ' . $b['category'], $ebSearchTerm)) {
        return false;
    }
    return true;
}));

$ebSummary = [
    'totalBatches' => count($allExpiredBatches),
    'itemsAffected' => count(array_unique(array_column($allExpiredBatches, 'medicineId'))),
    'thisMonth' => count(array_filter($allExpiredBatches, fn ($b) => $b['archivedAtRaw'] !== '' && date('Y-m', strtotime($b['archivedAtRaw'])) === date('Y-m'))),
];

$ebTotal = count($filteredExpiredBatches);
$ebPerPage = 10;
$ebTotalPages = max(1, (int) ceil($ebTotal / $ebPerPage));
$ebCurrentPage = min(max(1, (int) ($_GET['page'] ?? 1)), $ebTotalPages);
$expiredBatches = array_slice($filteredExpiredBatches, ($ebCurrentPage - 1) * $ebPerPage, $ebPerPage);

if (!function_exists('eb_page_url')) {
    function eb_page_url(int $page): string
    {
        $qs = $_GET;
        $qs['page'] = $page;
        return 'expired_batches.php?' . http_build_query($qs);
    }
}
