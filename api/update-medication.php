<?php
/**
 * =====================================================================
 * Update Medication (api/update-medication.php)
 * ---------------------------------------------------------------------
 * Doctor-only actions on one medications/{id} doc, called from the View
 * Details modal (includes/shared/medication_details_modal.php,
 * assets/js/medication-details.js) on doctor/medications.php:
 *
 *   discontinue — stop a medication before its end date (reason required)
 *   extend      — extend or renew it to a new end date
 *   confirm     — confirm a course whose period has ended was actually
 *                 completed (otherwise it only reads "Treatment Period
 *                 Ended", never "Completed")
 *
 * Moving a medication from Current to Past when its period ends is NOT
 * done here — that is automatic and date-based (hc_medication_classify()).
 *
 * Every action preserves the original prescription: startDate, endDate,
 * duration, dosage, etc. are never rewritten. An extension is recorded
 * in extendedEndDate plus an append-only extensions[] log; a
 * discontinuation/confirmation adds its own fields alongside. Each one
 * also writes a medication_history timeline entry and an audit log.
 *
 * Only a signed-in doctor may call this — medical staff, sub-admins and
 * pharmacists can view medications but never change their dates or
 * status.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../includes/firestore/medications.php';
require_once __DIR__ . '/../includes/shared/audit_log_writer.php'; // write_audit_log()
require_once __DIR__ . '/../includes/firestore/patient_history.php'; // hc_record_patient_history()

header('Content-Type: application/json');

function hc_update_med_fail(int $code, string $error): never
{
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $error]);
    exit;
}

if (($_SESSION['role'] ?? null) !== 'doctor') {
    hc_update_med_fail(403, 'Only a doctor can change a medication.');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    hc_update_med_fail(405, 'Method not allowed.');
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$id = trim((string) ($body['id'] ?? ''));
$action = (string) ($body['action'] ?? '');
$reason = trim((string) ($body['reason'] ?? ''));

if ($id === '' || !preg_match('/^[A-Za-z0-9_-]{1,128}$/', $id)) {
    hc_update_med_fail(422, 'Missing medication id.');
}
if (!in_array($action, ['discontinue', 'extend', 'confirm'], true)) {
    hc_update_med_fail(422, 'Unknown action.');
}
if (mb_strlen($reason) > 500) {
    hc_update_med_fail(422, 'Please keep the reason under 500 characters.');
}

$fields = $firestore->getDocument('medications', $id);
if ($fields === null) {
    hc_update_med_fail(404, 'Medication not found.');
}

$doctorName = $_SESSION['name'] ?? 'Doctor';
$today = hc_medication_today();
$c = hc_medication_classify($fields, $today);
$patientId = (string) ($fields['patientId'] ?? '');
$label = ($fields['name'] ?? 'Medication') . ' (' . ($fields['dosage'] ?? '') . ')';

switch ($action) {
    case 'discontinue':
        if (!$c['isCurrent']) {
            hc_update_med_fail(409, 'Only a current medication can be discontinued.');
        }
        if ($reason === '') {
            hc_update_med_fail(422, 'Please give a reason for discontinuing.');
        }
        $fields['status'] = 'discontinued';
        $fields['discontinuedDate'] = $today;
        $fields['discontinuedBy'] = $doctorName;
        $fields['discontinueReason'] = $reason;
        $event = 'Medication discontinued';
        $detail = 'Discontinued ' . $label . ' by ' . $doctorName . '. Reason: ' . $reason;
        $color = 'gray';
        break;

    case 'extend':
        if (in_array($c['state'], ['discontinued', 'completed'], true)) {
            hc_update_med_fail(409, 'A ' . strtolower($c['label']) . ' medication cannot be extended — prescribe it again instead.');
        }
        $newEnd = hc_medication_normalize_date((string) ($body['newEndDate'] ?? ''));
        if ($newEnd === null || !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($body['newEndDate'] ?? ''))) {
            hc_update_med_fail(422, 'Please choose a valid new end date.');
        }
        if ($newEnd < $today) {
            hc_update_med_fail(422, 'The new end date cannot be in the past.');
        }
        if ($c['startDate'] !== null && $newEnd < $c['startDate']) {
            hc_update_med_fail(422, 'The new end date cannot be before the start date.');
        }
        if ($c['endDate'] !== null && $newEnd <= $c['endDate']) {
            hc_update_med_fail(422, 'The new end date must be later than the current end date (' . hc_medication_fmt_date($c['endDate']) . ').');
        }
        $log = is_array($fields['extensions'] ?? null) ? $fields['extensions'] : [];
        $log[] = [
            'previousEndDate' => $c['endDate'],
            'newEndDate' => $newEnd,
            'reason' => $reason,
            'by' => $doctorName,
            'at' => $today,
        ];
        $fields['extensions'] = $log;
        $fields['extendedEndDate'] = $newEnd;
        $event = match (true) {
            $c['state'] === 'ended' || $c['state'] === 'lapsed' => 'Medication renewed',
            $c['endDate'] === null => 'End date set',
            default => 'Medication extended',
        };
        $detail = match ($event) {
            'Medication renewed' => 'Renewed ',
            'End date set' => 'Set an end date for ',
            default => 'Extended ',
        } . $label . ' until ' . hc_medication_fmt_date($newEnd)
            . ($c['endDate'] !== null ? ' (was ' . hc_medication_fmt_date($c['endDate']) . ')' : '')
            . ' by ' . $doctorName . '.' . ($reason !== '' ? ' Reason: ' . $reason : '');
        $color = 'blue';
        break;

    case 'confirm':
        if (!in_array($c['state'], ['ended', 'lapsed'], true)) {
            hc_update_med_fail(409, 'Only a medication whose treatment period has ended can be confirmed as completed.');
        }
        $fields['status'] = 'completed';
        $fields['completionConfirmedBy'] = $doctorName;
        $fields['completionConfirmedDate'] = $today;
        if ($reason !== '') {
            $fields['completionNote'] = $reason;
        }
        $event = 'Treatment completed';
        $detail = 'Completion of ' . $label . ' confirmed by ' . $doctorName . '.' . ($reason !== '' ? ' ' . $reason : '');
        $color = 'green';
        break;
}

if (!$firestore->setDocument('medications', $id, $fields)) {
    hc_update_med_fail(500, 'Could not save the change. Please try again.');
}

hc_record_medication_history($patientId, $event, $detail, $color, $id);
write_audit_log('updated', 'Patient Management', $detail);
if ($patientId !== '') {
    hc_record_patient_history($patientId, 'medication', 'updated', $detail);
}

echo json_encode(['success' => true]);
