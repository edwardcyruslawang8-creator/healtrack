<?php
/**
 * =====================================================================
 * Archive Medicine / Vaccine (api/archive-medicine.php)
 * ---------------------------------------------------------------------
 * Called by assets/js/medicine_vaccine_inventory_pharmacist.js when a
 * pharmacist or sub-admin confirms "Archive" from a row's three-dot
 * menu on pharmacist/medicine_vaccine_inventory.php or
 * subadmin/medicine_vaccine_inventory.php. Sets a real "archived"
 * boolean on the medicines/{id} doc — a soft removal (discontinued/no
 * longer carried), not api/delete-medicine.php's permanent one. Every
 * read path over the "medicines" collection excludes archived === true
 * from its default list (firebase/firestore.php's getAllMedicines()/
 * getAllVaccines() — so patients never see it either —
 * includes/firestore/medicines.php's hc_get_inventory_items(), and
 * includes/shared/medicine_vaccine_inventory_shared.php), so an
 * archived item just stops appearing anywhere rather than being
 * destroyed.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../includes/shared/audit_log_writer.php'; // write_audit_log()

header('Content-Type: application/json');

if (!in_array($_SESSION['role'] ?? null, ['pharmacist', 'subadmin'], true)) {
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

if ($id === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Missing item id.']);
    exit;
}

$existing = $firestore->getDocument('medicines', $id);
if ($existing === null) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'This item no longer exists.']);
    exit;
}

if ($existing['archived'] ?? false) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'This item is already archived.']);
    exit;
}

$updated = array_merge($existing, [
    'archived' => true,
    'lastUpdated' => date('c'),
]);

$saved = $firestore->setDocument('medicines', $id, $updated);

if (!$saved) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not archive this item. Please try again.']);
    exit;
}

$name = $existing['name'] ?? '(unnamed)';
$type = $existing['type'] ?? 'medicine';
write_audit_log('updated', 'Medicine & Vaccine Inventory', 'Archived ' . $type . ' "' . $name . '".');

echo json_encode(['success' => true]);
