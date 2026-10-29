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
 * Three ways in, checked in this order (first match wins):
 *   1. Same-day registrant — whoever registered this patient
 *      (patients.registeredBy) can verify the intake info they just
 *      took is correct, but only on the same calendar day.
 *   2. Same-day consultation (doctor only) — a doctor with a real,
 *      non-cancelled consultation assigned to them for this patient,
 *      scheduled for today, same reasoning: the patient showing up for
 *      a scheduled visit is itself a real consent signal, but only for
 *      that visit's own day.
 *   3. An ACCEPTED consent_records doc for this exact
 *      (patientId, requestedByUid) pair — real patient consent, no
 *      expiry built in yet (see this file's own header comment in
 *      api/request-patient-consent.php for why).
 * Sub-admin has no exception beyond #3 — confirmed deliberately with
 * the user; sub-admin cannot register patients at all (api/create-patient.php
 * role-gates to healthcare_staff|doctor only) and has no consultation
 * concept of its own, so in practice a sub-admin always needs #3.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore
require_once __DIR__ . '/../firestore/consultations.php'; // hc_get_consultations_for_patient()

if (!function_exists('hc_patient_consent_status')) {
    /**
     * @param string $patientId Real patients/{id} — already confirmed to exist and belong to the signed-in user's own facility by the caller.
     * @param string $myUid Signed-in user's own uid ($_SESSION['uid']).
     * @param string $myRole Signed-in user's own role ($_SESSION['role']) — only 'doctor' gets the same-day-consultation exception.
     * @param string $myName Signed-in user's own real name — matched against consultations.assignedPersonnel, the same plain-name-string matching every other doctor-queue feature in this app already uses (no doctor-uid field exists on a consultation doc).
     * @return array{access: bool, reason: string, pendingRequest: ?array, declinedRequest: ?array}
     *         reason is one of: 'registrant_today', 'consultation_today', 'consent_accepted', 'none'.
     *         pendingRequest / declinedRequest are each either null or the
     *         real consent_records row (id + fields) for this (patientId, myUid)
     *         pair, newest first, so the gated page can show "already
     *         sent, awaiting response" or "previously declined on X"
     *         instead of a duplicate request form.
     */
    function hc_patient_consent_status(string $patientId, string $myUid, string $myRole, string $myName): array
    {
        global $firestore;

        $today = date('Y-m-d');

        // --- 1. Same-day registrant ---
        $patientDoc = $firestore->getDocument('patients', $patientId);
        if ($patientDoc !== null
            && ($patientDoc['registeredBy'] ?? '') === $myUid
            && substr((string) ($patientDoc['dateRegistered'] ?? ''), 0, 10) === $today) {
            return ['access' => true, 'reason' => 'registrant_today', 'pendingRequest' => null, 'declinedRequest' => null];
        }

        // --- 2. Same-day consultation (doctor only) ---
        if ($myRole === 'doctor') {
            foreach (hc_get_consultations_for_patient($patientId) as $c) {
                if ($c['assignedPersonnel'] === $myName
                    && $c['status'] !== 'cancelled'
                    && substr($c['dateTime'], 0, 10) === $today) {
                    return ['access' => true, 'reason' => 'consultation_today', 'pendingRequest' => null, 'declinedRequest' => null];
                }
            }
        }

        // --- 3. Accepted real patient consent ---
        $myRequests = array_values(array_filter(
            $firestore->listDocuments('consent_records'),
            fn ($doc) => ($doc['fields']['patientId'] ?? '') === $patientId
                && ($doc['fields']['requestedByUid'] ?? '') === $myUid
        ));
        usort($myRequests, fn ($a, $b) => strcmp($b['fields']['givenOn'] ?? '', $a['fields']['givenOn'] ?? ''));

        $accepted = null;
        $pending = null;
        $declined = null;
        foreach ($myRequests as $doc) {
            $status = $doc['fields']['status'] ?? 'pending';
            if ($status === 'accepted' && $accepted === null) {
                $accepted = $doc;
            } elseif ($status === 'pending' && $pending === null) {
                $pending = $doc;
            } elseif ($status === 'rejected' && $declined === null) {
                $declined = $doc;
            }
        }

        if ($accepted !== null) {
            return ['access' => true, 'reason' => 'consent_accepted', 'pendingRequest' => null, 'declinedRequest' => null];
        }

        return [
            'access' => false,
            'reason' => 'none',
            'pendingRequest' => $pending !== null ? ['id' => $pending['id']] + $pending['fields'] : null,
            'declinedRequest' => $declined !== null ? ['id' => $declined['id']] + $declined['fields'] : null,
        ];
    }
}
