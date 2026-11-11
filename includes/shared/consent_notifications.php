<?php
/**
 * =====================================================================
 * Consent decision notifications (doctor + healthcare staff)
 * ---------------------------------------------------------------------
 * When a sub-admin approves or rejects a patient details access request
 * (api/review-patient-consent.php), the staff member who sent it
 * (api/request-patient-consent.php — requestedByUid) is told here. Built
 * live from consent_records like every other notification source in
 * this app (no separate "notifications" collection): one row per
 * request of theirs decided within the last 14 days, crc32() id so the
 * $_SESSION['ntfReadIds']/['ntfDismissedIds'] read/archive state keeps
 * working.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore

if (!function_exists('hc_get_consent_decision_notifications')) {
    /**
     * @return array<int, array{id: int, title: string, description: string, type: string, date: string, time: string, icon: string, iconBg: string, iconColor: string}>
     */
    function hc_get_consent_decision_notifications(string $uid): array
    {
        global $firestore;
        if ($uid === '') {
            return [];
        }

        $cutoff = strtotime('-14 days');

        $rows = [];
        foreach ($firestore->listDocuments('consent_records') as $doc) {
            $f = $doc['fields'];
            $status = $f['status'] ?? '';
            if (($f['type'] ?? '') !== 'profile_access'
                || ($f['requestedByUid'] ?? '') !== $uid
                || !in_array($status, ['accepted', 'rejected'], true)) {
                continue;
            }
            $respondedAt = strtotime((string) ($f['respondedAt'] ?? ''));
            if ($respondedAt === false || $respondedAt < $cutoff) {
                continue;
            }
            $rows[] = ['docId' => $doc['id'], 'fields' => $f, 'at' => $respondedAt];
        }

        usort($rows, fn ($a, $b) => $b['at'] <=> $a['at']);

        return array_map(function ($r) {
            $f = $r['fields'];
            $approved = $f['status'] === 'accepted';
            $patientName = ($f['patientName'] ?? '') !== '' ? $f['patientName'] : 'a patient';
            $reviewer = ($f['approvedBy'] ?? '') !== '' ? $f['approvedBy'] : 'the sub-administrator';

            return [
                // Status in the id: a decision is final, but this keeps an
                // approved and a rejected row for one request distinct.
                'id' => crc32('consent_records-' . $r['docId'] . '-' . $f['status']),
                'title' => $approved ? 'Access request approved' : 'Access request declined',
                'description' => $approved
                    ? 'Your request to view ' . $patientName . '\'s details was approved by ' . $reviewer . '. You can now open their profile.'
                    : 'Your request to view ' . $patientName . '\'s details was declined by ' . $reviewer . '.',
                'type' => 'Consent',
                'date' => date('M j, Y', $r['at']),
                'time' => date('g:i A', $r['at']),
                'icon' => $approved ? 'bi-shield-check' : 'bi-shield-x',
                'iconBg' => $approved ? '#E4F7EA' : '#FCE4E4',
                'iconColor' => $approved ? '#1E8E3E' : '#D93025',
            ];
        }, $rows);
    }
}

if (!function_exists('hc_get_pending_consent_request_notifications')) {
    /**
     * For the SUB-ADMIN: one notification per patient details access
     * request (consent_records, type profile_access, status pending —
     * created by api/request-patient-consent.php) about a patient at
     * this sub-admin's own facility, so they actually find out a request
     * is waiting instead of only seeing it if they happen to open
     * Consent Management. Gone once decided (api/review-patient-consent.php
     * flips it out of "pending") — the requester is told the outcome by
     * hc_get_consent_decision_notifications() above. Facility-scoped the
     * same way hc_get_pending_stock_request_notifications() is.
     *
     * @return array<int, array{id: int, title: string, description: string, type: string, date: string, time: string, icon: string, iconBg: string, iconColor: string}>
     */
    function hc_get_pending_consent_request_notifications(): array
    {
        global $firestore;

        $uid = $_SESSION['uid'] ?? '';
        if ($uid === '') {
            return [];
        }
        $myDoc = $firestore->getDocument('users', $uid);
        $facility = $myDoc['facility'] ?? '';
        if ($facility === '') {
            return [];
        }

        $pending = array_values(array_filter(
            $firestore->listDocuments('consent_records'),
            fn ($doc) => ($doc['fields']['type'] ?? '') === 'profile_access' && ($doc['fields']['status'] ?? '') === 'pending'
        ));
        if ($pending === []) {
            return [];
        }

        // Each request's patient must be at THIS facility — one read of
        // the patients this facility owns, not one read per request.
        $myFacilityPatientIds = [];
        foreach ($firestore->listDocuments('patients') as $p) {
            if (($p['fields']['facility'] ?? '') === $facility) {
                $myFacilityPatientIds[$p['id']] = true;
            }
        }

        $rows = array_values(array_filter($pending, fn ($doc) => isset($myFacilityPatientIds[$doc['fields']['patientId'] ?? ''])));
        usort($rows, fn ($a, $b) => strcmp($b['fields']['givenOn'] ?? '', $a['fields']['givenOn'] ?? ''));

        return array_map(function ($doc) {
            $f = $doc['fields'];
            $at = strtotime((string) ($f['givenOn'] ?? ''));
            $roleLabel = match ($f['requestedByRole'] ?? '') {
                'doctor' => 'Doctor',
                'healthcare_staff' => 'Medical Staff',
                'subadmin' => 'Sub-Administrator',
                default => 'Staff',
            };
            $requester = ($f['requestedByName'] ?? '') !== '' ? $f['requestedByName'] : 'A staff member';
            $patient = ($f['patientName'] ?? '') !== '' ? $f['patientName'] : 'a patient';
            $reason = trim((string) ($f['purpose'] ?? ''));

            return [
                'id' => crc32('pending-consent-request:' . $doc['id']),
                'title' => 'New Access Request',
                'description' => $requester . ' (' . $roleLabel . ') is asking to view ' . $patient . '\'s details.' . ($reason !== '' ? ' Reason: ' . $reason : ''),
                'type' => 'Consent',
                'date' => $at !== false ? date('M j, Y', $at) : '—',
                'time' => $at !== false ? date('g:i A', $at) : '',
                'icon' => 'bi-shield-lock-fill',
                'iconBg' => '#FDECDD',
                'iconColor' => '#E8710A',
            ];
        }, $rows);
    }
}
