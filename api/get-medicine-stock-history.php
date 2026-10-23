<?php
/**
 * =====================================================================
 * Medicine / Vaccine Stock History (api/get-medicine-stock-history.php)
 * ---------------------------------------------------------------------
 * Called by assets/js/medicine_vaccine_inventory_pharmacist.js when a
 * pharmacist or sub-admin clicks "View Stock History" from a row's
 * three-dot menu. There is no dedicated stock-history collection — this
 * reuses the real "audit_logs" collection every mutating
 * api/*-medicine.php endpoint already writes to
 * (includes/shared/audit_log_writer.php), filtered to
 * module === 'Medicine & Vaccine Inventory' and this item's name
 * appearing quoted in "details" (every one of those endpoints writes
 * the item's name as "$name", e.g. 'Updated medicine "Amoxicillin..."'
 * — see api/create-medicine.php / update-medicine.php / delete-medicine.php
 * / adjust-medicine-stock.php / archive-medicine.php, all of which quote
 * it the same way). A real, if imperfect, history: it's matched by the
 * item's CURRENT name, so an entry logged before a rename (api/update-medicine.php
 * changing "name") won't show — building a true per-item history would
 * need a dedicated collection with a real itemId, which doesn't exist
 * yet.
 *
 * Also returns this item's real, currently-held batches
 * (includes/firestore/medicine_batches.php's hc_get_batches_for_medicine(),
 * soonest-expiry first) when a real ?id= is given — the Stock History
 * modal shows these above the activity log so "what's actually on the
 * shelf and when does each part expire" is answered directly instead
 * of needing to piece it together from the log entries below it.
 *
 * Query params: ?name=<item name>&id=<item id, optional>. GET, no body.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../includes/firestore/medicine_batches.php'; // hc_get_batches_for_medicine()

header('Content-Type: application/json');

if (!in_array($_SESSION['role'] ?? null, ['pharmacist', 'subadmin'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Not authorized.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

$name = trim((string) ($_GET['name'] ?? ''));
$id = trim((string) ($_GET['id'] ?? ''));

if ($name === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Missing item name.']);
    exit;
}

$batches = $id !== '' ? hc_get_batches_for_medicine($id) : [];

$needle = '"' . $name . '"';
$history = [];

foreach ($firestore->listDocuments('audit_logs') as $doc) {
    $f = $doc['fields'];
    if (($f['module'] ?? '') !== 'Medicine & Vaccine Inventory') {
        continue;
    }
    $details = (string) ($f['details'] ?? '');
    if (strpos($details, $needle) === false) {
        continue;
    }
    $history[] = [
        'timestamp' => (string) ($f['timestamp'] ?? ''),
        'actorName' => (string) ($f['actorName'] ?? 'System'),
        'action' => (string) ($f['action'] ?? 'updated'),
        'details' => $details,
    ];
}

usort($history, fn ($a, $b) => strcmp($b['timestamp'], $a['timestamp']));

echo json_encode(['success' => true, 'history' => $history, 'batches' => $batches]);
