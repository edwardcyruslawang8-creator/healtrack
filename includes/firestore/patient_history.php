<?php
/**
 * =====================================================================
 * Shared data layer — Patient Record History & Activity Log
 * ---------------------------------------------------------------------
 * Pure Firestore-query functions for the real "patient_record_history"
 * and "patient_activity_log" collections — see includes/firestore/patients.php's
 * header comment for why this layer exists and what it does/doesn't
 * own.
 *
 * hc_record_patient_history()/hc_record_patient_activity() are the
 * WRITE side — called from every real api endpoint that changes
 * something on a patient's record (consultations, lab/x-ray results,
 * allergies, documents, dispensing), so doctor/medical-staff/subadmin's
 * History tab (each role's own history_firestore.php data file)
 * actually reflects real activity instead of staying frozen on old
 * seed data. Both are
 * fire-and-forget by design — a history-logging failure must never
 * fail the real action it's describing, so neither throws; a failed
 * write here just means that one entry doesn't show up later, caught
 * via error_log() like every other best-effort write in this app.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore

if (!function_exists('hc_get_record_history_for_patient')) {
    /**
     * Every patient_record_history/{id} doc for $patientId, newest first.
     *
     * @return array<int, array{date: string, time: string, type: string, action: string, details: string, changedBy: string}>
     */
    function hc_get_record_history_for_patient(string $patientId): array
    {
        global $firestore;

        $docs = array_values($firestore->listDocumentsWhere('patient_record_history', 'patientId', $patientId));
        usort($docs, fn ($a, $b) => strcmp($b['fields']['date'] ?? '', $a['fields']['date'] ?? '') ?: strcmp($b['fields']['time'] ?? '', $a['fields']['time'] ?? ''));

        return array_map(fn ($doc) => [
            'date' => date('M j, Y', strtotime($doc['fields']['date'])),
            'time' => $doc['fields']['time'],
            'type' => $doc['fields']['type'],
            'action' => $doc['fields']['action'],
            'details' => $doc['fields']['details'],
            'changedBy' => $doc['fields']['changedBy'],
        ], $docs);
    }
}

if (!function_exists('hc_get_activity_log_for_patient')) {
    /**
     * Every patient_activity_log/{id} doc for $patientId, newest first.
     *
     * @return array<int, array{date: string, time: string, activity: string, performedBy: string}>
     */
    function hc_get_activity_log_for_patient(string $patientId): array
    {
        global $firestore;

        $docs = array_values($firestore->listDocumentsWhere('patient_activity_log', 'patientId', $patientId));
        usort($docs, fn ($a, $b) => strcmp($b['fields']['date'] ?? '', $a['fields']['date'] ?? '') ?: strcmp($b['fields']['time'] ?? '', $a['fields']['time'] ?? ''));

        return array_map(fn ($doc) => [
            'date' => date('M j, Y', strtotime($doc['fields']['date'])),
            'time' => $doc['fields']['time'],
            'activity' => $doc['fields']['activity'],
            'performedBy' => $doc['fields']['performedBy'],
        ], $docs);
    }
}

if (!function_exists('hc_record_patient_history')) {
    /**
     * Writes one real patient_record_history/{autoId} doc — a single
     * row in the History tab's "Record History" table.
     *
     * @param string $type   One of: patient_profile, medical_record,
     *                       laboratory_result, medication, document,
     *                       vaccination_record, allergy (the exact keys
     *                       each role's history_firestore.php data file's
     *                       own $recordTypeMeta maps to a label/icon).
     * @param string $action One of: added, updated, uploaded, deleted,
     *                       modified (each role's history_firestore.php's
     *                       own $actionMeta keys).
     */
    function hc_record_patient_history(string $patientId, string $type, string $action, string $details): void
    {
        global $firestore;

        if (!isset($firestore) || $patientId === '') {
            return;
        }

        $now = new DateTime();
        $firestore->addDocument('patient_record_history', [
            'patientId' => $patientId,
            'date' => $now->format('Y-m-d'),
            'time' => $now->format('g:i A'),
            'type' => $type,
            'action' => $action,
            'details' => $details,
            'changedBy' => $_SESSION['name'] ?? 'System',
        ]);
    }
}

if (!function_exists('hc_record_patient_activity')) {
    /**
     * Writes one real patient_activity_log/{autoId} doc — a single row
     * in the History tab's "Activity Log" table.
     */
    function hc_record_patient_activity(string $patientId, string $activity): void
    {
        global $firestore;

        if (!isset($firestore) || $patientId === '') {
            return;
        }

        $now = new DateTime();
        $firestore->addDocument('patient_activity_log', [
            'patientId' => $patientId,
            'date' => $now->format('Y-m-d'),
            'time' => $now->format('g:i A'),
            'activity' => $activity,
            'performedBy' => $_SESSION['name'] ?? 'System',
        ]);
    }
}
