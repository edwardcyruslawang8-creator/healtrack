<?php
/**
 * =====================================================================
 * Shared data layer — Staff Accounts (Pending Approvals)
 * ---------------------------------------------------------------------
 * Pure Firestore-query function over the real "users" collection's
 * self-registered, still-"pending" accounts (register.php,
 * api/register.php) — see includes/firestore/patients.php's header
 * comment for why this layer exists and what it does/doesn't own.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore

if (!function_exists('hc_get_pending_account_notifications')) {
    /**
     * One notification row per self-registered account awaiting the
     * CURRENTLY SIGNED-IN sub-admin's approval
     * (subadmin/user_management.php's "Pending Approvals" table) —
     * scoped to their own facility, the same isolation
     * includes/subadmin/user_management_firestore.php already applies
     * ($_SESSION['uid'] resolved to a real facility, never trusted from
     * the client). Same row shape as
     * includes/firestore/medicines.php's
     * hc_get_medicine_expiry_notifications() — 'unread' is set by the
     * caller, not here.
     *
     * @return array<int, array{id: int, title: string, description: string, type: string, date: string, time: string, icon: string, iconBg: string, iconColor: string}>
     */
    function hc_get_pending_account_notifications(): array
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

        $pending = array_values(array_filter($firestore->listDocuments('users'), function ($doc) use ($facility) {
            $f = $doc['fields'];
            return ($f['status'] ?? '') === 'pending' && ($f['facility'] ?? '') === $facility;
        }));

        usort($pending, fn ($a, $b) => strcmp($b['fields']['createdAt'] ?? '', $a['fields']['createdAt'] ?? ''));

        return array_map(function ($doc) {
            $f = $doc['fields'];
            $name = $f['name'] ?? '(no name)';
            $jobTitle = $f['jobTitle'] ?? ucfirst($f['role'] ?? 'Unknown');
            $timestamp = isset($f['createdAt']) ? strtotime((string) $f['createdAt']) : false;

            return [
                'id' => crc32('pending-account:' . $doc['id']),
                'title' => 'New Account Request',
                'description' => $name . ' requested access as ' . $jobTitle . '.',
                'type' => 'User',
                'date' => $timestamp ? date('M j, Y', $timestamp) : '—',
                'time' => $timestamp ? date('g:i A', $timestamp) : '',
                'icon' => 'bi-person-plus-fill',
                'iconBg' => '#F1E8FD',
                'iconColor' => '#8B5CF6',
            ];
        }, $pending);
    }
}
