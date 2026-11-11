<?php
/**
 * =====================================================================
 * Shared — Attach a Real File to a Completed Lab Result
 * ---------------------------------------------------------------------
 * Called by every lab-test completion endpoint (api/complete-gram-stain-test.php,
 * complete-cd4-count-test.php, complete-urinalysis-test.php,
 * complete-serology-rapid-test.php, complete-lab-result.php) right after
 * each one's own successful lab_results save. Builds the printable
 * report (includes/shared/lab_result_report.php) and stores it in the
 * SAME local-disk folder every Documents page already reads from
 * (includes/shared/document_storage.php) — one physical file serves
 * both Laboratory Result Details' own Attached File box AND the
 * patient's Documents tab, no duplication. Writes to both real places:
 * the lab_results/{id} doc itself (attachedFileName/attachedFileSize/
 * storedFileName — same field shape xray_results already uses) and a
 * new "patient_documents" entry (category "laboratory_result") so it
 * shows up on doctor/healthcare_staff/subadmin's Documents page
 * automatically, with no manual upload.
 * =====================================================================
 */

require_once __DIR__ . '/lab_result_report.php';
require_once __DIR__ . '/document_storage.php';
require_once __DIR__ . '/xray_result_storage.php'; // hc_format_file_size()
require_once __DIR__ . '/../firestore/patients.php'; // hc_get_patient_profile()

if (!function_exists('hc_attach_lab_result_report')) {
    /**
     * Best-effort — if the file write or either Firestore write fails,
     * this simply returns without throwing: the lab result itself was
     * already saved successfully by the caller before this runs, and a
     * missing attachment should never turn an otherwise-successful
     * "Save Result" into an error response.
     */
    function hc_attach_lab_result_report(string $patientId, string $labResultDocId, array $mergedFields, string $performedBy): void
    {
        global $firestore;

        $patient = hc_get_patient_profile($patientId);
        $patientName = $patient['name'] ?? 'Unknown Patient';

        $html = hc_build_lab_result_report_html($patient ?? ['name' => $patientName], $mergedFields, $performedBy);

        $storedFileName = bin2hex(random_bytes(16)) . '.html';
        $destination = hc_document_storage_dir() . '/' . $storedFileName;
        if (file_put_contents($destination, $html) === false) {
            return;
        }

        $testName = hc_lab_test_display_name($mergedFields['testName'] ?? 'Laboratory Test');
        $sizeBytes = (int) filesize($destination);
        $downloadName = $testName . ' Report - ' . $patientName . '.html';

        $firestore->setDocument('lab_results', $labResultDocId, array_merge($mergedFields, [
            'attachedFileName' => $downloadName,
            'attachedFileSize' => hc_format_file_size($sizeBytes),
            'storedFileName' => $storedFileName,
        ]));

        $now = new DateTime();
        $firestore->addDocument('patient_documents', [
            'patientId' => $patientId,
            'name' => $downloadName,
            'fileType' => 'html',
            'category' => 'laboratory_result',
            'description' => $testName . ' laboratory report, automatically filed when the result was completed.',
            'uploadedBy' => $performedBy,
            'uploadDate' => $now->format('Y-m-d'),
            'uploadTime' => $now->format('g:i A'),
            'storedFileName' => $storedFileName,
            'sizeBytes' => $sizeBytes,
        ]);
    }
}
