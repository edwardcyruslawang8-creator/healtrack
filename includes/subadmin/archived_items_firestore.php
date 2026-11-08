<?php
/**
 * =====================================================================
 * Archived Items (Sub-Admin) — Data
 * ---------------------------------------------------------------------
 * Same archived Medicines/Vaccines lists, search, category filter,
 * pagination and summary counts as pharmacist/archived_items.php — the
 * "medicines" collection isn't tracked per facility, so there's nothing
 * role-specific about that data. Reuses
 * includes/pharmacist/archived_items_pharmacist_firestore.php for it
 * and only adds the sub-admin's own $adminProfile (sidebar/topbar).
 * =====================================================================
 */

require_once __DIR__ . '/../pharmacist/archived_items_pharmacist_firestore.php';
// $myDoc, $dashboardDateLabel, $archivedSummary, $medicineExpiryBadgeMeta,
// $medicines/$vaccines + their filters and pagination.

$adminProfile = [
    'name' => $_SESSION['name'] ?? 'Sub-Administrator',
    'role' => 'Sub-Admin',
    'facility' => $myDoc['facility'] ?? 'Unassigned Facility',
];
