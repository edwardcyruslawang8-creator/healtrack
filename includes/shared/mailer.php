<?php
/**
 * =====================================================================
 * Shared — Outbound Email (SMTP via PHPMailer)
 * ---------------------------------------------------------------------
 * Real transactional email, distinct from Firebase Auth's own built-in
 * action-link emails (password reset, email-change verification — those
 * only support Firebase's own fixed templates, not arbitrary custom
 * content like "your account was approved", which is why this app needs
 * its own separate mailer for account-lifecycle notifications at all).
 *
 * hc_send_email() never throws and never lets a delivery failure break
 * the caller's own request — api/register.php, api/approve-account.php,
 * and api/reject-account.php all still succeed (Firestore write still
 * happens) even if config/mail.php's credentials are blank/wrong or the
 * SMTP send otherwise fails; the failure is only logged
 * (error_log, visible in Apache's error log) so this can never turn a
 * real account action into a user-facing error just because an email
 * didn't go out.
 * =====================================================================
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/mail.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

if (!function_exists('hc_send_email')) {
    /**
     * @param string $toEmail
     * @param string $toName
     * @param string $subject
     * @param string $bodyHtml Already-escaped HTML — callers build this themselves (see the small shared wrapper each call site uses).
     * @return bool True if SMTP accepted the message, false on any failure (logged, never thrown).
     */
    function hc_send_email(string $toEmail, string $toName, string $subject, string $bodyHtml): bool
    {
        if (HC_MAIL_SMTP_USERNAME === '' || HC_MAIL_SMTP_PASSWORD === '') {
            error_log('[mailer] Skipped sending "' . $subject . '" to ' . $toEmail . ': config/mail.php has no SMTP credentials filled in yet.');
            return false;
        }

        $mail = new PHPMailer(true);
        try {
            // Without this, PHPMailer defaults to ISO-8859-1 — any
            // non-ASCII character in the subject or body (em dashes,
            // curly quotes, accented names) comes through as mojibake
            // ("â€"" for "—") in a real inbox even though the PHP
            // source itself is UTF-8.
            $mail->CharSet = PHPMailer::CHARSET_UTF8;
            $mail->isSMTP();
            $mail->Host = HC_MAIL_SMTP_HOST;
            $mail->Port = HC_MAIL_SMTP_PORT;
            $mail->SMTPAuth = true;
            $mail->Username = HC_MAIL_SMTP_USERNAME;
            $mail->Password = HC_MAIL_SMTP_PASSWORD;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;

            $mail->setFrom(HC_MAIL_SMTP_USERNAME, HC_MAIL_FROM_NAME);
            $mail->addAddress($toEmail, $toName);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $bodyHtml;
            $mail->AltBody = trim(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $bodyHtml)));

            $mail->send();
            return true;
        } catch (PHPMailerException $e) {
            error_log('[mailer] Failed to send "' . $subject . '" to ' . $toEmail . ': ' . $mail->ErrorInfo);
            return false;
        } catch (\Throwable $e) {
            error_log('[mailer] Failed to send "' . $subject . '" to ' . $toEmail . ': ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('hc_account_email_shell')) {
    /**
     * Shared HTML wrapper every account-lifecycle email uses, so the
     * three call sites (register.php / approve-account.php /
     * reject-account.php) only need to supply their own message body,
     * not repeat the same layout markup three times.
     */
    function hc_account_email_shell(string $heading, string $bodyHtml): string
    {
        return '<div style="font-family: Arial, Helvetica, sans-serif; max-width: 480px; margin: 0 auto; color: #1f2328;">'
            . '<div style="background: #5B0A0A; padding: 20px 24px; border-radius: 10px 10px 0 0;">'
            . '<span style="color: #fff; font-size: 18px; font-weight: 700;">HEALTrack</span><br>'
            . '<span style="color: #f0d8d8; font-size: 12px;">North Super Health Center</span>'
            . '</div>'
            . '<div style="border: 1px solid #e5e7eb; border-top: none; border-radius: 0 0 10px 10px; padding: 24px;">'
            . '<h2 style="margin: 0 0 14px; font-size: 17px; color: #1f2328;">' . htmlspecialchars($heading) . '</h2>'
            . $bodyHtml
            . '<p style="margin: 20px 0 0; font-size: 11.5px; color: #9aa0ac;">This is an automated message from HEALTrack — please don\'t reply to this email.</p>'
            . '</div>'
            . '</div>';
    }
}
