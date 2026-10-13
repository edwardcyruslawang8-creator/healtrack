<?php
/**
 * =====================================================================
 * Allergy Details — Data
 * ---------------------------------------------------------------------
 * Reuses includes/allergies_firestore.php for everything about the patient
 * and their allergy list. $selectedAllergy is resolved by
 * hc_resolve_selected_allergy() (includes/firestore/allergies.php) against
 * $_GET['recordId'] — the doc id allergies.php's View button passes —
 * falling back to the most recently added record if recordId is
 * missing or doesn't match.
 * =====================================================================
 */

require_once __DIR__ . '/allergies_firestore.php';
// $adminProfile, $dashboardDateLabel, $facilityOptions, $patient,
// $severityMeta, $allergyStatusMeta, $allergyRecords, $allergyRecordsTotal,
// $selectedAllergy (default: most recently added — overridden below by ?recordId=)

$selectedAllergy = hc_resolve_selected_allergy($allergyRecords, $_GET['recordId'] ?? null);
