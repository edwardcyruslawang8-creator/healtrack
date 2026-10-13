<?php
/**
 * =====================================================================
 * Patient Consent (patient-consent.php)
 * ---------------------------------------------------------------------
 * Public page reached from the emailed link api/decide-patient-access-request.php
 * sends — no login, no session, no patient account (patients don't have
 * one in this app). See includes/shared/patient_access_requests.php's
 * header comment for the full "doctor requests access to another
 * doctor's patient" workflow this is the final step of.
 *
 * Security is entirely the token in the URL: the raw value only ever
 * existed in that one email, only sha256(token) was ever stored
 * server-side (same scheme as includes/shared/remember_me.php's
 * hc_issue_remember_token()). Every failure — missing/wrong/expired/
 * already-decided — renders the exact same generic message below;
 * never reveals which specific check failed.
 *
 * The actual decision is a plain HTML <form method="post"> to
 * api/respond-patient-consent.php (not this app's usual JSON/fetch
 * convention) — deliberately works with zero JavaScript, since this is
 * the one page in the whole app a real patient has to act on from
 * whatever device they opened the email on.
 * =====================================================================
 */

require_once __DIR__ . '/config/firebase.php';
require_once __DIR__ . '/config/constants.php'; // BASE_URL, asset_url()

$requestId = trim((string) ($_GET['rid'] ?? ''));
$rawToken = (string) ($_GET['token'] ?? '');
$result = $_GET['result'] ?? ''; // '', 'invalid', 'consented', 'declined' — set by api/respond-patient-consent.php's own redirect

$request = null;
$patientName = '';
$doctorName = '';

if ($result === '') {
    // First visit (from the email) — validate the token for real before
    // showing anything about the request.
    $existing = $requestId !== '' ? $firestore->getDocument('patient_access_requests', $requestId) : null;
    $tokenHash = $existing['consentTokenHash'] ?? '';
    $expiresAt = $existing['consentTokenExpiresAt'] ?? '';

    $valid = $existing !== null
        && ($existing['status'] ?? '') === 'pending_patient'
        && $rawToken !== ''
        && $tokenHash !== ''
        && hash_equals($tokenHash, hash('sha256', $rawToken))
        && $expiresAt !== ''
        && strtotime($expiresAt) >= time();

    if ($valid) {
        $request = $existing;
        $patientName = $existing['patientName'] ?? 'you';
        $doctorName = $existing['requestedByName'] ?? 'A doctor';
    } else {
        $result = 'invalid';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Consent Request — HEALTrack</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="<?= asset_url('/assets/css/base.css') ?>">
<style>
    body { background: var(--bg-light); font-family: 'Poppins', sans-serif; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; }
    .pc-card { background: var(--card-white); border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,.08); max-width: 460px; width: 100%; padding: 36px 32px; text-align: center; }
    .pc-icon { width: 56px; height: 56px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 18px; font-size: 26px; }
    .pc-title { font-size: 19px; font-weight: 700; color: var(--text-dark); margin-bottom: 10px; }
    .pc-body { font-size: 14px; color: var(--text-muted); line-height: 1.6; margin-bottom: 22px; }
    .pc-btn-consent { background: var(--maroon); color: #fff; border: none; border-radius: 8px; padding: 11px 20px; font-weight: 600; font-size: 14px; width: 100%; margin-bottom: 10px; }
    .pc-btn-decline { background: #fff; color: var(--text-muted); border: 1px solid var(--border-soft); border-radius: 8px; padding: 11px 20px; font-weight: 600; font-size: 14px; width: 100%; }
</style>
</head>
<body>

<div class="pc-card">
    <?php if ($result === 'invalid'): ?>
        <div class="pc-icon" style="background: var(--red-bg); color: var(--red);"><i class="bi bi-exclamation-triangle-fill"></i></div>
        <div class="pc-title">This link is invalid or has expired</div>
        <div class="pc-body">This consent link is no longer valid — it may have already been used, expired, or been entered incorrectly. If you still want to respond to this request, please contact your health center.</div>
    <?php elseif ($result === 'consented'): ?>
        <div class="pc-icon" style="background: var(--green-bg); color: var(--green);"><i class="bi bi-check-circle-fill"></i></div>
        <div class="pc-title">Thank you — your consent was recorded</div>
        <div class="pc-body">The requesting doctor now has access to the record you approved. You can close this page.</div>
    <?php elseif ($result === 'declined'): ?>
        <div class="pc-icon" style="background: var(--bg-light); color: var(--text-muted);"><i class="bi bi-slash-circle-fill"></i></div>
        <div class="pc-title">Your response was recorded</div>
        <div class="pc-body">You declined to share this record. The requesting doctor will not be given access. You can close this page.</div>
    <?php else: ?>
        <div class="pc-icon" style="background: var(--orange-bg); color: var(--orange);"><i class="bi bi-shield-lock-fill"></i></div>
        <div class="pc-title">A doctor is requesting access to your record</div>
        <div class="pc-body">
            <strong><?= htmlspecialchars($doctorName) ?></strong> has asked for permission to view <strong><?= htmlspecialchars($patientName) ?></strong>'s medical record at your health center. Nothing is shared unless you consent below.
        </div>
        <form method="post" action="<?= BASE_URL ?>/api/respond-patient-consent.php">
            <input type="hidden" name="rid" value="<?= htmlspecialchars($requestId) ?>">
            <input type="hidden" name="token" value="<?= htmlspecialchars($rawToken) ?>">
            <button type="submit" name="decision" value="consent" class="pc-btn-consent">I consent to sharing my record</button>
            <button type="submit" name="decision" value="decline" class="pc-btn-decline">I do not consent</button>
        </form>
    <?php endif; ?>
</div>

</body>
</html>
