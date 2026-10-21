<?php
/**
 * =====================================================================
 * Unarchive Notifications (api/unarchive-notifications.php)
 * ---------------------------------------------------------------------
 * Called by assets/js/notifications.js when a notification's
 * "Unarchive" button is clicked (only ever shown on an already-archived
 * row — see each role's notifications.php). The exact reverse of
 * api/archive-notifications.php: removes the given ids from
 * $_SESSION['ntfDismissedIds'] + the signed-in user's own
 * users/{uid}.notificationsDismissedIds, so the notification goes back
 * to showing under every normal tab instead of only "Archived".
 *
 * Body: {"ids": [1, 2, ...]}.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php'; // $firestore

header('Content-Type: application/json');

if (!in_array($_SESSION['role'] ?? null, ['subadmin', 'medical_technician', 'radio_technologist', 'pharmacist', 'doctor', 'healthcare_staff'], true)) {
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
$archivedIds = $_SESSION['ntfDismissedIds'] ?? [];

if (!empty($body['ids']) && is_array($body['ids'])) {
    $idsToRemove = array_map('intval', $body['ids']);
    $archivedIds = array_values(array_diff($archivedIds, $idsToRemove));
}

$_SESSION['ntfDismissedIds'] = $archivedIds;

$uid = $_SESSION['uid'] ?? '';
if ($uid !== '') {
    $existing = $firestore->getDocument('users', $uid) ?? [];
    $existing['notificationsDismissedIds'] = $archivedIds;
    $firestore->setDocument('users', $uid, $existing);
}

echo json_encode(['success' => true]);
