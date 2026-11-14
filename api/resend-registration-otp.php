<?php
/**
 * =====================================================================
 * Resend Registration Email OTP (api/resend-registration-otp.php)
 * ---------------------------------------------------------------------
 * "Resend OTP" button on the OTP modal (register.php /
 * assets/js/register.js). Reads ONLY $_SESSION['pendingRegistration']
 * (set by api/send-registration-otp.php) — same "never trust a
 * client-supplied identifier for mid-flow state" principle as
 * api/verify-registration-otp.php. Generates a fresh 6-digit code,
 * resets the attempt counter, and re-sends the same email template.
 *
 * Bounded two ways to prevent email abuse (per the spec): the same
 * 60-second cooldown api/send-registration-otp.php enforces on a
 * resubmit, and a hard cap on total resends per registration attempt —
 * once exhausted, the person has to go back and submit the form again
 * (a brand-new $_SESSION['pendingRegistration'], fresh counters).
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../includes/shared/mailer.php'; // hc_send_email(), hc_account_email_shell()

header('Content-Type: application/json');

const HC_REG_OTP_TTL_SECONDS = 300;
const HC_REG_OTP_RESEND_COOLDOWN_SECONDS = 60;
const HC_REG_OTP_MAX_RESENDS = 5;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

$pending = $_SESSION['pendingRegistration'] ?? null;
if ($pending === null) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Your registration session has expired. Please fill out the form again.']);
    exit;
}

$secondsSinceLastSent = time() - (int) $pending['lastSentAt'];
if ($secondsSinceLastSent < HC_REG_OTP_RESEND_COOLDOWN_SECONDS) {
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'error' => 'Please wait before requesting another code.',
        'resendAvailableInSeconds' => HC_REG_OTP_RESEND_COOLDOWN_SECONDS - $secondsSinceLastSent,
    ]);
    exit;
}

if ((int) $pending['resendCount'] >= HC_REG_OTP_MAX_RESENDS) {
    http_response_code(429);
    echo json_encode(['success' => false, 'error' => 'Too many code requests for this registration. Please fill out the form again to start over.']);
    exit;
}

$otp = (string) random_int(100000, 999999);

$sent = hc_send_email($pending['email'], trim($pending['firstName'] . ' ' . $pending['lastName']), 'HEALTrack – Verify Your Email Address', hc_account_email_shell(
    'Welcome to HEALTrack!',
    '<p style="margin:0 0 12px; font-size:14px; line-height:1.6;">Thank you for registering an account with HEALTrack – North Super Health Center.</p>'
    . '<p style="margin:0 0 16px; font-size:14px; line-height:1.6;">To verify your email address and continue your registration, please enter the following One-Time Password (OTP):</p>'
    . '<div style="margin:0 0 16px; padding:14px 0; text-align:center; background:#f5f0f0; border-radius:8px; font-size:28px; font-weight:700; letter-spacing:6px; color:#5B0A0A;">' . htmlspecialchars($otp) . '</div>'
    . '<p style="margin:0 0 12px; font-size:14px; line-height:1.6;">This verification code is valid for <strong>5 minutes</strong>.</p>'
    . '<p style="margin:0 0 12px; font-size:13px; line-height:1.6; color:#6B7280;">If you did not request this verification, please disregard this email.</p>'
    . '<p style="margin:0; font-size:13px; line-height:1.6; color:#6B7280;">For your security, do not share this code with anyone.</p>'
));

if (!$sent) {
    http_response_code(502);
    echo json_encode(['success' => false, 'error' => 'We couldn\'t send a verification email right now. Please try again shortly.']);
    exit;
}

$pending['otpHash'] = password_hash($otp, PASSWORD_DEFAULT);
$pending['otpExpiresAt'] = time() + HC_REG_OTP_TTL_SECONDS;
$pending['attempts'] = 0;
$pending['lastSentAt'] = time();
$pending['resendCount'] = (int) $pending['resendCount'] + 1;
$_SESSION['pendingRegistration'] = $pending;

echo json_encode([
    'success' => true,
    'expiresInSeconds' => HC_REG_OTP_TTL_SECONDS,
    'resendAvailableInSeconds' => HC_REG_OTP_RESEND_COOLDOWN_SECONDS,
]);
