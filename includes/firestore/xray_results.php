<?php
/**
 * =====================================================================
 * Shared data layer — X-ray Results
 * ---------------------------------------------------------------------
 * Pure Firestore-query functions for the real "xray_results" collection
 * — see includes/firestore/patients.php's header comment for why this layer
 * exists and what it does/doesn't own.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore

if (!function_exists('hc_get_xray_results_for_patient')) {
    /**
     * Every xray_results/{id} doc for $patientId in the compact
     * list-row shape patient_laboratory_xray.php needs, newest
     * completed first.
     *
     * @return array<int, array{id: string, datetime: string, time: string, examName: string, bodyPart: string, orderedBy: string, facility: string, status: string}>
     */
    function hc_get_xray_results_for_patient(string $patientId): array
    {
        $rows = [];
        foreach (hc_get_xray_result_docs_for_patient($patientId) as $doc) {
            $f = $doc['fields'];
            $rows[] = [
                'id' => $doc['id'],
                'datetime' => date('M j, Y', strtotime($f['orderedDate'])),
                'time' => $f['orderedTime'],
                'examName' => $f['examName'],
                'bodyPart' => $f['bodyPart'],
                'orderedBy' => $f['orderedBy'],
                'facility' => $f['facility'],
                'status' => $f['status'],
            ];
        }

        return $rows;
    }
}

if (!function_exists('hc_get_all_xray_result_docs')) {
    /**
     * @internal Every raw xray_results/{id} doc across ALL patients,
     * unfiltered — for a technician-facing queue view
     * (medical_technician/laboratory_xray.php), unlike
     * hc_get_xray_result_docs_for_patient() below which scopes to one
     * patient's own profile pages.
     */
    function hc_get_all_xray_result_docs(): array
    {
        global $firestore;

        return $firestore->listDocuments('xray_results');
    }
}

if (!function_exists('hc_get_pending_xray_count')) {
    /**
     * Count of real xray_results docs still "pending" (ordered, no
     * result entered yet) — for radio_technologist's own sidebar nav
     * badge, scoped to X-ray only unlike
     * includes/firestore/lab_results.php's hc_get_pending_lab_xray_count()
     * (which also counts pending lab_results, meaningless for this role).
     */
    function hc_get_pending_xray_count(): int
    {
        $count = 0;
        foreach (hc_get_all_xray_result_docs() as $doc) {
            if (($doc['fields']['status'] ?? '') === 'pending') {
                $count++;
            }
        }

        return $count;
    }
}

if (!function_exists('hc_get_xray_result_docs_for_patient')) {
    /** @internal Raw (unmapped) docs, newest completed first — shared by the list and detail resolvers above/below. */
    function hc_get_xray_result_docs_for_patient(string $patientId): array
    {
        global $firestore;

        $docs = array_values(array_filter(
            $firestore->listDocuments('xray_results'),
            fn ($doc) => ($doc['fields']['patientId'] ?? '') === $patientId
        ));
        usort($docs, fn ($a, $b) => strcmp($b['fields']['completedDate'] ?? '', $a['fields']['completedDate'] ?? ''));

        return $docs;
    }
}

if (!function_exists('hc_empty_xray_exam')) {
    function hc_empty_xray_exam(): array
    {
        return [
            'examName' => 'No x-ray results on record.', 'bodyPart' => '—',
            'orderedDate' => '—', 'orderedTime' => '—', 'completedDate' => '—', 'completedTime' => '—',
            'orderedBy' => '—', 'facility' => '—', 'status' => 'pending',
            'attachedFile' => ['name' => '—', 'size' => '—'],
        ];
    }
}

if (!function_exists('hc_resolve_xray_result')) {
    /**
     * The full "one x-ray exam" detail bundle laboratory_result_details.php
     * needs — the doc matching $recordId if $wantThisType is true, else
     * this patient's most recent x-ray, else an empty-state shape.
     *
     * @return array{xrayExam: array, xrayFindings: array, xrayImpression: string}
     */
    function hc_resolve_xray_result(string $patientId, bool $wantThisType, ?string $recordId): array
    {
        $docs = hc_get_xray_result_docs_for_patient($patientId);

        $selected = null;
        if ($wantThisType && $recordId !== null && $recordId !== '') {
            foreach ($docs as $doc) {
                if ($doc['id'] === $recordId) {
                    $selected = $doc;
                    break;
                }
            }
        }
        $selected ??= $docs[0] ?? null;

        if ($selected === null) {
            return [
                'xrayExam' => hc_empty_xray_exam(),
                'xrayFindings' => [],
                'xrayImpression' => 'No x-ray results on record for this patient.',
            ];
        }

        $xf = $selected['fields'];

        return [
            'xrayExam' => [
                'examName' => $xf['examName'],
                'bodyPart' => $xf['bodyPart'],
                'orderedDate' => date('M j, Y', strtotime($xf['orderedDate'])), 'orderedTime' => $xf['orderedTime'],
                'completedDate' => date('M j, Y', strtotime($xf['completedDate'])), 'completedTime' => $xf['completedTime'],
                'orderedBy' => $xf['orderedBy'],
                'facility' => $xf['facility'],
                'status' => $xf['status'],
                'attachedFile' => ['name' => $xf['attachedFileName'], 'size' => $xf['attachedFileSize']],
            ],
            'xrayFindings' => $xf['findings'] ?? [],
            'xrayImpression' => $xf['impression'],
        ];
    }
}
