<?php
/**
 * =====================================================================
 * Patient Documents (Medical Staff) — Data
 * ---------------------------------------------------------------------
 * Reuses includes/medical-staff/patient_profile_medstaff_firestore.php
 * for everything about the patient themselves ($staffProfile, $patient,
 * $profileTabs) rather than duplicating it — re-marks the "documents"
 * tab active here.
 *
 * CONNECTED to real Firestore data — $documents reads live from the
 * "patient_documents" collection (same one subadmin/documents.php
 * reads), filtered to $patient['id']. Upload/Download/View/Delete are
 * all real now (api/upload-document.php, api/download-document.php,
 * api/delete-document.php) — see includes/shared/document_storage.php's
 * header comment for the local-disk file storage behind them.
 * =====================================================================
 */

require_once __DIR__ . '/patient_profile_medstaff_firestore.php';
// $staffProfile, $dashboardDateLabel, $facilityOptions, $patient, $profileTabs
require_once __DIR__ . '/../shared/pagination_helpers.php'; // hc_paginate(), hc_render_pagination_nav()

foreach ($profileTabs as &$tab) {
    $tab['active'] = ($tab['key'] === 'documents');
}
unset($tab);

// Category -> badge color meta.
$documentCategoryMeta = [
    'laboratory_result'   => ['label' => 'Laboratory Result',  'bg' => '#E8F0FE', 'color' => '#1A73E8'],
    'imaging'             => ['label' => 'Imaging',             'bg' => '#FDECDD', 'color' => '#E8710A'],
    'prescription'        => ['label' => 'Prescription',        'bg' => '#E4F7EA', 'color' => '#1E8E3E'],
    'medical_certificate' => ['label' => 'Medical Certificate', 'bg' => '#F1E8FD', 'color' => '#8B5CF6'],
    'vaccination_record'  => ['label' => 'Vaccination Record',  'bg' => '#FDF3DC', 'color' => '#B7791F'],
];

// ---------------------------------------------------------------------
// Real documents for this patient, newest first.
// ---------------------------------------------------------------------
$rawDocumentDocs = array_values(array_filter(
    $firestore->listDocuments('patient_documents'),
    fn ($doc) => ($doc['fields']['patientId'] ?? '') === $patient['id']
));
usort($rawDocumentDocs, fn ($a, $b) => strcmp($b['fields']['uploadDate'] ?? '', $a['fields']['uploadDate'] ?? '') ?: strcmp($b['fields']['uploadTime'] ?? '', $a['fields']['uploadTime'] ?? ''));

$allDocuments = array_map(fn ($doc) => [
    'id' => $doc['id'],
    'name' => $doc['fields']['name'],
    'fileType' => $doc['fields']['fileType'],
    'category' => $doc['fields']['category'],
    'uploadedBy' => $doc['fields']['uploadedBy'],
    'uploadDate' => date('M j, Y', strtotime($doc['fields']['uploadDate'])),
    'uploadTime' => $doc['fields']['uploadTime'],
    'description' => $doc['fields']['description'],
], $rawDocumentDocs);

// ---------------------------------------------------------------------
// Documents Summary — real counts over $allDocuments.
// ---------------------------------------------------------------------
$documentsSummary = [
    'totalDocuments' => count($allDocuments),
    'lastUploadedDate' => $allDocuments[0]['uploadDate'] ?? '—',
    'lastUploadedFile' => $allDocuments[0]['name'] ?? '—',
];

// ---------------------------------------------------------------------
// Documents — real, paginated.
// ---------------------------------------------------------------------
$documentsPerPage = 5;
$documentsPagination = hc_paginate($allDocuments, 'page', $documentsPerPage);
$documents = $documentsPagination['items'];
$documentsTotal = $documentsPagination['total'];
$documentsTotalPages = $documentsPagination['totalPages'];
$documentsCurrentPage = $documentsPagination['page'];

// ---------------------------------------------------------------------
// Upload Document panel settings — still decorative, no upload endpoint.
// ---------------------------------------------------------------------
$uploadSettings = [
    'supportedFormats' => ['PDF', 'JPG', 'PNG'],
    'acceptAttr' => '.pdf,.jpg,.jpeg,.png',
    'maxSizeMB' => 10,
];
