<?php
/**
 * =====================================================================
 * Shared data layer — Patient Profile "Overview" tab bundle
 * ---------------------------------------------------------------------
 * Composes every other includes/data/*.php module into the exact
 * summary/activeMedicalConditions/latestConsultation/recentLabResults/
 * recentXray/vaccinationHistory shape patient_profile.php's Overview
 * tab renders — identical between subadmin/ and medical-staff/, so it
 * only needs to be assembled once. See includes/firestore/patients.php's
 * header comment for why this layer exists.
 * =====================================================================
 */

require_once __DIR__ . '/medical_records.php';
require_once __DIR__ . '/vaccination_records.php';
require_once __DIR__ . '/lab_results.php';
require_once __DIR__ . '/xray_results.php';
require_once __DIR__ . '/allergies.php';
require_once __DIR__ . '/medications.php';

if (!function_exists('hc_get_patient_overview')) {
    /**
     * @return array{
     *   summary: array{totalConsultations: int, activeMedicalConditions: int, allergies: int, currentMedications: int},
     *   activeMedicalConditions: array,
     *   latestConsultation: array{date: string, time: string, chiefComplaint: string, diagnosis: string, physician: string},
     *   recentLabResults: array,
     *   recentXray: array{name: string, date: string, status: string, findings: string},
     *   vaccinationHistory: array,
     * }
     */
    function hc_get_patient_overview(string $patientId): array
    {
        $consultationsOnly = array_values(array_filter(
            hc_get_medical_records_for_patient($patientId),
            fn ($r) => $r['type'] === 'consultation'
        ));
        // hc_get_medical_records_for_patient() is already newest-first.
        $latestConsultationRaw = $consultationsOnly[0] ?? null;

        // Formatted once here (not per-view) — "Temp: 37.5°C • BP: 120/80 •
        // HR: 78 bpm", skipping any sub-field the doctor left blank; null
        // when there's no vitals data at all (no consultation yet, or an
        // older consultation predating this field).
        $vitalsRaw = $latestConsultationRaw['vitals'] ?? null;
        $vitalsSummary = null;
        if (is_array($vitalsRaw)) {
            $parts = [];
            if (($vitalsRaw['temperature'] ?? '') !== '') {
                $parts[] = 'Temp: ' . $vitalsRaw['temperature'] . '°C';
            }
            if (($vitalsRaw['bloodPressureSystolic'] ?? '') !== '' && ($vitalsRaw['bloodPressureDiastolic'] ?? '') !== '') {
                $parts[] = 'BP: ' . $vitalsRaw['bloodPressureSystolic'] . '/' . $vitalsRaw['bloodPressureDiastolic'];
            }
            if (($vitalsRaw['heartRate'] ?? '') !== '') {
                $parts[] = 'HR: ' . $vitalsRaw['heartRate'] . ' bpm';
            }
            // A literal bullet character, not the &bull; HTML entity — this
            // string is printed through htmlspecialchars() on the view side
            // (like every other field here), which would otherwise double-
            // encode the entity's "&" into visible "&amp;bull;" text.
            $vitalsSummary = $parts !== [] ? implode(' • ', $parts) : null;
        }

        $latestConsultation = $latestConsultationRaw !== null ? [
            'date' => $latestConsultationRaw['date'],
            'time' => $latestConsultationRaw['time'],
            'chiefComplaint' => $latestConsultationRaw['chiefComplaint'] ?? '—',
            'diagnosis' => $latestConsultationRaw['diagnosis'] ?? '—',
            'physician' => $latestConsultationRaw['provider'] ?? '—',
            'vitals' => $vitalsSummary,
        ] : ['date' => '—', 'time' => '—', 'chiefComplaint' => 'No consultations on record.', 'diagnosis' => '—', 'physician' => '—', 'vitals' => null];

        $conditions = hc_get_medical_conditions_for_patient($patientId);
        $activeConditions = array_values(array_filter($conditions, fn ($c) => $c['status'] === 'Active'));

        $allergies = hc_get_allergies_for_patient($patientId);
        $activeMedications = array_filter(hc_get_medications_for_patient($patientId), fn ($m) => $m['status'] === 'active');

        $summary = [
            'totalConsultations' => count($consultationsOnly),
            'activeMedicalConditions' => count($activeConditions),
            'allergies' => count($allergies),
            'currentMedications' => count($activeMedications),
        ];

        $labDocs = hc_get_lab_result_docs_for_patient($patientId); // newest-completed-first
        $recentLabResults = array_map(fn ($doc) => [
            'name' => $doc['fields']['testName'],
            'date' => date('M j, Y', strtotime($doc['fields']['completedDate'])),
            'status' => str_contains(strtolower($doc['fields']['resultBadgeLabel'] ?? ''), 'normal') ? 'normal' : 'abnormal',
        ], array_slice($labDocs, 0, 4));

        $xrayDocs = hc_get_xray_result_docs_for_patient($patientId); // newest-completed-first
        $latestXrayDoc = $xrayDocs[0] ?? null;
        $recentXray = $latestXrayDoc !== null ? [
            'name' => $latestXrayDoc['fields']['examName'],
            'date' => date('M j, Y', strtotime($latestXrayDoc['fields']['completedDate'])),
            'status' => str_contains(strtolower($latestXrayDoc['fields']['impression'] ?? ''), 'no active') ? 'normal' : 'abnormal',
            'findings' => $latestXrayDoc['fields']['impression'] ?? '—',
        ] : ['name' => 'No x-ray on record.', 'date' => '—', 'status' => 'normal', 'findings' => '—'];

        $vaccinationHistory = array_map(fn ($v) => [
            'name' => $v['name'],
            'date' => $v['dateAdministered'],
            'status' => $v['status'],
        ], array_slice(hc_get_vaccination_records_for_patient($patientId), 0, 4));

        return [
            'summary' => $summary,
            'activeMedicalConditions' => array_map(fn ($c) => ['label' => $c['label'], 'diagnosedOn' => $c['diagnosedOn'], 'status' => $c['status']], $activeConditions),
            'latestConsultation' => $latestConsultation,
            'recentLabResults' => $recentLabResults,
            'recentXray' => $recentXray,
            'vaccinationHistory' => $vaccinationHistory,
        ];
    }
}
