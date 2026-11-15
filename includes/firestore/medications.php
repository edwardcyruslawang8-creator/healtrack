<?php
/**
 * =====================================================================
 * Shared data layer — Medications
 * ---------------------------------------------------------------------
 * Pure Firestore-query functions for the real "medications" and
 * "medication_history" collections — see includes/firestore/patients.php's
 * header comment for why this layer exists and what it does/doesn't
 * own.
 *
 * AUTOMATIC CURRENT -> PAST CLASSIFICATION
 * ---------------------------------------------------------------------
 * A medication's Current/Past placement is NEVER stored as a flag that
 * something has to remember to flip. It is derived, on every read, from
 * the doc's own dates by hc_medication_classify() — so the moment a
 * prescription's treatment period ends it is "past" everywhere at once
 * (every role's Medications page, the patient overview count, anything
 * else that calls hc_get_medications_for_patient()), with no cron job,
 * no manual "move" action, no duplicate record, and no dependence on
 * anyone opening a particular page first.
 *
 * Fields a medications/{id} doc may carry (all but the first block are
 * optional — older docs simply lack them and are handled below):
 *   patientId, name, category, dosage, frequency, route, indication,
 *   startDate (Y-m-d), endDate (Y-m-d|null), endDateNote, prescribedBy,
 *   notes, status ('active' | 'completed' | 'discontinued')
 *   duration          — the prescriber's own free-text duration ("7 days")
 *   durationDays      — parsed length in days, or null
 *   isMaintenance     — true = long-term / as-needed, no defined end date
 *   consultationId / consultationCode — the prescribing consultation
 *   extendedEndDate   — current end date after a doctor's extension;
 *                       endDate itself is never rewritten
 *   extensions[]      — {previousEndDate, newEndDate, reason, by, at}
 *   discontinuedDate / discontinuedBy / discontinueReason
 *   completionConfirmedBy / completionConfirmedDate
 *
 * Every date is a plain Y-m-d calendar date in the clinic's own timezone
 * (Asia/Manila) — see hc_medication_today() — so a server/browser in a
 * different timezone can never shift a medication a day early or late.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore

if (!defined('HC_MEDICATION_TZ')) {
    define('HC_MEDICATION_TZ', 'Asia/Manila');
    /**
     * A medication with NO end date and NOT marked as maintenance (e.g. a
     * prescription saved with a blank Duration, or a pharmacist's direct
     * over-the-counter dispense) keeps the app's previous behavior: it
     * leaves Current once this many months pass since it started, so a
     * one-off prescription doesn't sit in Current forever. Maintenance
     * medications are exempt — see hc_medication_classify().
     */
    define('HC_MEDICATION_OPEN_ENDED_LAPSE', '-3 months');
}

if (!function_exists('hc_medication_today')) {
    /** Today's date (Y-m-d) in the clinic's timezone. */
    function hc_medication_today(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone(HC_MEDICATION_TZ)))->format('Y-m-d');
    }
}

if (!function_exists('hc_medication_normalize_date')) {
    /** A stored date string as Y-m-d, or null when blank/unparseable. */
    function hc_medication_normalize_date(?string $raw): ?string
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return null;
        }
        try {
            return (new DateTimeImmutable($raw, new DateTimeZone(HC_MEDICATION_TZ)))->format('Y-m-d');
        } catch (Exception $e) {
            return null;
        }
    }
}

if (!function_exists('hc_parse_medication_duration')) {
    /**
     * Reads a prescriber's free-text Duration ("7 days", "1-2 days",
     * "up to 3 days", "2 weeks", "1 month", "x5d", "10", "maintenance",
     * "as needed").
     *
     * Ranges use their upper bound ("1-2 days" -> 2) so a medication is
     * never moved to Past while it could still legitimately be taken.
     * Anything that isn't recognisably a length of time (e.g. "1-2 times
     * a day" typed in the wrong column) yields no duration rather than a
     * guess.
     *
     * @return array{days: ?int, months: ?int, maintenance: bool}
     */
    function hc_parse_medication_duration(string $raw): array
    {
        $none = ['days' => null, 'months' => null, 'maintenance' => false];
        $text = strtolower(trim($raw));
        if ($text === '') {
            return $none;
        }

        if (preg_match('/\b(maintenance|maint|ongoing|long[\s-]?term|indefinite(ly)?|continuous(ly)?|lifelong|for life|prn|as needed|as necessary|until further notice)\b/', $text)) {
            return ['days' => null, 'months' => null, 'maintenance' => true];
        }

        $count = null;
        $unit = 'day';
        if (preg_match('/(\d+(?:\.\d+)?)\s*(?:(?:-|–|to)\s*(\d+(?:\.\d+)?))?\s*(days?|d|weeks?|wks?|wk|w|months?|mos?|mo)\b/', $text, $m)) {
            $count = (float) (($m[2] ?? '') !== '' ? $m[2] : $m[1]);
            $unit = match (true) {
                str_starts_with($m[3], 'w') => 'week',
                str_starts_with($m[3], 'm') => 'month',
                default => 'day',
            };
        } elseif (preg_match('/^(\d+)$/', $text, $m)) {
            $count = (float) $m[1]; // a bare number in a Duration box means days
        }

        if ($count === null || $count <= 0) {
            return $none;
        }

        if ($unit === 'month' && floor($count) == $count) {
            return ['days' => null, 'months' => (int) $count, 'maintenance' => false];
        }
        $days = (int) ceil(match ($unit) {
            'week' => $count * 7,
            'month' => $count * 30,
            default => $count,
        });

        return $days > 0 && $days <= 3650 ? ['days' => $days, 'months' => null, 'maintenance' => false] : $none;
    }
}

if (!function_exists('hc_medication_end_date')) {
    /**
     * Last day of treatment (inclusive) for a course starting $startDate:
     * 7 days from Nov 14 ends Nov 20; 1 month from Nov 14 ends Dec 13;
     * 1 month from Jan 31 ends Feb 27 (Jan 31 + 1 month clamps to Feb 28).
     *
     * @param array{days: ?int, months: ?int, maintenance: bool} $parsed hc_parse_medication_duration()'s result.
     */
    function hc_medication_end_date(string $startDate, array $parsed): ?string
    {
        $start = hc_medication_normalize_date($startDate);
        if ($start === null || $parsed['maintenance']) {
            return null;
        }
        $d = new DateTimeImmutable($start, new DateTimeZone(HC_MEDICATION_TZ));
        if ($parsed['months'] !== null) {
            // Clamp to month end so Jan 31 + 1 month doesn't overflow into March.
            $target = $d->modify('first day of +' . $parsed['months'] . ' month');
            $day = min((int) $d->format('j'), (int) $target->format('t'));
            return $target->setDate((int) $target->format('Y'), (int) $target->format('n'), $day)->modify('-1 day')->format('Y-m-d');
        }
        if ($parsed['days'] !== null) {
            return $d->modify('+' . ($parsed['days'] - 1) . ' days')->format('Y-m-d');
        }
        return null;
    }
}

if (!function_exists('hc_medication_classify')) {
    /**
     * The single rule deciding where a medication belongs right now.
     *
     *   state         list     label
     *   ------------  -------  -------------------------------------------
     *   discontinued  past     Discontinued (a doctor stopped it early)
     *   completed     past     Completed (completion confirmed by staff)
     *   scheduled     current  Scheduled (start date still in the future)
     *   active        current  Active (within its prescribed period)
     *   ended         past     Treatment Period Ended (end date has passed)
     *   ongoing       current  Ongoing (maintenance / as-needed, no end date)
     *   open          current  Active — no end date recorded
     *   lapsed        past     No Longer Active (no end date, >3 months old)
     *
     * "ended" deliberately does NOT say "Completed": an elapsed period
     * only means the prescribed course is over, not that the patient
     * actually finished it. Only an explicit confirmation by a doctor
     * (status 'completed') earns "Completed".
     *
     * @param array<string, mixed> $f The medication doc's raw fields.
     * @return array{state: string, isCurrent: bool, label: string, tone: string, startDate: ?string, endDate: ?string, originalEndDate: ?string, isMaintenance: bool}
     */
    function hc_medication_classify(array $f, ?string $today = null): array
    {
        $today ??= hc_medication_today();
        $status = strtolower((string) ($f['status'] ?? 'active'));
        $start = hc_medication_normalize_date($f['startDate'] ?? null);
        $originalEnd = hc_medication_normalize_date($f['endDate'] ?? null);
        $end = hc_medication_normalize_date($f['extendedEndDate'] ?? null) ?? $originalEnd;

        // Maintenance = explicitly flagged, a maintenance-style Duration,
        // or a medication the patient reported already taking at
        // registration (no prescriber here set an end date for it).
        $isMaintenance = !empty($f['isMaintenance'])
            || hc_parse_medication_duration((string) ($f['duration'] ?? ''))['maintenance']
            || ($end === null && ($f['notes'] ?? '') === 'Recorded during patient registration.');

        $base = ['startDate' => $start, 'endDate' => $end, 'originalEndDate' => $originalEnd, 'isMaintenance' => $isMaintenance];
        $as = fn (string $state, bool $current, string $label, string $tone) => ['state' => $state, 'isCurrent' => $current, 'label' => $label, 'tone' => $tone] + $base;

        if ($status === 'discontinued') {
            return $as('discontinued', false, 'Discontinued', 'red');
        }
        if ($status === 'completed') {
            return $as('completed', false, 'Completed', 'green');
        }
        if ($start !== null && $start > $today) {
            return $as('scheduled', true, 'Scheduled', 'blue');
        }
        if ($end !== null) {
            return $end >= $today
                ? $as('active', true, 'Active', 'green')
                : $as('ended', false, 'Treatment Period Ended', 'gray');
        }
        if ($isMaintenance) {
            return $as('ongoing', true, 'Ongoing (no end date)', 'blue');
        }
        $lapseCutoff = (new DateTimeImmutable($today, new DateTimeZone(HC_MEDICATION_TZ)))->modify(HC_MEDICATION_OPEN_ENDED_LAPSE)->format('Y-m-d');
        if ($start === null || $start >= $lapseCutoff) {
            return $as('open', true, 'Active — no end date recorded', 'amber');
        }
        return $as('lapsed', false, 'No Longer Active', 'gray');
    }
}

if (!function_exists('hc_medication_is_current')) {
    /** Kept for any caller still using the old signature — see hc_medication_classify(). */
    function hc_medication_is_current(string $rawStatus, string $rawStartDate): bool
    {
        return hc_medication_classify(['status' => $rawStatus, 'startDate' => $rawStartDate])['isCurrent'];
    }
}

if (!function_exists('hc_medication_fmt_date')) {
    function hc_medication_fmt_date(?string $ymd): ?string
    {
        return $ymd !== null ? date('M j, Y', strtotime($ymd)) : null;
    }
}

if (!function_exists('hc_build_prescribed_medication_fields')) {
    /**
     * The duration-related fields for a medication prescribed on
     * $startDate with free-text $duration — shared by every writer so
     * the end date is computed the same way everywhere.
     *
     * @return array{duration: ?string, durationDays: ?int, isMaintenance: bool, endDate: ?string}
     */
    function hc_build_prescribed_medication_fields(string $startDate, string $duration): array
    {
        $parsed = hc_parse_medication_duration($duration);
        $end = hc_medication_end_date($startDate, $parsed);
        $days = $end !== null
            ? (int) (new DateTimeImmutable($startDate))->diff(new DateTimeImmutable($end))->days + 1
            : null;

        return [
            'duration' => trim($duration) !== '' ? trim($duration) : null,
            'durationDays' => $days,
            'isMaintenance' => $parsed['maintenance'],
            'endDate' => $end,
        ];
    }
}

if (!function_exists('hc_map_medication_doc')) {
    /** One medications/{id} doc -> the display row every Medications page renders. */
    function hc_map_medication_doc(array $doc, ?string $today = null): array
    {
        $f = $doc['fields'];
        $c = hc_medication_classify($f, $today);

        $duration = trim((string) ($f['duration'] ?? ''));
        if ($duration === '' && !empty($f['durationDays'])) {
            $duration = $f['durationDays'] . ' day' . ($f['durationDays'] == 1 ? '' : 's');
        }

        $consultationCode = (string) ($f['consultationCode'] ?? '');
        if ($consultationCode === '' && preg_match('/consultation (\S+) on/', (string) ($f['notes'] ?? ''), $m)) {
            $consultationCode = $m[1];
        }

        $extensions = array_map(fn ($e) => [
            'previousEndDate' => hc_medication_fmt_date(hc_medication_normalize_date($e['previousEndDate'] ?? null)) ?? 'No end date',
            'newEndDate' => hc_medication_fmt_date(hc_medication_normalize_date($e['newEndDate'] ?? null)) ?? '—',
            'reason' => (string) ($e['reason'] ?? ''),
            'by' => (string) ($e['by'] ?? ''),
            'date' => hc_medication_fmt_date(hc_medication_normalize_date($e['at'] ?? null)) ?? '',
        ], is_array($f['extensions'] ?? null) ? $f['extensions'] : []);

        $endLabel = match (true) {
            $c['state'] === 'discontinued' && !empty($f['discontinuedDate']) => 'Stopped ' . hc_medication_fmt_date(hc_medication_normalize_date($f['discontinuedDate'])),
            $c['endDate'] !== null => hc_medication_fmt_date($c['endDate']),
            $c['isMaintenance'] => 'No end date (maintenance)',
            default => 'No end date recorded',
        };

        return [
            'id' => $doc['id'],
            'name' => (string) ($f['name'] ?? ''),
            'category' => (string) ($f['category'] ?? ''),
            'dosage' => (string) ($f['dosage'] ?? ''),
            'frequency' => (string) ($f['frequency'] ?? ''),
            'route' => (string) ($f['route'] ?? ''),
            'indication' => (string) ($f['indication'] ?? ''),
            'startDate' => hc_medication_fmt_date($c['startDate']) ?? '—',
            'endDate' => hc_medication_fmt_date($c['endDate']),
            'endLabel' => $endLabel,
            'originalEndDate' => hc_medication_fmt_date($c['originalEndDate']),
            'endDateNote' => $f['endDateNote'] ?? null,
            'duration' => $duration !== '' ? $duration : ($c['isMaintenance'] ? 'Maintenance / as needed' : 'Not specified'),
            'prescribedBy' => (string) ($f['prescribedBy'] ?? ''),
            'notes' => (string) ($f['notes'] ?? ''),
            'status' => (string) ($f['status'] ?? 'active'),
            'state' => $c['state'],
            'statusLabel' => $c['label'],
            'statusTone' => $c['tone'],
            'isCurrent' => $c['isCurrent'],
            'isMaintenance' => $c['isMaintenance'],
            'consultationCode' => $consultationCode,
            'extensions' => $extensions,
            'discontinuedDate' => hc_medication_fmt_date(hc_medication_normalize_date($f['discontinuedDate'] ?? null)),
            'discontinuedBy' => (string) ($f['discontinuedBy'] ?? ''),
            'discontinueReason' => (string) ($f['discontinueReason'] ?? ''),
            'completionConfirmedBy' => (string) ($f['completionConfirmedBy'] ?? ''),
            'completionConfirmedDate' => hc_medication_fmt_date(hc_medication_normalize_date($f['completionConfirmedDate'] ?? null)),
            // Raw Y-m-d values for sorting / the derived history timeline.
            'rawStartDate' => $c['startDate'],
            'rawEndDate' => $c['endDate'],
        ];
    }
}

if (!function_exists('hc_get_medications_for_patient')) {
    /**
     * Every medications/{id} doc for $patientId, newest started first,
     * each already classified Current/Past for today.
     *
     * @return array<int, array<string, mixed>> See hc_map_medication_doc().
     */
    function hc_get_medications_for_patient(string $patientId): array
    {
        global $firestore;

        $docs = array_values($firestore->listDocumentsWhere('medications', 'patientId', $patientId));
        usort($docs, fn ($a, $b) => strcmp($b['fields']['startDate'] ?? '', $a['fields']['startDate'] ?? ''));

        $today = hc_medication_today();
        return array_map(fn ($doc) => hc_map_medication_doc($doc, $today), $docs);
    }
}

if (!function_exists('hc_get_medication_history_for_patient')) {
    /**
     * Every medication_history/{id} doc for $patientId, newest first.
     *
     * When $medications (hc_get_medications_for_patient()'s result) is
     * given, a "Treatment period ended" entry is also shown for every
     * prescription whose period has elapsed. That entry is derived, not
     * stored, for the same reason the Current/Past split is: it becomes
     * true by the calendar alone, with no event to write it.
     *
     * @return array<int, array{date: string, event: string, detail: string, indicatorColor: string}>
     */
    function hc_get_medication_history_for_patient(string $patientId, ?array $medications = null): array
    {
        global $firestore;

        $rows = array_map(fn ($doc) => [
            'raw' => (string) ($doc['fields']['date'] ?? ''),
            'event' => (string) ($doc['fields']['event'] ?? ''),
            'detail' => (string) ($doc['fields']['detail'] ?? ''),
            'indicatorColor' => (string) ($doc['fields']['indicatorColor'] ?? 'gray'),
        ], array_values($firestore->listDocumentsWhere('medication_history', 'patientId', $patientId)));

        foreach ($medications ?? [] as $m) {
            if ($m['state'] === 'ended' && $m['rawEndDate'] !== null) {
                $rows[] = [
                    'raw' => $m['rawEndDate'],
                    'event' => 'Treatment period ended',
                    'detail' => $m['name'] . ' (' . $m['dosage'] . ', ' . $m['frequency'] . ') — prescribed course ended on ' . $m['endDate'] . '.',
                    'indicatorColor' => 'gray',
                ];
            }
        }

        usort($rows, fn ($a, $b) => strcmp($b['raw'], $a['raw']));

        return array_map(fn ($r) => [
            'date' => $r['raw'] !== '' ? date('M j, Y', strtotime($r['raw'])) : '—',
            'event' => $r['event'],
            'detail' => $r['detail'],
            'indicatorColor' => $r['indicatorColor'],
        ], $rows);
    }
}

if (!function_exists('hc_record_medication_history')) {
    /** Appends one real medication_history/{id} timeline entry. */
    function hc_record_medication_history(string $patientId, string $event, string $detail, string $color = 'blue', ?string $medicationId = null): void
    {
        global $firestore;

        $firestore->addDocument('medication_history', [
            'patientId' => $patientId,
            'medicationId' => $medicationId,
            'date' => hc_medication_today(),
            'event' => $event,
            'detail' => $detail,
            'indicatorColor' => $color,
        ]);
    }
}

if (!function_exists('hc_medications_summary')) {
    /** Real counts/summary over an already-fetched $medications list, plus whether this patient has any allergy on record. */
    function hc_medications_summary(array $medications, bool $hasAllergies): array
    {
        $active = array_filter($medications, fn ($m) => $m['isCurrent']);

        return [
            'activeMedications' => count($active),
            'noKnownAllergicReactions' => $hasAllergies ? 'No — see Allergies tab' : 'Yes',
            'lastUpdated' => $medications[0]['startDate'] ?? '—',
            'lastUpdatedBy' => $medications[0]['prescribedBy'] ?? '—',
        ];
    }
}
