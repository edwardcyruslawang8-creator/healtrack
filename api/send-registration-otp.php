<?php
/**
 * =====================================================================
 * Send Registration Email OTP (api/send-registration-otp.php)
 * ---------------------------------------------------------------------
 * Step 2 of the manual (email+password) self-registration flow —
 * called by assets/js/register.js's submit handler instead of
 * api/register.php, right after the Create Account form's own
 * client-side checks pass. Validates the submission exactly like
 * api/register.php used to, but does NOT create any Firebase Auth user
 * or Firestore record yet: it generates a 6-digit OTP (random_int,
 * CSPRNG), stores only its password_hash() (never the plaintext code)
 * together with the submitted form data in
 * $_SESSION['pendingRegistration'], and emails the real code to the
 * address just submitted.
 *
 * Mirrors this codebase's existing "mid-flow state lives in the PHP
 * session, keyed to nothing the client supplies" pattern —
 * api/session-login.php's $_SESSION['pending2faUid'] /
 * api/verify-2fa.php is the direct precedent. Nothing here becomes a
 * real account (and so nothing ever reaches the sub-admin's Pending
 * Approvals table) unless api/verify-registration-otp.php later
 * confirms the right code was entered — there is deliberately no
 * provisional/disabled account sitting in Firestore in the meantime.
 *
 * Resubmitting this endpoint for the same in-progress session (a
 * genuine resend, or the person editing the form and clicking Create
 * Account again) is rate-limited the same 60-second cooldown
 * api/resend-registration-otp.php enforces, so this one check covers
 * both "abusing resend" and "spamming the form".
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../includes/shared/registration.php'; // hc_validate_registration_fields(), HC_JOB_TITLE_TO_ROLE, hc_email_already_registered()

header('Content-Type: application/json');

const HC_REG_OTP_TTL_SECONDS = 300;
const HC_REG_OTP_RESEND_COOLDOWN_SECONDS = 60;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];

$firstName = trim((string) ($body['firstName'] ?? ''));
$lastName = trim((string) ($body['lastName'] ?? ''));
$email = trim((string) ($body['email'] ?? ''));
$phone = trim((string) ($body['phone'] ?? ''));
$password = (string) ($body['password'] ?? '');
$confirmPassword = (string) ($body['confirmPassword'] ?? '');
$jobTitle = trim((string) ($body['role'] ?? ''));
$termsAccepted = (bool) ($body['termsAccepted'] ?? false);

$validationError = hc_validate_registration_fields($firstName, $lastName, $email, $phone, $jobTitle, $password, $confirmPassword, $termsAccepted);
if ($validationError !== null) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => $validationError]);
    exit;
}

if (hc_email_already_registered($email)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'An account with this email already exists. Please sign in instead.']);
    exit;
}

// Same 60s cooldown as a dedicated Resend click — covers resubmitting
// this same form before the previous code's cooldown has elapsed, e.g.
// a double-click or a second tab.
$pending = $_SESSION['pendingRegistration'] ?? null;
if ($pending !== null) {
    $secondsSinceLastSent = time() - (int) $pending['lastSentAt'];
    if ($secondsSinceLastSent < HC_REG_OTP_RESEND_COOLDOWN_SECONDS) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'error' => 'A verification code was already sent. Please wait before requesting another.',
            'resendAvailableInSeconds' => HC_REG_OTP_RESEND_COOLDOWN_SECONDS - $secondsSinceLastSent,
        ]);
        exit;
    }
}

$otp = (string) random_int(100000, 999999);

$newPending = [
    'firstName' => $firstName,
    'lastName' => $lastName,
    'email' => $email,
    'phone' => $phone,
    'jobTitle' => $jobTitle,
    'password' => $password,
    'otpHash' => password_hash($otp, PASSWORD_DEFAULT),
    'otpExpiresAt' => time() + HC_REG_OTP_TTL_SECONDS,
    'attempts' => 0,
    'lastSentAt' => time(),
    'resendCount' => 0,
];

$sent = hc_send_email($email, trim($firstName . ' ' . $lastName), 'HEALTrack – Verify Your Email Address', hc_account_email_shell(
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
    echo json_encode(['success' => false, 'error' => 'We couldn\'t send a verification email to that address. Please check it and try again.']);
    exit;
}

$_SESSION['pendingRegistration'] = $newPending;

$atPos = strrpos($email, '@');
$maskedEmail = $atPos === false ? $email : (substr($email, 0, min(2, $atPos)) . str_repeat('*', max(1, $atPos - 2)) . substr($email, $atPos));

echo json_encode([
    'success' => true,
    'maskedEmail' => $maskedEmail,
    'expiresInSeconds' => HC_REG_OTP_TTL_SECONDS,
    'resendAvailableInSeconds' => HC_REG_OTP_RESEND_COOLDOWN_SECONDS,
]);
