<?php
/**
 * =====================================================================
 * Shared data layer — Family History
 * ---------------------------------------------------------------------
 * Pure Firestore-query functions for the real "family_history"
 * collection — a brand-new collection (no prior reader or writer
 * anywhere in this app) backing the Medical History tab's Family
 * History section (subadmin/doctor/medical-staff's medical_history.php,
 * written by api/add-family-history.php / api/update-family-history.php
 * / api/delete-family-history.php). "Relation" is free text (Mother,
 * Father, Maternal Aunt, Sibling, ...) — no fixed list fits the real
 * range of family relations cleanly, same reasoning
 * includes/medical-staff/add_patient_modal.php's own free-text fields
 * already use elsewhere in this app.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore

if (!function_exists('hc_get_family_history_for_patient')) {
    /**
     * Every family_history/{id} doc for $patientId. No real "newest
     * first" ordering exists for this data (a family history entry has
     * no inherent date), so this returns Firestore's own insertion
     * order instead of sorting by anything invented.
     *
     * @return array<int, array{id: string, condition: string, relation: string}>
     */
    function hc_get_family_history_for_patient(string $patientId): array
    {
        global $firestore;

        $rows = array_values(array_filter(
            $firestore->listDocuments('family_history'),
            fn ($doc) => ($doc['fields']['patientId'] ?? '') === $patientId
        ));

        return array_map(fn ($doc) => [
            'id' => $doc['id'],
            'condition' => $doc['fields']['condition'],
            'relation' => $doc['fields']['relation'],
        ], $rows);
    }
}
