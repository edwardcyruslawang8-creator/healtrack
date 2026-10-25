<?php
/**
 * =====================================================================
 * Get Unread Notification Count (api/get-unread-count.php)
 * ---------------------------------------------------------------------
 * Polled every few seconds by assets/js/notification-poll.js (injected
 * on every signed-in page by includes/shared/notification_poll.php's
 * hc_render_notification_poll_script()) so the topbar bell + sidebar
 * "Notifications" badges — and a "you have a new notification" toast —
 * stay live without the page ever needing a reload, no matter which
 * page is actually open. A plain GET, not POST: this only reads real,
 * already-computed state (the same hc_*_unread_notification_count()
 * functions every page's own server-rendered badge already calls), it
 * never changes anything, so there's nothing here that needs CSRF
 * protection or a request body.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php'; // $firestore — every *_unread_notification_count() function below reads real Firestore data
require_once __DIR__ . '/../config/constants.php'; // hc_unread_notification_count_for_role()

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

$uid = $_SESSION['uid'] ?? '';
if ($uid === '') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not signed in.']);
    exit;
}

echo json_encode([
    'success' => true,
    'unreadCount' => hc_unread_notification_count_for_role($_SESSION['role'] ?? null),
]);
