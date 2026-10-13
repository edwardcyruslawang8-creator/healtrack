<?php
/**
 * =====================================================================
 * Respond to Patient Consent Request (api/respond-patient-consent.php)
 * ---------------------------------------------------------------------
 * Posted to by patient-consent.php's plain HTML form — step 3 (the
 * final one) of the workflow (see
 * includes/shared/patient_access_requests.php's header comment).
 *
 * DELIBERATELY not the app's usual JSON-endpoint convention: this is
 * the one point in the whole app a real patient (no account, no login,
 * arriving from an email link on whatever device they own) has to act.
 * A plain <form method="post"> + redirect works with zero JavaScript,
 * unlike every other api/*.php endpoint here.
 *
 * No session/role check at all — there's no patient account to check
 * against. Security instead comes entirely from the token: the raw
 * value only ever existed in the one email api/decide-patient-access-request.php
 * sent, and only sha256(token) was ever stored server-side (same
 * scheme as includes/shared/remember_me.php's hc_issue_remember_token()).
 * Every failure path (missing/wrong/expired/already-used token) shows
 * the exact same generic message on patient-consent.php — never reveals
 * which specific check failed.
 * =====================================================================
 */

require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../config/constants.php'; // BASE_URL
require_once __DIR__ . '/../includes/shared/audit_log_writer.php'; // write_audit_log() — safe with no session, resolves to a 'System' actor
require_once __DIR__ . '/../includes/firestore/patient_history.php'; // hc_record_patient_activity() — also safe with no session

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

$requestId = trim((string) ($_POST['rid'] ?? ''));
$rawToken = (string) ($_POST['token'] ?? '');
$decision = trim((string) ($_POST['decision'] ?? ''));

$invalidUrl = BASE_URL . '/patient-consent.php?rid=' . urlencode($requestId) . '&result=invalid';

if ($requestId === '' || $rawToken === '' || !in_array($decision, ['consent', 'decline'], true)) {
    header('Location: ' . $invalidUrl);
    exit;
}

$existing = $firestore->getDocument('patient_access_requests', $requestId);
$tokenHash = $existing['consentTokenHash'] ?? '';
$expiresAt = $existing['consentTokenExpiresAt'] ?? '';

$valid = $existing !== null
    && ($existing['status'] ?? '') === 'pending_patient'
    && $tokenHash !== ''
    && hash_equals($tokenHash, hash('sha256', $rawToken))
    && $expiresAt !== ''
    && strtotime($expiresAt) >= time();

if (!$valid) {
    header('Location: ' . $invalidUrl);
    exit;
}

$updated = $existing;
$updated['status'] = $decision === 'consent' ? 'approved' : 'declined_by_patient';
$updated['patientDecidedAt'] = date('c');

$saved = $firestore->setDocument('patient_access_requests', $requestId, $updated);
if (!$saved) {
    header('Location: ' . $invalidUrl);
    exit;
}

write_audit_log(
    $decision === 'consent' ? 'approved' : 'rejected',
    'Patient Management',
    'Patient "' . ($existing['patientName'] ?? '') . '" ' . ($decision === 'consent' ? 'consented to' : 'declined') . ' sharing their record with ' . ($existing['requestedByName'] ?? 'a doctor') . '.'
);
hc_record_patient_activity(
    $existing['patientId'] ?? '',
    'Patient ' . ($decision === 'consent' ? 'consented to' : 'declined') . ' sharing their record with ' . ($existing['requestedByName'] ?? 'a doctor') . '.',
    $existing['patientName'] ?? 'Patient'
);

header('Location: ' . BASE_URL . '/patient-consent.php?rid=' . urlencode($requestId) . '&result=' . ($decision === 'consent' ? 'consented' : 'declined'));
