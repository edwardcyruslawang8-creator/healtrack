<?php
/**
 * =====================================================================
 * Shared — Follow-up Visit Email Reminder
 * ---------------------------------------------------------------------
 * The patient-facing half of the follow-up reminder feature — the
 * doctor/healthcare-staff in-app half needs no code here at all:
 * includes/firestore/consultations.php's hc_get_followup_notifications()
 * already computes "due today"/"due soon"/"overdue" live, on every
 * page load, straight from the same nextFollowUp field this reads.
 *
 * Patients have no login/portal in this app (confirmed — see
 * login.php's own header comment), so email is the only real channel
 * for notifying THEM specifically. Two one-time emails per consultation:
 *   - "upcoming" — once nextFollowUp is within HC_FOLLOWUP_REMINDER_DAYS_BEFORE
 *     days out (inclusive of that whole window, not just the exact day,
 *     so a missed cron run still catches it before the date passes).
 *   - "today" — once nextFollowUp's date IS today.
 * Each tier is sent at most once per consultation, tracked the same way
 * two_fa_reminder.php tracks its own "last sent" state — a real field
 * on the consultation doc itself (followUpRemindersSent, an array of
 * which tiers have already gone out), reset by
 * api/complete-consultation.php whenever the date itself actually
 * changes. Run by scripts/send-followup-reminders.php (Windows Task
 * Scheduler, daily — see that file's own header comment).
 * =====================================================================
 */

require_once __DIR__ . '/mailer.php'; // hc_send_email(), hc_account_email_shell()
require_once __DIR__ . '/../firestore/patients.php'; // hc_get_patient_profile()

const HC_FOLLOWUP_REMINDER_DAYS_BEFORE = 3;

if (!function_exists('hc_followup_reminder_tier_due')) {
    /**
     * Which reminder tier (if any) is due right now for this
     * consultations/{id} doc — 'today', 'upcoming', or null (nothing to
     * send: no real follow-up date, date already in the past, or the
     * relevant tier already went out). $c is the raw Firestore fields
     * array (listDocuments()'s shape), not the mapped
     * hc_map_consultation_doc() one — this is called from the CLI
     * script's own listDocuments() loop, same convention
     * hc_2fa_reminder_email_due() uses.
     */
    function hc_followup_reminder_tier_due(array $c): ?string
    {
        $nextFollowUp = trim((string) ($c['nextFollowUp'] ?? ''));
        if ($nextFollowUp === '') {
            return null;
        }

        $due = strtotime($nextFollowUp);
        if ($due === false) {
            return null;
        }

        $dueDateOnly = strtotime(date('Y-m-d', $due));
        $todayOnly = strtotime(date('Y-m-d'));
        $daysUntil = (int) round(($dueDateOnly - $todayOnly) / 86400);

        $alreadySent = is_array($c['followUpRemindersSent'] ?? null) ? $c['followUpRemindersSent'] : [];

        if ($daysUntil === 0) {
            return in_array('today', $alreadySent, true) ? null : 'today';
        }
        if ($daysUntil > 0 && $daysUntil <= HC_FOLLOWUP_REMINDER_DAYS_BEFORE) {
            return in_array('upcoming', $alreadySent, true) ? null : 'upcoming';
        }

        return null;
    }
}

if (!function_exists('hc_send_followup_reminder_email')) {
    /**
     * Sends the real reminder email to the patient and records the
     * tier as sent (followUpRemindersSent) so
     * hc_followup_reminder_tier_due() won't return it again for the
     * same date. Does nothing (no write at all) when the patient has
     * no real email on file — that's a normal, expected state (email
     * is optional at patient registration, api/create-patient.php),
     * not an error, and leaving followUpRemindersSent untouched means a
     * later-added email address still gets picked up on the next run
     * while the window is still open.
     */
    function hc_send_followup_reminder_email(string $consultationId, array $c, string $tier): void
    {
        global $firestore;

        $patientId = (string) ($c['patientId'] ?? '');
        $patientName = (string) ($c['patientName'] ?? 'there');
        $patient = $patientId !== '' ? hc_get_patient_profile($patientId) : null;
        $email = $patient['email'] ?? '';
        if ($patient === null || $email === '' || $email === '—') {
            return;
        }

        $due = strtotime((string) ($c['nextFollowUp'] ?? ''));
        $dueLabel = $due !== false ? date('F j, Y', $due) : 'your scheduled date';

        if ($tier === 'today') {
            $subject = 'Reminder: your follow-up visit is today';
            $heading = 'Your follow-up visit is today';
            $bodyHtml = '<p style="margin:0 0 12px; font-size:14px; line-height:1.6;">Hi ' . htmlspecialchars($patientName) . ', this is a reminder that your follow-up visit at HEALTrack is scheduled for <strong>today, ' . htmlspecialchars($dueLabel) . '</strong>.</p>'
                . '<p style="margin:0; font-size:14px; line-height:1.6;">Please come in at your earliest convenience. If you\'re unable to make it today, please contact the facility to reschedule.</p>';
        } else {
            $subject = 'Reminder: upcoming follow-up visit on ' . $dueLabel;
            $heading = 'You have an upcoming follow-up visit';
            $bodyHtml = '<p style="margin:0 0 12px; font-size:14px; line-height:1.6;">Hi ' . htmlspecialchars($patientName) . ', this is a reminder that you have a follow-up visit scheduled at HEALTrack on <strong>' . htmlspecialchars($dueLabel) . '</strong>.</p>'
                . '<p style="margin:0; font-size:14px; line-height:1.6;">Please make sure to come in on that date. If you need to reschedule, please contact the facility beforehand.</p>';
        }

        hc_send_email($email, $patientName, $subject, hc_account_email_shell($heading, $bodyHtml));

        $alreadySent = is_array($c['followUpRemindersSent'] ?? null) ? $c['followUpRemindersSent'] : [];
        $alreadySent[] = $tier;
        $firestore->setDocument('consultations', $consultationId, array_merge($c, [
            'followUpRemindersSent' => array_values(array_unique($alreadySent)),
        ]));
    }
}
