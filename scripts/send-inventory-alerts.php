<?php
/**
 * =====================================================================
 * Daily Inventory Alert Job (scripts/send-inventory-alerts.php)
 * ---------------------------------------------------------------------
 * CLI-only — run DAILY by a real Windows Task Scheduler job, same
 * mechanism as scripts/send-2fa-reminders.php / send-followup-reminders.php:
 *
 *   schtasks /Create /TN "HEALTrack Inventory Alerts" /SC DAILY /ST 00:15 ^
 *     /TR "C:\xampp\php\php.exe C:\xampp\htdocs\Healtrack\scripts\send-inventory-alerts.php" /F
 *
 * Needs to run daily, not weekly, since an item can newly cross into
 * low stock or hit its "expiring soon" window on any given day.
 *
 * For every real medicines/{id} doc (medicine or vaccine, not
 * archived), emails every active pharmacist whenever that item newly
 * crosses into low stock, out of stock, expiring soon, or expired — see
 * includes/shared/inventory_alert_email.php's header comment for
 * exactly how repeat sends are prevented and how a resolved item resets
 * so a later recurrence alerts again.
 *
 * Pharmacist in-app notifications for the same conditions do NOT need
 * this script at all — hc_get_low_stock_notifications()/
 * hc_get_medicine_expiry_notifications() already compute those live, on
 * every page load, straight from the same real stock/expiry fields.
 *
 * Usage: C:\xampp\php\php.exe scripts\send-inventory-alerts.php
 * =====================================================================
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script is CLI-only.');
}

require_once __DIR__ . '/../config/firebase.php'; // $firestore
require_once __DIR__ . '/../includes/shared/inventory_alert_email.php'; // hc_process_inventory_alert_for_item()

$stockSent = 0;
$expirySent = 0;
$skipped = 0;
$startedAt = date('c');

foreach ($firestore->listDocuments('medicines') as $doc) {
    $f = $doc['fields'];
    if ($f['archived'] ?? false) {
        $skipped++;
        continue;
    }

    $result = hc_process_inventory_alert_for_item($doc['id'], $f);
    if ($result['stockSent']) {
        $stockSent++;
        echo "[{$startedAt}] Sent stock alert for " . ($f['name'] ?? $doc['id']) . "\n";
    }
    if ($result['expirySent']) {
        $expirySent++;
        echo "[{$startedAt}] Sent expiry alert for " . ($f['name'] ?? $doc['id']) . "\n";
    }
    if (!$result['stockSent'] && !$result['expirySent']) {
        $skipped++;
    }
}

echo "[{$startedAt}] Done. Stock alerts sent: {$stockSent}, expiry alerts sent: {$expirySent}, skipped: {$skipped}\n";
