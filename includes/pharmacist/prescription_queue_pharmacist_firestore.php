<?php
/**
 * =====================================================================
 * Prescription Queue (Pharmacist) — Data
 * ---------------------------------------------------------------------
 * The pharmacist's real dispensing queue — every real pharmacy_orders/
 * {id} doc across every patient (includes/firestore/pharmacy_orders.php),
 * created when a doctor completes a consultation routed to "Pharmacy"
 * with at least one medicine/vaccine row (api/complete-consultation.php's
 * "pharmacy" routing block) — the pharmacist-side equivalent of
 * medical_technician/lab_xray_consultation.php's referral inbox.
 *
 * REAL for everything the schema actually models: patient, item name/
 * type, dosage/frequency/duration/route/instructions, prescribed by,
 * facility, date/time, and status ("pending"/"dispensed" — this
 * collection has no other status value, so those are the only two
 * filter options offered, same principle as every other queue page in
 * this app that trims filter options to values that actually occur).
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore
require_once __DIR__ . '/../firestore/patients.php';
require_once __DIR__ . '/../firestore/pharmacy_orders.php';
require_once __DIR__ . '/../firestore/medicines.php'; // hc_get_inventory_items() — Dispense now decrements real inventory (api/dispense-pharmacy-order.php)
require_once __DIR__ . '/../shared/inventory_match.php'; // hc_item_names_match() — best-guess inventory item per order below

$myUid = $_SESSION['uid'] ?? '';
$myDoc = $myUid !== '' ? $firestore->getDocument('users', $myUid) : null;

$staffProfile = [
    'name'     => $_SESSION['name'] ?? 'Pharmacist',
    'role'     => 'Pharmacist',
    'facility' => $myDoc['facility'] ?? 'North Super Health Center',
];

$dashboardDateLabel = date('F j, Y');
$facilityOptions = ['North Super Health Center', 'East Super Health Center', 'West Super Health Center'];

// ---------------------------------------------------------------------
// Build the queue — real pharmacy_orders docs across every patient.
// ---------------------------------------------------------------------
$statusMeta = [
    'pending'   => ['label' => 'Pending',   'bg' => '#FDECDD', 'color' => '#E8710A'],
    'dispensed' => ['label' => 'Dispensed', 'bg' => '#E4F7EA', 'color' => '#1E8E3E'],
];

// Real current medicine/vaccine inventory — the Dispense modal's item
// dropdown always builds from this live list (assets/js/prescription-queue.js
// via window.PRESCRIPTION_QUEUE_DATA below), never a stale snapshot. An
// expired item is excluded entirely — it can never be dispensed
// (api/dispense-pharmacy-order.php re-checks this server-side too), so
// it shouldn't even be offered as a choice, and the fuzzy-suggested
// match (data-suggested-item-id) simply won't pre-select one that's
// not in this list, falling back to "Not in inventory" instead.
$notExpired = fn ($i) => $i['expiryStatus'] !== 'expired';
// array_values() re-indexes after array_filter — without it, a
// filtered-out middle item leaves a gap in the keys and json_encode()
// below would serialize this as a JS object instead of an array,
// breaking window.PRESCRIPTION_QUEUE_DATA.medicines.forEach().
$medicineInventoryOptions = array_map(fn ($m) => ['id' => $m['id'], 'name' => $m['name'], 'unit' => $m['unit'], 'currentStock' => $m['currentStock']], array_values(array_filter(hc_get_inventory_items('medicine'), $notExpired)));
$vaccineInventoryOptions = array_map(fn ($v) => ['id' => $v['id'], 'name' => $v['name'], 'unit' => $v['unit'], 'currentStock' => $v['currentStock']], array_values(array_filter(hc_get_inventory_items('vaccine'), $notExpired)));

$allOrders = [];
foreach (hc_get_all_pharmacy_order_docs() as $doc) {
    $f = $doc['fields'];
    $isVaccine = ($f['itemType'] ?? '') === 'vaccine';
    $itemName = $f['itemName'] ?? '—';

    // Best-guess real inventory match (fuzzy — see hc_item_names_match()'s
    // own comment) so the Dispense modal can pre-select it; '' when
    // nothing plausibly matches (the pharmacist picks manually, or
    // dispenses without touching inventory — this order's item may
    // genuinely not be a tracked inventory item).
    $suggestedInventoryId = '';
    foreach (($isVaccine ? $vaccineInventoryOptions : $medicineInventoryOptions) as $opt) {
        if (hc_item_names_match($opt['name'], $itemName)) {
            $suggestedInventoryId = $opt['id'];
            break;
        }
    }

    // 'source' distinguishes a real doctor-ordered doc (api/complete-consultation.php,
    // the default/legacy every existing doc implicitly has) from a
    // walk-in one this patient asked the pharmacist for directly
    // (api/dispense-without-prescription.php) — see that file's header
    // comment. Walk-ins have no real prescriber, so the table shows
    // that honestly instead of a bare "—".
    $isWalkIn = ($f['source'] ?? 'prescribed') === 'walk_in';

    $allOrders[] = [
        'id' => $doc['id'],
        'type' => $f['itemType'] ?? 'medication',
        'typeLabel' => $isVaccine ? 'Vaccine' : 'Medicine',
        'isWalkIn' => $isWalkIn,
        'patientId' => $f['patientId'] ?? '',
        'patientName' => $f['patientName'] ?? 'Unknown Patient',
        'itemName' => $itemName,
        'dosage' => $f['dosage'] ?? '—',
        'frequency' => $f['frequency'] ?? '—',
        'duration' => $f['duration'] ?? '—',
        'route' => $f['route'] ?? '—',
        'instructions' => $f['instructions'] ?? null,
        'prescribedBy' => $isWalkIn ? 'Self-requested (no prescription)' : ($f['prescribedBy'] ?? '—'),
        'facility' => $f['facility'] ?? '—',
        'orderedDateRaw' => $f['orderedDate'] ?? '',
        'date' => isset($f['orderedDate']) && $f['orderedDate'] !== '' ? date('M j, Y', strtotime($f['orderedDate'])) : '—',
        'time' => $f['orderedTime'] ?? '—',
        'status' => $f['status'] ?? 'pending',
        'suggestedInventoryId' => $suggestedInventoryId,
        // Only meaningful once dispensed — the "View Details" modal
        // shows these for the Dispensed tab's rows (who/when), blank
        // (still-pending) rows just don't render this section.
        'dispensedDate' => ($f['dispensedDate'] ?? '') !== '' ? date('M j, Y', strtotime($f['dispensedDate'])) : '',
        'dispensedTime' => $f['dispensedTime'] ?? '',
        'dispensedBy' => $f['dispensedBy'] ?? '',
        'dispensedQuantity' => $f['dispensedQuantity'] ?? null,
        'dispensedItemName' => $f['dispensedItemName'] ?? null, // real inventory item name actually decremented, if any
    ];
}

usort($allOrders, fn ($a, $b) => strcmp($b['orderedDateRaw'] . $b['time'], $a['orderedDateRaw'] . $a['time']));

// ---------------------------------------------------------------------
// Summary cards — real counts over $allOrders.
// ---------------------------------------------------------------------
$ordersSummary = [
    'pending' => count(array_filter($allOrders, fn ($r) => $r['status'] === 'pending')),
    'medicines' => count(array_filter($allOrders, fn ($r) => $r['status'] === 'pending' && $r['type'] !== 'vaccine')),
    'vaccines' => count(array_filter($allOrders, fn ($r) => $r['status'] === 'pending' && $r['type'] === 'vaccine')),
];

// ---------------------------------------------------------------------
// Tab (?tab=pending|dispensed, defaults to pending — the actionable
// view) + search (?q=) + type (?type=all|medication|vaccine) + date
// range (?from=, ?to=) filters — all real over $allOrders.
// ---------------------------------------------------------------------
$activeTab = $_GET['tab'] ?? 'pending';
if (!in_array($activeTab, ['pending', 'dispensed'], true)) {
    $activeTab = 'pending';
}

$typeFilter = $_GET['type'] ?? 'all';
if (!in_array($typeFilter, ['all', 'medication', 'vaccine'], true)) {
    $typeFilter = 'all';
}

$searchTerm = trim($_GET['q'] ?? '');
$dateFrom = $_GET['from'] ?? '';
$dateTo = $_GET['to'] ?? '';

$filteredOrders = array_values(array_filter($allOrders, function ($r) use ($activeTab, $typeFilter, $searchTerm, $dateFrom, $dateTo) {
    if ($r['status'] !== $activeTab) {
        return false;
    }
    if ($typeFilter !== 'all' && $r['type'] !== $typeFilter) {
        return false;
    }
    if ($searchTerm !== '' && stripos($r['patientName'] . ' ' . $r['patientId'] . ' ' . $r['itemName'], $searchTerm) === false) {
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
$ordersTotal = count($filteredOrders);
$ordersPerPage = 8;
$ordersCurrentPage = max(1, (int) ($_GET['page'] ?? 1));
$ordersTotalPages = max(1, (int) ceil($ordersTotal / $ordersPerPage));
$ordersCurrentPage = min($ordersCurrentPage, $ordersTotalPages);

$orders = array_slice($filteredOrders, ($ordersCurrentPage - 1) * $ordersPerPage, $ordersPerPage);

$ordersPaginationWindow = [];
if ($ordersTotalPages <= 5) {
    for ($p = 1; $p <= $ordersTotalPages; $p++) {
        $ordersPaginationWindow[] = $p;
    }
} else {
    $ordersPaginationWindow = [1, 2, 3, '...', $ordersTotalPages];
}
