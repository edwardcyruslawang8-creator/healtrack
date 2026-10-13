<?php
/**
 * =====================================================================
 * Generate 2FA Secret (api/generate-2fa-secret.php)
 * ---------------------------------------------------------------------
 * Called by assets/js/two-fa.js when a signed-in user clicks "Enable
 * 2FA" on their Profile / Settings page (any of the 6 roles — this
 * endpoint doesn't branch on role, same convention as
 * api/update-admin-password.php). Generates a fresh TOTP secret
 * (pragmarx/google2fa — pure PHP, no GD/Imagick dependency) and its
 * otpauth:// URI, and returns both — the QR code itself is rendered
 * CLIENT-SIDE from that URI (assets/js/two-fa.js), not here.
 *
 * Deliberately writes NOTHING to Firestore yet — only
 * api/confirm-2fa-setup.php does, once the user proves they actually
 * scanned/configured it by entering a real code back. Generating here
 * without persisting means an abandoned setup attempt never leaves a
 * half-configured secret sitting on the account.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/firebase.php';

use PragmaRX\Google2FA\Google2FA;

header('Content-Type: application/json');

if (!in_array($_SESSION['role'] ?? null, ['admin', 'subadmin', 'healthcare_staff', 'pharmacist', 'medical_technician', 'doctor'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Not authorized.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

$email = $_SESSION['email'] ?? '';
if ($email === '') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Your session has expired. Please sign in again.']);
    exit;
}

$google2fa = new Google2FA();
$secret = $google2fa->generateSecretKey();
$otpauthUrl = $google2fa->getQRCodeUrl('HEALTrack', $email, $secret);

echo json_encode(['success' => true, 'secret' => $secret, 'otpauthUrl' => $otpauthUrl]);
