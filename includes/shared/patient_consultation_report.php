<?php
/**
 * =====================================================================
 * Shared — "My Patients Report" (doctor / medical-staff / medical
 * technologist / radiologic technologist Analytics & Reports page)
 * ---------------------------------------------------------------------
 * A date-filtered, exportable list of the patients the SIGNED-IN staff
 * member personally consulted/tested — today or any past date range.
 * Four roles, different real "this is mine" fields, same output
 * row shape {patientName, date, time, type, detail, status} so one
 * table/CSV renderer works for all four:
 *   - Doctor: consultations/{id}.assignedPersonnel === their name
 *     (hc_consultation_report_rows(), fed $myConsultations already
 *     filtered that way by includes/doctor/analytics_reports_doctor_firestore.php
 *     — same field every other doctor page scopes "my queue" by).
 *   - Medical staff: consultations/{id}.createdBy === their uid
 *     (hc_consultation_report_rows() again, fed $myConsultations
 *     filtered that way by includes/medical-staff/analytics_reports_medstaff_firestore.php).
 *   - Medical technologist: NEITHER lab_results NOR xray_results records
 *     who completed a test (see includes/medical_technician/analytics_reports_medtech_firestore.php's
 *     header comment) — but every completion endpoint
 *     (api/complete-lab-result.php, complete-cd4-count-test.php,
 *     complete-gram-stain-test.php, complete-urinalysis-test.php,
 *     complete-serology-rapid-test.php, complete-xray-result.php) ALSO
 *     writes a real medical_records/{id} doc (type 'laboratory') with a
 *     real 'provider' field stamped from $_SESSION['name'] at the exact
 *     moment that technician completed it — a genuine, non-fabricated
 *     "who did this" trail, just recorded on a different collection
 *     than lab_results/xray_results themselves. hc_lab_report_rows()
 *     reads that.
 *   - Radiologic technologist: same medical_records/{id} trail as medical
 *     technologist above (api/complete-xray-result.php writes the same
 *     shape, 'title' prefixed "X-ray — ") — hc_xray_report_rows() below
 *     reads the same collection, narrowed to just the X-ray-prefixed
 *     rows, so a radiologic technologist's own report never includes a lab
 *     test some other technologist completed.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore

if (!function_exists('hc_report_date_range_from_get')) {
    /**
     * ?from=&?to= (both YYYY-MM-DD), defaulting to today when missing,
     * malformed, or out of order — this report always has a well-formed
     * range to run with, never a blank/broken one.
     *
     * @return array{from: string, to: string}
     */
    function hc_report_date_range_from_get(): array
    {
        $today = date('Y-m-d');
        $isIsoDate = fn ($v) => is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) === 1;

        $from = $_GET['from'] ?? '';
        $to = $_GET['to'] ?? '';
        $from = $isIsoDate($from) ? $from : $today;
        $to = $isIsoDate($to) ? $to : $today;

        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        return ['from' => $from, 'to' => $to];
    }
}

if (!function_exists('hc_consultation_report_rows')) {
    /**
     * $consultations (already scoped to "mine" by the caller) narrowed
     * to [$from, $to] inclusive by dateTime's date, newest first.
     *
     * @return array<int, array{date: string, time: string, patientName: string, type: string, detail: string, status: string}>
     */
    function hc_consultation_report_rows(array $consultations, string $from, string $to): array
    {
        $typeMeta = hc_consultation_type_meta();
        $statusMeta = hc_consultation_status_meta();

        $rows = array_values(array_filter($consultations, function ($c) use ($from, $to) {
            $day = substr((string) ($c['dateTime'] ?? ''), 0, 10);
            return $day >= $from && $day <= $to;
        }));
        usort($rows, fn ($a, $b) => strcmp($b['dateTime'], $a['dateTime']));

        return array_map(function ($c) use ($typeMeta, $statusMeta) {
            $timestamp = strtotime((string) $c['dateTime']);
            $detail = trim((string) ($c['diagnosis'] ?? '')) !== '' ? $c['diagnosis'] : ($c['chiefComplaint'] ?? '—');

            return [
                'date' => $timestamp !== false ? date('M j, Y', $timestamp) : '—',
                'time' => $timestamp !== false ? date('g:i A', $timestamp) : '—',
                'patientName' => $c['patientName'] ?? '(unknown patient)',
                'type' => $typeMeta[$c['consultationType']]['label'] ?? ucfirst((string) ($c['consultationType'] ?? '—')),
                'detail' => $detail,
                'status' => $statusMeta[$c['status']]['label'] ?? ucfirst((string) ($c['status'] ?? '—')),
            ];
        }, $rows);
    }
}

if (!function_exists('hc_lab_report_rows')) {
    /**
     * Every medical_records/{id} doc (type 'laboratory') this
     * technologist personally completed ('provider' === their name),
     * narrowed to [$from, $to] inclusive, newest first.
     *
     * @return array<int, array{date: string, time: string, patientName: string, type: string, detail: string, status: string}>
     */
    function hc_lab_report_rows(string $myName, string $from, string $to): array
    {
        global $firestore;

        $patientNamesById = [];
        foreach ($firestore->listDocuments('patients') as $doc) {
            $patientNamesById[$doc['id']] = $doc['fields']['name'] ?? '(unknown patient)';
        }

        $rows = [];
        foreach ($firestore->listDocuments('medical_records') as $doc) {
            $f = $doc['fields'];
            if (($f['type'] ?? '') !== 'laboratory' || ($f['provider'] ?? '') !== $myName) {
                continue;
            }
            $day = (string) ($f['date'] ?? '');
            if ($day < $from || $day > $to) {
                continue;
            }

            $timestamp = strtotime($day . ' ' . ($f['time'] ?? ''));
            $rows[] = [
                'sortKey' => $day . ' ' . ($f['time'] ?? ''),
                'date' => $timestamp !== false ? date('M j, Y', $timestamp) : $day,
                'time' => (string) ($f['time'] ?? '—'),
                'patientName' => $patientNamesById[$f['patientId'] ?? ''] ?? '(unknown patient)',
                'type' => 'Laboratory',
                'detail' => (string) ($f['title'] ?? 'Laboratory test'),
                'status' => 'Completed',
            ];
        }
        usort($rows, fn ($a, $b) => strcmp($b['sortKey'], $a['sortKey']));

        return array_map(fn ($r) => array_diff_key($r, ['sortKey' => true]), $rows);
    }
}

if (!function_exists('hc_xray_report_rows')) {
    /**
     * Every medical_records/{id} doc (type 'laboratory') this radio
     * technologist personally completed — same underlying collection
     * hc_lab_report_rows() reads (api/complete-xray-result.php stamps
     * X-ray completions there too, with 'title' prefixed "X-ray — ",
     * since neither lab_results nor xray_results itself records who
     * completed a test — see this file's header comment), narrowed to
     * ONLY the X-ray-prefixed rows ('provider' === their name AND
     * 'title' starts with "X-ray — ") so a radiologic technologist's own
     * report never includes a lab test some other technologist entered
     * under the same shared medical_records type.
     *
     * @return array<int, array{date: string, time: string, patientName: string, type: string, detail: string, status: string}>
     */
    function hc_xray_report_rows(string $myName, string $from, string $to): array
    {
        global $firestore;

        $patientNamesById = [];
        foreach ($firestore->listDocuments('patients') as $doc) {
            $patientNamesById[$doc['id']] = $doc['fields']['name'] ?? '(unknown patient)';
        }

        $rows = [];
        foreach ($firestore->listDocuments('medical_records') as $doc) {
            $f = $doc['fields'];
            if (($f['type'] ?? '') !== 'laboratory' || ($f['provider'] ?? '') !== $myName) {
                continue;
            }
            $title = (string) ($f['title'] ?? '');
            if (!str_starts_with($title, 'X-ray — ')) {
                continue;
            }
            $day = (string) ($f['date'] ?? '');
            if ($day < $from || $day > $to) {
                continue;
            }

            $timestamp = strtotime($day . ' ' . ($f['time'] ?? ''));
            $rows[] = [
                'sortKey' => $day . ' ' . ($f['time'] ?? ''),
                'date' => $timestamp !== false ? date('M j, Y', $timestamp) : $day,
                'time' => (string) ($f['time'] ?? '—'),
                'patientName' => $patientNamesById[$f['patientId'] ?? ''] ?? '(unknown patient)',
                'type' => 'X-ray',
                'detail' => substr($title, strlen('X-ray — ')) ?: 'X-ray exam',
                'status' => 'Completed',
            ];
        }
        usort($rows, fn ($a, $b) => strcmp($b['sortKey'], $a['sortKey']));

        return array_map(fn ($r) => array_diff_key($r, ['sortKey' => true]), $rows);
    }
}

if (!function_exists('hc_stream_patient_report_csv')) {
    /**
     * Streams $rows (the same {date,time,patientName,type,detail,status}
     * shape both builders above return) as a real CSV download and
     * exits — shared by every role's export-*-patient-report.php so the
     * column order/headers/escaping stay identical everywhere this
     * report is offered.
     *
     * @param array<int, array{date: string, time: string, patientName: string, type: string, detail: string, status: string}> $rows
     */
    function hc_stream_patient_report_csv(string $filename, array $rows): never
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $out = fopen('php://output', 'w');
        fputcsv($out, ['Patient', 'Date', 'Time', 'Type', 'Details', 'Status']);
        foreach ($rows as $r) {
            fputcsv($out, [$r['patientName'], $r['date'], $r['time'], $r['type'], $r['detail'], $r['status']]);
        }
        fclose($out);
        exit;
    }
}
