<?php
/**
 * =====================================================================
 * Remove Profile Photo (api/remove-profile-photo.php)
 * ---------------------------------------------------------------------
 * Called by assets/js/profile-photo-preview.js's "Remove Photo" link —
 * unlike a new upload (deferred until "Save Changes"), removal takes
 * effect immediately, same convention as this page's other instant
 * actions (Change Password, Enable 2FA). Deletes the actual file from
 * local disk (see includes/shared/profile_photo_storage.php's header
 * comment for why photos live there instead of Firebase Cloud Storage)
 * and clears the on-disk filename + update timestamp from the
 * signed-in user's own real users/{uid} Firestore doc — the same two
 * fields api/upload-profile-photo.php writes.
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

$existing = $firestore->getDocument('users', $uid);
if ($existing === null) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Your account could not be found.']);
    exit;
}

// uid itself (not attacker-controlled) is safe to use directly in a
// path — same reasoning as api/upload-profile-photo.php. Removes
// whichever extension is actually on disk; idempotent if there's
// nothing there (e.g. a stale double-click) rather than erroring.
$dir = hc_profile_photo_storage_dir();
foreach (hc_profile_photo_allowed_extensions() as $ext) {
    @unlink($dir . '/' . $uid . '.' . $ext);
}

$updated = array_merge($existing, [
    'profilePhotoFileName' => null,
    'profilePhotoUpdatedAt' => null,
]);
$saved = $firestore->setDocument('users', $uid, $updated);

if (!$saved) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not remove the photo. Please try again.']);
    exit;
}

// Same reasoning as api/upload-profile-photo.php's own session sync —
// every admin-family page reads this straight from the session
// (includes/shared/profile_photo_storage.php's hc_render_sidebar_avatar()).
unset($_SESSION['profilePhotoFileName'], $_SESSION['profilePhotoUpdatedAt']);

write_audit_log('updated', 'Profile / Settings', 'Removed profile photo.');

echo json_encode(['success' => true]);
