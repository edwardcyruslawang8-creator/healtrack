<?php
/**
 * =====================================================================
 * Update Medicine / Vaccine (api/update-medicine.php)
 * ---------------------------------------------------------------------
 * Called by assets/js/medicine_vaccine_inventory_pharmacist.js when a
 * sub-admin submits the "Add Medicine"/"Add Vaccine" modal
 * (includes/pharmacist/add_medicine_modal.php, reused as-is by
 * subadmin/medicine_vaccine_inventory.php) in EDIT mode — the same
 * shared form api/create-medicine.php's modal uses, just pre-filled
 * and posting here instead when an existing item's Edit button opened
 * it. Writes to the same real "medicines" Firestore collection
 * includes/firestore/medicines.php reads.
 *
 * Subadmin-only — a pharmacist can still see every field (their own
 * "Manage Medicine"/"Manage Vaccine" modal, includes/pharmacist/medicine_action_modals.php)
 * but can't edit name/category/unit/expiry date/current stock/minimum
 * level/reorder level; only a sub-admin can change this master
 * inventory data. api/create-medicine.php (adding a genuinely NEW
 * item) stays pharmacist-allowed — this restriction is specifically
 * about editing an EXISTING item's own data, not about who can
 * register new stock lines.
 *
 * Reads the existing doc first and merges the edited fields into it
 * (same reasoning as api/update-subadmin.php's header comment) rather
 * than overwriting the whole doc — FirestoreRestClient::setDocument()
 * PATCHes with no updateMask, so passing only the new fields would
 * silently drop createdBy/icon/color/type, none of which this form
 * edits.
 *
 * Current Stock and Expiry Date are NOT editable here any more — both
 * are now a maintained rollup over this item's own real
 * medicine_batches docs (includes/firestore/medicine_batches.php),
 * recomputed from the soonest-expiring batch that still has stock, so
 * a single free-form edit here could no longer honestly represent
 * either once more than one batch with a different expiry exists. Real
 * stock changes go through Adjust Stock (api/adjust-medicine-stock.php,
 * itself now batch-aware) or a stock request release
 * (api/update-stock-request-status.php) instead.
 *
 * The stored "form" field is set equal to $unit rather than collected
 * as its own form input — see
 * includes/pharmacist/add_medicine_modal.php's header comment for why
 * that field was removed from the Add/Edit form (same as
 * api/create-medicine.php's own copy of this).
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../includes/firestore/medicines.php';
require_once __DIR__ . '/../includes/shared/audit_log_writer.php'; // write_audit_log()

header('Content-Type: application/json');

if (($_SESSION['role'] ?? null) !== 'subadmin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Not authorized.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];

$id = trim((string) ($body['id'] ?? ''));
$name = trim((string) ($body['name'] ?? ''));
$category = trim((string) ($body['category'] ?? ''));
$unit = trim((string) ($body['unit'] ?? ''));
$desc = trim((string) ($body['desc'] ?? ''));
$minLevel = $body['minLevel'] ?? null;
$reorderLevel = $body['reorderLevel'] ?? null;
// Optional — blank/omitted means "no ceiling set" (0), same convention
// as api/create-medicine.php's own copy of this.
$maxLevel = $body['maxLevel'] ?? '';
$maxLevel = ($maxLevel === '' || $maxLevel === null) ? 0 : $maxLevel;

if ($id === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Missing item id.']);
    exit;
}
if ($name === '' || $category === '' || $unit === ''
    || !is_numeric($minLevel) || !is_numeric($reorderLevel) || !is_numeric($maxLevel)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Please fill out every required field.']);
    exit;
}
if ((float) $minLevel < 0 || (float) $reorderLevel < 0 || (float) $maxLevel < 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Stock levels cannot be negative.']);
    exit;
}

$existing = $firestore->getDocument('medicines', $id);
if ($existing === null) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'This item no longer exists.']);
    exit;
}

$minLevel = (int) $minLevel;
$reorderLevel = (int) $reorderLevel;
$maxLevel = (int) $maxLevel;
// currentStock is untouched here (real rollup over this item's own
// batches) — only "status" needs recomputing, since a changed
// minLevel/maxLevel alone can flip Low/In Stock/Overstocked against
// the SAME real stock number.
$currentStock = (int) ($existing['currentStock'] ?? 0);
$status = hc_inventory_status_for_stock($currentStock, $minLevel, $maxLevel);

$updated = array_merge($existing, [
    'name' => $name,
    'category' => $category,
    'unit' => $unit,
    'form' => $unit, // see this file's header comment
    'desc' => $desc !== '' ? $desc : null,
    'minLevel' => $minLevel,
    'reorderLevel' => $reorderLevel,
    'maxLevel' => $maxLevel,
    'status' => $status,
    'lastUpdated' => date('c'),
]);

$saved = $firestore->setDocument('medicines', $id, $updated);

if (!$saved) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not save your changes. Please try again.']);
    exit;
}

write_audit_log('updated', 'Medicine & Vaccine Inventory', 'Updated ' . ($existing['type'] ?? 'item') . ' "' . $name . '" (' . $currentStock . ' ' . $unit . ' in stock).');

echo json_encode(['success' => true, 'item' => ['id' => $id, 'name' => $name, 'status' => $status]]);
