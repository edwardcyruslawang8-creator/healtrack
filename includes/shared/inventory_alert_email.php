<?php
/**
 * =====================================================================
 * Shared — Inventory Alert Email (low stock / expiring / expired)
 * ---------------------------------------------------------------------
 * Real email for pharmacists, run DAILY by a scheduled job
 * (scripts/send-inventory-alerts.php) — same CLI-only mechanism as
 * scripts/send-2fa-reminders.php / send-followup-reminders.php.
 * Medicines/vaccines are one shared, system-wide inventory (no facility
 * field — see includes/admin/medicine_vaccine_inventory_firestore.php's
 * own header comment), so every active pharmacist account gets the
 * same alerts, regardless of which facility they're attached to.
 *
 * Repeat sends are prevented the same bookkeeping way
 * two_fa_reminder.php's twoFaReminderLastSentAt does, via two real
 * fields written directly on the medicines/{id} doc itself:
 *   - stockAlertSentFor: the stock status ('low' | 'unavailable') the
 *     last alert was actually sent for. Reset to null the moment stock
 *     recovers (back to 'available' or 'overstocked'), so a later dip
 *     alerts again instead of staying silently suppressed forever.
 *   - expiryAlertSentFor / expiryAlertSentForDate: the expiry tier
 *     ('soon' | 'expired') and the exact expiry date an alert was last
 *     sent for. Keeping the date alongside the tier means a brand new
 *     batch with a fresh expiry date is treated as new even if the
 *     previous batch's expiry had already been alerted on.
 * =====================================================================
 */

require_once __DIR__ . '/mailer.php'; // hc_send_email(), hc_account_email_shell()
require_once __DIR__ . '/../../config/firebase.php'; // $firestore
require_once __DIR__ . '/../firestore/medicines.php'; // hc_map_medicine_doc_to_item(), hc_items_apply_usable_stock(), hc_qty_unit()

if (!function_exists('hc_inventory_alert_recipients')) {
    /** Every active pharmacist, system-wide — inventory is global, not per-facility. */
    function hc_inventory_alert_recipients(): array
    {
        global $firestore;

        $recipients = [];
        foreach ($firestore->listDocuments('users') as $doc) {
            $f = $doc['fields'];
            if (($f['role'] ?? '') !== 'pharmacist' || ($f['status'] ?? 'active') !== 'active') {
                continue;
            }
            $email = trim((string) ($f['email'] ?? ''));
            if ($email !== '') {
                $recipients[] = ['email' => $email, 'name' => (string) ($f['name'] ?? 'Pharmacist')];
            }
        }

        return $recipients;
    }
}

if (!function_exists('hc_send_stock_alert_email')) {
    function hc_send_stock_alert_email(array $item): void
    {
        $isOut = $item['status'] === 'unavailable';
        $subject = ($isOut ? 'Out of stock' : 'Low stock alert') . ' — ' . $item['name'];
        $body = hc_account_email_shell(
            $isOut ? 'Item out of stock' : 'Low stock alert',
            '<p style="margin:0 0 12px; font-size:14px; line-height:1.6;"><strong>' . htmlspecialchars($item['name']) . '</strong> is ' . ($isOut ? 'now out of stock' : 'running low') . ' — ' . htmlspecialchars(hc_qty_unit($item['currentStock'], $item['unit'])) . ' remaining (reorder level: ' . htmlspecialchars(hc_qty_unit($item['reorderLevel'], $item['unit'])) . ').</p>'
            . '<p style="margin:0; font-size:14px; line-height:1.6;">Sign in to HEALTrack to review inventory or submit a stock request.</p>'
        );
        foreach (hc_inventory_alert_recipients() as $r) {
            hc_send_email($r['email'], $r['name'], $subject, $body);
        }
    }
}

if (!function_exists('hc_send_expiry_alert_email')) {
    function hc_send_expiry_alert_email(array $item): void
    {
        $isExpired = $item['expiryStatus'] === 'expired';
        $subject = ($isExpired ? 'Expired' : 'Expiring soon') . ' — ' . $item['name'];
        $body = hc_account_email_shell(
            $isExpired ? 'Item expired' : 'Item expiring soon',
            '<p style="margin:0 0 12px; font-size:14px; line-height:1.6;"><strong>' . htmlspecialchars($item['name']) . '</strong> ' . ($isExpired ? 'expired on' : 'expires on') . ' ' . htmlspecialchars($item['expiryDate']) . '.</p>'
            . '<p style="margin:0; font-size:14px; line-height:1.6;">Sign in to HEALTrack to review this item.</p>'
        );
        foreach (hc_inventory_alert_recipients() as $r) {
            hc_send_email($r['email'], $r['name'], $subject, $body);
        }
    }
}

if (!function_exists('hc_process_inventory_alert_for_item')) {
    /**
     * One real medicines/{id} doc -> sends whichever alert(s) are newly
     * due and writes the updated tracking fields back, all in one place
     * so scripts/send-inventory-alerts.php's own loop stays a thin CLI
     * wrapper, same division of labor as
     * hc_followup_reminder_tier_due()/hc_send_followup_reminder_email().
     *
     * @return array{stockSent: bool, expirySent: bool}
     */
    function hc_process_inventory_alert_for_item(string $id, array $rawFields): array
    {
        global $firestore;

        $items = [hc_map_medicine_doc_to_item($id, $rawFields)];
        hc_items_apply_usable_stock($items);
        $item = $items[0];

        $result = ['stockSent' => false, 'expirySent' => false];
        $updates = [];

        $lastStock = $rawFields['stockAlertSentFor'] ?? null;
        if (in_array($item['status'], ['low', 'unavailable'], true)) {
            if ($lastStock !== $item['status']) {
                hc_send_stock_alert_email($item);
                $updates['stockAlertSentFor'] = $item['status'];
                $result['stockSent'] = true;
            }
        } elseif ($lastStock !== null) {
            $updates['stockAlertSentFor'] = null;
        }

        $lastExpiryTier = $rawFields['expiryAlertSentFor'] ?? null;
        $lastExpiryDate = $rawFields['expiryAlertSentForDate'] ?? null;
        if (in_array($item['expiryStatus'], ['soon', 'expired'], true)) {
            if ($lastExpiryTier !== $item['expiryStatus'] || $lastExpiryDate !== $item['expiryDateRaw']) {
                hc_send_expiry_alert_email($item);
                $updates['expiryAlertSentFor'] = $item['expiryStatus'];
                $updates['expiryAlertSentForDate'] = $item['expiryDateRaw'];
                $result['expirySent'] = true;
            }
        } elseif ($lastExpiryTier !== null) {
            $updates['expiryAlertSentFor'] = null;
            $updates['expiryAlertSentForDate'] = null;
        }

        if ($updates !== []) {
            $firestore->setDocument('medicines', $id, array_merge($rawFields, $updates));
        }

        return $result;
    }
}
