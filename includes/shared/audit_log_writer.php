<?php
/**
 * =====================================================================
 * Audit log writer
 * ---------------------------------------------------------------------
 * Called from every mutating api/*.php endpoint (create/update/delete
 * sub-admin, create facility, login, logout, ...) to write one entry to
 * Firestore's "audit_logs" collection (auto-generated doc id via
 * FirestoreRestClient::addDocument() — nothing needs to address a log
 * entry by id afterward). admin/audit_logs.php,
 * includes/admin_dashboard_firestore.php's "Recent Sub-Administrator Activity", and
 * includes/account_monitoring_firestore.php's "Recent Account Activities"
 * all read this same collection back.
 *
 * Requires config/firebase.php ($firestore) to already be required by
 * the including script — every api/*.php caller already does this for
 * its own Firestore/Auth work.
 * =====================================================================
 */

if (!function_exists('write_audit_log')) {
    /**
     * @param string $action  One of: created, updated, deleted, viewed,
     *                        approved, logged_in, logged_out.
     * @param string $module  e.g. "Sub-Administrator Management", "Facility Management".
     * @param string $details Human-readable description of what happened.
     * @param string $status  "success" or "failed".
     */
    function write_audit_log(string $action, string $module, string $details, string $status = 'success'): void
    {
        global $firestore;

        if (!isset($firestore)) {
            return;
        }

        $actorRole = match ($_SESSION['role'] ?? null) {
            'admin' => 'System Administrator',
            'subadmin' => 'Sub-Administrator',
            'healthcare_staff' => 'Healthcare Staff',
            'pharmacist' => 'Pharmacist',
            'doctor' => 'Doctor',
            'medical_technician' => 'Medical Technologist',
            'radio_technologist' => 'Radiologic Technologist',
            default => 'System',
        };

        $firestore->addDocument('audit_logs', [
            'actorUid' => $_SESSION['uid'] ?? '',
            'actorName' => $_SESSION['name'] ?? 'System',
            'actorRole' => $actorRole,
            'action' => $action,
            'module' => $module,
            'details' => $details,
            'status' => $status,
            'timestamp' => date('c'),
        ]);
    }
}

if (!function_exists('hc_get_all_audit_logs_annotated')) {
    /**
     * Every real audit_logs/{id} doc, newest first, each annotated with
     * a real "facility" (resolved by joining actorUid against the
     * "users" collection — audit_logs docs carry no facility field of
     * their own, same reasoning as includes/subadmin/subadmin_dashboard_firestore.php's
     * consultations join). Shared by admin/audit_logs.php (sees every
     * facility) and subadmin/audit_logs.php (locks the result to its
     * own facility) so both pages search/filter/paginate the exact same
     * real dataset instead of two copies of this query.
     *
     * @return array<int, array{id: string, timestamp: string, actorName: string, actorRole: string, actorUid: string, facility: string, action: string, module: string, details: string, status: string}>
     */
    function hc_get_all_audit_logs_annotated(): array
    {
        global $firestore;

        $facilityByUid = [];
        foreach ($firestore->listDocuments('users') as $doc) {
            $facilityByUid[$doc['id']] = $doc['fields']['facility'] ?? '';
        }

        $rows = [];
        foreach ($firestore->listDocuments('audit_logs') as $doc) {
            $f = $doc['fields'];
            $uid = $f['actorUid'] ?? '';
            $rows[] = [
                'id' => $doc['id'],
                'timestamp' => $f['timestamp'] ?? '',
                'actorName' => $f['actorName'] ?? 'System',
                'actorRole' => $f['actorRole'] ?? 'System',
                'actorUid' => $uid,
                'facility' => $facilityByUid[$uid] ?? '',
                'action' => $f['action'] ?? 'updated',
                'module' => $f['module'] ?? 'General',
                'details' => $f['details'] ?? '',
                'status' => $f['status'] ?? 'success',
            ];
        }

        usort($rows, fn ($a, $b) => strcmp($b['timestamp'], $a['timestamp']));

        return $rows;
    }
}

if (!function_exists('hc_get_my_audit_logs_annotated')) {
    /**
     * hc_get_all_audit_logs_annotated()'s own rows, narrowed to ONE real
     * actorUid — used by doctor/pharmacist/medical-staff/
     * medical_technician's own Audit Logs page, where a signed-in staff
     * member sees only their OWN activity, never the whole facility
     * (that's includes/subadmin/subadmin_audit_logs_firestore.php's job)
     * or every facility (admin/audit_logs.php's). A blank $uid (no
     * session) matches nothing, same as every other uid-scoped query in
     * this app rather than accidentally returning everyone's log.
     *
     * @return array<int, array{id: string, timestamp: string, actorName: string, actorRole: string, actorUid: string, facility: string, action: string, module: string, details: string, status: string}>
     */
    function hc_get_my_audit_logs_annotated(string $uid): array
    {
        if ($uid === '') {
            return [];
        }

        return array_values(array_filter(
            hc_get_all_audit_logs_annotated(),
            fn ($log) => $log['actorUid'] === $uid
        ));
    }
}

if (!function_exists('hc_audit_log_matches_date_from_to')) {
    /**
     * @internal True if $timestamp (a real ISO 'c'-format audit log
     * timestamp) falls on or after $from and on or before $to — any
     * real span, not just a preset Today/This Week/This Month bucket.
     * Either end blank means that side is open-ended; both blank means
     * no filter at all.
     */
    function hc_audit_log_matches_date_from_to(string $timestamp, string $from, string $to): bool
    {
        if ($timestamp === '' || ($from === '' && $to === '')) {
            return true;
        }
        $ts = strtotime($timestamp);
        if ($ts === false) {
            return false;
        }
        $day = date('Y-m-d', $ts);

        if ($from !== '' && $day < $from) {
            return false;
        }
        if ($to !== '' && $day > $to) {
            return false;
        }

        return true;
    }
}

if (!function_exists('hc_filter_audit_logs')) {
    /**
     * $logs (hc_get_all_audit_logs_annotated()'s own shape) narrowed by
     * every real filter this app's Audit Logs pages offer — a blank/
     * "All ..." value in any one of them means "don't filter on this".
     *
     * @param array<int, array<string, mixed>> $logs
     * @param array{q?: string, user?: string, module?: string, action?: string, status?: string, from?: string, to?: string, role?: string} $filters
     */
    function hc_filter_audit_logs(array $logs, array $filters): array
    {
        require_once __DIR__ . '/fuzzy_search.php'; // hc_fuzzy_match()

        $q = trim((string) ($filters['q'] ?? ''));
        $user = (string) ($filters['user'] ?? 'All Users');
        $module = (string) ($filters['module'] ?? 'All Modules');
        $actionLabel = (string) ($filters['action'] ?? 'All Actions');
        $statusLabel = (string) ($filters['status'] ?? 'All Statuses');
        $dateFrom = trim((string) ($filters['from'] ?? ''));
        $dateTo = trim((string) ($filters['to'] ?? ''));
        // 'admin'/'subadmin'/'doctor'/'healthcare_staff'/'medical_technician'/
        // 'radio_technologist'/'pharmacist' — driven by admin/audit_logs.php's
        // and subadmin/audit_logs.php's own sidebar dropdowns (Audit Logs
        // > Admin / Sub Admin / Doctor / Medical Staff / Medical
        // Technologist / Radiologic Technologist / Pharmacist — 'admin'
        // only exists on admin/audit_logs.php's own dropdown, 'subadmin'
        // only on subadmin/audit_logs.php's), never a real dropdown
        // option of its own, so no "All ..." placeholder to check for;
        // anything else (including '') means no role split.
        $roleFilter = (string) ($filters['role'] ?? '');

        return array_values(array_filter($logs, function ($log) use ($q, $user, $module, $actionLabel, $statusLabel, $dateFrom, $dateTo, $roleFilter) {
            if ($q !== '' && !hc_fuzzy_match($log['actorName'] . ' ' . $log['action'] . ' ' . $log['module'] . ' ' . $log['details'], $q)) {
                return false;
            }
            if ($user !== '' && $user !== 'All Users' && $log['actorName'] !== $user) {
                return false;
            }
            if ($module !== '' && $module !== 'All Modules' && $log['module'] !== $module) {
                return false;
            }
            if ($actionLabel !== '' && $actionLabel !== 'All Actions' && strcasecmp(str_replace('_', ' ', $log['action']), $actionLabel) !== 0) {
                return false;
            }
            if ($statusLabel !== '' && $statusLabel !== 'All Statuses' && strcasecmp($log['status'], $statusLabel) !== 0) {
                return false;
            }
            if (!hc_audit_log_matches_date_from_to($log['timestamp'], $dateFrom, $dateTo)) {
                return false;
            }
            if ($roleFilter === 'subadmin' && $log['actorRole'] !== 'Sub-Administrator') {
                return false;
            }
            if ($roleFilter === 'admin' && $log['actorRole'] !== 'System Administrator') {
                return false;
            }
            // 'doctor'/'healthcare_staff'/'medical_technician'/
            // 'radio_technologist'/'pharmacist' — one specific staff role
            // at a time, same granular breakdown on both the admin and
            // subadmin sidebars now.
            $specificRoleToActorRole = [
                'doctor' => 'Doctor',
                'healthcare_staff' => 'Healthcare Staff',
                'medical_technician' => 'Medical Technologist',
                'radio_technologist' => 'Radiologic Technologist',
                'pharmacist' => 'Pharmacist',
            ];
            if (isset($specificRoleToActorRole[$roleFilter]) && $log['actorRole'] !== $specificRoleToActorRole[$roleFilter]) {
                return false;
            }

            return true;
        }));
    }
}
