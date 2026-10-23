<?php
/**
 * =====================================================================
 * Delete Medicine / Vaccine (api/delete-medicine.php)
 * ---------------------------------------------------------------------
 * Called by assets/js/medicine_vaccine_inventory_pharmacist.js when a
 * sub-admin confirms "Delete" from a row's three-dot menu on
 * subadmin/medicine_vaccine_inventory.php. Deletes a real
 * medicines/{id} doc from Firestore — same delete pattern as
 * api/reject-account.php.
 *
 * Subadmin-only — a pharmacist can archive an item
 * (api/archive-medicine.php, reversible) but never permanently delete
 * one; pharmacist/archived_items.php's own row actions reflect this
 * (Unarchive only, no Delete).
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php';
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

$deleted = $firestore->deleteDocument('medicines', $id);

if (!$deleted) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not delete this item. Please try again.']);
    exit;
}

$name = $existing['name'] ?? '(unnamed)';
$type = $existing['type'] ?? 'medicine';
write_audit_log('deleted', 'Medicine & Vaccine Inventory', 'Deleted ' . $type . ' "' . $name . '".');

echo json_encode(['success' => true]);
