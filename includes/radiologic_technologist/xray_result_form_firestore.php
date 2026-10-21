<?php
/**
 * =====================================================================
 * X-ray Result Form (Radiologic Technologist) — Data
 * ---------------------------------------------------------------------
 * Backs radiologic_technologist/xray_result_form.php — the generic "fill in
 * a result" form for X-ray exams. Modeled directly on
 * includes/medical_technician/xray_result_form_firestore.php. Reached
 * with an explicit &recordId= so the exact xray_results doc is targeted
 * directly.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore
require_once __DIR__ . '/../firestore/patients.php';
require_once __DIR__ . '/../firestore/xray_results.php';

$myUid = $_SESSION['uid'] ?? '';
$myDoc = $myUid !== '' ? $firestore->getDocument('users', $myUid) : null;

$staffProfile = [
    'name'     => $_SESSION['name'] ?? 'Radiologic Technologist',
    'role'     => 'Radiologic Technologist',
    'facility' => $myDoc['facility'] ?? 'North Super Health Center',
];

$dashboardDateLabel = date('F j, Y');

$patientId = trim($_GET['patientId'] ?? '');
$recordId = trim($_GET['recordId'] ?? '');
$patient = hc_get_patient_profile($patientId);

$currentDoc = null;
if ($patient !== null && $recordId !== '') {
    foreach (hc_get_xray_result_docs_for_patient($patientId) as $doc) {
        if ($doc['id'] === $recordId) {
            $currentDoc = $doc;
            break;
        }
    }
}

$isEditable = $currentDoc !== null && ($currentDoc['fields']['status'] ?? '') === 'pending';

// "Proceed to Doctor" state — see api/proceed-lab-result-to-doctor.php's
// 'xray_generic' testType branch.
$routedToDoctorName = ($currentDoc !== null && ($currentDoc['fields']['status'] ?? '') === 'completed')
    ? ($currentDoc['fields']['routedToDoctorName'] ?? null)
    : null;
