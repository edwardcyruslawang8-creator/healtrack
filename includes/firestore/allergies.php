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
require_once __DIR__ . '/../shared/inventory_match.php'; // hc_normalize_text()

if (!function_exists('hc_text_mentions_possible_allergy')) {
    /**
     * Whether free text (e.g. a consultation's chiefComplaint) mentions
     * a possible allergy at all — a generic trigger-word check, NOT an
     * attempt to extract which allergen. Same simple case-insensitive
     * substring convention as includes/shared/predictive_analytics_core.php's
     * hc_notifiable_cases_from_records() (hc_normalize_text() + str_contains(),
     * no regex/word-boundary/stemming) — used by
     * doctor/active_consultation.php to prompt the doctor to confirm/add
     * a real allergy record via the existing api/add-allergy.php mini-form
     * when Save & End is clicked, rather than silently creating anything.
     */
    function hc_text_mentions_possible_allergy(string $text): bool
    {
        $normalized = hc_normalize_text($text);
        if ($normalized === '') {
            return false;
        }

        $triggerWords = ['allergic', 'allergy', 'allergies', 'hypersensitive', 'anaphylaxis', 'anaphylactic'];
        foreach ($triggerWords as $word) {
            if (str_contains($normalized, $word)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('hc_get_allergies_for_patient')) {
    /**
     * Every allergies/{id} doc for $patientId, newest added first.
     *
     * @return array<int, array{id: string, allergen: string, subtype: ?string, type: string, icon: string, iconBg: string, iconColor: string, reaction: string, severity: string, onsetDate: string, onsetDateRaw: string, lastOccurred: string, addedOn: string, updatedOn: string, status: string, clinicalStatus: string, verificationStatus: string, notes: string, recommendations: string, summary: string, description: string, symptoms: array, history: array}>
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
                'onsetDateRaw' => $f['onsetDate'],
                'lastOccurred' => date('M j, Y', strtotime($f['lastOccurred'])),
                'addedOn' => date('M j, Y', strtotime($f['addedOn'])),
                // Last status change (api/update-allergy-clinical-status.php);
                // records never changed since being added fall back to addedOn.
                'updatedOn' => date('M j, Y', strtotime($f['statusUpdatedOn'] ?? $f['addedOn'])),
                'status' => $f['status'],
                // clinicalStatus/verificationStatus are new fields — a doc
                // written before this feature existed has neither, so both
                // are backfilled here on read rather than requiring a data
                // migration: clinicalStatus derives from the legacy
                // 2-value 'status' field (an 'inactive' value never
                // existed pre-feature, so there's nothing to derive it
                // from), and an unreviewed old record defaults to the
                // most conservative verification value, 'unconfirmed' —
                // never silently treated as Confirmed/Refuted.
                'clinicalStatus' => $f['clinicalStatus'] ?? ($f['status'] === 'active' ? 'active' : 'resolved'),
                'verificationStatus' => $f['verificationStatus'] ?? 'unconfirmed',
                'notes' => $f['notes'],
                'recommendations' => $f['recommendations'],
                'summary' => $f['summary'],
                'description' => $f['description'],
                'symptoms' => $f['symptoms'] ?? [],
                'history' => array_map(function ($h) {
                    $h['date'] = date('M j, Y', strtotime($h['date']));
                    if (!empty($h['assessmentDate'])) {
                        $h['assessmentDate'] = date('M j, Y', strtotime($h['assessmentDate']));
                    }
                    return $h;
                }, $f['history'] ?? []),
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
            'reaction' => '—', 'severity' => 'mild', 'onsetDate' => '—', 'onsetDateRaw' => '', 'lastOccurred' => '—', 'addedOn' => '—', 'updatedOn' => '—',
            'status' => 'resolved', 'clinicalStatus' => 'resolved', 'verificationStatus' => 'unconfirmed',
            'notes' => '—', 'recommendations' => '—', 'summary' => 'No allergies on record for this patient.',
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
