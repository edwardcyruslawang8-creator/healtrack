<?php
/**
 * =====================================================================
 * Shared — Outbound SMS
 * ---------------------------------------------------------------------
 * hc_send_sms() is the SMS sibling of includes/shared/mailer.php's
 * hc_send_email() — same shape, same "never fatal, just log and return
 * false" behavior. A real Semaphore integration was built and tested
 * here, then deliberately reverted: the decision was to notify patients
 * by email only for now, not email + SMS. This stays a safe no-op
 * (logs, never sends) so nothing currently calls it, and no code
 * changes are needed if SMS is turned back on later — the real
 * Semaphore HTTP call (api.semaphore.co/api/v4/messages, apikey/
 * number/message/sendername fields) goes back in this function's body.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/sms.php';

if (!function_exists('hc_send_sms')) {
    /**
     * @param string $phone Real patient/user contact number, as stored on their own record (not yet normalized to a specific provider's expected format — do that here once a real provider is wired in).
     * @param string $message Plain-text SMS body (no HTML — real SMS has no markup).
     * @return bool true once a real provider genuinely accepts the message; always false right now.
     */
    function hc_send_sms(string $phone, string $message): bool
    {
        if ($phone === '' || $phone === '—') {
            error_log('[sms] Skipped sending SMS: no real phone number on file.');
            return false;
        }

        if (HC_SMS_API_KEY === '') {
            error_log('[sms] Skipped sending SMS to ' . $phone . ': config/sms.php has no SMS provider API key filled in yet.');
            return false;
        }

        // SMS notifications are off for now (email-only decision) — no
        // provider call happens here even though a key may be present.
        error_log('[sms] SMS notifications are disabled for now (email-only) — SMS to ' . $phone . ' was NOT sent.');

        return false;
    }
}
