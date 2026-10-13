<?php
/**
 * =====================================================================
 * View Profile Photo (api/view-profile-photo.php)
 * ---------------------------------------------------------------------
 * Called by the <img> src includes/shared/profile_photo_storage.php's
 * hc_render_sidebar_avatar() generates. Deliberately simple: there is
 * no "view a colleague's photo" feature anywhere in this app, so this
 * only ever streams the SIGNED-IN user's own photo — no uid parameter
 * at all, nothing to authorize against another account.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/constants.php'; // asset_url() not needed here, but keeps require style consistent
require_once __DIR__ . '/../includes/shared/profile_photo_storage.php';

if (!in_array($_SESSION['role'] ?? null, ['admin', 'subadmin', 'healthcare_staff', 'pharmacist', 'medical_technician', 'doctor'], true)) {
    http_response_code(403);
    exit;
}

$fileName = (string) ($_SESSION['profilePhotoFileName'] ?? '');
$filePath = $fileName !== '' ? hc_profile_photo_storage_dir() . '/' . $fileName : '';
if ($fileName === '' || !is_file($filePath)) {
    http_response_code(404);
    exit;
}

$ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

header('Content-Type: ' . hc_profile_photo_mime_for_extension($ext));
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: private, max-age=86400'); // this user's browser only, same filename reused on re-upload so the URL's own ?v= cache-buster is what actually matters
header('X-Content-Type-Options: nosniff');
readfile($filePath);
