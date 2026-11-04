<?php
/**
 * =====================================================================
 * Shared — Allergy Status-Change Attachment Storage
 * ---------------------------------------------------------------------
 * Parallel to includes/shared/document_storage.php but deliberately
 * NOT reusing the "patient_documents" collection/storage folder — that
 * collection's category enum (laboratory_result/imaging/prescription/
 * medical_certificate/vaccination_record) has no "allergy assessment"
 * value, and reusing it would entangle this feature with the unrelated
 * Documents tab. Same local-disk + .htaccess-blocked + random-filename
 * + double (extension + real-bytes MIME) validation convention,
 * reusing hc_document_allowed_extensions()/hc_document_max_size_bytes()/
 * hc_document_mime_for_extension() from document_storage.php since
 * those are generic. Metadata for an uploaded file lives on the
 * specific allergies/{id}.history[] entry it belongs to, not a
 * separate Firestore collection — see api/update-allergy-clinical-status.php.
 * =====================================================================
 */

require_once __DIR__ . '/document_storage.php'; // hc_document_allowed_extensions(), hc_document_max_size_bytes(), hc_document_mime_for_extension()

if (!function_exists('hc_allergy_attachment_storage_dir')) {
    /** Absolute path to the local storage folder, created on first use if missing. */
    function hc_allergy_attachment_storage_dir(): string
    {
        $dir = __DIR__ . '/../../storage/allergies';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return $dir;
    }
}

if (!function_exists('hc_store_allergy_attachment')) {
    /**
     * Validates and saves one uploaded file (a single $_FILES[...] entry)
     * to storage/allergies/. Same validation body as api/upload-document.php.
     *
     * @param array $file One $_FILES[...] entry.
     * @return array ['error' => string] on any failure, or ['originalName' => string, 'storedFileName' => string, 'sizeBytes' => int, 'mimeExt' => string] on success.
     */
    function hc_store_allergy_attachment(array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $tooLarge = in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true);
            return ['error' => $tooLarge ? 'File is too large. Maximum size is 10MB.' : 'The file could not be uploaded. Please try again.'];
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            return ['error' => 'The file could not be uploaded. Please try again.'];
        }
        if ($file['size'] > hc_document_max_size_bytes()) {
            return ['error' => 'File is too large. Maximum size is 10MB.'];
        }

        $originalName = trim((string) $file['name']);
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $ext = $ext === 'jpeg' ? 'jpg' : $ext;
        if (!in_array($ext, hc_document_allowed_extensions(), true)) {
            return ['error' => 'Unsupported file type. Please upload a PDF, JPG, or PNG file.'];
        }

        // Real server-side check that the file's actual bytes match the
        // claimed type — a renamed .php-with-a-.pdf-extension file still
        // gets rejected here, not just by its extension.
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $realMime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        $expectedMimes = ['pdf' => ['application/pdf'], 'jpg' => ['image/jpeg'], 'png' => ['image/png']];
        if (!in_array($realMime, $expectedMimes[$ext] ?? [], true)) {
            return ['error' => 'This file does not look like a valid ' . strtoupper($ext) . ' file.'];
        }

        $storedFileName = bin2hex(random_bytes(16)) . '.' . $ext;
        $destination = hc_allergy_attachment_storage_dir() . '/' . $storedFileName;
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            return ['error' => 'Could not save the file. Please try again.'];
        }

        return [
            'originalName' => $originalName,
            'storedFileName' => $storedFileName,
            'sizeBytes' => (int) $file['size'],
            'mimeExt' => $ext,
        ];
    }
}
