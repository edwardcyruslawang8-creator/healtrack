<?php
/**
 * =====================================================================
 * Vaccination Record Details (Medical Staff) — Data
 * ---------------------------------------------------------------------
 * Reuses includes/vaccination_records_medstaff_firestore.php for everything
 * about the patient and their vaccination history. $selectedVaccineRecord
 * is resolved by hc_resolve_selected_vaccine_record()
 * (includes/firestore/vaccination_records.php) against $_GET['recordId'] —
 * the doc id vaccination_records.php's View button passes — falling
 * back to the most recent record if recordId is missing or doesn't
 * match.
 * =====================================================================
 */

require_once __DIR__ . '/vaccination_records_medstaff_firestore.php';
// $staffProfile, $dashboardDateLabel, $facilityOptions, $patient,
// $vaccineTypeMeta, $vaccinationRecords, $vaccinationRecordsTotal,
// $selectedVaccineRecord (default: most recent — overridden below by ?recordId=)

$selectedVaccineRecord = hc_resolve_selected_vaccine_record($vaccinationRecords, $_GET['recordId'] ?? null);

$vaccineStatusMeta = [
    'completed' => ['label' => 'Completed', 'bg' => '#E4F7EA', 'color' => '#1E8E3E'],
    'pending'   => ['label' => 'Pending',   'bg' => '#FDECDD', 'color' => '#E8710A'],
];
