<?php
/**
 * =====================================================================
 * Shared data layer — Laboratory Results
 * ---------------------------------------------------------------------
 * Pure Firestore-query functions for the real "lab_results" collection
 * — see includes/firestore/patients.php's header comment for why this layer
 * exists and what it does/doesn't own.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore
require_once __DIR__ . '/xray_results.php'; // hc_get_all_xray_result_docs()
require_once __DIR__ . '/patients.php'; // hc_get_all_patients_summary()

if (!function_exists('hc_lab_test_catalog')) {
    /**
     * The fixed set of orderable lab tests, grouped by category — used
     * by doctor/active_consultation.php's "Select Laboratory Tests"
     * panel (rendering the checkbox grid) AND by
     * api/complete-consultation.php (validating submitted test names
     * against this same list server-side, and looking up each test's
     * real category rather than trusting a client-supplied one — see
     * that file's header comment). No prior test-type list existed
     * anywhere in this app; this is a new, hardcoded catalog, not real
     * seed data.
     *
     * @return array<string, array<int, string>>
     */
    function hc_lab_test_catalog(): array
    {
        return [
            'Bacteriology' => ['Gram Stain', 'Wet Mount'],
            'Clinical Microscopy' => ['Urinalysis', 'Stool Exam', 'Fecal Occult Blood (FBOT)'],
            'Serology' => ['HBSAG', 'Syphilis', 'HIV', 'CD4', 'Viral Load', 'Dengue NS1', 'IGM and IGG'],
            'Hematology' => ['CBC'],
            'Clinical Chemistry' => ['Fasting Blood Sugar', 'Blood Urea Nitrogen', 'Creatinine', 'Blood Uric Acid', 'Total Cholesterol', 'Triglycerides', 'HDL/LDL', 'SGOT', 'SGPT', 'HBA1C'],
        ];
    }
}

if (!function_exists('hc_serology_rapid_test_catalog')) {
    /**
     * The 3 Serology tests covered by the Lipa City Health Office's real
     * paper "SEROLOGY / RAPID IMMUNOCHROMATOGRAPHIC TESTS" form (medtech
     * fills this out per patient, then prints it on that exact template
     * — see medical_technician/serology_rapid_test_form.php and
     * medical_technician/print_serology_rapid_test.php). Keys are the
     * exact testName values used everywhere else (hc_lab_test_catalog(),
     * the lab_results docs api/complete-consultation.php creates);
     * values are the display label the paper form actually prints
     * ("HBsAg", not the catalog's all-caps "HBSAG").
     *
     * @return array<string, string>
     */
    function hc_serology_rapid_test_catalog(): array
    {
        return [
            'HBSAG' => 'HBsAg',
            'Syphilis' => 'Syphilis',
            'HIV' => 'HIV',
        ];
    }
}

if (!function_exists('hc_get_serology_rapid_docs_for_patient')) {
    /**
     * This patient's lab_results docs for the 3 hc_serology_rapid_test_catalog()
     * tests, keyed by testName — whichever status they're in (a still-
     * "pending" order the medtech hasn't filled in yet, or an already-
     * "completed" one). Used by both the fill-out form (editable only
     * while pending) and the print view (reads whatever's really saved,
     * blank if a given test was never ordered for this patient at all).
     *
     * @return array<string, array{id: string, fields: array}>
     */
    function hc_get_serology_rapid_docs_for_patient(string $patientId): array
    {
        $wanted = array_keys(hc_serology_rapid_test_catalog());
        $out = [];
        foreach (hc_get_lab_result_docs_for_patient($patientId) as $doc) {
            $testName = $doc['fields']['testName'] ?? '';
            if (in_array($testName, $wanted, true) && !isset($out[$testName])) {
                $out[$testName] = $doc;
            }
        }
        return $out;
    }
}

if (!function_exists('hc_urinalysis_physical_fields')) {
    /**
     * The 6 "PHYSICAL EXAMINATION" fields on the Lipa City Health
     * Office's real paper "URINALYSIS" form — keys are the
     * resultGroups.group1_rows "test" values written by
     * api/complete-urinalysis-test.php, in the same order the paper
     * form prints them. See
     * medical_technician/urinalysis_form.php/print_urinalysis.php.
     *
     * @return array<string, string>
     */
    function hc_urinalysis_physical_fields(): array
    {
        return [
            'color' => 'Color',
            'transparency' => 'Transparency',
            'specificGravity' => 'Specific Gravity',
            'ph' => 'pH',
            'glucose' => 'Glucose',
            'albumin' => 'Albumin',
        ];
    }
}

if (!function_exists('hc_urinalysis_microscopic_fields')) {
    /** The 8 "MICROSCOPIC EXAMINATION" fields — see hc_urinalysis_physical_fields()'s header comment. */
    function hc_urinalysis_microscopic_fields(): array
    {
        return [
            'redBloodCells' => 'Red Blood Cells',
            'whiteBloodCells' => 'White Blood Cells',
            'epithelialCells' => 'Epithelial Cells',
            'amorphousUrates' => 'Amorphous Urates',
            'amorphousPhosphates' => 'Amorphous Phosphates',
            'mucusThreads' => 'Mucus Threads',
            'bacteria' => 'Bacteria',
            'otherFindings' => 'Other Findings',
        ];
    }
}

if (!function_exists('hc_get_urinalysis_docs_for_patient')) {
    /**
     * This patient's real lab_results docs for the "Urinalysis" test
     * (hc_lab_test_catalog()'s Clinical Microscopy category), newest
     * completed first — same source data as
     * hc_get_lab_result_docs_for_patient(), just narrowed to this one
     * test name.
     *
     * @return array<int, array{id: string, fields: array}>
     */
    function hc_get_urinalysis_docs_for_patient(string $patientId): array
    {
        return array_values(array_filter(
            hc_get_lab_result_docs_for_patient($patientId),
            fn ($doc) => ($doc['fields']['testName'] ?? '') === 'Urinalysis'
        ));
    }
}

if (!function_exists('hc_cd4_specimen_label')) {
    /**
     * The Lipa City Health Office's real paper "CD4 COUNT" form has two
     * pre-printed constants (not something the medtech chooses per
     * patient) — the specimen type and the normal reference range.
     * Shared by medical_technician/cd4_count_form.php,
     * api/complete-cd4-count-test.php, and print_cd4_count.php so all
     * three stay in sync with the paper form's real fixed text.
     */
    function hc_cd4_specimen_label(): string
    {
        return 'WHOLE BLOOD';
    }
}

if (!function_exists('hc_cd4_reference_range')) {
    /** @return array{low: int, high: int, label: string} */
    function hc_cd4_reference_range(): array
    {
        return ['low' => 850, 'high' => 1150, 'label' => '850 - 1150 c/uL'];
    }
}

if (!function_exists('hc_get_cd4_count_docs_for_patient')) {
    /**
     * This patient's real lab_results docs for the "CD4" test
     * (hc_lab_test_catalog()'s Serology category), newest completed
     * first — same source data as hc_get_lab_result_docs_for_patient(),
     * just narrowed to this one test name.
     *
     * @return array<int, array{id: string, fields: array}>
     */
    function hc_get_cd4_count_docs_for_patient(string $patientId): array
    {
        return array_values(array_filter(
            hc_get_lab_result_docs_for_patient($patientId),
            fn ($doc) => ($doc['fields']['testName'] ?? '') === 'CD4'
        ));
    }
}

if (!function_exists('hc_get_gram_stain_docs_for_patient')) {
    /**
     * This patient's real lab_results docs for the "Gram Stain" test
     * (hc_lab_test_catalog()'s Bacteriology category), newest completed
     * first — same source data as hc_get_lab_result_docs_for_patient(),
     * just narrowed to this one test name. Unlike CD4 Count, the real
     * paper form has no fixed specimen/reference range — both Specimen
     * and Result are free text the medtech fills in per patient (a
     * Gram stain result is a descriptive read, not a single number).
     *
     * @return array<int, array{id: string, fields: array}>
     */
    function hc_get_gram_stain_docs_for_patient(string $patientId): array
    {
        return array_values(array_filter(
            hc_get_lab_result_docs_for_patient($patientId),
            fn ($doc) => ($doc['fields']['testName'] ?? '') === 'Gram Stain'
        ));
    }
}

if (!function_exists('hc_get_pending_lab_xray_notifications')) {
    /**
     * One real notification row per lab_results/xray_results doc still
     * "pending" (an order placed but no result entered yet) — the
     * medical technologist equivalent of
     * includes/firestore/medicines.php's hc_get_low_stock_notifications():
     * a genuinely actionable alert (a test this technician still owes a
     * result for) derived from real data, not a fabricated placeholder
     * list. Both collections' pending docs are merged into one list,
     * oldest ordered-date first (longest-waiting first — most urgent).
     *
     * 'id' is crc32() of "{collection}-{docId}" (same reasoning as
     * hc_get_low_stock_notifications()'s header comment — keeps
     * $_SESSION['ntfReadIds']/['ntfDismissedIds'] as a plain int array;
     * the collection prefix keeps a lab and an xray doc that happen to
     * share a raw Firestore id from colliding).
     *
     * @return array<int, array{id: int, title: string, description: string, type: string, date: string, time: string, icon: string, iconBg: string, iconColor: string}>
     */
    function hc_get_pending_lab_xray_notifications(): array
    {
        $patientNames = array_column(hc_get_all_patients_summary(), 'name', 'id');

        $rows = [];
        foreach (hc_get_all_lab_result_docs() as $doc) {
            $f = $doc['fields'];
            if (($f['status'] ?? '') !== 'pending') {
                continue;
            }
            $rows[] = [
                'collection' => 'lab_results',
                'docId' => $doc['id'],
                'testName' => $f['testName'] ?? 'Lab test',
                'patientId' => $f['patientId'] ?? '',
                'orderedDate' => $f['orderedDate'] ?? '',
                'orderedTime' => $f['orderedTime'] ?? '',
            ];
        }
        foreach (hc_get_all_xray_result_docs() as $doc) {
            $f = $doc['fields'];
            if (($f['status'] ?? '') !== 'pending') {
                continue;
            }
            $rows[] = [
                'collection' => 'xray_results',
                'docId' => $doc['id'],
                'testName' => $f['examName'] ?? 'X-ray exam',
                'patientId' => $f['patientId'] ?? '',
                'orderedDate' => $f['orderedDate'] ?? '',
                'orderedTime' => $f['orderedTime'] ?? '',
            ];
        }

        usort($rows, fn ($a, $b) => strcmp($a['orderedDate'] . $a['orderedTime'], $b['orderedDate'] . $b['orderedTime']));

        return array_map(function ($r) use ($patientNames) {
            $orderedAt = $r['orderedDate'] !== '' ? strtotime($r['orderedDate'] . ' ' . $r['orderedTime']) : false;
            $isXray = $r['collection'] === 'xray_results';
            $daysWaiting = $orderedAt !== false ? (int) floor((time() - $orderedAt) / 86400) : 0;
            $patientName = $patientNames[$r['patientId']] ?? '(unknown patient)';

            return [
                'id' => crc32($r['collection'] . '-' . $r['docId']),
                'title' => 'Pending ' . ($isXray ? 'X-ray' : 'lab') . ' result',
                'description' => $r['testName'] . ' for ' . $patientName . ' — ordered ' . ($orderedAt !== false ? date('M j, Y', $orderedAt) : 'recently') . ', still awaiting a result.',
                'type' => $isXray ? 'X-ray' : 'Laboratory',
                'date' => $orderedAt !== false ? date('M j, Y', $orderedAt) : '—',
                'time' => $orderedAt !== false ? date('g:i A', $orderedAt) : '',
                'icon' => $isXray ? 'bi-file-earmark-medical-fill' : 'bi-clipboard2-pulse-fill',
                'iconBg' => $daysWaiting >= 2 ? '#FCE4E4' : '#FDECDD',
                'iconColor' => $daysWaiting >= 2 ? '#D93025' : '#E8710A',
            ];
        }, $rows);
    }
}

if (!function_exists('hc_get_pending_lab_notifications')) {
    /**
     * The Laboratory-only slice of hc_get_pending_lab_xray_notifications()
     * above — medical_technician's own badges/notification list no longer
     * show X-ray referrals (radio_technologist owns that queue/badge
     * exclusively now, via its own type==='X-ray' filter on this same
     * shared function), so this is medtech's actual source instead of the
     * combined one directly.
     */
    function hc_get_pending_lab_notifications(): array
    {
        return array_values(array_filter(hc_get_pending_lab_xray_notifications(), fn ($n) => $n['type'] === 'Laboratory'));
    }
}

if (!function_exists('hc_get_pending_lab_count')) {
    /** Just the count behind hc_get_pending_lab_notifications() above — for sidebar nav badges that only need the number. */
    function hc_get_pending_lab_count(): int
    {
        return count(hc_get_pending_lab_notifications());
    }
}

if (!function_exists('hc_get_pending_xray_notifications')) {
    /**
     * The X-ray-only slice of hc_get_pending_lab_xray_notifications()
     * above — the named counterpart to hc_get_pending_lab_notifications(),
     * for radio_technologist's own predictive/prescriptive analytics
     * card (includes/radiologic_technologist/predictive_overview_firestore.php)
     * to reuse instead of inlining the same type==='X-ray' filter again.
     */
    function hc_get_pending_xray_notifications(): array
    {
        return array_values(array_filter(hc_get_pending_lab_xray_notifications(), fn ($n) => $n['type'] === 'X-ray'));
    }
}

if (!function_exists('hc_get_pending_lab_xray_count')) {
    /**
     * Just the count behind hc_get_pending_lab_xray_notifications() above,
     * without building the full notification-row shape — for sidebar nav
     * badges that only need the number. kept combined (not Laboratory-only)
     * since this one isn't tied to a specific role's own queue.
     */
    function hc_get_pending_lab_xray_count(): int
    {
        $count = 0;
        foreach (hc_get_all_lab_result_docs() as $doc) {
            if (($doc['fields']['status'] ?? '') === 'pending') {
                $count++;
            }
        }
        foreach (hc_get_all_xray_result_docs() as $doc) {
            if (($doc['fields']['status'] ?? '') === 'pending') {
                $count++;
            }
        }
        return $count;
    }
}

if (!function_exists('hc_get_doctor_result_ready_notifications')) {
    /**
     * The doctor-facing mirror of hc_get_pending_lab_xray_notifications()
     * above: one real notification row per lab_results/xray_results doc
     * this doctor personally ordered ('orderedBy' === their real name,
     * the same field every completion endpoint stamps — see
     * api/complete-lab-result.php etc.) that's now 'completed' — i.e.
     * the medical technologist is DONE and this doctor can actually open
     * it (doctor/active_consultation.php's "Lab & X-ray Results" card,
     * or patient_laboratory_xray.php outside an active consultation).
     * Capped to the last 14 days (same reasoning
     * hc_get_followup_notifications()'s own 7-day window gives: a
     * meaningfully "new" alert, not an ever-growing list of every result
     * this doctor has ever ordered) — older completions simply age out
     * of the bell rather than needing a separate dismiss-forever flag.
     * Also excludes anything already routedToDoctorAt (the medical
     * technologist's own "Proceed to Doctor" action,
     * api/proceed-lab-result-to-doctor.php) — once a result has actually
     * been sent to the doctor that way, it's already sitting in their
     * consultation's own "Lab & X-ray Results" card, so a "ready to
     * view" alert for it is stale, not new.
     *
     * @return array<int, array{id: int, title: string, description: string, type: string, date: string, time: string, icon: string, iconBg: string, iconColor: string}>
     */
    function hc_get_doctor_result_ready_notifications(string $doctorName): array
    {
        if ($doctorName === '') {
            return [];
        }

        $cutoff = date('Y-m-d', strtotime('-14 days'));
        $patientNames = array_column(hc_get_all_patients_summary(), 'name', 'id');

        $rows = [];
        foreach (hc_get_all_lab_result_docs() as $doc) {
            $f = $doc['fields'];
            if (($f['status'] ?? '') !== 'completed' || ($f['orderedBy'] ?? '') !== $doctorName || !empty($f['routedToDoctorAt'])) {
                continue;
            }
            if (($f['completedDate'] ?? '') < $cutoff) {
                continue;
            }
            $rows[] = [
                'collection' => 'lab_results',
                'docId' => $doc['id'],
                'testName' => $f['testName'] ?? 'Lab test',
                'patientId' => $f['patientId'] ?? '',
                'completedDate' => $f['completedDate'] ?? '',
                'completedTime' => $f['completedTime'] ?? '',
            ];
        }
        foreach (hc_get_all_xray_result_docs() as $doc) {
            $f = $doc['fields'];
            if (($f['status'] ?? '') !== 'completed' || ($f['orderedBy'] ?? '') !== $doctorName || !empty($f['routedToDoctorAt'])) {
                continue;
            }
            if (($f['completedDate'] ?? '') < $cutoff) {
                continue;
            }
            $rows[] = [
                'collection' => 'xray_results',
                'docId' => $doc['id'],
                'testName' => $f['examName'] ?? 'X-ray exam',
                'patientId' => $f['patientId'] ?? '',
                'completedDate' => $f['completedDate'] ?? '',
                'completedTime' => $f['completedTime'] ?? '',
            ];
        }

        usort($rows, fn ($a, $b) => strcmp($b['completedDate'] . $b['completedTime'], $a['completedDate'] . $a['completedTime']));

        return array_map(function ($r) use ($patientNames) {
            $completedAt = $r['completedDate'] !== '' ? strtotime($r['completedDate'] . ' ' . $r['completedTime']) : false;
            $isXray = $r['collection'] === 'xray_results';
            $patientName = $patientNames[$r['patientId']] ?? '(unknown patient)';

            return [
                'id' => crc32($r['collection'] . '-' . $r['docId'] . '-ready'),
                'title' => ($isXray ? 'X-ray' : 'Lab') . ' result ready',
                'description' => $r['testName'] . ' for ' . $patientName . ' is ready to view.',
                'type' => $isXray ? 'X-ray' : 'Laboratory',
                'date' => $completedAt !== false ? date('M j, Y', $completedAt) : '—',
                'time' => $completedAt !== false ? date('g:i A', $completedAt) : '',
                'icon' => $isXray ? 'bi-file-earmark-medical-fill' : 'bi-clipboard2-pulse-fill',
                'iconBg' => '#E4F7EA',
                'iconColor' => '#1E8E3E',
            ];
        }, $rows);
    }
}

if (!function_exists('hc_consultation_is_awaiting_lab_results')) {
    /**
     * True while this consultation is genuinely "with the lab or
     * radiology" — routed there (nextRouting === 'laboratory' or
     * 'xray') AND at least one lab_results/xray_results doc whose
     * sourceConsultationId matches is still 'pending' (the medical
     * technologist/radiologic technologist hasn't completed it yet). Once
     * every test/exam ordered from it is 'completed', this flips back
     * to false on its own — nothing to separately clear. Used to block
     * a doctor from resuming the clinical documentation workspace
     * (doctor/active_consultation.php) for a patient who's physically
     * at the lab/radiology room right now, same real signal
     * hc_get_doctor_result_ready_notifications() above already reads
     * (sourceConsultationId + status), just checking for ANY remaining
     * 'pending' instead of a completed one.
     */
    function hc_consultation_is_awaiting_lab_results(array $consultation): bool
    {
        if (!in_array($consultation['nextRouting'] ?? '', ['laboratory', 'xray'], true)) {
            return false;
        }

        global $firestore;
        foreach ($firestore->listDocuments('lab_results') as $doc) {
            $f = $doc['fields'];
            if (($f['sourceConsultationId'] ?? '') === $consultation['id'] && ($f['status'] ?? '') === 'pending') {
                return true;
            }
        }
        foreach ($firestore->listDocuments('xray_results') as $doc) {
            $f = $doc['fields'];
            if (($f['sourceConsultationId'] ?? '') === $consultation['id'] && ($f['status'] ?? '') === 'pending') {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('hc_get_lab_results_for_patient')) {
    /**
     * Every lab_results/{id} doc for $patientId in the compact list-row
     * shape patient_laboratory_xray.php needs, newest completed first.
     *
     * @return array<int, array{id: string, datetime: string, time: string, testName: string, testType: string, orderedBy: string, facility: string, status: string}>
     */
    function hc_get_lab_results_for_patient(string $patientId): array
    {
        $rows = [];
        foreach (hc_get_lab_result_docs_for_patient($patientId) as $doc) {
            $f = $doc['fields'];
            $rows[] = [
                'id' => $doc['id'],
                'datetime' => date('M j, Y', strtotime($f['orderedDate'])),
                'time' => $f['orderedTime'],
                'testName' => $f['testName'],
                'testType' => $f['testType'],
                'orderedBy' => $f['orderedBy'],
                'facility' => $f['facility'],
                'status' => $f['status'],
            ];
        }

        return $rows;
    }
}

if (!function_exists('hc_get_all_lab_result_docs')) {
    /**
     * @internal Every raw lab_results/{id} doc across ALL patients,
     * unfiltered — for a technician-facing queue view
     * (medical_technician/laboratory_xray.php), unlike
     * hc_get_lab_result_docs_for_patient() below which scopes to one
     * patient's own profile pages.
     */
    function hc_get_all_lab_result_docs(): array
    {
        global $firestore;

        return $firestore->listDocuments('lab_results');
    }
}

if (!function_exists('hc_get_lab_result_docs_for_patient')) {
    /** @internal Raw (unmapped) docs, newest completed first — shared by the list and detail resolvers above/below. */
    function hc_get_lab_result_docs_for_patient(string $patientId): array
    {
        global $firestore;

        $docs = array_values(array_filter(
            $firestore->listDocuments('lab_results'),
            fn ($doc) => ($doc['fields']['patientId'] ?? '') === $patientId
        ));
        usort($docs, fn ($a, $b) => strcmp($b['fields']['completedDate'] ?? '', $a['fields']['completedDate'] ?? ''));

        return $docs;
    }
}

if (!function_exists('hc_empty_lab_test')) {
    function hc_empty_lab_test(): array
    {
        return [
            'testName' => 'No laboratory results on record.', 'testType' => '—',
            'orderedDate' => '—', 'orderedTime' => '—', 'completedDate' => '—', 'completedTime' => '—',
            'orderedBy' => '—', 'facility' => '—', 'status' => 'pending', 'specimenType' => '—',
            'remarks' => null, 'clinicalNotes' => '—', 'attachedFile' => ['name' => '—', 'size' => '—'],
        ];
    }
}

if (!function_exists('hc_resolve_lab_result')) {
    /**
     * The full "one lab test" detail bundle laboratory_result_details.php
     * needs — the doc matching $recordId if $wantThisType is true, else
     * this patient's most recent lab result, else an empty-state shape.
     *
     * @return array{labTest: array, labResultBadge: array, labResultGroups: array, labInterpretation: string}
     */
    function hc_resolve_lab_result(string $patientId, bool $wantThisType, ?string $recordId): array
    {
        $docs = hc_get_lab_result_docs_for_patient($patientId);

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
                'labTest' => hc_empty_lab_test(),
                'labResultBadge' => ['label' => 'No Data', 'bg' => '#EEF0F3', 'color' => '#6B7280'],
                'labResultGroups' => [],
                'labInterpretation' => 'No laboratory results on record for this patient.',
            ];
        }

        $lf = $selected['fields'];
        $labTest = [
            'testName' => $lf['testName'],
            'testType' => $lf['testType'],
            'orderedDate' => date('M j, Y', strtotime($lf['orderedDate'])), 'orderedTime' => $lf['orderedTime'],
            // A still-"pending" order (api/complete-consultation.php's
            // Laboratory routing) has no real completedDate yet — guard
            // against strtotime('') so this doesn't render as "Jan 1,
            // 1970" (every doc had a real completed date before this).
            'completedDate' => $lf['completedDate'] !== '' ? date('M j, Y', strtotime($lf['completedDate'])) : '—',
            'completedTime' => $lf['completedTime'],
            'orderedBy' => $lf['orderedBy'],
            'facility' => $lf['facility'],
            'status' => $lf['status'],
            'specimenType' => $lf['specimenType'],
            'remarks' => $lf['remarks'],
            'clinicalNotes' => $lf['clinicalNotes'],
            'attachedFile' => ['name' => $lf['attachedFileName'], 'size' => $lf['attachedFileSize']],
        ];

        $labResultGroups = [];
        $rg = $lf['resultGroups'] ?? [];
        if (!empty($rg['group1_name'])) {
            $labResultGroups[$rg['group1_name']] = $rg['group1_rows'] ?? [];
        }
        if (!empty($rg['group2_name'])) {
            $labResultGroups[$rg['group2_name']] = $rg['group2_rows'] ?? [];
        }

        return [
            'labTest' => $labTest,
            'labResultBadge' => ['label' => $lf['resultBadgeLabel'], 'bg' => '#E4F7EA', 'color' => $lf['resultBadgeColor']],
            'labResultGroups' => $labResultGroups,
            'labInterpretation' => $lf['interpretation'],
        ];
    }
}
