<?php
/**
 * =====================================================================
 * Shared data layer — Allergies
 * ---------------------------------------------------------------------
 * Pure Firestore-query functions for the real "allergies" collection —
 * see includes/firestore/patients.php's header comment for why this layer
 * exists and what it does/doesn't own.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore

if (!function_exists('hc_get_allergies_for_patient')) {
    /**
     * Every allergies/{id} doc for $patientId, newest added first.
     *
     * @return array<int, array{id: string, allergen: string, subtype: ?string, type: string, icon: string, iconBg: string, iconColor: string, reaction: string, severity: string, onsetDate: string, lastOccurred: string, addedOn: string, updatedOn: string, status: string, notes: string, recommendations: string, summary: string, description: string, symptoms: array, history: array}>
     */
    function hc_get_allergies_for_patient(string $patientId): array
    {
        global $firestore;

        $docs = array_values($firestore->listDocumentsWhere('allergies', 'patientId', $patientId));
        usort($docs, fn ($a, $b) => strcmp($b['fields']['addedOn'] ?? '', $a['fields']['addedOn'] ?? ''));

        return array_map(function ($doc) {
            $f = $doc['fields'];
            return [
                'id' => $doc['id'],
                'allergen' => $f['allergen'],
                'subtype' => $f['subtype'],
                'type' => $f['type'],
                'icon' => $f['icon'],
                'iconBg' => $f['iconBg'],
                'iconColor' => $f['iconColor'],
                'reaction' => $f['reaction'],
                'severity' => $f['severity'],
                'onsetDate' => date('M j, Y', strtotime($f['onsetDate'])),
                'lastOccurred' => date('M j, Y', strtotime($f['lastOccurred'])),
                'addedOn' => date('M j, Y', strtotime($f['addedOn'])),
                // Last status change (api/update-allergy-status.php);
                // records never changed since being added fall back to addedOn.
                'updatedOn' => date('M j, Y', strtotime($f['statusUpdatedOn'] ?? $f['addedOn'])),
                'status' => $f['status'],
                'notes' => $f['notes'],
                'recommendations' => $f['recommendations'],
                'summary' => $f['summary'],
                'description' => $f['description'],
                'symptoms' => $f['symptoms'] ?? [],
                'history' => array_map(fn ($h) => ['date' => date('M j, Y', strtotime($h['date'])), 'event' => $h['event'], 'details' => $h['details']], $f['history'] ?? []),
            ];
        }, $docs);
    }
}

if (!function_exists('hc_empty_allergy')) {
    function hc_empty_allergy(): array
    {
        return [
            'id' => '', 'allergen' => 'No allergies on record.', 'subtype' => null, 'type' => '—',
            'icon' => 'bi-shield-fill-check', 'iconBg' => '#EEF0F3', 'iconColor' => '#6B7280',
            'reaction' => '—', 'severity' => 'mild', 'onsetDate' => '—', 'lastOccurred' => '—', 'addedOn' => '—', 'updatedOn' => '—',
            'status' => 'resolved', 'notes' => '—', 'recommendations' => '—', 'summary' => 'No allergies on record for this patient.',
            'description' => '—', 'symptoms' => [], 'history' => [],
        ];
    }
}

if (!function_exists('hc_resolve_selected_allergy')) {
    /** Picks the row matching $recordId out of $allergyRecords, falling back to the first (most recently added) row, or the empty-state shape if there are none. */
    function hc_resolve_selected_allergy(array $allergyRecords, ?string $recordId): array
    {
        if ($recordId !== null && $recordId !== '') {
            foreach ($allergyRecords as $rec) {
                if ($rec['id'] === $recordId) {
                    return $rec;
                }
            }
        }

        return $allergyRecords[0] ?? hc_empty_allergy();
    }
}

if (!function_exists('hc_allergy_type_meta')) {
    /**
     * Allergy Type -> table icon/colors, for allergies added from the
     * medical-staff "Add Allergy Record" modal (api/create-allergy-record.php).
     * "Other" matches the icon/colors api/create-patient.php and
     * api/add-allergy.php already store.
     *
     * @return array<string, array{icon: string, iconBg: string, iconColor: string}>
     */
    function hc_allergy_type_meta(): array
    {
        return [
            'Environmental' => ['icon' => 'bi-tree-fill',                 'iconBg' => '#E4F7EA', 'iconColor' => '#1E8E3E'],
            'Food'          => ['icon' => 'bi-egg-fried',                 'iconBg' => '#FDECDD', 'iconColor' => '#E8710A'],
            'Medication'    => ['icon' => 'bi-capsule',                   'iconBg' => '#FCE4E4', 'iconColor' => '#D93025'],
            'Other'         => ['icon' => 'bi-exclamation-triangle-fill', 'iconBg' => '#FDECDD', 'iconColor' => '#E8710A'],
        ];
    }
}

if (!function_exists('hc_allergy_summary')) {
    /**
     * Real counts/summary over an already-fetched $allergyRecords list.
     *
     * - totalAllergies: every record, active or not — each record is
     *   counted once no matter which table it's in.
     * - activeAllergies: status "active" only.
     * - severeAllergies: ACTIVE severe allergies only ("Requires
     *   caution") — a resolved severe allergy no longer needs it.
     * - lastUpdated: newest of any record's added date / last status change.
     */
    function hc_allergy_summary(array $allergyRecords): array
    {
        $total = count($allergyRecords);
        $active = array_filter($allergyRecords, fn ($a) => $a['status'] === 'active');

        $latest = 0;
        foreach ($allergyRecords as $a) {
            foreach ([$a['addedOn'], $a['updatedOn'] ?? $a['addedOn']] as $d) {
                $latest = max($latest, (int) strtotime($d));
            }
        }

        return [
            'totalAllergies' => $total,
            'activeAllergies' => count($active),
            'severeAllergies' => count(array_filter($active, fn ($a) => $a['severity'] === 'severe')),
            'lastUpdated' => $latest > 0 ? date('M j, Y', $latest) : '—',
            'status' => $total > 0 ? 'Up to date' : 'No records',
        ];
    }
}
