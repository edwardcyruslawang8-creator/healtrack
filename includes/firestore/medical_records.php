<?php
/**
 * =====================================================================
 * Shared data layer — Medical Records & Medical Conditions
 * ---------------------------------------------------------------------
 * Pure Firestore-query functions for the real "medical_records" and
 * "medical_conditions" collections — see includes/firestore/patients.php's
 * header comment for why this layer exists and what it does/doesn't
 * own.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore

if (!function_exists('hc_get_all_medical_record_docs')) {
    /**
     * @internal Every raw medical_records/{id} doc across ALL patients,
     * unfiltered — for cross-patient/facility aggregates (e.g.
     * includes/admin/facility_analytics_firestore.php, which filters
     * these by the real "facility" field each doc already carries),
     * unlike hc_get_medical_records_for_patient() below which scopes
     * to one patient's own profile pages.
     */
    function hc_get_all_medical_record_docs(): array
    {
        global $firestore;

        return $firestore->listDocuments('medical_records');
    }
}

if (!function_exists('hc_get_medical_records_for_patient')) {
    /**
     * Every medical_records/{id} doc for $patientId, newest first.
     *
     * @return array<int, array{id: string, type: string, title: string, date: string, time: string, provider: ?string, facility: string}>
     */
    function hc_get_medical_records_for_patient(string $patientId): array
    {
        global $firestore;

        $rows = array_values(array_filter(
            $firestore->listDocuments('medical_records'),
            fn ($doc) => ($doc['fields']['patientId'] ?? '') === $patientId
        ));
        usort($rows, fn ($a, $b) => strcmp($b['fields']['date'] ?? '', $a['fields']['date'] ?? '') ?: strcmp($b['fields']['time'] ?? '', $a['fields']['time'] ?? ''));

        return array_map(fn ($doc) => [
            'id' => $doc['id'],
            'type' => $doc['fields']['type'],
            'title' => $doc['fields']['title'],
            'date' => date('M j, Y', strtotime($doc['fields']['date'])),
            'time' => $doc['fields']['time'],
            'provider' => $doc['fields']['provider'],
            'facility' => $doc['fields']['facility'],
            // Overview-tab callers need these un-formatted; harmless extra keys for list callers.
            'chiefComplaint' => $doc['fields']['chiefComplaint'] ?? null,
            'diagnosis' => $doc['fields']['diagnosis'] ?? null,
            'vitals' => $doc['fields']['vitals'] ?? null,
        ], $rows);
    }
}

if (!function_exists('hc_format_vitals_summary')) {
    /**
     * A medical record's raw 'vitals' field (hc_get_medical_records_for_patient()
     * above) formatted as "Temp: 37.5°C • BP: 120/80 • HR: 78 bpm",
     * skipping any sub-field left blank — same exact formatting
     * includes/firestore/patient_overview.php's Overview-tab "latest
     * vitals" card already uses, pulled out here so
     * subadmin/medical_records.php's View Details modal can show the
     * same real data the same way instead of re-deriving it.
     * Null when there's no vitals data at all.
     */
    function hc_format_vitals_summary(?array $vitals): ?string
    {
        if ($vitals === null) {
            return null;
        }

        $parts = [];
        if (($vitals['temperature'] ?? '') !== '') {
            $parts[] = 'Temp: ' . $vitals['temperature'] . '°C';
        }
        if (($vitals['bloodPressureSystolic'] ?? '') !== '' && ($vitals['bloodPressureDiastolic'] ?? '') !== '') {
            $parts[] = 'BP: ' . $vitals['bloodPressureSystolic'] . '/' . $vitals['bloodPressureDiastolic'];
        }
        if (($vitals['heartRate'] ?? '') !== '') {
            $parts[] = 'HR: ' . $vitals['heartRate'] . ' bpm';
        }

        return $parts !== [] ? implode(' • ', $parts) : null;
    }
}

if (!function_exists('hc_get_medical_conditions_for_patient')) {
    /**
     * Every medical_conditions/{id} doc for $patientId (all statuses —
     * callers filter to 'Active' for the Overview tab's card).
     *
     * @return array<int, array{id: string, label: string, diagnosedOn: string, status: string}>
     */
    function hc_get_medical_conditions_for_patient(string $patientId): array
    {
        global $firestore;

        $rows = array_values(array_filter(
            $firestore->listDocuments('medical_conditions'),
            fn ($doc) => ($doc['fields']['patientId'] ?? '') === $patientId
        ));

        return array_map(fn ($doc) => [
            'id' => $doc['id'],
            'label' => $doc['fields']['label'],
            'diagnosedOn' => date('M j, Y', strtotime($doc['fields']['diagnosedOn'])),
            'status' => $doc['fields']['status'],
        ], $rows);
    }
}
