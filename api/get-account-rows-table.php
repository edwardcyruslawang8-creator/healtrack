<?php
/**
 * =====================================================================
 * Get Account Rows Table (api/get-account-rows-table.php)
 * ---------------------------------------------------------------------
 * Called by assets/js/account-monitoring-ajax.js whenever the search
 * box, Role filter, Status filter, or a pagination link changes on
 * admin/account_monitoring.php's "All User Accounts" card — returns
 * just that table+pagination HTML fragment (same markup
 * includes/admin/_account_rows_table.php renders on a normal full page
 * load) so only that fragment swaps, instead of a full page reload.
 *
 * Every ?acct_q=/acct_role=/acct_status=/acct_page= param is read
 * exactly the same way the full page reads it — this file just runs
 * the SAME data file admin/account_monitoring.php does, then echoes
 * one fragment instead of the whole page.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/constants.php'; // BASE_URL, asset_url()

if (($_SESSION['role'] ?? null) !== 'admin') {
    http_response_code(403);
    exit('Not authorized.');
}

require_once __DIR__ . '/../includes/admin/account_monitoring_firestore.php';
// $allAccountRows, $accountRowsPage, $accountRowsTotal, $accountRowsPerPage,
// $accountRowsCurrentPage, $accountRowsTotalPages, $accountRowsPaginationWindow,
// hc_acct_query()

require __DIR__ . '/../includes/admin/_account_rows_table.php';
