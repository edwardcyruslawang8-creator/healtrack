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

if (!function_exists('hc_vaccine_type_meta')) {
    /**
     * Vaccine category key -> label/icon/color for the Vaccination
     * Records pages' type badge (subadmin/doctor/medical-staff), and the
     * allowed "type" values for api/add-vaccination-record.php. The
     * first four keys are the original seeded ones; the rest match
     * includes/subadmin/vaccine_reports_firestore.php's $vrTypeMeta keys.
     *
     * @return array<string, array{label: string, icon: string, bg: string, color: string}>
     */
    function hc_vaccine_type_meta(): array
    {
        $icon = 'bi-shield-fill-check';
        return [
            'covid19'            => ['label' => 'COVID-19',                 'icon' => $icon, 'bg' => '#F1E8FD', 'color' => '#8B5CF6'],
            'tetanus'            => ['label' => 'Tetanus',                  'icon' => $icon, 'bg' => '#E8F0FE', 'color' => '#1A73E8'],
            'influenza'          => ['label' => 'Influenza',                'icon' => $icon, 'bg' => '#E3F6FB', 'color' => '#12A6C6'],
            'hepatitis_b'        => ['label' => 'Hepatitis B',              'icon' => $icon, 'bg' => '#E4F7EA', 'color' => '#1E8E3E'],
            'tetanus_diphtheria' => ['label' => 'Tetanus-Diphtheria (Td)',  'icon' => $icon, 'bg' => '#E8F0FE', 'color' => '#1558B0'],
            'polio'              => ['label' => 'Polio (OPV/IPV)',          'icon' => $icon, 'bg' => '#E0F5F6', 'color' => '#0E9CA8'],
            'pneumococcal'       => ['label' => 'Pneumococcal',             'icon' => $icon, 'bg' => '#FDECDD', 'color' => '#E8710A'],
            'measles'            => ['label' => 'Measles (MR/MMR)',         'icon' => $icon, 'bg' => '#FCE4E4', 'color' => '#D93025'],
            'bcg'                => ['label' => 'BCG',                      'icon' => $icon, 'bg' => '#FBEFD9', 'color' => '#B45309'],
            'pentavalent'        => ['label' => 'Pentavalent (DPT-HepB-Hib)', 'icon' => $icon, 'bg' => '#EEF0FE', 'color' => '#6366F1'],
            'hpv'                => ['label' => 'HPV',                      'icon' => $icon, 'bg' => '#FCE7F3', 'color' => '#DB2777'],
            'rabies'             => ['label' => 'Rabies',                   'icon' => $icon, 'bg' => '#F3EDE7', 'color' => '#8B5E34'],
            'other'              => ['label' => 'Other',                    'icon' => $icon, 'bg' => '#EEF0F3', 'color' => '#6B7280'],
        ];
    }
}

if (!function_exists('hc_get_all_vaccination_record_docs')) {
    /**
     * @internal Every raw vaccination_records/{id} doc across ALL
     * patients, unfiltered — for cross-patient aggregates (e.g.
     * firebase/firestore.php's getDashboardStats()), unlike
     * hc_get_vaccination_records_for_patient() below which scopes to
     * one patient's own profile pages.
     *
     * Every current caller counts doses ADMINISTERED (dashboard stats,
     * facility analytics, Vaccine Reports), so "historical" entries —
     * vaccines a patient received before registering / elsewhere,
     * entered from a vaccination card — are left out unless
     * $includeHistorical is true.
     */
    function hc_get_all_vaccination_record_docs(bool $includeHistorical = false): array
    {
        global $firestore;

        $docs = $firestore->listDocuments('vaccination_records');
        if ($includeHistorical) {
            return $docs;
        }

        return array_values(array_filter($docs, fn ($doc) => ($doc['fields']['recordType'] ?? '') !== 'historical'));
    }
}

if (!function_exists('hc_get_vaccination_records_for_patient')) {
    /**
     * Every vaccination_records/{id} doc for $patientId, newest
     * administered first.
     *
     * @return array<int, array{id: string, name: string, type: string, description: string, status: string, dateAdministered: string, dose: string, manufacturer: string, lotNumber: string, expiryDate: string, givenBy: string, facility: string, route: string, site: string, notes: string, nextDoseDue: ?string, recordType: string}>
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
                // "historical" (received before registration / elsewhere,
                // entered from a vaccination card etc.) or
                // "administered_here". Older records predate this field
                // and were all given at the facility.
                'recordType' => $f['recordType'] ?? 'administered_here',
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
            'recordType' => 'administered_here',
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
