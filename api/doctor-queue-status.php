<?php
/**
 * =====================================================================
 * Doctor Queue Status (api/doctor-queue-status.php)
 * ---------------------------------------------------------------------
 * Polled every few seconds by assets/js/doctor_consultations.js on
 * doctor/consultations.php so the "Patient Information" / "Ongoing
 * Consultation" / "Next to Consult" section (#cnsQueueStatus) updates
 * itself in place — e.g. the moment medical staff sets a new
 * consultation for this doctor — without the doctor needing to
 * manually reload the page.
 *
 * Returns the exact same includes/doctor/_consultation_queue_cards.php
 * markup the page itself renders (via hc_get_doctor_queue_status(),
 * includes/firestore/consultations.php), plus nextId/ongoingId so the
 * client can cheaply tell whether anything actually changed before
 * touching the DOM — most polls return unchanged ids and the page does
 * nothing.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../includes/firestore/consultations.php';

header('Content-Type: application/json');

if (($_SESSION['role'] ?? null) !== 'doctor') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Not authorized.']);
    exit;
}

$myName = $_SESSION['name'] ?? '';

$consultationTypeMeta = hc_consultation_type_meta();
$consultationStatusMeta = hc_consultation_status_meta();
$consultationStatusMeta['ongoing']['label'] = 'In Progress'; // matches doctor/consultations.php's own override

$queueStatus = hc_get_doctor_queue_status($myName);
$patient = $queueStatus['patient'];
$ongoingConsult = $queueStatus['ongoingConsult'];
$ongoingPatient = $queueStatus['ongoingPatient'];
$nextConsult = $queueStatus['nextConsult'];
$nextPatient = $queueStatus['nextPatient'];

ob_start();
require __DIR__ . '/../includes/doctor/_consultation_queue_cards.php';
$html = ob_get_clean();

echo json_encode([
    'success' => true,
    'html' => $html,
    'nextId' => $nextConsult['id'] ?? '',
    'ongoingId' => $ongoingConsult['id'] ?? '',
]);
