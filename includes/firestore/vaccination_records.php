<?php
/**
 * =====================================================================
 * Shared data layer — Vaccination Records
 * ---------------------------------------------------------------------
 * Pure Firestore-query functions for the real "vaccination_records"
 * collection — see includes/firestore/patients.php's header comment for why
 * this layer exists and what it does/doesn't own.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore

if (!function_exists('hc_get_all_vaccination_record_docs')) {
    /**
     * @internal Every raw vaccination_records/{id} doc across ALL
     * patients, unfiltered — for cross-patient aggregates (e.g.
     * firebase/firestore.php's getDashboardStats()), unlike
     * hc_get_vaccination_records_for_patient() below which scopes to
     * one patient's own profile pages.
     */
    function hc_get_all_vaccination_record_docs(): array
    {
        global $firestore;

        return $firestore->listDocuments('vaccination_records');
    }
}

if (!function_exists('hc_get_vaccination_records_for_patient')) {
    /**
     * Every vaccination_records/{id} doc for $patientId, newest
     * administered first.
     *
     * @return array<int, array{id: string, name: string, type: string, description: string, status: string, dateAdministered: string, dose: string, manufacturer: string, lotNumber: string, expiryDate: string, givenBy: string, facility: string, route: string, site: string, notes: string, nextDoseDue: ?string}>
     */
    function hc_get_vaccination_records_for_patient(string $patientId): array
    {
        global $firestore;

        $rows = array_values(array_filter(
            $firestore->listDocuments('vaccination_records'),
            fn ($doc) => ($doc['fields']['patientId'] ?? '') === $patientId
        ));
        usort($rows, fn ($a, $b) => strcmp($b['fields']['dateAdministered'] ?? '', $a['fields']['dateAdministered'] ?? ''));

        return array_map(function ($doc) {
            $f = $doc['fields'];
            return [
                'id' => $doc['id'],
                'name' => $f['name'],
                'type' => $f['type'],
                'description' => $f['description'],
                'status' => $f['status'],
                'dateAdministered' => $f['dateAdministered'] ? date('M j, Y', strtotime($f['dateAdministered'])) : 'Not yet given',
                'dose' => $f['dose'],
                'manufacturer' => $f['manufacturer'],
                'lotNumber' => $f['lotNumber'],
                'expiryDate' => $f['expiryDate'] ? date('M j, Y', strtotime($f['expiryDate'])) : '—',
                'givenBy' => $f['givenBy'],
                'facility' => $f['facility'],
                'route' => $f['route'],
                'site' => $f['site'],
                'notes' => $f['notes'],
                'nextDoseDue' => $f['nextDoseDue'] ? date('M j, Y', strtotime($f['nextDoseDue'])) : null,
            ];
        }, $rows);
    }
}

if (!function_exists('hc_empty_vaccine_record')) {
    /** Empty-state shape for pages that always need a "selected" record, even with zero real ones. */
    function hc_empty_vaccine_record(): array
    {
        return [
            'id' => '', 'name' => 'No vaccination records yet.', 'type' => '', 'description' => '', 'status' => 'pending',
            'dateAdministered' => '—', 'dose' => '—', 'manufacturer' => '—', 'lotNumber' => '—', 'expiryDate' => '—',
            'givenBy' => '—', 'facility' => '—', 'route' => '—', 'site' => '—', 'notes' => '—', 'nextDoseDue' => null,
        ];
    }
}

if (!function_exists('hc_resolve_selected_vaccine_record')) {
    /** Picks the row matching $recordId out of $vaccinationRecords, falling back to the first (most recent) row, or the empty-state shape if there are none. */
    function hc_resolve_selected_vaccine_record(array $vaccinationRecords, ?string $recordId): array
    {
        if ($recordId !== null && $recordId !== '') {
            foreach ($vaccinationRecords as $rec) {
                if ($rec['id'] === $recordId) {
                    return $rec;
                }
            }
        }

        return $vaccinationRecords[0] ?? hc_empty_vaccine_record();
    }
}
