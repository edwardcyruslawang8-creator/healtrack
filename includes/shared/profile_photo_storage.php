<?php
/**
 * =====================================================================
 * Shared — Profile Photo File Storage
 * ---------------------------------------------------------------------
 * Firebase Cloud Storage is NOT enabled for this project — same
 * underlying gap documented in includes/shared/document_storage.php's
 * header comment, same fix: the actual FILE lives on local disk under
 * storage/profile_photos/ (blocked from direct web access by its own
 * .htaccess), with just the on-disk filename + an update timestamp (for
 * cache-busting the <img> src) stored on the real users/{uid} Firestore
 * doc. api/upload-profile-photo.php writes both; api/view-profile-photo.php
 * reads both and streams the file back — but ONLY ever the signed-in
 * user's own photo, never anyone else's (this app has no "view a
 * colleague's profile" page to need that for).
 *
 * hc_render_sidebar_avatar() is the one function every one of the ~100
 * admin-family pages' sidebar ".adm-avatar"/topbar ".adm-topbar-avatar"
 * blocks now calls instead of hardcoding the bi-person-circle icon —
 * reads straight from $_SESSION (populated at login by
 * api/session-login.php, kept in sync by api/upload-profile-photo.php),
 * never a Firestore read of its own, so adding a real photo to every
 * page's sidebar didn't cost every page an extra query.
 * =====================================================================
 */

if (!function_exists('hc_profile_photo_storage_dir')) {
    /** Absolute path to the local storage folder, created on first use if missing. */
    function hc_profile_photo_storage_dir(): string
    {
        $dir = __DIR__ . '/../../storage/profile_photos';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return $dir;
    }
}

if (!function_exists('hc_profile_photo_allowed_extensions')) {
    function hc_profile_photo_allowed_extensions(): array
    {
        return ['jpg', 'png'];
    }
}

if (!function_exists('hc_profile_photo_max_size_bytes')) {
    function hc_profile_photo_max_size_bytes(): int
    {
        // The upload is client-side resized/re-encoded before it ever
        // reaches here (assets/js/profile-photo-upload.js), so a real
        // photo is typically well under 200KB — this ceiling is just a
        // sanity backstop against something bypassing that, not a
        // limit anyone should normally approach.
        return 3 * 1024 * 1024; // 3MB
    }
}

if (!function_exists('hc_profile_photo_mime_for_extension')) {
    function hc_profile_photo_mime_for_extension(string $ext): string
    {
        return match ($ext) {
            'jpg' => 'image/jpeg',
            'png' => 'image/png',
            default => 'application/octet-stream',
        };
    }
}

if (!function_exists('hc_render_sidebar_avatar')) {
    /**
     * The signed-in user's own photo as a real <img>, cache-busted by
     * when it was last changed (so a re-upload shows immediately, but a
     * page the browser already has cached doesn't re-fetch it every
     * load) — or the plain bi-person-circle icon when no photo has ever
     * been uploaded. $extraClass lets a caller add its own class
     * (e.g. ".adm-topbar-avatar img" needs slightly different sizing
     * than ".adm-avatar img") without this function needing to know
     * which block it's being called from.
     */
    function hc_render_sidebar_avatar(string $extraClass = ''): string
    {
        $fileName = $_SESSION['profilePhotoFileName'] ?? null;
        if ($fileName === null || $fileName === '') {
            return '<i class="bi bi-person-circle"></i>';
        }

        $v = (string) ($_SESSION['profilePhotoUpdatedAt'] ?? '');
        $class = trim('hc-avatar-img ' . $extraClass);

        return '<img src="' . htmlspecialchars(BASE_URL) . '/api/view-profile-photo.php?v=' . urlencode($v) . '" alt="" class="' . htmlspecialchars($class) . '">';
    }
}
