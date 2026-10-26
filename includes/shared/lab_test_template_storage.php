<?php
/**
 * =====================================================================
 * Shared — Lab Test Template File Storage
 * ---------------------------------------------------------------------
 * Same local-disk pattern as includes/shared/profile_photo_storage.php
 * (no Firebase Cloud Storage in this project) — one uploaded file per
 * "lab_test_catalog" doc, named by that doc's own real Firestore id so
 * a re-upload overwrites the previous file directly (api/upload-lab-test-template.php)
 * rather than accumulating orphaned old files. Unlike profile photos,
 * ANY medical_technician may view ANY test's template (there's no
 * per-uploader ownership here — it's a shared reference document for
 * the whole facility's catalog), so api/view-lab-test-template.php just
 * checks the role, not who uploaded it.
 *
 * Reference only — see includes/firestore/lab_results.php's
 * hc_get_lab_test_template() header comment for what this file is (and
 * isn't) used for.
 * =====================================================================
 */

if (!function_exists('hc_lab_test_template_storage_dir')) {
    /** Absolute path to the local storage folder, created on first use if missing. */
    function hc_lab_test_template_storage_dir(): string
    {
        $dir = __DIR__ . '/../../storage/lab_test_templates';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return $dir;
    }
}

if (!function_exists('hc_lab_test_template_allowed_extensions')) {
    /** Same real paper-form file types as includes/shared/document_storage.php's own list. */
    function hc_lab_test_template_allowed_extensions(): array
    {
        return ['pdf', 'jpg', 'png'];
    }
}

if (!function_exists('hc_lab_test_template_max_size_bytes')) {
    function hc_lab_test_template_max_size_bytes(): int
    {
        return 10 * 1024 * 1024; // 10MB, matching includes/shared/document_storage.php's own ceiling
    }
}

if (!function_exists('hc_lab_test_template_mime_for_extension')) {
    function hc_lab_test_template_mime_for_extension(string $ext): string
    {
        return match ($ext) {
            'pdf' => 'application/pdf',
            'jpg' => 'image/jpeg',
            'png' => 'image/png',
            default => 'application/octet-stream',
        };
    }
}
