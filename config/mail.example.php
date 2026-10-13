<?php
/**
 * =====================================================================
 * Outbound Email Configuration (SMTP) — EXAMPLE TEMPLATE
 * ---------------------------------------------------------------------
 * Copy this file to config/mail.php (gitignored — never committed) and
 * fill in real credentials for includes/shared/mailer.php's
 * hc_send_email() — used for account-lifecycle emails (registration
 * received, approved, rejected; see api/register.php,
 * api/approve-account.php, api/reject-account.php). NOT used for
 * anything Firebase Auth already sends itself (password reset /
 * email-change verification — those go through Kreait's action-link
 * emails instead, unrelated to this file).
 *
 * Fill in a real Gmail address + a Google "App Password" (NOT that
 * account's normal login password) generated at
 * myaccount.google.com/apppasswords — that page only appears once
 * 2-Step Verification is turned on for the sending account. Until this
 * is filled in with real values, hc_send_email() will fail every send
 * (caught and logged, never fatal — see that function's own comment).
 *
 * This file holds real credentials — never expose it to the browser
 * (it isn't, it's only require_once'd server-side) and never commit it
 * to a public repo.
 * =====================================================================
 */

define('HC_MAIL_SMTP_HOST', 'smtp.gmail.com');
define('HC_MAIL_SMTP_PORT', 587);
define('HC_MAIL_SMTP_USERNAME', '');
define('HC_MAIL_SMTP_PASSWORD', ''); // App Password (spaces stripped) — not the account's real login password
define('HC_MAIL_FROM_NAME', 'North Super Health Center — HEALTrack');
