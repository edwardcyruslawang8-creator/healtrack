<?php
/**
 * =====================================================================
 * Upload Profile Photo (api/upload-profile-photo.php)
 * ---------------------------------------------------------------------
 * Called by assets/js/profile-photo-upload.js from every role's
 * Profile / Settings page's "Save Changes" button — if a new photo was
 * picked, this fires first (and must succeed) before the existing
 * api/update-profile-settings.php JSON save runs for the other fields.
 * Saves the actual file to local disk (see
 * includes/shared/profile_photo_storage.php's header comment for why,
 * instead of Firebase Cloud Storage) and records just the on-disk
 * filename + an update timestamp on the signed-in user's own real
 * users/{uid} Firestore doc.
 *
 * multipart/form-data, not JSON — carries a real file. The client
 * already resized/re-encoded it to a small JPEG before sending (see
 * that JS file), but every check below still runs for real — a request
 * bypassing the browser entirely gets the same validation a real
 * upload would.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../includes/shared/profile_photo_storage.php';
require_once __DIR__ . '/../includes/shared/audit_log_writer.php'; // write_audit_log()

header('Content-Type: application/json');

if (!in_array($_SESSION['role'] ?? null, ['admin', 'subadmin', 'healthcare_staff', 'pharmacist', 'medical_technician', 'radio_technologist', 'doctor'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Not authorized.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

$uid = $_SESSION['uid'] ?? '';
if ($uid === '') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Your session has expired. Please sign in again.']);
    exit;
}

$file = $_FILES['photo'] ?? null;
if ($file === null || !isset($file['error'])) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Please choose a photo to upload.']);
    exit;
}
if ($file['error'] !== UPLOAD_ERR_OK) {
    $tooLarge = in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true);
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => $tooLarge ? 'Photo is too large.' : 'The photo could not be uploaded. Please try again.']);
    exit;
}
if (!is_uploaded_file($file['tmp_name'])) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'The photo could not be uploaded. Please try again.']);
    exit;
}
if ($file['size'] > hc_profile_photo_max_size_bytes()) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Photo is too large.']);
    exit;
}

$originalName = trim((string) $file['name']);
$ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
$ext = $ext === 'jpeg' ? 'jpg' : $ext;
if (!in_array($ext, hc_profile_photo_allowed_extensions(), true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Please upload a JPG or PNG image.']);
    exit;
}

// Real server-side check that the file's actual bytes match the
// claimed type — same reasoning as api/upload-document.php's own copy
// of this check.
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$realMime = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);
$expectedMimes = ['jpg' => ['image/jpeg'], 'png' => ['image/png']];
if (!in_array($realMime, $expectedMimes[$ext] ?? [], true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'This file does not look like a valid image.']);
    exit;
}

$existing = $firestore->getDocument('users', $uid);
if ($existing === null) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Your account could not be found.']);
    exit;
}

// One file per user, named by their own uid — a re-upload overwrites
// it directly rather than accumulating orphaned old photos. uid itself
// (not attacker-controlled) is safe to use directly in a path.
$storedFileName = $uid . '.' . $ext;
$dir = hc_profile_photo_storage_dir();

// If the user's previous photo used the OTHER extension (e.g. switching
// from a .png upload to a .jpg one), remove it so it doesn't linger as
// an orphaned file no longer referenced by anything.
foreach (hc_profile_photo_allowed_extensions() as $otherExt) {
    if ($otherExt !== $ext) {
        @unlink($dir . '/' . $uid . '.' . $otherExt);
    }
}

$destination = $dir . '/' . $storedFileName;
if (!move_uploaded_file($file['tmp_name'], $destination)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not save the photo. Please try again.']);
    exit;
}

$updatedAt = date('c');
$updated = array_merge($existing, [
    'profilePhotoFileName' => $storedFileName,
    'profilePhotoUpdatedAt' => $updatedAt,
]);
$saved = $firestore->setDocument('users', $uid, $updated);

if (!$saved) {
    @unlink($destination); // don't leave an orphaned file if the Firestore write failed
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not save the photo. Please try again.']);
    exit;
}

// Same reasoning as api/update-profile-settings.php's own name/theme/
// language sync — every admin-family page reads this straight from the
// session (includes/shared/profile_photo_storage.php's
// hc_render_sidebar_avatar()) rather than a Firestore read per page.
$_SESSION['profilePhotoFileName'] = $storedFileName;
$_SESSION['profilePhotoUpdatedAt'] = $updatedAt;

write_audit_log('updated', 'Profile / Settings', 'Updated profile photo.');

echo json_encode(['success' => true]);
