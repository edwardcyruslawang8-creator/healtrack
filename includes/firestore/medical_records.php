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

        $rows = array_values($firestore->listDocumentsWhere('medical_records', 'patientId', $patientId));
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
     * callers filter to 'Active' for the Overview tab's card), newest
     * diagnosis first. 'details' (Notes) is real — written since
     * api/create-patient.php's registration flow, and by
     * api/add-condition.php/api/update-condition.php now too — just
     * never surfaced by this reader until the Medical History tab
     * (subadmin/doctor/medical-staff's medical_history.php) needed it.
     *
     * 'diagnosedOnRaw' is the real ISO date (same value stored in
     * Firestore) alongside 'diagnosedOn's human-formatted display
     * version — assets/js/medical-history.js needs the raw ISO form to
     * pre-fill the Edit modal's <input type="date">, which can't parse
     * "Feb 15, 2024".
     *
     * @return array<int, array{id: string, label: string, diagnosedOn: string, diagnosedOnRaw: string, status: string, details: ?string}>
     */
    function hc_get_medical_conditions_for_patient(string $patientId): array
    {
        global $firestore;

        $rows = array_values($firestore->listDocumentsWhere('medical_conditions', 'patientId', $patientId));
        usort($rows, fn ($a, $b) => strcmp($b['fields']['diagnosedOn'] ?? '', $a['fields']['diagnosedOn'] ?? ''));

        return array_map(fn ($doc) => [
            'id' => $doc['id'],
            'label' => $doc['fields']['label'],
            'diagnosedOn' => date('M j, Y', strtotime($doc['fields']['diagnosedOn'])),
            'diagnosedOnRaw' => date('Y-m-d', strtotime($doc['fields']['diagnosedOn'])),
            'status' => $doc['fields']['status'],
            'details' => $doc['fields']['details'] ?? null,
        ], $rows);
    }
}

if (!function_exists('hc_get_surgical_history_for_patient')) {
    /**
     * Every surgical_history/{id} doc for $patientId, newest first. This
     * collection has been written since the Add Patient wizard's Step 3
     * (api/create-patient.php) but had no reader anywhere until the
     * Medical History tab needed one — see that function's own comment.
     * 'date' prefers the real 'datePerformed' (only ever set by
     * api/add-surgery.php/api/update-surgery.php, where the actual
     * surgery date is a real field on the Add/Edit form) over the
     * registration wizard's 'dateRecorded' (just stamps the day the
     * patient was registered, not necessarily when the surgery actually
     * happened) — so a legacy registration-time row still shows SOME
     * real date instead of nothing. 'surgeonFacility' is null for every
     * row written before this field existed.
     *
     * 'datePerformedRaw' is the real ISO 'datePerformed' value ONLY
     * (never falls back to 'dateRecorded') — for pre-filling the Edit
     * modal's <input type="date"> honestly: a legacy row that only has
     * 'dateRecorded' (when it was registered, not when the surgery
     * actually happened) should open Edit with the Date field BLANK,
     * not silently promote that registration timestamp into looking
     * like a real chosen surgery date.
     *
     * @return array<int, array{id: string, procedure: string, date: string, datePerformedRaw: ?string, surgeonFacility: ?string, details: ?string}>
     */
    function hc_get_surgical_history_for_patient(string $patientId): array
    {
        global $firestore;

        $rows = array_values($firestore->listDocumentsWhere('surgical_history', 'patientId', $patientId));
        usort($rows, fn ($a, $b) => strcmp(
            $b['fields']['datePerformed'] ?? $b['fields']['dateRecorded'] ?? '',
            $a['fields']['datePerformed'] ?? $a['fields']['dateRecorded'] ?? ''
        ));

        return array_map(function ($doc) {
            $f = $doc['fields'];
            $rawDate = $f['datePerformed'] ?? $f['dateRecorded'] ?? null;

            return [
                'id' => $doc['id'],
                'procedure' => $f['procedure'],
                'date' => $rawDate !== null ? date('M j, Y', strtotime($rawDate)) : '—',
                'datePerformedRaw' => isset($f['datePerformed']) ? date('Y-m-d', strtotime($f['datePerformed'])) : null,
                'surgeonFacility' => $f['surgeonFacility'] ?? null,
                'details' => $f['details'] ?? null,
            ];
        }, $rows);
    }
}
