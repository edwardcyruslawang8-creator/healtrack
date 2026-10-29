<?php
/**
 * =====================================================================
 * Outbound SMS Configuration — EXAMPLE TEMPLATE
 * ---------------------------------------------------------------------
 * Copy this file to config/sms.php (gitignored — never committed) once
 * a real SMS provider account exists. includes/shared/sms.php's
 * hc_send_sms() checks HC_SMS_API_KEY — until it's filled in, every
 * send is skipped and logged (never fatal), same convention as
 * config/mail.php / hc_send_email().
 *
 * These placeholder constant names assume a generic REST-based provider
 * (e.g. Semaphore, which is common for Philippine mobile numbers) —
 * hc_send_sms() itself will need its actual HTTP call filled in once a
 * real provider is chosen, since the request shape differs per
 * provider. This file only holds the credentials.
 *
 * This file holds real credentials — never expose it to the browser
 * (it isn't, it's only require_once'd server-side) and never commit it
 * to a public repo.
 * =====================================================================
 */

define('HC_SMS_API_KEY', '');
define('HC_SMS_SENDER_NAME', 'HEALTrack');
