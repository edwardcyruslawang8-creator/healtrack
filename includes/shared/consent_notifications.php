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
