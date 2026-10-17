<?php
/**
 * =====================================================================
 * Shared data layer — Patients
 * ---------------------------------------------------------------------
 * Pure Firestore-query functions for the real "patients" collection —
 * no role-awareness at all. Every role's page-data file (subadmin/,
 * medical-staff/, ...) calls these instead of each running its own
 * copy of the same listDocuments()/getDocument() + array_map/usort
 * chain. A patient looks the same to every role, so the query for one
 * only needs to exist once.
 *
 * Callers still own anything role-specific: which fields a page shows,
 * $_GET-driven search/filter/pagination, and how "not found" is
 * displayed.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore

if (!function_exists('hc_map_patient_summary_doc')) {
    /**
     * One raw patients/{id} doc -> the list-row shape Patient Management
     * pages need — used by hc_get_all_patients_summary() below.
     *
     * @return array{id: string, name: string, age: int, gender: string, contact: string, address: string, dateRegisteredRaw: string, dateRegistered: string, initialReasonForVisit: string, birthDate: string, facility: string, primaryPhysician: ?string, intakeVitals: array}
     */
    function hc_map_patient_summary_doc(array $doc): array
    {
        $f = $doc['fields'];

        return [
            'id' => $doc['id'],
            'name' => $f['name'] ?? '(no name)',
            'age' => (int) ($f['age'] ?? 0),
            'gender' => $f['gender'] ?? '—',
            'contact' => $f['contact'] ?? '—',
            'address' => $f['address'] ?? '—',
            'dateRegisteredRaw' => $f['dateRegistered'] ?? '',
            'dateRegistered' => isset($f['dateRegistered']) ? date('M j, Y', strtotime($f['dateRegistered'])) : '—',
            'initialReasonForVisit' => $f['initialReasonForVisit'] ?? '—',
            'birthDate' => isset($f['birthDate']) ? date('M j, Y', strtotime($f['birthDate'])) : '—',
            'facility' => $f['facility'] ?? '',
            // Informational only — the name of whoever registered this
            // patient (staff or doctor, api/create-patient.php sets this
            // unconditionally to $_SESSION['name']), not necessarily a
            // real doctor account. Shown on the Overview tab as
            // "Primary Physician"; does not gate who can view this
            // patient's chart.
            'primaryPhysician' => $f['primaryPhysician'] ?? null,
            // Registration-time Vital Signs snapshot (Add Patient
            // modal's Step 2) — used by medical-staff/consultations.php's
            // "Set Consultation" modal to auto-fill vitals ONLY when
            // this patient was registered earlier TODAY, so a same-day
            // walk-in doesn't need vitals taken twice within minutes
            // of each other. An older patient's stale intake reading
            // is never reused for a new visit.
            'intakeVitals' => is_array($f['intakeVitals'] ?? null) ? $f['intakeVitals'] : [],
        ];
    }
}

if (!function_exists('hc_get_all_patients_summary')) {
    /**
     * Every patients/{id} doc in the list-row shape Patient Management
     * pages need, newest registration first — a full collection read.
     * Every role's Patient Management page calls this and then filters
     * the result to its own signed-in user's real facility (see e.g.
     * includes/medical-staff/patient_management_medstaff_firestore.php)
     * before doing anything else with it — a WHERE facility==X query
     * combined with ORDER BY dateRegistered would need a Firestore
     * composite index this project's service account has no permission
     * to deploy (confirmed live: a 400 FAILED_PRECONDITION asking for
     * one, then a 403 PERMISSION_DENIED attempting to create it), so
     * facility scoping happens in PHP over a full read instead of via a
     * narrower server-side query.
     *
     * @return array<int, array{id: string, name: string, age: int, gender: string, contact: string, address: string, dateRegisteredRaw: string, dateRegistered: string, initialReasonForVisit: string, birthDate: string, facility: string, intakeVitals: array}>
     */
    function hc_get_all_patients_summary(): array
    {
        global $firestore;

        $patients = array_map('hc_map_patient_summary_doc', $firestore->listDocuments('patients'));

        usort($patients, fn ($a, $b) => strcmp($b['dateRegisteredRaw'], $a['dateRegisteredRaw']));

        return $patients;
    }
}

if (!function_exists('hc_get_patient_profile')) {
    /**
     * One patient in the full Patient Profile header-card shape, or
     * null if $id is blank or doesn't match any real patient doc.
     *
     * @return array{id: string, name: string, gender: string, age: int, birthDate: string, phone: string, email: string, address: string, bloodType: string, civilStatus: string, occupation: string, registeredOn: string, lastVisit: string, primaryPhysician: string, facility: string}|null
     */
    function hc_get_patient_profile(string $id): ?array
    {
        global $firestore;

        if ($id === '') {
            return null;
        }

        $doc = $firestore->getDocument('patients', $id);
        if ($doc === null) {
            return null;
        }

        return [
            'id' => $id,
            'name' => $doc['name'] ?? '(no name)',
            'gender' => $doc['gender'] ?? '—',
            'age' => (int) ($doc['age'] ?? 0),
            'birthDate' => isset($doc['birthDate']) ? date('M j, Y', strtotime($doc['birthDate'])) : '—',
            'phone' => $doc['contact'] ?? '—',
            'email' => $doc['email'] ?? '—',
            'address' => $doc['address'] ?? '—',
            'bloodType' => $doc['bloodType'] ?? '—',
            'civilStatus' => $doc['civilStatus'] ?? '—',
            'occupation' => $doc['occupation'] ?? '—',
            'registeredOn' => isset($doc['dateRegistered']) ? date('M j, Y', strtotime($doc['dateRegistered'])) : '—',
            'lastVisit' => isset($doc['lastVisit']) ? date('M j, Y', strtotime($doc['lastVisit'])) : '—',
            'primaryPhysician' => $doc['primaryPhysician'] ?? '—',
            // Not shown on the profile card itself — callers use this to
            // enforce facility isolation (a patient belongs to exactly
            // one facility; a direct ?id= link must not expose one
            // facility's patient to another facility's staff) before
            // ever rendering the rest of this array.
            'facility' => $doc['facility'] ?? '',
        ];
    }
}

if (!function_exists('hc_not_found_patient_profile')) {
    /** The "Patient Not Found" fallback shape, same keys as hc_get_patient_profile(). */
    function hc_not_found_patient_profile(string $requestedId): array
    {
        return [
            'id' => $requestedId !== '' ? $requestedId : '(none)',
            'name' => 'Patient Not Found', 'gender' => '—', 'age' => 0, 'birthDate' => '—',
            'phone' => '—', 'email' => '—', 'address' => '—', 'bloodType' => '—',
            'civilStatus' => '—', 'occupation' => '—', 'registeredOn' => '—',
            'lastVisit' => '—', 'primaryPhysician' => '—', 'facility' => '',
        ];
    }
}

if (!function_exists('hc_patient_age_group')) {
    /** Shared age-bucketing used by every "Patients by Age Group" widget. */
    function hc_patient_age_group(int $age): string
    {
        return match (true) {
            $age <= 12 => '0-12',
            $age <= 17 => '13-17',
            $age <= 30 => '18-30',
            $age <= 45 => '31-45',
            $age <= 60 => '46-60',
            default => '60+',
        };
    }
}
