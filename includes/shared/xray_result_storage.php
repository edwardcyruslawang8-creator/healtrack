<?php
/**
 * =====================================================================
 * Shared — X-ray Result File Storage
 * ---------------------------------------------------------------------
 * Same local-disk pattern as includes/shared/document_storage.php (no
 * Firebase Cloud Storage in this project) — a separate storage root and
 * helper set rather than reusing that file directly, since its own
 * hc_user_can_access_patient_document() is hardcoded to
 * doctor/healthcare_staff/subadmin, not medical_technician/
 * radio_technologist. Uploaded result files live under
 * storage/xray_results/ (blocked from direct web access by its own
 * .htaccess, same as patient_documents/) with the real on-disk filename
 * stored alongside the xray_results/{id} doc itself (attachedFileName/
 * attachedFileSize/storedFileName — see api/complete-xray-result.php).
 * api/download-xray-result.php reads both back, re-checking the
 * signed-in user's role before ever streaming a file.
 * =====================================================================
 */

if (!function_exists('hc_xray_result_storage_dir')) {
    /** Absolute path to the local storage folder, created on first use if missing. */
    function hc_xray_result_storage_dir(): string
    {
        $dir = __DIR__ . '/../../storage/xray_results';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return $dir;
    }
}

if (!function_exists('hc_xray_result_allowed_extensions')) {
    /** Lowercase, 'jpeg' already folded into 'jpg' — same accepted types as includes/shared/document_storage.php. */
    function hc_xray_result_allowed_extensions(): array
    {
        return ['pdf', 'jpg', 'png'];
    }
}

if (!function_exists('hc_xray_result_max_size_bytes')) {
    function hc_xray_result_max_size_bytes(): int
    {
        return 10 * 1024 * 1024; // 10MB, same ceiling as patient documents
    }
}

if (!function_exists('hc_xray_result_mime_for_extension')) {
    /** Real Content-Type for api/download-xray-result.php's response headers. */
    function hc_xray_result_mime_for_extension(string $ext): string
    {
        return match ($ext) {
            'pdf' => 'application/pdf',
            'jpg' => 'image/jpeg',
            'png' => 'image/png',
            default => 'application/octet-stream',
        };
    }
}

if (!function_exists('hc_format_file_size')) {
    /** Human-readable size (e.g. "2.4 MB") — xray_results' attachedFileSize is displayed as-is, with no further formatting, by laboratory_result_details.php/xray_result_form.php. */
    function hc_format_file_size(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }

        return round($bytes / (1024 * 1024), 1) . ' MB';
    }
}
