<?php
/**
 * =====================================================================
 * Unarchive Medicine / Vaccine (api/unarchive-medicine.php)
 * ---------------------------------------------------------------------
 * Called by assets/js/archived-items.js when a pharmacist confirms
 * "Unarchive" on pharmacist/archived_items.php — the real reverse of
 * api/archive-medicine.php. Clears the real "archived" boolean on the
 * medicines/{id} doc, so it reappears in every normal inventory read
 * path (includes/firestore/medicines.php's hc_get_inventory_items())
 * exactly as it was before archiving — same stock/category/expiry
 * data, untouched.
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

if (!($existing['archived'] ?? false)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'This item is not archived.']);
    exit;
}

$updated = array_merge($existing, [
    'archived' => false,
    'lastUpdated' => date('c'),
]);

$saved = $firestore->setDocument('medicines', $id, $updated);

if (!$saved) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not unarchive this item. Please try again.']);
    exit;
}

$name = $existing['name'] ?? '(unnamed)';
$type = $existing['type'] ?? 'medicine';
write_audit_log('updated', 'Medicine & Vaccine Inventory', 'Unarchived ' . $type . ' "' . $name . '".');

echo json_encode(['success' => true]);
