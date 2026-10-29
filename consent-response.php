<?php
/**
 * =====================================================================
 * NORTH SUPER HEALTH CENTER — Healthcare Management System
 * Patient Consent Response (consent-response.php)
 * ---------------------------------------------------------------------
 * The ONE real public, unauthenticated page in this app that performs a
 * meaningful write (via api/respond-patient-consent.php) — reached
 * only through the real ?token= link api/request-patient-consent.php
 * emails (and, once a real SMS provider exists, texts) directly to a
 * patient. Patients have no login/portal anywhere else in this app
 * (see includes/shared/followup_reminder.php's own header comment), so
 * this is the only way a patient can ever approve or decline a real
 * consent request — there is nowhere to "sign in" to do this instead.
 *
 * Reuses login.php's/forgot-password.php's exact left-panel branding
 * and assets/css/login.css's card/field styles verbatim — same shared
 * pre-login shell, no new CSS needed. The token lookup itself happens
 * server-side right here (not via a separate "get info" API) since
 * this page already needs direct Firestore access to decide which of
 * 3 states to render (valid pending request / already decided-or-
 * expired-or-invalid / thank-you after a real response).
 * =====================================================================
 */

require_once __DIR__ . '/config/constants.php'; // BASE_URL, asset_url()
require_once __DIR__ . '/config/firebase.php'; // $firestore

$token = trim((string) ($_GET['token'] ?? ''));

$consentDoc = null;
$errorMessage = null;

if ($token === '') {
    $errorMessage = 'This link is missing some information. Please use the exact link from your email or text message.';
} else {
    $matches = array_values(array_filter(
        $firestore->listDocuments('consent_records'),
        fn ($doc) => ($doc['fields']['respondToken'] ?? '') === $token
    ));
    if ($matches === []) {
        $errorMessage = 'This link is invalid. Please use the exact link from your email or text message.';
    } else {
        $f = $matches[0]['fields'];
        if (!empty($f['tokenUsed'])) {
            $errorMessage = 'This link has already been used — you already responded to this request.';
        } elseif (($f['status'] ?? 'pending') !== 'pending') {
            $errorMessage = 'This request has already been decided.';
        } elseif (!empty($f['tokenExpiresAt']) && strtotime($f['tokenExpiresAt']) !== false && strtotime($f['tokenExpiresAt']) < time()) {
            $errorMessage = 'This link has expired. Please ask the staff member to send a new request.';
        } else {
            $consentDoc = $f;
        }
    }
}

$roleLabel = [
    'subadmin' => 'Sub-Administrator',
    'doctor' => 'Doctor',
    'healthcare_staff' => 'Medical Staff',
][$consentDoc['requestedByRole'] ?? ''] ?? 'Staff';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Consent Request — North Super Health Center</title>

<!-- Google Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

<!-- Bootstrap 5 -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

<!-- Base stylesheet (shared CSS variables/fonts only — this page doesn't use the dashboard sidebar) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/base.css') ?>">
<!-- Reused verbatim from login.php/forgot-password.php — same card/field/left-panel styles -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/login.css') ?>">
</head>
<body>

<div class="login-page">

    <!-- LEFT PANEL — brand (identical to login.php/forgot-password.php) -->
    <div class="login-left">
        <div class="login-logo-row">
            <div class="logo-badge"><i class="bi bi-shield-fill-plus"></i></div>
            <div class="login-brand-text">
                <h1>NORTH SUPER HEALTH CENTER</h1>
                <p>Healthcare Management System</p>
            </div>
        </div>
        <hr class="login-divider">
        <h2>Your Information, Your Choice.</h2>
        <p class="login-left-desc">
            HEALTrack asks for your real permission before any staff member can view your full patient details —
            you decide who gets access, and why.
        </p>
        <div class="login-illustration">
            <img src="<?= BASE_URL ?>/assets/images/bg.png" alt="Healthcare illustration">
        </div>
        <div class="login-tagline">
            <i class="bi bi-shield-fill-check"></i>
            <span>Your partner in building a healthier tomorrow.</span>
        </div>
    </div>

    <!-- RIGHT PANEL — consent response -->
    <div class="login-right">
        <div class="login-card">

            <?php if ($errorMessage !== null): ?>
            <h2>This link can't be used</h2>
            <div class="alert alert-danger py-2 px-3" style="font-size:13px;"><?= htmlspecialchars($errorMessage) ?></div>

            <?php elseif ($consentDoc !== null): ?>
            <div id="crPendingView">
                <h2>Someone wants to view your patient details</h2>
                <p class="login-card-subtitle">Please review this request before deciding.</p>

                <div class="alert alert-light border py-2 px-3 mb-3" style="font-size:13.5px; text-align:left;">
                    <div class="mb-2"><strong>Requested by:</strong> <?= htmlspecialchars($consentDoc['requestedByName'] ?? '') ?> (<?= htmlspecialchars($roleLabel) ?>)</div>
                    <div><strong>Reason given:</strong> <?= htmlspecialchars($consentDoc['purpose'] ?? '') ?></div>
                </div>

                <div id="crError" class="alert alert-danger py-2 px-3 d-none" style="font-size:13px;"></div>

                <div class="d-flex gap-2">
                    <button type="button" class="btn-sign-in" id="crApproveBtn" style="background:#1E8E3E; flex:1;">
                        <i class="bi bi-check-circle-fill"></i> Approve
                    </button>
                    <button type="button" class="btn-sign-in" id="crDeclineBtn" style="background:#D93025; flex:1;">
                        <i class="bi bi-x-circle-fill"></i> Decline
                    </button>
                </div>
            </div>

            <div id="crDoneView" class="d-none">
                <h2 id="crDoneHeading">Thank you</h2>
                <p class="login-card-subtitle" id="crDoneMessage"></p>
            </div>

            <script>window.APP_BASE_URL = "<?= BASE_URL ?>";</script>
            <script>window.CONSENT_RESPONSE_TOKEN = "<?= htmlspecialchars($token, ENT_QUOTES) ?>";</script>
            <script src="<?= asset_url('/assets/js/consent-response.js') ?>"></script>
            <?php endif; ?>

        </div>
    </div>

</div>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
