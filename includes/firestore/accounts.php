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

if (!function_exists('hc_email_already_registered')) {
    /**
     * One email = at most one account in this app, full stop — checked
     * by EMAIL, not by Firebase Auth uid, since a Google-authenticated
     * identity can end up with a different uid than an email/password
     * one for the very same address (depends on this Firebase
     * project's account-linking setting, which this app doesn't
     * control), so relying on Firebase Auth's own per-provider
     * uniqueness alone isn't enough to stop the same person (or
     * someone else entirely) collecting a second account under the
     * same email. Checked against every users/{uid} doc regardless of
     * status — pending, active, locked, doesn't matter, since the
     * point is "this email already belongs to an account in the
     * system." Shared by every real account-creation endpoint
     * (api/register.php, api/create-user.php, api/create-subadmin.php)
     * rather than each keeping its own copy.
     */
    function hc_email_already_registered(string $email): bool
    {
        global $firestore;
        $needle = strtolower(trim($email));
        if ($needle === '') {
            return false;
        }
        foreach ($firestore->listDocuments('users') as $doc) {
            if (strtolower(trim((string) ($doc['fields']['email'] ?? ''))) === $needle) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('hc_get_active_staff_by_role')) {
    /**
     * Every real, currently-active users/{uid} account for one $role at
     * one $facility, sorted by name — "who could this actually be sent
     * to right now" for doctor/active_consultation.php's Laboratory/
     * X-ray staff picker (a pending/inactive/locked account can't act on
     * anything, so those are excluded; see
     * includes/subadmin/user_management_firestore.php's own status
     * values for why 'active' is the one real "can sign in and work"
     * state). Facility-scoped the same way every other per-facility
     * list in this app already is — a doctor only ever sends a patient
     * to staff at their OWN facility.
     *
     * @return array<int, array{uid: string, name: string}>
     */
    function hc_get_active_staff_by_role(string $role, string $facility): array
    {
        global $firestore;

        if ($facility === '') {
            return [];
        }

        $staff = array_values(array_filter($firestore->listDocuments('users'), function ($doc) use ($role, $facility) {
            $f = $doc['fields'];

            return ($f['role'] ?? '') === $role
                && ($f['status'] ?? '') === 'active'
                && ($f['facility'] ?? '') === $facility;
        }));

        usort($staff, fn ($a, $b) => strcasecmp($a['fields']['name'] ?? '', $b['fields']['name'] ?? ''));

        return array_map(fn ($doc) => [
            'uid' => $doc['id'],
            'name' => $doc['fields']['name'] ?? '(no name)',
        ], $staff);
    }
}
