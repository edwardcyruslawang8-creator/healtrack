<?php
/**
 * =====================================================================
 * Check Medicine Name Spelling (api/check-medicine-name.php)
 * ---------------------------------------------------------------------
 * Called by includes/pharmacist/add_medicine_modal.php's own script as
 * the pharmacist types/leaves the "Medicine Name" field, and re-checked
 * server-side by api/create-medicine.php/api/update-medicine.php right
 * before saving (a client that never called this, or ignored its
 * result, must still be stopped there) — see
 * includes/shared/medicine_name_reference.php's header comment for what
 * this can and can't catch.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../includes/shared/medicine_name_reference.php';

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
$name = trim((string) ($body['name'] ?? ''));

if ($name === '') {
    echo json_encode(['success' => true, 'status' => 'ok', 'suggestion' => null]);
    exit;
}

$result = hc_medicine_name_spelling_check($name);

echo json_encode(['success' => true, 'status' => $result['status'], 'suggestion' => $result['suggestion']]);
