<?php
/**
 * =====================================================================
 * Respond to Patient Consent Request (api/respond-patient-consent.php)
 * ---------------------------------------------------------------------
 * Called by consent-response.php's Approve/Decline buttons — the ONE
 * real public, unauthenticated write endpoint in this app. There is no
 * $_SESSION here at all and there is deliberately no precedent for
 * this anywhere else in the codebase (patients have no login/portal —
 * see includes/shared/followup_reminder.php's own header comment), so
 * the real token IS the only proof this is genuinely the patient who
 * received the email/SMS: a 64-hex-char (256-bit) random value
 * (bin2hex(random_bytes(32))) generated once by
 * api/request-patient-consent.php, checked against the real
 * consent_records doc it's stored on, and burned (tokenUsed = true)
 * the moment this runs so the same link can never be replayed.
 * =====================================================================
 */

require_once __DIR__ . '/../config/firebase.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$token = trim((string) ($body['token'] ?? ''));
$decision = trim((string) ($body['decision'] ?? ''));

if ($token === '' || !in_array($decision, ['accepted', 'rejected'], true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Invalid request.']);
    exit;
}

$matches = array_values(array_filter(
    $firestore->listDocuments('consent_records'),
    fn ($doc) => ($doc['fields']['respondToken'] ?? '') === $token
));

if ($matches === []) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'This link is invalid.']);
    exit;
}

$doc = $matches[0];
$fields = $doc['fields'];

if (!empty($fields['tokenUsed'])) {
    http_response_code(410);
    echo json_encode(['success' => false, 'error' => 'This link has already been used.']);
    exit;
}
if (($fields['status'] ?? 'pending') !== 'pending') {
    http_response_code(410);
    echo json_encode(['success' => false, 'error' => 'This request has already been decided.']);
    exit;
}
$expiresAt = $fields['tokenExpiresAt'] ?? null;
if ($expiresAt !== null && strtotime($expiresAt) !== false && strtotime($expiresAt) < time()) {
    http_response_code(410);
    echo json_encode(['success' => false, 'error' => 'This link has expired.']);
    exit;
}

$saved = $firestore->setDocument('consent_records', $doc['id'], array_merge($fields, [
    'status' => $decision,
    'respondedAt' => date('c'),
    'tokenUsed' => true,
    'approvedBy' => $decision === 'accepted' ? ($fields['patientName'] ?? 'Patient') : null,
]));

if (!$saved) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not save your response. Please try again.']);
    exit;
}

echo json_encode(['success' => true, 'decision' => $decision]);
