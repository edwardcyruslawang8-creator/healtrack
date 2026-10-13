<?php
/**
 * =====================================================================
 * Shared — Patient Document File Storage
 * ---------------------------------------------------------------------
 * Firebase Cloud Storage is NOT enabled for this project (see
 * assets/js/profile-photo-preview.js's own header comment — this is
 * the same underlying gap that left the Documents upload panel and
 * profile photo upload both wired to nothing). Rather than wait on
 * that, uploaded document FILES are stored on local disk under
 * storage/patient_documents/ (blocked from direct web access by its
 * own .htaccess) with each file's real metadata — including which
 * on-disk filename it maps to — in the existing real "patient_documents"
 * Firestore collection every role's Documents page already reads.
 * api/upload-document.php writes both; api/download-document.php
 * reads both, re-checking the signed-in user's access to the owning
 * patient before ever streaming a file back.
 * =====================================================================
 */

if (!function_exists('hc_document_storage_dir')) {
    /** Absolute path to the local storage folder, created on first use if missing. */
    function hc_document_storage_dir(): string
    {
        $dir = __DIR__ . '/../../storage/patient_documents';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return $dir;
    }
}

if (!function_exists('hc_document_allowed_extensions')) {
    /** Lowercase, 'jpeg' already folded into 'jpg' — matches every documents.php page's own $uploadSettings/$fileTypeIconMeta. */
    function hc_document_allowed_extensions(): array
    {
        return ['pdf', 'jpg', 'png'];
    }
}

if (!function_exists('hc_document_max_size_bytes')) {
    function hc_document_max_size_bytes(): int
    {
        return 10 * 1024 * 1024; // 10MB, matching every documents.php page's own $uploadSettings
    }
}

if (!function_exists('hc_document_mime_for_extension')) {
    /** Real Content-Type for api/download-document.php's response headers. */
    function hc_document_mime_for_extension(string $ext): string
    {
        return match ($ext) {
            'pdf' => 'application/pdf',
            'jpg' => 'image/jpeg',
            'png' => 'image/png',
            default => 'application/octet-stream',
        };
    }
}

if (!function_exists('hc_user_can_access_patient_document')) {
    /**
     * True if the SIGNED-IN user (from $_SESSION) may upload/download
     * documents for $patient — the same access rule each role's real
     * Patient Profile tab family already enforces, re-derived here
     * since api/upload-document.php and api/download-document.php
     * don't have a per-page $doctorProfile/$staffProfile/$adminProfile
     * to read it from. A doctor needs real ownership (or an approved
     * access request, see includes/shared/patient_access_requests.php);
     * medical staff and sub-admins just need to share the patient's
     * facility, same as includes/medical-staff/patient_profile_medstaff_firestore.php's
     * and includes/subadmin/patient_profile_firestore.php's own gates.
     *
     * @param array $patient Real patients/{id} doc fields, with 'id' set.
     */
    function hc_user_can_access_patient_document(array $patient): bool
    {
        global $firestore;

        $role = $_SESSION['role'] ?? null;
        $myUid = $_SESSION['uid'] ?? '';
        if ($myUid === '' || !in_array($role, ['doctor', 'healthcare_staff', 'subadmin'], true)) {
            return false;
        }

        $myDoc = $firestore->getDocument('users', $myUid);
        $myFacility = $myDoc['facility'] ?? null;
        if ($myFacility === null || $myFacility !== ($patient['facility'] ?? '')) {
            return false;
        }

        if ($role === 'doctor') {
            require_once __DIR__ . '/patient_access_requests.php'; // hc_doctor_has_patient_access()

            return hc_doctor_has_patient_access($patient, $myUid);
        }

        return true; // healthcare_staff / subadmin — facility match above is the whole gate.
    }
}
