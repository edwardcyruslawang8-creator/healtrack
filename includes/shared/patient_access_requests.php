<?php
/**
 * =====================================================================
 * Doctor Patient-Access Requests + Patient Email Consent
 * ---------------------------------------------------------------------
 * Real "patient_access_requests" collection (Firestore auto-ID docs).
 * doctor/patient_management.php scopes visibility by FACILITY only —
 * every doctor at a facility can already see every patient registered
 * there. This module adds a stricter, per-doctor ownership layer on
 * top of that: a patient "belongs" to whichever real doctor account
 * hc_resolve_patient_owning_doctor() resolves (see that function's own
 * comment for the exact rule), and a DIFFERENT doctor who wants to view
 * that patient's chart must have their request approved by the owning
 * doctor AND have the patient themselves consent via an emailed link
 * (api/decide-patient-access-request.php sends it, patient-consent.php
 * + api/respond-patient-consent.php handle the patient's side — neither
 * requires a patient login, since patients have no accounts in this
 * app).
 *
 * Doc shape:
 *   patientId, patientName          — denormalized for display
 *   requestedByUid, requestedByName — the doctor asking for access
 *   ownerUid, ownerName             — the resolved owning doctor (may be
 *                                      null — see hc_maybe_trigger_auto_access_request()
 *                                      below, where a patient can be unowned)
 *   reason                          — free-text — either the requester's
 *                                      own note, or a system-generated
 *                                      explanation for an auto-detected request
 *   initiatedBy                     — 'colleague_request' (a doctor clicked
 *                                      Request Access) | 'system_detected'
 *                                      (hc_maybe_trigger_auto_access_request()
 *                                      recognized a real consultation
 *                                      pattern on its own)
 *   status                          — 'pending_owner' | 'denied_by_owner'
 *                                      | 'pending_patient' | 'approved'
 *                                      | 'declined_by_patient'
 *                                      (system_detected requests start
 *                                      straight at 'pending_patient' — no
 *                                      owning-doctor approval step; the
 *                                      real consultation history already
 *                                      IS the evidence)
 *   consentTokenHash                — sha256(raw token) — same scheme as
 *                                      includes/shared/remember_me.php's
 *                                      hc_issue_remember_token(): only the
 *                                      hash is ever stored server-side.
 *                                      Set once a consent email goes out
 *                                      (owner-approved, or system-detected).
 *   consentTokenExpiresAt           — date('c'), 7 days from send
 *   createdAt, ownerDecidedAt, patientDecidedAt
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore
require_once __DIR__ . '/../firestore/consultations.php'; // hc_get_physician_options(), hc_get_all_consultations()
require_once __DIR__ . '/../firestore/patients.php'; // hc_get_patient_profile()
require_once __DIR__ . '/mailer.php'; // hc_send_email(), hc_account_email_shell()
require_once __DIR__ . '/audit_log_writer.php'; // write_audit_log()

const HC_ACCESS_CONSENT_TOKEN_TTL_DAYS = 7;

// How many of a patient's most recent consultations must all be the same
// doctor before hc_maybe_trigger_auto_access_request() treats that as a
// real, recognized care relationship worth asking the patient about.
const HC_AUTO_ACCESS_CONSULT_STREAK = 3;

if (!function_exists('hc_resolve_patient_owning_doctor')) {
    /**
     * The real doctor account (['uid' => ..., 'name' => ...]) this
     * patient "belongs to", or null if nobody can be resolved (the
     * patient stays visible to every doctor at the facility, same as
     * this app's behavior before this feature existed — never lock a
     * patient to nobody).
     *
     * Rule: (1) $patient['primaryPhysician'] (set to whoever registered
     * them, api/create-patient.php — could be a nurse) if that name
     * matches a real, active doctor account at this facility; else
     * (2) whichever real doctor at this facility has the most
     * consultations for this patient; else (3) unowned.
     */
    function hc_resolve_patient_owning_doctor(array $patient): ?array
    {
        $facility = $patient['facility'] ?? '';
        $physicians = hc_get_physician_options($facility); // [{'uid','name'}, ...]

        // '—' is includes/firestore/patients.php's own hc_get_patient_profile()
        // placeholder for "not set" (hc_map_patient_summary_doc(), the
        // other real caller, uses null instead) — treat both the same.
        $primaryPhysician = $patient['primaryPhysician'] ?? null;
        if ($primaryPhysician !== null && $primaryPhysician !== '' && $primaryPhysician !== '—') {
            foreach ($physicians as $doc) {
                if ($doc['name'] === $primaryPhysician) {
                    return $doc;
                }
            }
        }

        $consultCounts = [];
        foreach (hc_get_all_consultations() as $c) {
            if (($c['patientId'] ?? '') !== ($patient['id'] ?? '')) {
                continue;
            }
            $name = $c['assignedPersonnel'] ?? '';
            $consultCounts[$name] = ($consultCounts[$name] ?? 0) + 1;
        }
        arsort($consultCounts);
        foreach (array_keys($consultCounts) as $name) {
            foreach ($physicians as $doc) {
                if ($doc['name'] === $name) {
                    return $doc;
                }
            }
        }

        return null;
    }
}

if (!function_exists('hc_doctor_has_patient_access')) {
    /**
     * True if $doctorUid may view $patient's full chart — either
     * they're the resolved owning doctor, the patient is unowned (see
     * hc_resolve_patient_owning_doctor()), or a prior
     * patient_access_requests doc for this exact (patient, doctor) pair
     * reached real 'approved' status (both the owner AND the patient
     * said yes).
     */
    function hc_doctor_has_patient_access(array $patient, string $doctorUid): bool
    {
        global $firestore;

        $owner = hc_resolve_patient_owning_doctor($patient);
        if ($owner === null || $owner['uid'] === $doctorUid) {
            return true;
        }

        foreach ($firestore->listDocuments('patient_access_requests') as $doc) {
            $f = $doc['fields'];
            if (($f['patientId'] ?? '') === ($patient['id'] ?? '')
                && ($f['requestedByUid'] ?? '') === $doctorUid
                && ($f['status'] ?? '') === 'approved') {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('hc_get_pending_owner_requests')) {
    /** Real requests waiting on $ownerUid to decide, newest first. */
    function hc_get_pending_owner_requests(string $ownerUid): array
    {
        global $firestore;

        $rows = [];
        foreach ($firestore->listDocuments('patient_access_requests') as $doc) {
            $f = $doc['fields'];
            if (($f['ownerUid'] ?? '') !== $ownerUid || ($f['status'] ?? '') !== 'pending_owner') {
                continue;
            }
            $rows[] = [
                'id' => $doc['id'],
                'patientId' => $f['patientId'] ?? '',
                'patientName' => $f['patientName'] ?? '',
                'requestedByUid' => $f['requestedByUid'] ?? '',
                'requestedByName' => $f['requestedByName'] ?? '',
                'reason' => $f['reason'] ?? '',
                'createdAtRaw' => $f['createdAt'] ?? '',
                'createdAt' => isset($f['createdAt']) ? date('M j, Y g:i A', strtotime($f['createdAt'])) : '—',
            ];
        }
        usort($rows, fn ($a, $b) => strcmp($b['createdAtRaw'], $a['createdAtRaw']));

        return $rows;
    }
}

if (!function_exists('hc_get_access_request_notifications')) {
    /**
     * Real notifications for this workflow, shape-matched to
     * includes/firestore/consultations.php's hc_get_followup_notifications()
     * so it merges into the same pipeline (e.g.
     * includes/doctor/notifications_doctor_firestore.php) with no markup
     * changes. Covers BOTH directions for $doctorUid: requests waiting
     * on them to decide (as owner), and their own requests that have
     * since been decided (approved / denied / declined).
     */
    function hc_get_access_request_notifications(string $doctorUid): array
    {
        global $firestore;

        $rows = [];
        foreach ($firestore->listDocuments('patient_access_requests') as $doc) {
            $f = $doc['fields'];
            $status = $f['status'] ?? '';
            $patientName = $f['patientName'] ?? 'a patient';

            if (($f['ownerUid'] ?? '') === $doctorUid && $status === 'pending_owner') {
                $ts = strtotime($f['createdAt'] ?? '') ?: 0;
                $rows[] = [
                    '_ts' => $ts,
                    'id' => crc32($doc['id'] . '-access-owner'),
                    'title' => 'Patient access requested',
                    'description' => ($f['requestedByName'] ?? 'A doctor') . ' requested access to ' . $patientName . "'s record.",
                    'type' => 'Patient Access',
                    'date' => $ts ? date('M j, Y', $ts) : '—',
                    'time' => $ts ? date('g:i A', $ts) : '—',
                    'icon' => 'bi-person-lock',
                    'iconBg' => '#FDECDD',
                    'iconColor' => '#E8710A',
                ];
                continue;
            }

            if (($f['requestedByUid'] ?? '') === $doctorUid && $status === 'pending_patient') {
                $ts = strtotime($f['consentTokenExpiresAt'] ?? $f['createdAt'] ?? '') ?: 0;
                $rows[] = [
                    '_ts' => $ts,
                    'id' => crc32($doc['id'] . '-access-awaiting-patient'),
                    'title' => 'Awaiting patient consent',
                    'description' => ($f['initiatedBy'] ?? '') === 'system_detected'
                        ? 'A consent email was sent to ' . $patientName . ' after we noticed you\'ve been their regular doctor.'
                        : 'A consent email was sent to ' . $patientName . ' for your access request.',
                    'type' => 'Patient Access',
                    'date' => $ts ? date('M j, Y', $ts) : '—',
                    'time' => $ts ? date('g:i A', $ts) : '—',
                    'icon' => 'bi-envelope-paper-fill',
                    'iconBg' => '#E8F0FE',
                    'iconColor' => '#1A73E8',
                ];
                continue;
            }

            if (($f['requestedByUid'] ?? '') === $doctorUid && in_array($status, ['approved', 'declined_by_patient', 'denied_by_owner'], true)) {
                $decidedAtRaw = $f['patientDecidedAt'] ?? $f['ownerDecidedAt'] ?? $f['createdAt'] ?? '';
                $ts = strtotime($decidedAtRaw) ?: 0;
                $labels = [
                    'approved' => ['Access granted', $patientName . "'s record is now visible to you.", '#E4F7EA', '#1E8E3E', 'bi-unlock-fill'],
                    'declined_by_patient' => ['Access declined', 'The patient declined to share ' . $patientName . "'s record.", '#FCE4E4', '#D93025', 'bi-slash-circle-fill'],
                    'denied_by_owner' => ['Access denied', ($f['ownerName'] ?? 'The owning doctor') . ' denied your request for ' . $patientName . '.', '#FCE4E4', '#D93025', 'bi-slash-circle-fill'],
                ];
                [$title, $description, $bg, $color, $icon] = $labels[$status];
                $rows[] = [
                    '_ts' => $ts,
                    'id' => crc32($doc['id'] . '-access-decided-' . $status),
                    'title' => $title,
                    'description' => $description,
                    'type' => 'Patient Access',
                    'date' => $ts ? date('M j, Y', $ts) : '—',
                    'time' => $ts ? date('g:i A', $ts) : '—',
                    'icon' => $icon,
                    'iconBg' => $bg,
                    'iconColor' => $color,
                ];
            }
        }

        usort($rows, fn ($a, $b) => $b['_ts'] <=> $a['_ts']);

        return array_map(function ($r) {
            unset($r['_ts']);

            return $r;
        }, $rows);
    }
}

if (!function_exists('hc_maybe_trigger_auto_access_request')) {
    /**
     * Called right after a real consultation is completed
     * (api/complete-consultation.php) — checks whether $doctorName has
     * now consulted $patientId for their last HC_AUTO_ACCESS_CONSULT_STREAK
     * consultations IN A ROW (a real, recognized care relationship, not
     * a one-off visit), and if so — and they aren't already the
     * resolved owner, and this exact (patient, doctor) pair has never
     * been asked before in ANY prior request regardless of status (a
     * patient's prior decline is respected — the system never asks
     * again) — creates a real patient_access_requests doc straight at
     * 'pending_patient' (no owning-doctor approval step for a
     * system-recognized pattern; the consultation history itself is
     * the evidence) and emails the patient a real consent link, same
     * token mechanism api/decide-patient-access-request.php uses for a
     * manually approved request.
     *
     * Fire-and-forget: never throws. A patient with no email on file
     * just skips silently (logged, same as hc_send_email()'s own
     * behavior) since there's no request in flight for a caller to
     * report an error back to — completing a consultation must never
     * fail just because this side-effect couldn't fully run.
     */
    function hc_maybe_trigger_auto_access_request(string $patientId, string $doctorName): void
    {
        global $firestore;

        try {
            $patient = hc_get_patient_profile($patientId);
            if ($patient === null) {
                return;
            }

            $physicians = hc_get_physician_options($patient['facility'] ?? '');
            $doctor = null;
            foreach ($physicians as $p) {
                if ($p['name'] === $doctorName) {
                    $doctor = $p;
                    break;
                }
            }
            if ($doctor === null) {
                return; // not a real, active doctor account at this facility
            }

            $owner = hc_resolve_patient_owning_doctor($patient);
            if ($owner !== null && $owner['uid'] === $doctor['uid']) {
                return; // already the recognized owner — nothing to detect
            }

            $patientConsultations = array_values(array_filter(
                hc_get_all_consultations(),
                fn ($c) => ($c['patientId'] ?? '') === $patientId
            ));
            usort($patientConsultations, fn ($a, $b) => strcmp($b['dateTime'], $a['dateTime']));

            if (count($patientConsultations) < HC_AUTO_ACCESS_CONSULT_STREAK) {
                return; // not enough history yet
            }
            foreach (array_slice($patientConsultations, 0, HC_AUTO_ACCESS_CONSULT_STREAK) as $c) {
                if (($c['assignedPersonnel'] ?? '') !== $doctorName) {
                    return; // streak broken somewhere in the last N — not a settled pattern yet
                }
            }

            // Never ask twice for the same (patient, doctor) pair — covers
            // a prior manual request AND a prior auto-detected one,
            // whatever its outcome.
            foreach ($firestore->listDocuments('patient_access_requests') as $doc) {
                $f = $doc['fields'];
                if (($f['patientId'] ?? '') === $patientId && ($f['requestedByUid'] ?? '') === $doctor['uid']) {
                    return;
                }
            }

            $patientEmail = $patient['email'] ?? '';
            if ($patientEmail === '' || $patientEmail === '—') {
                error_log('[patient-access] Auto-detected care pattern for patient ' . $patientId . ' / doctor ' . $doctorName . ' but the patient has no email on file — skipped.');

                return;
            }

            $rawToken = bin2hex(random_bytes(32));
            $expiresAt = time() + (HC_ACCESS_CONSENT_TOKEN_TTL_DAYS * 86400);

            $newId = $firestore->addDocument('patient_access_requests', [
                'patientId' => $patientId,
                'patientName' => $patient['name'] ?? '',
                'requestedByUid' => $doctor['uid'],
                'requestedByName' => $doctor['name'],
                'ownerUid' => $owner['uid'] ?? null,
                'ownerName' => $owner['name'] ?? null,
                'reason' => 'Automatically detected: ' . $doctor['name'] . ' has been the consulting physician for the last ' . HC_AUTO_ACCESS_CONSULT_STREAK . ' visits.',
                'initiatedBy' => 'system_detected',
                'status' => 'pending_patient',
                'createdAt' => date('c'),
                'consentTokenHash' => hash('sha256', $rawToken),
                'consentTokenExpiresAt' => date('c', $expiresAt),
            ]);
            if ($newId === null) {
                return;
            }

            require_once __DIR__ . '/../../config/constants.php'; // BASE_URL — safe if already loaded (require_once)
            $consentUrl = (($_SERVER['HTTPS'] ?? 'off') !== 'off' ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost') . BASE_URL
                . '/patient-consent.php?rid=' . urlencode($newId) . '&token=' . urlencode($rawToken);

            hc_send_email($patientEmail, $patient['name'] ?? '', 'Please confirm: sharing your medical record with your doctor', hc_account_email_shell(
                'We noticed a pattern in your care',
                '<p style="margin:0 0 12px; font-size:14px; line-height:1.6;">'
                    . '<strong>' . htmlspecialchars($doctor['name']) . '</strong> has been the doctor seeing you for your last several visits at your health center. '
                    . 'To make sure they can keep reviewing your full medical record for your ongoing care, we need your consent.</p>'
                . '<p style="margin:0 0 18px; font-size:14px; line-height:1.6;">Nothing changes unless you say yes — please review and let us know your decision.</p>'
                . '<a href="' . htmlspecialchars($consentUrl) . '" style="display:inline-block; background:#5B0A0A; color:#fff; text-decoration:none; padding:10px 20px; border-radius:8px; font-size:13.5px; font-weight:600;">Review Consent Request</a>'
                . '<p style="margin:18px 0 0; font-size:12.5px; color:#6b7280;">This link expires in ' . HC_ACCESS_CONSENT_TOKEN_TTL_DAYS . ' days.</p>'
            ));

            write_audit_log(
                'created',
                'Patient Management',
                'System detected a consistent care pattern for "' . ($patient['name'] ?? $patientId) . '" — automatically requested patient consent to share their record with ' . $doctor['name'] . '.'
            );
        } catch (\Throwable $e) {
            error_log('[patient-access] hc_maybe_trigger_auto_access_request() failed: ' . $e->getMessage());
        }
    }
}
