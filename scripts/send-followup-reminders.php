<?php
/**
 * =====================================================================
 * Daily Follow-up Reminder Job (scripts/send-followup-reminders.php)
 * ---------------------------------------------------------------------
 * CLI-only — run DAILY by a real Windows Task Scheduler job (same
 * mechanism as scripts/send-2fa-reminders.php — see that file's own
 * header comment for the schtasks setup pattern). Needs to run daily,
 * not weekly, since "due" here is tied to a specific calendar date,
 * not a rolling interval.
 *
 * For every real consultations/{id} doc with a real "nextFollowUp"
 * date set — written either by medical staff's "Set Consultation"
 * modal or by a doctor's "Also needs a follow-up visit" checkbox on
 * Active Consultation, both the same field — emails the patient once
 * when the date is within HC_FOLLOWUP_REMINDER_DAYS_BEFORE days out,
 * and again on the day itself. See
 * includes/shared/followup_reminder.php's hc_followup_reminder_tier_due()
 * for the exact "due" rule and how repeat sends are prevented
 * (followUpRemindersSent on the consultation doc itself, same
 * bookkeeping idiom as users/{uid}'s twoFaReminderLastSentAt).
 *
 * Doctor/healthcare-staff in-app notifications for a follow-up due
 * today do NOT need this script at all — hc_get_followup_notifications()
 * already computes those live, on every page load, straight from the
 * same nextFollowUp field.
 *
 * Usage: C:\xampp\php\php.exe scripts\send-followup-reminders.php
 * =====================================================================
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script is CLI-only.');
}

require_once __DIR__ . '/../config/firebase.php'; // $firestore
require_once __DIR__ . '/../includes/shared/followup_reminder.php'; // hc_followup_reminder_tier_due(), hc_send_followup_reminder_email()

$sent = 0;
$skipped = 0;
$startedAt = date('c');

foreach ($firestore->listDocuments('consultations') as $doc) {
    $c = $doc['fields'];
    $tier = hc_followup_reminder_tier_due($c);
    if ($tier === null) {
        $skipped++;
        continue;
    }

    hc_send_followup_reminder_email($doc['id'], $c, $tier);
    $sent++;
    echo "[{$startedAt}] Sent '{$tier}' follow-up reminder for " . ($c['patientName'] ?? $doc['id']) . " (consultation {$doc['id']})\n";
}

echo "[{$startedAt}] Done. Sent: {$sent}, skipped (no follow-up due today/soon, or already sent): {$skipped}\n";
