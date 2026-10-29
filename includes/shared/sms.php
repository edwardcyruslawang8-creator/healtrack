<?php
/**
 * =====================================================================
 * Shared — Outbound SMS
 * ---------------------------------------------------------------------
 * hc_send_sms() is the SMS sibling of includes/shared/mailer.php's
 * hc_send_email() — same shape, same "never fatal, just log and return
 * false" behavior. No real SMS provider account exists for this
 * project yet (config/sms.php's HC_SMS_API_KEY is blank), so right now
 * every call safely no-ops and logs it, the same way hc_send_email()
 * already does when config/mail.php's SMTP credentials are blank.
 *
 * Once a real provider (e.g. Semaphore, common for Philippine mobile
 * numbers) and its API key exist, the body of this function is the
 * ONLY place that needs the real HTTP call filled in — every caller
 * (api/request-patient-consent.php, etc.) already calls this exact
 * function signature and doesn't need to change.
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

        // No real provider wired in yet — this branch is unreachable
        // until HC_SMS_API_KEY is filled in, at which point the real
        // HTTP call to that provider's API goes here.
        error_log('[sms] HC_SMS_API_KEY is set but no provider call is implemented yet — SMS to ' . $phone . ' was NOT sent.');

        return false;
    }
}
