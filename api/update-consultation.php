<?php
/**
 * =====================================================================
 * Update Consultation (api/update-consultation.php)
 * ---------------------------------------------------------------------
 * Called by assets/js/consultations_medstaff.js for two actions on
 * medical-staff/consultations.php: the "Edit" modal (full field
 * update) and the one-click "Complete" row action (status-only
 * update). Also called by assets/js/doctor_consultations.js for
 * doctor/consultations.php's one-click "Start Consultation" action
 * (status-only update, pending -> ongoing). Both PATCH the same real
 * "consultations" Firestore doc — see
 * includes/firestore/consultations.php's header comment.
 *
 * Firestore's setDocument() PATCHes without an updateMask, which
 * replaces the WHOLE doc with only the fields passed in, so the
 * existing doc is read first and written back with just the changed
 * fields overlaid (same pattern as api/update-profile-settings.php).
 *
 * Body: {"id": "...", "status": "completed"} for a status-only update
 * (Complete action), or the full field set for an Edit. A pending ->
 * ongoing status transition also stamps a real "startedAt" the FIRST
 * time only (see below) — doctor/active_consultation.php's "Started
 * at" footer reads it back. "Cancel Consultation" on that same page
 * reuses this endpoint unchanged too: {"id": "...", "status": "cancelled"}.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../includes/firestore/consultations.php';
require_once __DIR__ . '/../includes/firestore/patients.php'; // hc_get_patient_profile()
require_once __DIR__ . '/../includes/shared/audit_log_writer.php'; // write_audit_log()
require_once __DIR__ . '/../includes/shared/vitals_validation.php'; // hc_validate_vitals()
require_once __DIR__ . '/../includes/firestore/patient_history.php'; // hc_record_patient_history(), hc_record_patient_activity()

header('Content-Type: application/json');

if (!in_array($_SESSION['role'] ?? null, ['healthcare_staff', 'doctor'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Not authorized.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$id = trim((string) ($body['id'] ?? ''));

if ($id === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Missing consultation id.']);
    exit;
}

$existing = $firestore->getDocument('consultations', $id);
if ($existing === null) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Consultation not found.']);
    exit;
}

// Never trust the client-supplied id alone — it must belong to this
// staff member's own facility, resolved server-side from their own
// users/{uid} doc, same as every other facility-scoped write in this
// app (see api/update-stock-request-status.php's own copy of this
// check). Consultations docs don't carry their own "facility" field
// (unlike lab_results/xray_results/stock_requests) — it's derived from
// the linked patient's real facility instead, the same source of truth
// hc_get_patient_profile() already is everywhere else.
$myUid = $_SESSION['uid'] ?? '';
$myDoc = $myUid !== '' ? $firestore->getDocument('users', $myUid) : null;
$myFacility = $myDoc['facility'] ?? null;
$existingPatient = hc_get_patient_profile($existing['patientId'] ?? '');
if ($myFacility === null || $existingPatient === null || $existingPatient['facility'] !== $myFacility) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Consultation not found.']);
    exit;
}

$validTypes = array_keys(hc_consultation_type_meta());
$validStatuses = array_keys(hc_consultation_status_meta());
$validPriorities = array_keys(hc_consultation_priority_meta());

$updated = $existing;

if (array_key_exists('patientId', $body)) {
    $patientId = trim((string) $body['patientId']);
    $patientName = trim((string) ($body['patientName'] ?? ''));
    if ($patientId === '' || $patientName === '') {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Please select a patient.']);
        exit;
    }
    $updated['patientId'] = $patientId;
    $updated['patientName'] = $patientName;
}
if (array_key_exists('consultationType', $body)) {
    $type = trim((string) $body['consultationType']);
    if (!in_array($type, $validTypes, true)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Invalid consultation type.']);
        exit;
    }
    $updated['consultationType'] = $type;
}
if (array_key_exists('dateTime', $body)) {
    $dateTime = trim((string) $body['dateTime']);
    if ($dateTime === '') {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Date & time is required.']);
        exit;
    }
    $updated['dateTime'] = $dateTime;
}
// Same double-booking guard as api/create-consultation.php — only worth
// re-checking when this edit could actually change the effective slot
// (patient and/or date/time), not on every status-only PATCH (e.g. the
// "Complete" row action, which never sends either field).
if (array_key_exists('patientId', $body) || array_key_exists('dateTime', $body)) {
    foreach (hc_get_all_consultations() as $otherConsult) {
        if ($otherConsult['id'] !== $id && $otherConsult['patientId'] === $updated['patientId']
            && $otherConsult['dateTime'] === $updated['dateTime'] && $otherConsult['status'] !== 'cancelled') {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'This patient already has a consultation scheduled at that exact date and time.']);
            exit;
        }
    }
}
if (array_key_exists('assignedPersonnel', $body)) {
    $assignedPersonnel = trim((string) $body['assignedPersonnel']);
    if ($assignedPersonnel === '') {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Assigned personnel is required.']);
        exit;
    }
    $updated['assignedPersonnel'] = $assignedPersonnel;
}
if (array_key_exists('chiefComplaint', $body)) {
    $chiefComplaint = trim((string) $body['chiefComplaint']);
    if ($chiefComplaint === '') {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Chief complaint is required.']);
        exit;
    }
    $updated['chiefComplaint'] = $chiefComplaint;
}
if (array_key_exists('status', $body)) {
    $status = trim((string) $body['status']);
    if (!in_array($status, $validStatuses, true)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Invalid status.']);
        exit;
    }
    $updated['status'] = $status;
    // First time this consultation goes "ongoing" — stamp when the
    // doctor actually started, for doctor/active_consultation.php's
    // "Started at" footer. Only once: a doctor navigating back to an
    // already-ongoing consultation shouldn't reset its real start time.
    if ($status === 'ongoing' && empty($existing['startedAt'])) {
        $updated['startedAt'] = date('c');
    }
}
if (array_key_exists('priority', $body)) {
    $priority = trim((string) $body['priority']);
    if ($priority !== '' && !in_array($priority, $validPriorities, true)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Invalid priority.']);
        exit;
    }
    $updated['priority'] = $priority !== '' ? $priority : null;
}
if (array_key_exists('nextFollowUp', $body)) {
    $nextFollowUp = trim((string) $body['nextFollowUp']);
    $updated['nextFollowUp'] = $nextFollowUp !== '' ? $nextFollowUp : null;
}
if (array_key_exists('notes', $body)) {
    $notes = trim((string) $body['notes']);
    $updated['notes'] = $notes !== '' ? $notes : null;
}
if (array_key_exists('vitals', $body)) {
    // Same required-field convention as api/create-consultation.php's
    // own vitals handling (see its header comment) — medical staff
    // editing the "Set Consultation" modal always resubmits Vital
    // Signs, so this only ever runs on a real full-form Edit, never on
    // the "Complete" row action's status-only {id, status} PATCH (which
    // never includes a "vitals" key at all).
    $rawVitals = is_array($body['vitals']) ? $body['vitals'] : [];
    $newVitals = [
        'temperature' => trim((string) ($rawVitals['temperature'] ?? '')),
        'bloodPressureSystolic' => trim((string) ($rawVitals['bloodPressureSystolic'] ?? '')),
        'bloodPressureDiastolic' => trim((string) ($rawVitals['bloodPressureDiastolic'] ?? '')),
        'heartRate' => trim((string) ($rawVitals['heartRate'] ?? '')),
        'weight' => trim((string) ($rawVitals['weight'] ?? '')),
        'height' => trim((string) ($rawVitals['height'] ?? '')),
        'bmi' => trim((string) ($rawVitals['bmi'] ?? '')),
    ];
    if ($newVitals['temperature'] === '' || $newVitals['bloodPressureSystolic'] === '' || $newVitals['bloodPressureDiastolic'] === ''
        || $newVitals['heartRate'] === '' || $newVitals['weight'] === '' || $newVitals['height'] === '') {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Please fill out every required field, including Vital Signs.']);
        exit;
    }
    // Presence already checked above — this only re-checks real ranges.
    $vitalsError = hc_validate_vitals($newVitals, false);
    if ($vitalsError !== null) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => $vitalsError]);
        exit;
    }
    $updated['vitals'] = $newVitals;
}

$updated['updatedAt'] = date('c');
$updated['updatedBy'] = $_SESSION['uid'] ?? '';

$saved = $firestore->setDocument('consultations', $id, $updated);

if (!$saved) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not save changes. Please try again.']);
    exit;
}

// Status-only PATCHes get a human-readable verb matching what actually
// changed ("Completed"/"Started"/"Cancelled"); the audit log's own
// action-type field stays "completed" (pre-existing precedent for the
// Complete row action) or "updated" for everything else, rather than
// inventing new action-type values write_audit_log()'s callers don't
// otherwise use (see its header comment's documented enum).
$isStatusOnlyUpdate = array_key_exists('status', $body) && count($body) <= 2;
$statusVerb = ['completed' => 'Completed', 'ongoing' => 'Started', 'cancelled' => 'Cancelled', 'pending' => 'Reopened'];
$actionVerb = $isStatusOnlyUpdate ? ($statusVerb[$updated['status']] ?? 'Updated') : 'Updated';
write_audit_log(
    $isStatusOnlyUpdate && $updated['status'] === 'completed' ? 'completed' : 'updated',
    'Consultations',
    $actionVerb . ' the consultation for ' . ($updated['patientName'] ?? '') . ' (' . ($updated['consultationCode'] ?? $id) . ').'
);
hc_record_patient_history($updated['patientId'] ?? '', 'medical_record', 'updated', $actionVerb . ' consultation ' . ($updated['consultationCode'] ?? $id) . '.');
hc_record_patient_activity($updated['patientId'] ?? '', 'Consultation ' . ($updated['consultationCode'] ?? $id) . ' ' . strtolower($actionVerb) . '.');

echo json_encode(['success' => true]);
