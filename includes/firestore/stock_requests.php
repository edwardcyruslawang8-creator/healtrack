<?php
/**
 * =====================================================================
 * Shared data layer — Stock Requests
 * ---------------------------------------------------------------------
 * Pure Firestore-query functions for the real "stock_requests"
 * collection — no role-awareness, same reasoning as
 * includes/firestore/patients.php's header comment. Both
 * subadmin/stock_requests.php and pharmacist/stock_requests.php read
 * the exact same collection (subadmin's page originated it this
 * session); this file lets pharmacist's page stop duplicating fake
 * placeholder data and read the real thing too, and gives
 * api/update-stock-request-status.php one place to share type/status
 * badge meta with both pages instead of two copies going stale
 * independently.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore

if (!function_exists('hc_stock_request_type_meta')) {
    /** Request type -> badge color meta, keyed by the real "type" field. */
    function hc_stock_request_type_meta(): array
    {
        return [
            'medicine' => ['label' => 'Medicine', 'bg' => '#E8F0FE', 'color' => '#1A73E8'],
            'vaccine'  => ['label' => 'Vaccine',  'bg' => '#E4F7EA', 'color' => '#1E8E3E'],
        ];
    }
}

if (!function_exists('hc_stock_request_status_meta')) {
    /** Status -> badge color meta, keyed by the real "status" field. */
    function hc_stock_request_status_meta(): array
    {
        return [
            'pending'  => ['label' => 'Pending',  'bg' => '#FDECDD', 'color' => '#E8710A'],
            'approved' => ['label' => 'Approved', 'bg' => '#E4F7EA', 'color' => '#1E8E3E'],
            'released' => ['label' => 'Released', 'bg' => '#F1E8FD', 'color' => '#8B5CF6'],
            'rejected' => ['label' => 'Rejected', 'bg' => '#FCE4E4', 'color' => '#D93025'],
        ];
    }
}

if (!function_exists('hc_get_pending_stock_request_notifications')) {
    /**
     * One real notification row per stock_requests doc still "pending"
     * at the signed-in sub-admin's own facility — same real-source
     * pattern/shape as includes/firestore/accounts.php's
     * hc_get_pending_account_notifications() (facility-scoped, 'id' is
     * crc32() of the doc id, for the same reason: $_SESSION['ntfReadIds']/
     * ['ntfDismissedIds'] stay a plain int array). A pharmacist
     * creating a new stock request previously had no real notification
     * anywhere telling a sub-admin it needed a decision — this is that
     * real signal, not a fabricated placeholder.
     *
     * @return array<int, array{id: int, title: string, description: string, type: string, date: string, time: string, icon: string, iconBg: string, iconColor: string}>
     */
    function hc_get_pending_stock_request_notifications(): array
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

        $pending = array_values(array_filter(hc_get_all_stock_requests(), fn ($r) => $r['status'] === 'pending' && $r['facility'] === $facility));

        return array_map(function ($r) {
            $timestamp = $r['requestedAtRaw'] !== '' ? strtotime($r['requestedAtRaw']) : false;
            $typeMeta = hc_stock_request_type_meta()[$r['type']] ?? ['label' => 'Item'];

            return [
                'id' => crc32('pending-stock-request:' . $r['docId']),
                'title' => 'New Stock Request',
                'description' => $r['pharmacist'] . ' requested ' . $r['itemsCount'] . ' ' . strtolower($typeMeta['label']) . ' item(s).',
                'type' => 'Stock Request',
                'date' => $timestamp ? date('M j, Y', $timestamp) : '—',
                'time' => $timestamp ? date('g:i A', $timestamp) : '',
                'icon' => 'bi-box-seam-fill',
                'iconBg' => '#E8F0FE',
                'iconColor' => '#1A73E8',
            ];
        }, $pending);
    }
}

if (!function_exists('hc_get_all_stock_requests')) {
    /**
     * Every stock_requests/{autoId} doc, newest first. "Request ID"
     * (REQ-XXXXXXXX style) has no real per-doc field — Firestore's auto
     * ID is the true identifier, so a short display code is derived
     * from it instead of faking a sequential number; 'docId' carries
     * the real id for anything that needs to address the doc (e.g.
     * api/update-stock-request-status.php).
     *
     * @return array<int, array{id: string, docId: string, pharmacist: string, pharmacistUid: string, requestDate: string, requestTime: string, requestedAtRaw: string, items: array, itemsCount: int, type: string, status: string, dateNeeded: string, dateNeededRaw: string, facility: string}>
     */
    function hc_get_all_stock_requests(): array
    {
        global $firestore;

        $rawRequests = $firestore->listDocuments('stock_requests');
        usort($rawRequests, fn ($a, $b) => strcmp($b['fields']['requestedAt'] ?? '', $a['fields']['requestedAt'] ?? ''));

        return array_map(function ($doc) {
            $fields = $doc['fields'];
            $requestedAt = $fields['requestedAt'] ?? null;

            return [
                'id' => 'REQ-' . strtoupper(substr($doc['id'], 0, 8)),
                'docId' => $doc['id'],
                'pharmacist' => $fields['pharmacistName'] ?? 'Unknown',
                'pharmacistUid' => $fields['pharmacistUid'] ?? '',
                'requestDate' => $requestedAt ? date('M j, Y', strtotime($requestedAt)) : '—',
                'requestTime' => $requestedAt ? date('g:i A', strtotime($requestedAt)) : '—',
                'requestedAtRaw' => $requestedAt ?? '',
                'items' => $fields['items'] ?? [],
                'itemsCount' => (int) ($fields['itemsCount'] ?? count($fields['items'] ?? [])),
                'type' => $fields['type'] ?? 'medicine',
                'status' => $fields['status'] ?? 'pending',
                'dateNeeded' => isset($fields['dateNeeded']) ? date('M j, Y', strtotime($fields['dateNeeded'])) : '—',
                'dateNeededRaw' => $fields['dateNeeded'] ?? '',
                'facility' => $fields['facility'] ?? '',
            ];
        }, $rawRequests);
    }
}
