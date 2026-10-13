<?php
/**
 * =====================================================================
 * Update Homepage Settings (api/update-homepage-settings.php)
 * ---------------------------------------------------------------------
 * Called by assets/js/homepage-settings.js (subadmin/homepage_settings.php's
 * "Save Changes" button). Writes the real site_settings/homepage doc
 * includes/shared/homepage_settings.php's hc_get_homepage_settings()
 * reads back — the same function index.php itself uses to render its
 * About section and footer, so a save here is immediately live on the
 * public homepage.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php'; // $firestore
require_once __DIR__ . '/../includes/shared/audit_log_writer.php'; // write_audit_log()
require_once __DIR__ . '/../includes/shared/homepage_settings.php'; // HC_HOMEPAGE_SETTINGS_DOC_ID

header('Content-Type: application/json');

if (($_SESSION['role'] ?? null) !== 'subadmin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Not authorized.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];

$missionText = trim((string) ($body['missionText'] ?? ''));
$visionText = trim((string) ($body['visionText'] ?? ''));
$communityFirstText = trim((string) ($body['communityFirstText'] ?? ''));
$contactAddress = trim((string) ($body['contactAddress'] ?? ''));
$contactPhone = trim((string) ($body['contactPhone'] ?? ''));
$contactEmail = trim((string) ($body['contactEmail'] ?? ''));

if ($missionText === '' || $visionText === '' || $communityFirstText === '' || $contactAddress === '' || $contactPhone === '' || $contactEmail === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Please fill out every text field.']);
    exit;
}

// Operating Hours — skip blank-label rows (a stray empty row left over
// from the repeatable-row UI, same tolerance api/create-stock-request.php's
// item rows already have).
$rawOperatingHours = is_array($body['operatingHours'] ?? null) ? $body['operatingHours'] : [];
$operatingHours = [];
foreach ($rawOperatingHours as $row) {
    $label = trim((string) ($row['label'] ?? ''));
    $hours = trim((string) ($row['hours'] ?? ''));
    if ($label === '' || $hours === '') {
        continue;
    }
    $operatingHours[] = ['label' => $label, 'hours' => $hours];
}

$firestore->setDocument('site_settings', HC_HOMEPAGE_SETTINGS_DOC_ID, [
    'missionText' => $missionText,
    'visionText' => $visionText,
    'communityFirstText' => $communityFirstText,
    'contactAddress' => $contactAddress,
    'contactPhone' => $contactPhone,
    'contactEmail' => $contactEmail,
    'operatingHours' => $operatingHours,
    'updatedAt' => date('c'),
    'updatedBy' => $_SESSION['name'] ?? 'Sub-Administrator',
]);

write_audit_log('updated', 'Homepage Settings', 'Updated the public homepage content (About section and footer).');

echo json_encode(['success' => true]);
