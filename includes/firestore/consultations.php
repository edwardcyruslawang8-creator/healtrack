<?php
/**
 * =====================================================================
 * Shared data layer — Consultations
 * ---------------------------------------------------------------------
 * Pure Firestore-query functions for the real "consultations"
 * collection — see includes/firestore/patients.php's header comment for why
 * this layer exists and what it does/doesn't own. This is the first
 * real consultations model in the app: previously every consultations
 * page was front-end only (see medical-staff/consultations.php's
 * earlier header comments).
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore
require_once __DIR__ . '/patients.php'; // hc_get_patient_profile(), hc_not_found_patient_profile()
require_once __DIR__ . '/lab_results.php'; // hc_consultation_is_awaiting_lab_results()

if (!function_exists('hc_map_consultation_doc')) {
    /**
     * Shared field-mapping for a raw consultations/{id} Firestore doc's
     * fields array — used by both hc_get_all_consultations() (bulk,
     * listDocuments()'s ['fields'] shape) and hc_get_consultation_by_id()
     * (single, getDocument()'s already-flattened shape) so the two never
     * drift out of sync. Includes the "Active Consultation" workspace's
     * fields (doctor/active_consultation.php) — all optional/nullable,
     * since they're only ever written once a doctor Saves & Ends a
     * consultation (api/complete-consultation.php), not at creation.
     *
     * @return array{id: string, consultationCode: string, patientId: string, patientName: string, consultationType: string, dateTime: string, assignedPersonnel: string, chiefComplaint: string, status: string, priority: string, nextFollowUp: string, notes: string, createdAtRaw: string, createdBy: string, startedAt: string, endedAt: string, historyOfPresentIllness: string, reviewOfSymptoms: string, diagnosis: string, prescriptionHtml: string, vitals: array, medications: array, nextRouting: string, labTests: array, doctorNotes: string, labResultDocIds: array}
     */
    function hc_map_consultation_doc(string $id, array $f): array
    {
        return [
            'id' => $id,
            'consultationCode' => $f['consultationCode'] ?? $id,
            'patientId' => $f['patientId'] ?? '',
            'patientName' => $f['patientName'] ?? '(unknown patient)',
            'consultationType' => $f['consultationType'] ?? 'walk_in',
            'dateTime' => $f['dateTime'] ?? '',
            'assignedPersonnel' => $f['assignedPersonnel'] ?? '—',
            'chiefComplaint' => $f['chiefComplaint'] ?? '—',
            'status' => $f['status'] ?? 'pending',
            'priority' => $f['priority'] ?? '',
            'nextFollowUp' => $f['nextFollowUp'] ?? '',
            'notes' => $f['notes'] ?? '',
            'createdAtRaw' => $f['createdAt'] ?? '',
            'createdBy' => $f['createdBy'] ?? '',
            'startedAt' => $f['startedAt'] ?? '',
            'endedAt' => $f['endedAt'] ?? '',
            'historyOfPresentIllness' => $f['historyOfPresentIllness'] ?? '',
            'reviewOfSymptoms' => $f['reviewOfSymptoms'] ?? '',
            'diagnosis' => $f['diagnosis'] ?? '',
            'prescriptionHtml' => $f['prescriptionHtml'] ?? '',
            'vitals' => $f['vitals'] ?? [],
            'medications' => $f['medications'] ?? [],
            'nextRouting' => $f['nextRouting'] ?? '',
            // Independent of nextRouting (which picks at most one of
            // Laboratory/Pharmacy/Vaccination) — a real, separate
            // "schedule another visit" flag, since needing a follow-up
            // doesn't rule out also being routed somewhere today. A
            // doc saved before this flag existed, back when "Follow-up/
            // Another Consultation" was itself one of the nextRouting
            // radio choices, still reads as checked here for that old
            // data.
            'needsFollowUp' => (bool) ($f['needsFollowUp'] ?? (($f['nextRouting'] ?? '') === 'follow_up')),
            'labTests' => $f['labTests'] ?? [],
            'xrayExams' => $f['xrayExams'] ?? [],
            'doctorNotes' => $f['doctorNotes'] ?? '',
            // The real lab_results doc ids this follow-up consultation
            // was created from (api/proceed-lab-result-to-doctor.php) —
            // empty for every consultation NOT created that way.
            'labResultDocIds' => $f['labResultDocIds'] ?? [],
        ];
    }
}

if (!function_exists('hc_get_all_consultations')) {
    /**
     * Every consultations/{id} doc, newest dateTime first.
     *
     * @return array<int, array{id: string, consultationCode: string, patientId: string, patientName: string, consultationType: string, dateTime: string, assignedPersonnel: string, chiefComplaint: string, status: string, priority: string, nextFollowUp: string, notes: string, createdAtRaw: string}>
     */
    function hc_get_all_consultations(): array
    {
        global $firestore;

        $rows = [];
        foreach ($firestore->listDocuments('consultations') as $doc) {
            $rows[] = hc_map_consultation_doc($doc['id'], $doc['fields']);
        }

        usort($rows, fn ($a, $b) => strcmp($b['dateTime'], $a['dateTime']));

        return $rows;
    }
}

if (!function_exists('hc_get_consultations_for_patient')) {
    /**
     * Every real consultations/{id} doc for $patientId — across every
     * doctor/medical-staff member who ever saw them, any status
     * (pending/ongoing/completed/cancelled) — newest dateTime first. Feeds
     * the Patient Profile's History tab ("Consultation History" table,
     * includes/shared/_history_record_table.php) for doctor/medical-staff/
     * subadmin alike.
     *
     * @return array<int, array{id: string, consultationCode: string, patientId: string, patientName: string, consultationType: string, dateTime: string, assignedPersonnel: string, chiefComplaint: string, status: string, diagnosis: string}>
     */
    function hc_get_consultations_for_patient(string $patientId): array
    {
        return array_values(array_filter(
            hc_get_all_consultations(),
            fn ($c) => $c['patientId'] === $patientId
        ));
    }
}

if (!function_exists('hc_get_consultation_by_id')) {
    /**
     * One consultations/{id} doc in the same shape as
     * hc_get_all_consultations()'s rows, or null if $id is blank or
     * doesn't match any real doc. Used by doctor/active_consultation.php
     * instead of listing+filtering every consultation for a single
     * lookup.
     */
    function hc_get_consultation_by_id(string $id): ?array
    {
        global $firestore;

        if ($id === '') {
            return null;
        }

        $doc = $firestore->getDocument('consultations', $id);
        if ($doc === null) {
            return null;
        }

        return hc_map_consultation_doc($id, $doc);
    }
}

if (!function_exists('hc_get_doctor_queue_status')) {
    /**
     * One doctor's own "who's waiting" snapshot — the SAME derivation
     * includes/doctor/consultations_doctor_firestore.php uses for
     * doctor/consultations.php's "Next to Consult"/"Ongoing
     * Consultation"/"Patient Information" cards, factored out here so
     * api/doctor-queue-status.php (polled by assets/js/doctor_consultations.js
     * so that page updates itself without a manual reload once medical
     * staff sets a new consultation) computes it identically instead of
     * drifting out of sync with a second copy of this logic.
     *
     * "Next to Consult" priority ordering: any consultation carrying a
     * real "priority" value (Senior/PWD/Pregnant — all three treated as
     * the SAME priority lane, not ranked against each other) goes ahead
     * of every unflagged one regardless of scheduled time, per RA 9994/
     * RA 10754's mandated priority lanes. Within the same tier, soonest
     * scheduled time first.
     *
     * @return array{myConsultations: array, nextConsult: ?array, nextPatient: ?array, ongoingConsult: ?array, ongoingPatient: ?array, patient: ?array}
     */
    function hc_get_doctor_queue_status(string $doctorName): array
    {
        $allConsultations = hc_get_all_consultations();
        $myConsultations = array_values(array_filter($allConsultations, fn ($c) => $c['assignedPersonnel'] === $doctorName));

        $pendingConsultations = array_values(array_filter($myConsultations, fn ($c) => $c['status'] === 'pending'));
        usort($pendingConsultations, function ($a, $b) {
            $aHasPriority = $a['priority'] !== '' ? 1 : 0;
            $bHasPriority = $b['priority'] !== '' ? 1 : 0;
            if ($aHasPriority !== $bHasPriority) {
                return $bHasPriority - $aHasPriority;
            }
            return strcmp($a['dateTime'], $b['dateTime']);
        });
        $nextConsult = $pendingConsultations[0] ?? null;
        $nextPatient = $nextConsult !== null
            ? (hc_get_patient_profile($nextConsult['patientId']) ?? hc_not_found_patient_profile($nextConsult['patientId']))
            : null;

        $ongoingConsultations = array_values(array_filter($myConsultations, fn ($c) => $c['status'] === 'ongoing'));
        usort($ongoingConsultations, fn ($a, $b) => strcmp($b['startedAt'], $a['startedAt']));
        $ongoingConsult = $ongoingConsultations[0] ?? null;
        $ongoingPatient = $ongoingConsult !== null
            ? (hc_get_patient_profile($ongoingConsult['patientId']) ?? hc_not_found_patient_profile($ongoingConsult['patientId']))
            : null;
        // Still surfaced as "Ongoing Consultation" either way — the
        // patient hasn't gone anywhere — but "Resume Consultation" only
        // makes sense once the lab is actually done with them; see
        // hc_consultation_is_awaiting_lab_results()'s own comment.
        $ongoingAwaitingLab = $ongoingConsult !== null && hc_consultation_is_awaiting_lab_results($ongoingConsult);

        return [
            'myConsultations' => $myConsultations,
            'nextConsult' => $nextConsult,
            'nextPatient' => $nextPatient,
            'ongoingConsult' => $ongoingConsult,
            'ongoingPatient' => $ongoingPatient,
            'ongoingAwaitingLab' => $ongoingAwaitingLab,
            'patient' => $nextPatient,
        ];
    }
}

if (!function_exists('hc_get_followup_notifications')) {
    /**
     * One real notification row per consultation with a real
     * "nextFollowUp" date due within the next 7 days (or already
     * overdue), across the "consultations" collection — the doctor/
     * medical-staff equivalent of includes/firestore/medicines.php's
     * hc_get_low_stock_notifications(): a genuinely actionable alert
     * derived from real data, not a fabricated placeholder list.
     *
     * $assignedPersonnel scopes the list to one doctor's own caseload
     * (doctor/notifications.php passes their own name) — null (the
     * default) returns every follow-up facility-wide
     * (medical-staff/notifications.php's scope, since that role isn't
     * personally assigned the way a doctor is).
     *
     * Category is "reminders" (not "system_alerts" like the inventory
     * alerts above) — a follow-up due is genuinely a reminder, giving
     * that tab real content for the first time instead of staying
     * permanently empty.
     *
     * 'id' is crc32() of the consultation doc id (same reasoning as
     * hc_get_low_stock_notifications()'s header comment — keeps
     * $_SESSION['ntfReadIds']/['ntfDismissedIds'] as a plain int array).
     *
     * @return array<int, array{id: int, title: string, description: string, type: string, date: string, time: string, icon: string, iconBg: string, iconColor: string}>
     */
    function hc_get_followup_notifications(?string $assignedPersonnel = null): array
    {
        $now = time();
        $windowEnd = strtotime('+7 days', $now);

        $rows = array_values(array_filter(hc_get_all_consultations(), function ($c) use ($assignedPersonnel, $now, $windowEnd) {
            if ($c['nextFollowUp'] === '') {
                return false;
            }
            if ($assignedPersonnel !== null && $c['assignedPersonnel'] !== $assignedPersonnel) {
                return false;
            }
            $due = strtotime($c['nextFollowUp']);

            return $due !== false && $due <= $windowEnd;
        }));

        usort($rows, fn ($a, $b) => strtotime($a['nextFollowUp']) <=> strtotime($b['nextFollowUp']));

        return array_map(function ($c) use ($now) {
            $due = strtotime($c['nextFollowUp']);
            $isOverdue = $due < $now;

            return [
                'id' => crc32($c['id'] . '-followup'),
                'title' => $isOverdue ? 'Follow-up overdue' : 'Follow-up due soon',
                'description' => $c['patientName'] . ' — follow-up ' . ($isOverdue ? 'was due' : 'due') . ' ' . date('M j, Y', $due) . '.',
                'type' => 'Consultation',
                'date' => date('M j, Y', $due),
                'time' => date('g:i A', $due),
                'icon' => 'bi-calendar2-check-fill',
                'iconBg' => $isOverdue ? '#FCE4E4' : '#F1E8FD',
                'iconColor' => $isOverdue ? '#D93025' : '#8B5CF6',
            ];
        }, $rows);
    }
}

if (!function_exists('hc_consultation_type_meta')) {
    /** Consultation type -> badge color meta, keyed the same as the "consultationType" field. */
    function hc_consultation_type_meta(): array
    {
        return [
            'walk_in'    => ['label' => 'Walk-in',          'bg' => '#E4F7EA', 'color' => '#1E8E3E'],
            'follow_up'  => ['label' => 'Follow-up',        'bg' => '#E8F0FE', 'color' => '#1A73E8'],
            'emergency'  => ['label' => 'Emergency',        'bg' => '#FCE4E4', 'color' => '#D93025'],
            'scheduled'  => ['label' => 'Scheduled',        'bg' => '#F1E8FD', 'color' => '#8B5CF6'],
            'tele'       => ['label' => 'Teleconsultation', 'bg' => '#E3F6FB', 'color' => '#12A6C6'],
        ];
    }
}

if (!function_exists('hc_consultation_status_meta')) {
    /** Status -> badge color meta, keyed the same as the "status" field. */
    function hc_consultation_status_meta(): array
    {
        return [
            'pending'   => ['label' => 'Pending',   'bg' => '#FEF3C7', 'color' => '#B45309'],
            'ongoing'   => ['label' => 'Ongoing',    'bg' => '#E8F0FE', 'color' => '#1A73E8'],
            'completed' => ['label' => 'Completed',  'bg' => '#E4F7EA', 'color' => '#1E8E3E'],
            'cancelled' => ['label' => 'Cancelled',  'bg' => '#FCE4E4', 'color' => '#D93025'],
        ];
    }
}

if (!function_exists('hc_consultation_priority_meta')) {
    /** Priority -> badge color meta, keyed the same as the "priority" field. */
    function hc_consultation_priority_meta(): array
    {
        return [
            'pregnant' => ['label' => 'Pregnant', 'bg' => '#FCE4EC', 'color' => '#D6336C'],
            'pwd'      => ['label' => 'Person with Disability (PWD)', 'bg' => '#E8F0FE', 'color' => '#1A73E8'],
            'senior'   => ['label' => 'Senior',    'bg' => '#FDECDD', 'color' => '#E8710A'],
        ];
    }
}

if (!function_exists('hc_get_physician_options')) {
    /**
     * Real doctor accounts (role=doctor only — healthcare_staff are
     * medical staff, not physicians a consultation gets assigned to) for
     * the "Assigned Physician" dropdown — not a fabricated name list.
     * Only accounts subadmin has actually approved (status=active) are
     * eligible — a self-registered doctor account sitting in subadmin/
     * user_management.php's "Pending Approvals" table (status=pending)
     * has no real login access yet (api/approve-account.php is what
     * flips status to "active"), so it shouldn't be assignable here
     * either; the name appears in this list the moment that approval
     * happens, with no other change needed. $facility, when given,
     * further narrows this to doctors at that one facility
     * (medical-staff/consultations.php passes the signed-in staff
     * member's own facility — a consultation should only be assignable
     * to a doctor actually working at the same site).
     *
     * @return array<int, array{uid: string, name: string}>
     */
    function hc_get_physician_options(?string $facility = null): array
    {
        global $firestore;

        $physicians = [];
        foreach ($firestore->listDocuments('users') as $doc) {
            $f = $doc['fields'];
            if (($f['role'] ?? '') !== 'doctor') {
                continue;
            }
            if (($f['status'] ?? 'active') === 'pending') {
                continue;
            }
            if ($facility !== null && ($f['facility'] ?? '') !== $facility) {
                continue;
            }
            $physicians[] = ['uid' => $doc['id'], 'name' => $f['name'] ?? '(unnamed)'];
        }

        usort($physicians, fn ($a, $b) => strcasecmp($a['name'], $b['name']));

        return $physicians;
    }
}

if (!function_exists('hc_next_consultation_code')) {
    /** "CONS-{year}-{seq}" — seq is a simple count-based sequence, not a strict atomic counter (fine for this app's traffic). */
    function hc_next_consultation_code(array $allConsultations): string
    {
        $thisYear = date('Y');
        $countThisYear = count(array_filter($allConsultations, fn ($c) => str_starts_with($c['consultationCode'], "CONS-{$thisYear}-")));

        return sprintf('CONS-%s-%04d', $thisYear, $countThisYear + 1);
    }
}

if (!function_exists('hc_consultation_stats')) {
    /**
     * Real summary stat cards over $allConsultations — Total, Today's,
     * Pending, Completed, Follow-up Patients — each with an honestly
     * computed trend (comparing this month's vs last month's *created*
     * count) rather than a fabricated percentage. Trend is omitted
     * (not zero) when there's no prior-month data to compare against,
     * since "0% change" would misleadingly imply a real flat trend.
     *
     * @return array<int, array{label: string, value: int, trendLabel: ?string, trendDir: string}>
     */
    function hc_consultation_stats(array $allConsultations): array
    {
        $today = date('Y-m-d');
        $thisMonthPrefix = date('Y-m');
        $lastMonthPrefix = date('Y-m', strtotime('first day of last month'));

        $countThisMonth = count(array_filter($allConsultations, fn ($c) => str_starts_with($c['createdAtRaw'], $thisMonthPrefix)));
        $countLastMonth = count(array_filter($allConsultations, fn ($c) => str_starts_with($c['createdAtRaw'], $lastMonthPrefix)));
        $trendLabel = null;
        $trendDir = 'up';
        if ($countLastMonth > 0) {
            $pct = round((($countThisMonth - $countLastMonth) / $countLastMonth) * 100);
            $trendDir = $pct < 0 ? 'down' : 'up';
            $trendLabel = abs($pct) . '% from last month';
        }

        $todayCount = count(array_filter($allConsultations, fn ($c) => str_starts_with($c['dateTime'], $today)));
        $pendingCount = count(array_filter($allConsultations, fn ($c) => $c['status'] === 'pending'));
        $completedCount = count(array_filter($allConsultations, fn ($c) => $c['status'] === 'completed'));
        $followUpCount = count(array_filter($allConsultations, fn ($c) => $c['consultationType'] === 'follow_up'));

        return [
            ['icon' => 'bi-clipboard2-pulse-fill', 'iconBg' => '#FCE4E4', 'iconColor' => '#D93025', 'label' => 'Total Consultations',     'value' => count($allConsultations), 'trendLabel' => $trendLabel, 'trendDir' => $trendDir],
            ['icon' => 'bi-calendar2-check-fill',  'iconBg' => '#E8F0FE', 'iconColor' => '#1A73E8', 'label' => "Today's Consultations",   'value' => $todayCount,               'trendLabel' => null, 'trendDir' => 'up'],
            ['icon' => 'bi-clock-history',         'iconBg' => '#FEF3C7', 'iconColor' => '#B45309', 'label' => 'Pending Consultations',    'value' => $pendingCount,             'trendLabel' => null, 'trendDir' => 'up'],
            ['icon' => 'bi-check-circle-fill',     'iconBg' => '#E4F7EA', 'iconColor' => '#1E8E3E', 'label' => 'Completed Consultations',  'value' => $completedCount,           'trendLabel' => null, 'trendDir' => 'up'],
            ['icon' => 'bi-people-fill',           'iconBg' => '#F1E8FD', 'iconColor' => '#8B5CF6', 'label' => 'Follow-up Patients',       'value' => $followUpCount,            'trendLabel' => null, 'trendDir' => 'up'],
        ];
    }
}
