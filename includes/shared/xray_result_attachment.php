<?php
/**
 * =====================================================================
 * Shared — Auto-file a Completed X-ray's Attachment into Documents
 * ---------------------------------------------------------------------
 * Called by api/complete-xray-result.php right after a real result file
 * is uploaded and saved, and by the one-time backfill that ran this for
 * every already-completed x-ray from before this feature existed (see
 * includes/shared/lab_result_attachment.php's own copy of this header
 * comment for the lab-side equivalent). Copies the already-stored
 * xray_results file (includes/shared/xray_result_storage.php) into the
 * same local-disk folder every Documents page already reads from
 * (includes/shared/document_storage.php) under a new random name, then
 * adds a "patient_documents" entry (category "imaging") pointing to
 * that copy — a real, separate file rather than a shared pointer, same
 * reasoning as every other independent storage dir in this app.
 * Best-effort: a failed copy never blocks the x-ray result itself,
 * which was already saved successfully before this runs.
 * =====================================================================
 */

require_once __DIR__ . '/xray_result_storage.php';
require_once __DIR__ . '/document_storage.php';

if (!function_exists('hc_file_xray_result_in_documents')) {
    function hc_file_xray_result_in_documents(string $patientId, array $xrayFields, string $filedBy): void
    {
        global $firestore;

        $storedFileName = (string) ($xrayFields['storedFileName'] ?? '');
        $attachedFileName = (string) ($xrayFields['attachedFileName'] ?? '');
        if ($storedFileName === '' || $attachedFileName === '') {
            return;
        }

        $source = hc_xray_result_storage_dir() . '/' . $storedFileName;
        if (!is_file($source)) {
            return;
        }

        $ext = strtolower(pathinfo($storedFileName, PATHINFO_EXTENSION));
        $docsCopyName = bin2hex(random_bytes(16)) . '.' . $ext;
        if (!copy($source, hc_document_storage_dir() . '/' . $docsCopyName)) {
            return;
        }

        $now = new DateTime();
        $firestore->addDocument('patient_documents', [
            'patientId' => $patientId,
            'name' => $attachedFileName,
            'fileType' => $ext,
            'category' => 'imaging',
            'description' => 'X-ray — ' . ($xrayFields['examName'] ?? 'Exam') . ', automatically filed when the result was completed.',
            'uploadedBy' => $filedBy,
            'uploadDate' => $now->format('Y-m-d'),
            'uploadTime' => $now->format('g:i A'),
            'storedFileName' => $docsCopyName,
            'sizeBytes' => (int) filesize($source),
        ]);
    }
}
