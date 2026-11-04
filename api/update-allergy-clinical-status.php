<?php
/**
 * =====================================================================
 * Update Allergy Clinical Status (api/update-allergy-clinical-status.php)
 * ---------------------------------------------------------------------
 * Replaces the old api/update-allergy-status.php (binary deactivate/
 * reactivate toggle, healthcare_staff-only, no documented reason). This
 * is the ONE real decision point for every allergy clinical-status
 * transition now — called by assets/js/allergy-actions.js from the
 * "Manage Allergy" button (active rows) and "Reactivate Allergy" button
 * (inactive/resolved rows) on doctor/allergies.php and
 * medical-staff/allergies.php's shared includes/shared/manage_allergy_modal.php.
 *
 * Authorized clinicians only (doctor/healthcare_staff) — subadmin is
 * never granted a UI entry point to this action (its Allergies page
 * stays fully read-only), and this endpoint enforces that server-side
 * too, same convention as every other clinical-history write in this
 * app (api/add-condition.php, api/add-family-history.php, etc. — all
 * gated to exactly ['doctor','healthcare_staff']).
 *
 * Every save is a real, deliberate, documented clinical action — a
 * required free-text reason, a clinician-entered assessment date, and
 * the authenticated clinician's own identity (from $_SESSION, not
 * client-supplied) are all recorded on the allergy's own "history"
 * array alongside the old/new clinicalStatus and verificationStatus,
 * so nothing ever silently changes status. Never deletes or overwrites
 * the allergy's original reaction/severity/onsetDate/etc — setDocument()
 * is a full overwrite (FirestoreRestClient.php has no partial/merge
 * update), so every existing field is read back and copied forward
 * unchanged before only the status-related fields + history are
 * touched.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../includes/shared/document_storage.php'; // hc_user_can_access_patient_document()
require_once __DIR__ . '/../includes/shared/allergy_attachment_storage.php'; // hc_store_allergy_attachment(), hc_allergy_attachment_storage_dir()
require_once __DIR__ . '/../includes/shared/audit_log_writer.php'; // write_audit_log()
require_once __DIR__ . '/../includes/firestore/patient_history.php'; // hc_record_patient_history(), hc_record_patient_activity()

header('Content-Type: application/json');

function uacs_fail(int $status, string $message): never
{
    http_response_code($status);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

// 1. Role gate FIRST — the real enforcement of "no ordinary
// administrative user may change a clinically significant allergy's
// status." Matches every other clinical-history write endpoint in
// this app (medical_conditions/family_history/surgical_history).
if (!in_array($_SESSION['role'] ?? null, ['doctor', 'healthcare_staff'], true)) {
    uacs_fail(403, 'Not authorized.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    uacs_fail(405, 'Method not allowed.');
}

// multipart/form-data (not JSON) — the optional attachment needs $_FILES.
$allergyId = trim((string) ($_POST['allergyId'] ?? ''));
$newClinicalStatus = trim((string) ($_POST['newClinicalStatus'] ?? ''));
$newVerificationStatus = trim((string) ($_POST['newVerificationStatus'] ?? ''));
$reason = trim((string) ($_POST['reason'] ?? ''));
$assessmentDateRaw = trim((string) ($_POST['assessmentDate'] ?? ''));

$clinicalStatuses = ['active', 'inactive', 'resolved'];
$verificationStatuses = ['unconfirmed', 'presumed', 'confirmed', 'refuted', 'entered-in-error'];

if ($allergyId === '') {
    uacs_fail(422, 'Missing allergy record.');
}
if (!in_array($newClinicalStatus, $clinicalStatuses, true)) {
    uacs_fail(422, 'Please select a valid clinical status.');
}
if (!in_array($newVerificationStatus, $verificationStatuses, true)) {
    uacs_fail(422, 'Please select a valid verification status.');
}
if (mb_strlen($reason) < 10) {
    uacs_fail(422, 'Please document the clinical assessment or reason for this status change.');
}
$reason = mb_substr($reason, 0, 1000);

$assessmentDate = DateTime::createFromFormat('!Y-m-d', $assessmentDateRaw);
if (!$assessmentDate || $assessmentDate->format('Y-m-d') !== $assessmentDateRaw) {
    uacs_fail(422, 'Please enter a valid assessment date.');
}
$today = date('Y-m-d');
if ($assessmentDateRaw > $today) {
    uacs_fail(422, 'Assessment date cannot be in the future.');
}

// 2. Re-resolve the allergy + its owning patient server-side — never
// trust a client-supplied patientId (there isn't one in this request
// at all; the allergy doc's own patientId is the only source of truth).
$allergy = $firestore->getDocument('allergies', $allergyId);
if ($allergy === null) {
    uacs_fail(404, 'This allergy record could not be found.');
}

if ($assessmentDateRaw < ($allergy['onsetDate'] ?? '0000-00-00')) {
    uacs_fail(422, "Assessment date cannot be before the allergy's onset date.");
}

$patientId = (string) ($allergy['patientId'] ?? '');
$patient = $patientId !== '' ? $firestore->getDocument('patients', $patientId) : null;
if ($patient === null) {
    uacs_fail(404, 'This allergy record could not be found.');
}
$patient['id'] = $patientId;

// 3. Facility-ownership check — after the role gate, same layering the
// old update-allergy-status.php already used.
if (!hc_user_can_access_patient_document($patient)) {
    uacs_fail(404, 'This allergy record could not be found.');
}

// 4. No-op guard — every save must be a real, deliberate change. This
// is also what keeps a resolved symptom alone from ever silently
// "auto-resolving" an allergy: nothing here runs except an explicit
// clinician submission with an actual status change.
$prevClinicalStatus = $allergy['clinicalStatus'] ?? (($allergy['status'] ?? '') === 'active' ? 'active' : 'resolved');
$prevVerificationStatus = $allergy['verificationStatus'] ?? 'unconfirmed';
if ($prevClinicalStatus === $newClinicalStatus && $prevVerificationStatus === $newVerificationStatus) {
    uacs_fail(422, 'No change was made — the selected statuses match the current record. Refresh the page if this seems wrong.');
}

// 5. Optional supporting documentation.
$attachmentMeta = null;
if (!empty($_FILES['attachment']) && ($_FILES['attachment']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    $stored = hc_store_allergy_attachment($_FILES['attachment']);
    if (isset($stored['error'])) {
        uacs_fail(422, $stored['error']);
    }
    $attachmentMeta = $stored;
}

$clinicianName = $_SESSION['name'] ?? ($_SESSION['role'] === 'doctor' ? 'Doctor' : 'Medical Staff');
$clinicianUid = $_SESSION['uid'] ?? '';
$nowIso = date('c');
$allergen = (string) ($allergy['allergen'] ?? 'Allergy');

$clinicalLabel = ['active' => 'Active', 'inactive' => 'Inactive', 'resolved' => 'Resolved'];
$verificationLabel = ['unconfirmed' => 'Unconfirmed', 'presumed' => 'Presumed', 'confirmed' => 'Confirmed', 'refuted' => 'Refuted', 'entered-in-error' => 'Entered in Error'];

$event = 'Clinical status changed: ' . $clinicalLabel[$prevClinicalStatus] . ' → ' . $clinicalLabel[$newClinicalStatus];
if ($prevVerificationStatus !== $newVerificationStatus) {
    $event .= '; Verification: ' . $verificationLabel[$prevVerificationStatus] . ' → ' . $verificationLabel[$newVerificationStatus];
}
$details = 'Assessed by ' . $clinicianName . ' on ' . $assessmentDateRaw . '. Reason: ' . $reason;

// 6. setDocument() is a full overwrite — copy every existing field
// forward unchanged, only touch the status-related fields + history.
$updated = $allergy;
$updated['clinicalStatus'] = $newClinicalStatus;
$updated['verificationStatus'] = $newVerificationStatus;
$updated['status'] = $newClinicalStatus === 'active' ? 'active' : 'resolved'; // legacy field kept coherent for old readers (hc_allergy_summary(), etc.)
$updated['clinicalStatusUpdatedOn'] = $today;
$updated['clinicalStatusUpdatedAt'] = $nowIso;
$updated['clinicalStatusUpdatedBy'] = $clinicianName;
$updated['verificationStatusUpdatedOn'] = $today;
$updated['verificationStatusUpdatedAt'] = $nowIso;
$updated['verificationStatusUpdatedBy'] = $clinicianName;
$updated['statusUpdatedOn'] = $today; // legacy fields kept in sync too
$updated['statusUpdatedAt'] = $nowIso;
$updated['statusUpdatedBy'] = $clinicianName;

$updated['history'] = array_values(is_array($allergy['history'] ?? null) ? $allergy['history'] : []);
$updated['history'][] = [
    'date' => $today,
    'event' => $event,
    'details' => $details,
    'previousClinicalStatus' => $prevClinicalStatus,
    'newClinicalStatus' => $newClinicalStatus,
    'previousVerificationStatus' => $prevVerificationStatus,
    'newVerificationStatus' => $newVerificationStatus,
    'reason' => $reason,
    'assessmentDate' => $assessmentDateRaw,
    'assessedByName' => $clinicianName,
    'assessedByUid' => $clinicianUid,
    'assessedByRole' => $_SESSION['role'],
    'attachment' => $attachmentMeta,
    'recordedAt' => $nowIso,
];

if (!$firestore->setDocument('allergies', $allergyId, $updated)) {
    if ($attachmentMeta !== null) {
        @unlink(hc_allergy_attachment_storage_dir() . '/' . $attachmentMeta['storedFileName']); // no orphaned file left behind
    }
    uacs_fail(500, 'Could not update this allergy. Please try again.');
}

write_audit_log(
    'updated',
    'Patient Management',
    'Changed clinical status of allergy "' . $allergen . '" from ' . $clinicalLabel[$prevClinicalStatus] . ' to ' . $clinicalLabel[$newClinicalStatus]
        . ' for ' . ($patient['name'] ?? 'a patient') . '. Reason: ' . $reason
);
hc_record_patient_history($patientId, 'allergy', 'updated', 'Updated clinical status of "' . $allergen . '" to ' . $clinicalLabel[$newClinicalStatus] . '.');
hc_record_patient_activity($patientId, 'Allergy "' . $allergen . '" status updated to ' . $clinicalLabel[$newClinicalStatus] . '.');

echo json_encode([
    'success' => true,
    'clinicalStatus' => $newClinicalStatus,
    'verificationStatus' => $newVerificationStatus,
    'message' => '"' . $allergen . '" was updated to ' . $clinicalLabel[$newClinicalStatus] . '.',
]);
