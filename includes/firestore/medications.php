<?php
/**
 * =====================================================================
 * Shared data layer — Medications
 * ---------------------------------------------------------------------
 * Pure Firestore-query functions for the real "medications" and
 * "medication_history" collections — see includes/firestore/patients.php's
 * header comment for why this layer exists and what it does/doesn't
 * own.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore

if (!function_exists('hc_get_medications_for_patient')) {
    /**
     * Every medications/{id} doc for $patientId, newest started first.
     *
     * @return array<int, array{id: string, name: string, category: string, dosage: string, frequency: string, route: string, indication: string, startDate: string, endDate: ?string, endDateNote: ?string, prescribedBy: string, notes: string, status: string}>
     */
    function hc_get_medications_for_patient(string $patientId): array
    {
        global $firestore;

        $docs = array_values($firestore->listDocumentsWhere('medications', 'patientId', $patientId));
        usort($docs, fn ($a, $b) => strcmp($b['fields']['startDate'] ?? '', $a['fields']['startDate'] ?? ''));

        return array_map(function ($doc) {
            $f = $doc['fields'];
            return [
                'id' => $doc['id'],
                'name' => $f['name'],
                'category' => $f['category'],
                'dosage' => $f['dosage'],
                'frequency' => $f['frequency'],
                'route' => $f['route'],
                'indication' => $f['indication'],
                'startDate' => date('M j, Y', strtotime($f['startDate'])),
                'endDate' => $f['endDate'] ? date('M j, Y', strtotime($f['endDate'])) : null,
                'endDateNote' => $f['endDateNote'],
                'prescribedBy' => $f['prescribedBy'],
                'notes' => $f['notes'],
                'status' => $f['status'],
            ];
        }, $docs);
    }
}

if (!function_exists('hc_get_medication_history_for_patient')) {
    /**
     * Every medication_history/{id} doc for $patientId, newest first.
     *
     * @return array<int, array{date: string, event: string, detail: string, indicatorColor: string}>
     */
    function hc_get_medication_history_for_patient(string $patientId): array
    {
        global $firestore;

        $docs = array_values($firestore->listDocumentsWhere('medication_history', 'patientId', $patientId));
        usort($docs, fn ($a, $b) => strcmp($b['fields']['date'] ?? '', $a['fields']['date'] ?? ''));

        return array_map(fn ($doc) => [
            'date' => date('M j, Y', strtotime($doc['fields']['date'])),
            'event' => $doc['fields']['event'],
            'detail' => $doc['fields']['detail'],
            'indicatorColor' => $doc['fields']['indicatorColor'],
        ], $docs);
    }
}

if (!function_exists('hc_medications_summary')) {
    /** Real counts/summary over an already-fetched $medications list, plus whether this patient has any allergy on record. */
    function hc_medications_summary(array $medications, bool $hasAllergies): array
    {
        $active = array_filter($medications, fn ($m) => $m['status'] === 'active');

        return [
            'activeMedications' => count($active),
            'noKnownAllergicReactions' => $hasAllergies ? 'No — see Allergies tab' : 'Yes',
            'lastUpdated' => $medications[0]['startDate'] ?? '—',
            'lastUpdatedBy' => $medications[0]['prescribedBy'] ?? '—',
        ];
    }
}
