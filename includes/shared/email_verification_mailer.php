<?php
/**
 * =====================================================================
 * Shared — Branded Email Verification Message
 * ---------------------------------------------------------------------
 * Replaces Firebase Auth's own hosted verification email (the generic,
 * unbranded "Verify your email for project-751034583942" one that
 * firebase.auth().currentUser.sendEmailVerification() used to send
 * entirely client-side — see assets/js/email-verification.js's own
 * header comment, now updated) with HEALTrack's own branded template,
 * sent through this app's real SMTP mailer
 * (includes/shared/mailer.php) — same mechanism every other account-
 * lifecycle email in this app already uses.
 *
 * The underlying verification MECHANISM is unchanged: this still uses
 * Firebase Auth's own real action link ($firebaseAuth->getEmailVerificationLink()),
 * and clicking it still flips the same real Firebase Auth emailVerified
 * flag login.php's $_SESSION['emailVerified'] already reads. Only how
 * that link is DELIVERED changed — Firebase's hosted template can only
 * be restyled from the Firebase Console, and even then not to this
 * degree (a custom button, this exact copy/structure), so sending it
 * ourselves is the only way to actually control subject/body/branding.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firebaseAuth
require_once __DIR__ . '/mailer.php'; // hc_send_email(), hc_account_email_shell()

if (!function_exists('hc_send_verification_email')) {
    /**
     * @return array{success: bool, error: ?string}
     */
    function hc_send_verification_email(string $email, string $name): array
    {
        global $firebaseAuth;

        try {
            $link = $firebaseAuth->getEmailVerificationLink($email);
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'Could not generate a verification link: ' . $e->getMessage()];
        }

        $firstName = trim((string) strtok(trim($name), ' ')) ?: 'there';

        $bodyHtml = '<p style="margin:0 0 12px; font-size:14px; line-height:1.6;">Hello ' . htmlspecialchars($firstName) . ',</p>'
            . '<p style="margin:0 0 12px; font-size:14px; line-height:1.6;">Welcome to HEALTrack. Thank you for creating an account for North Super Health Center. To continue, please verify your email address by clicking the button below.</p>'
            . '<p style="margin:0 0 20px;"><a href="' . htmlspecialchars($link) . '" style="display:inline-block; background:#5B0A0A; color:#fff; text-decoration:none; padding:10px 22px; border-radius:8px; font-size:13.5px; font-weight:600;">Verify Email Address</a></p>'
            . '<p style="margin:0 0 6px; font-size:12.5px; color:#6b7280; line-height:1.5;">If the button above doesn\'t work, copy and paste this link into your browser:</p>'
            . '<p style="margin:0 0 20px; font-size:12px; word-break:break-all;"><a href="' . htmlspecialchars($link) . '" style="color:#5B0A0A;">' . htmlspecialchars($link) . '</a></p>'
            . '<p style="margin:0 0 18px; font-size:13px; color:#6b7280; line-height:1.6;">If you did not create this account, you may safely ignore this email.</p>'
            . '<p style="margin:0; font-size:13px; color:#374151; line-height:1.6;">Thank you,<br>HEALTrack System<br>North Super Health Center<br>Lipa City, Batangas</p>';

        $sent = hc_send_email($email, $name, 'HEALTrack – Verify Your Email Address', hc_account_email_shell(
            'Verify your email address',
            $bodyHtml
        ));

        return $sent
            ? ['success' => true, 'error' => null]
            : ['success' => false, 'error' => 'Could not send the verification email. Please try again.'];
    }
}
