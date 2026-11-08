<?php
/**
 * =====================================================================
 * Shared — Patient Profile Consent Gate
 * ---------------------------------------------------------------------
 * hc_patient_consent_status() is the single real access check every
 * role's own patient_profile.php now calls before building the full
 * profile. Previously there was NO per-patient access check beyond
 * facility match (confirmed in includes/doctor/patient_profile_doctor_firestore.php's
 * own prior comment: "Every doctor at a facility can see every patient
 * registered there — there is no per-doctor ownership/'belongs to one
 * doctor' restriction on top of that"). That facility-match check still
 * happens first, separately, exactly as before (blank $patient to null
 * / hc_not_found_patient_profile() on mismatch) — this file only
 * decides whether a SAME-facility staff member may open a SPECIFIC
 * patient's full profile right now.
 *
 * Four ways in, checked in this order (first match wins):
 *   1. Sub-admin oversight (unconditional) — sub-admin is the one who
 *      actually decides every other role's access requests
 *      (api/review-patient-consent.php), so sub-admin itself never
 *      waits on consent to open a profile. This is deliberately never
 *      silent: subadmin/patient_profile.php notifies the patient by
 *      email and records a real "viewed" audit log entry every single
 *      time a sub-admin opens a profile this way — the trade-off for
 *      skipping the request/approve cycle is that every such view is
 *      visible to both the patient and the facility's own audit trail.
 *   2. Doctor (unconditional) — a doctor's own clinical role already
 *      requires full access to any patient at their facility, not just
 *      ones with a same-day scheduled consultation (that narrower
 *      exception existed before this and is now superseded by this
 *      unconditional one). Unlike sub-admin's oversight path, this
 *      doesn't trigger the patient-notification email — a doctor
 *      opening a chart is ordinary clinical care, not an oversight
 *      exception — but the same "viewed" audit log entry still fires
 *      every time (doctor/patient_profile.php's own unconditional
 *      write_audit_log() call, unchanged).
 *   3. Same-day registrant — whoever registered this patient
 *      (patients.registeredBy) can verify the intake info they just
 *      took is correct, but only on the same calendar day. In practice
 *      this now only ever matters for healthcare_staff, since
 *      sub-admin and doctor both already bypass consent entirely above.
 *   4. An ACCEPTED consent_records doc for this exact
 *      (patientId, requestedByUid) pair — real patient consent, with a
 *      real 1-hour window: the FIRST time this function sees an
 *      accepted doc with no 'firstAccessedAt' yet, it stamps that field
 *      now (read-modify-write, same convention as every other write in
 *      this app) — that moment, not the sub-admin's approval moment, is
 *      the start of the clock. Once more than an hour has passed since
 *      firstAccessedAt, access is no longer granted this way and the
 *      doc's 'status' is flipped for real to 'expired' (the staff
 *      member must request access again — api/request-patient-consent.php
 *      only blocks a new request while an existing one is still
 *      'accepted' or 'pending', so flipping to 'expired' here is what
 *      actually lets them ask again). This also finally makes
 *      includes/subadmin/consent_management_firestore.php's existing
 *      'expired' badge/stat real instead of permanently dead. In
 *      practice this path too now only ever matters for
 *      healthcare_staff — the only role still subject to the
 *      request/approve cycle at all.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore

if (!function_exists('hc_patient_consent_status')) {
    /**
     * @param string $patientId Real patients/{id} — already confirmed to exist and belong to the signed-in user's own facility by the caller.
     * @param string $myUid Signed-in user's own uid ($_SESSION['uid']).
     * @param string $myRole Signed-in user's own role ($_SESSION['role']) — 'subadmin' and 'doctor' both get unconditional access; only 'healthcare_staff' goes through the request/approve cycle below.
     * @param string $myName Signed-in user's own real name — kept for callers that already pass it (same call signature as before); not currently consulted by this function now that doctor access is unconditional.
     * @return array{access: bool, reason: string, expiresAt: ?string, pendingRequest: ?array, declinedRequest: ?array, expiredRequest: ?array}
     *         reason is one of: 'subadmin_oversight', 'doctor_access', 'registrant_today', 'consent_accepted', 'consent_expired', 'none'.
     *         expiresAt is a real ISO timestamp only when reason is
     *         'consent_accepted' (null for every other reason — those
     *         access paths have no 1-hour window at all).
     *         pendingRequest / declinedRequest / expiredRequest are each
     *         either null or the real consent_records row (id + fields)
     *         for this (patientId, myUid) pair, newest first, so the
     *         gated page can show "already sent, awaiting response",
     *         "previously declined on X", or "your access expired on X"
     *         instead of a duplicate request form.
     */
    function hc_patient_consent_status(string $patientId, string $myUid, string $myRole, string $myName): array
    {
        global $firestore;

        // --- 1. Sub-admin oversight (unconditional) ---
        if ($myRole === 'subadmin') {
            return ['access' => true, 'reason' => 'subadmin_oversight', 'expiresAt' => null, 'pendingRequest' => null, 'declinedRequest' => null, 'expiredRequest' => null];
        }

        // --- 2. Doctor (unconditional) — never waits on sub-admin
        // approval; only healthcare_staff still goes through the
        // request/approve cycle below. ---
        if ($myRole === 'doctor') {
            return ['access' => true, 'reason' => 'doctor_access', 'expiresAt' => null, 'pendingRequest' => null, 'declinedRequest' => null, 'expiredRequest' => null];
        }

        $today = date('Y-m-d');

        // --- 3. Same-day registrant ---
        $patientDoc = $firestore->getDocument('patients', $patientId);
        if ($patientDoc !== null
            && ($patientDoc['registeredBy'] ?? '') === $myUid
            && substr((string) ($patientDoc['dateRegistered'] ?? ''), 0, 10) === $today) {
            return ['access' => true, 'reason' => 'registrant_today', 'expiresAt' => null, 'pendingRequest' => null, 'declinedRequest' => null, 'expiredRequest' => null];
        }

        // --- 4. Accepted real patient consent, with a real 1-hour window ---
        $myRequests = array_values(array_filter(
            $firestore->listDocuments('consent_records'),
            fn ($doc) => ($doc['fields']['patientId'] ?? '') === $patientId
                && ($doc['fields']['requestedByUid'] ?? '') === $myUid
        ));
        usort($myRequests, fn ($a, $b) => strcmp($b['fields']['givenOn'] ?? '', $a['fields']['givenOn'] ?? ''));

        $accepted = null;
        $pending = null;
        $declined = null;
        $expired = null;
        foreach ($myRequests as $doc) {
            $status = $doc['fields']['status'] ?? 'pending';
            if ($status === 'accepted' && $accepted === null) {
                $accepted = $doc;
            } elseif ($status === 'pending' && $pending === null) {
                $pending = $doc;
            } elseif ($status === 'rejected' && $declined === null) {
                $declined = $doc;
            } elseif ($status === 'expired' && $expired === null) {
                $expired = $doc;
            }
        }

        if ($accepted !== null) {
            $firstAccessedAt = $accepted['fields']['firstAccessedAt'] ?? null;
            if ($firstAccessedAt === null) {
                // This call IS the first real access — the clock starts now, not at approval.
                $firstAccessedAt = date('c');
                $firestore->setDocument('consent_records', $accepted['id'], array_merge($accepted['fields'], [
                    'firstAccessedAt' => $firstAccessedAt,
                ]));
            }

            $expiresAtTs = strtotime($firstAccessedAt) + 3600;
            if (time() < $expiresAtTs) {
                return ['access' => true, 'reason' => 'consent_accepted', 'expiresAt' => date('c', $expiresAtTs), 'pendingRequest' => null, 'declinedRequest' => null, 'expiredRequest' => null];
            }

            // The hour is up — flip this doc to 'expired' for real (not
            // just computed on the fly) so a fresh request isn't blocked
            // by api/request-patient-consent.php's own "already accepted"
            // duplicate guard.
            $firestore->setDocument('consent_records', $accepted['id'], array_merge($accepted['fields'], [
                'firstAccessedAt' => $firstAccessedAt,
                'status' => 'expired',
            ]));
            $expired = ['id' => $accepted['id'], 'fields' => array_merge($accepted['fields'], ['firstAccessedAt' => $firstAccessedAt, 'status' => 'expired'])];
        }

        return [
            'access' => false,
            'reason' => $expired !== null ? 'consent_expired' : 'none',
            'expiresAt' => null,
            'pendingRequest' => $pending !== null ? ['id' => $pending['id']] + $pending['fields'] : null,
            'declinedRequest' => $declined !== null ? ['id' => $declined['id']] + $declined['fields'] : null,
            'expiredRequest' => $expired !== null ? ['id' => $expired['id']] + $expired['fields'] : null,
        ];
    }
}
