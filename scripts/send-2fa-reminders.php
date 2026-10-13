<?php
/**
 * =====================================================================
 * Weekly 2FA Reminder Job (scripts/send-2fa-reminders.php)
 * ---------------------------------------------------------------------
 * CLI-only — run by a real recurring Windows Task Scheduler job (set up
 * once, outside this codebase, via schtasks; see the project's own
 * setup notes for the exact command used), NOT reachable over HTTP.
 * Scans every real users/{uid} doc and, for each one
 * includes/shared/two_fa_reminder.php's hc_2fa_reminder_email_due()
 * says is due (2FA still off, and 7+ real days since account creation
 * or the last reminder actually sent), sends the real reminder email
 * and records the new "last sent" timestamp — so an account that keeps
 * 2FA off keeps getting nudged roughly once a week indefinitely, not
 * just once right after it's created.
 *
 * Usage: C:\xampp\php\php.exe scripts\send-2fa-reminders.php
 * =====================================================================
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script is CLI-only.');
}

require_once __DIR__ . '/../config/firebase.php'; // $firestore
require_once __DIR__ . '/../includes/shared/two_fa_reminder.php'; // hc_2fa_reminder_email_due(), hc_send_2fa_reminder_email()

$sent = 0;
$skipped = 0;
$startedAt = date('c');

foreach ($firestore->listDocuments('users') as $doc) {
    $userDoc = $doc['fields'];
    if (!hc_2fa_reminder_email_due($userDoc)) {
        $skipped++;
        continue;
    }

    hc_send_2fa_reminder_email($doc['id'], $userDoc);
    $sent++;
    echo "[{$startedAt}] Sent 2FA reminder to " . ($userDoc['email'] ?? $doc['id']) . "\n";
}

echo "[{$startedAt}] Done. Sent: {$sent}, skipped (already has 2FA or not due yet): {$skipped}\n";
