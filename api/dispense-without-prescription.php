<?php
/**
 * =====================================================================
 * Dispense Without Prescription (api/dispense-without-prescription.php)
 * ---------------------------------------------------------------------
 * Called by pharmacist/medicine_vaccine_inventory.php's "Dispense (No
 * Prescription)" row action (assets/js/medicine_vaccine_inventory_pharmacist.js)
 * — this health center gives out medicines/vaccines directly when a
 * patient simply asks the pharmacist for one, with no doctor's order
 * behind it. Unlike api/dispense-pharmacy-order.php (which fulfills an
 * EXISTING pharmacy_orders doc a doctor already created), this creates
 * a brand-new one, already "dispensed", so it's still real and visible
 * everywhere a normal dispense is: Prescription Queue's Dispensed tab,
 * this patient's own Medications tab (medicine rows only — see below),
 * and Predictive Analytics' order-frequency signal
 * (includes/shared/predictive_analytics_core.php's hc_medicine_vaccine_shortage()
 * already reads BOTH pharmacy_orders and medications, so a walk-in
 * dispense counts toward real usage exactly like a prescribed one).
 *
 * Every check is real and server-side (never trusted from the
 * modal's own client-side math): a real patient, a real inventory
 * item, and a quantity that doesn't exceed real current stock — same
 * negative-stock guard api/adjust-medicine-stock.php and
 * api/dispense-pharmacy-order.php already use.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../includes/firestore/medicines.php'; // hc_inventory_status_for_stock()
require_once __DIR__ . '/../includes/shared/audit_log_writer.php'; // write_audit_log()
require_once __DIR__ . '/../includes/firestore/patient_history.php'; // hc_record_patient_history(), hc_record_patient_activity()

header('Content-Type: application/json');

if (($_SESSION['role'] ?? null) !== 'pharmacist') {
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
$itemId = trim((string) ($body['itemId'] ?? ''));
$itemType = trim((string) ($body['itemType'] ?? ''));
$patientId = trim((string) ($body['patientId'] ?? ''));
$quantity = $body['quantity'] ?? null;
$notes = trim((string) ($body['notes'] ?? ''));

if ($itemId === '' || $patientId === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Please select both an item and a patient.']);
    exit;
}
if (!in_array($itemType, ['medicine', 'vaccine'], true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Invalid item type.']);
    exit;
}
if (!is_numeric($quantity) || (int) $quantity < 1) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Please enter a valid quantity.']);
    exit;
}
$quantity = (int) $quantity;

$medicineDoc = $firestore->getDocument('medicines', $itemId);
if ($medicineDoc === null) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'This item no longer exists.']);
    exit;
}
if (($medicineDoc['type'] ?? 'medicine') !== $itemType) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'This item is not a ' . $itemType . '.']);
    exit;
}
// An expired medicine/vaccine can never be dispensed — re-checked here
// server-side (never trusted from the modal's own item list already
// having excluded it), same hc_inventory_expiry_status() every other
// real expiry check in this app already uses (the Inventory table's
// own "Expired" badge, hc_get_medicine_expiry_notifications()).
if (hc_inventory_expiry_status($medicineDoc['expiryDate'] ?? '') === 'expired') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => ($medicineDoc['name'] ?? 'This item') . ' has expired and can no longer be dispensed.']);
    exit;
}

$patientDoc = $firestore->getDocument('patients', $patientId);
if ($patientDoc === null) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'This patient no longer exists.']);
    exit;
}

$oldStock = (int) ($medicineDoc['currentStock'] ?? 0);
$newStock = $oldStock - $quantity;
if ($newStock < 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Only ' . $oldStock . ' ' . ($medicineDoc['unit'] ?? 'unit') . '(s) in stock — cannot dispense ' . $quantity . '.']);
    exit;
}

$itemName = $medicineDoc['name'] ?? '(unnamed)';
$pharmacistName = $_SESSION['name'] ?? 'Not specified';
$today = date('Y-m-d');
$now = date('g:i A');

// medicines docs have no "facility" field of their own (inventory is
// system-wide in this app's schema — see includes/admin/facility_analytics_firestore.php's
// header comment) — this dispensing pharmacist's own real facility is
// used instead, same resolution api/complete-consultation.php already
// uses for $doctorFacility.
$myUid = $_SESSION['uid'] ?? '';
$myDoc = $myUid !== '' ? $firestore->getDocument('users', $myUid) : null;
$pharmacistFacility = $myDoc['facility'] ?? 'North Super Health Center';

// Real, already-dispensed pharmacy_orders doc — no sourceConsultationId
// (nothing prescribed this), 'source' distinguishes it from a real
// doctor-ordered one on Prescription Queue's Dispensed tab.
$orderId = $firestore->addDocument('pharmacy_orders', [
    'patientId' => $patientId,
    'patientName' => $patientDoc['name'] ?? 'Unknown Patient',
    'itemName' => $itemName,
    'itemType' => $itemType,
    'dosage' => 'As dispensed',
    'frequency' => 'As needed',
    'duration' => '—',
    'route' => $itemType === 'vaccine' ? 'Injection' : 'Oral',
    'instructions' => $notes !== '' ? $notes : null,
    'prescribedBy' => null, // genuinely no prescriber — see 'source' below
    'facility' => $pharmacistFacility,
    'orderedDate' => $today,
    'orderedTime' => $now,
    'status' => 'dispensed',
    'sourceConsultationId' => null,
    'source' => 'walk_in', // vs. 'prescribed' (the default/legacy value every existing doctor-ordered doc implicitly has)
    'dispensedDate' => $today,
    'dispensedTime' => $now,
    'dispensedBy' => $pharmacistName,
    'dispensedQuantity' => $quantity,
    'dispensedItemName' => $itemName,
    'dispensedInventoryId' => $itemId,
    'notes' => $notes !== '' ? $notes : 'Dispensed directly by pharmacist upon patient request — no prescription.',
]);

if ($orderId === null) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not save. Please try again.']);
    exit;
}

// The actual inventory decrement — same status-recompute pattern as
// api/adjust-medicine-stock.php / api/dispense-pharmacy-order.php.
$status = hc_inventory_status_for_stock($newStock, (int) ($medicineDoc['minLevel'] ?? 0));
$firestore->setDocument('medicines', $itemId, array_merge($medicineDoc, [
    'currentStock' => $newStock,
    'status' => $status,
    'lastUpdated' => date('c'),
]));

// Medicine rows (not vaccines — no vaccination_records write path
// exists anywhere in this app yet, see includes/firestore/vaccination_records.php's
// own header comment) also become a real medications/{id} doc, the
// same shape api/create-patient.php and api/complete-consultation.php
// already write, so it shows up on this patient's ongoing Medications
// list too — not just buried inside this one pharmacy_orders doc.
if ($itemType === 'medicine') {
    $firestore->addDocument('medications', [
        'patientId' => $patientId,
        'name' => $itemName,
        'category' => $medicineDoc['category'] ?? 'General',
        'dosage' => 'As dispensed',
        'frequency' => 'As needed',
        'route' => 'Oral',
        'indication' => 'Patient-requested — no prescription on file',
        'startDate' => $today,
        'endDate' => null,
        'endDateNote' => null,
        'prescribedBy' => $pharmacistName . ' (Pharmacist — direct request)',
        'notes' => $notes !== '' ? $notes : 'Dispensed directly by pharmacist upon patient request.',
        'status' => 'active',
    ]);
}

write_audit_log(
    'created',
    'Medicine & Vaccine Inventory',
    'Dispensed ' . $quantity . ' unit(s) of "' . $itemName . '" to ' . ($patientDoc['name'] ?? 'an unknown patient') . ' without a prescription (patient-requested).'
);
hc_record_patient_history($patientId, 'medication', 'added', 'Dispensed ' . $quantity . ' unit(s) of "' . $itemName . '" (no prescription — patient-requested).');
hc_record_patient_activity($patientId, 'Dispensed "' . $itemName . '" (' . $quantity . ' unit(s)).');

echo json_encode(['success' => true]);