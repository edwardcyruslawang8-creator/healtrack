<?php
/**
 * =====================================================================
 * Patient History (Medical Staff) — Data
 * ---------------------------------------------------------------------
 * Reuses includes/patient_profile_medstaff_firestore.php for everything
 * about the patient themselves ($staffProfile, $patient, $profileTabs)
 * — re-marks the "history" tab active here. This is the last tab in
 * the medical-staff patient profile family. The actual Firestore
 * queries live in includes/firestore/patient_history.php — shared with
 * subadmin's identical page.
 * =====================================================================
 */

require_once __DIR__ . '/patient_profile_medstaff_firestore.php';
// $staffProfile, $dashboardDateLabel, $facilityOptions, $patient, $profileTabs
require_once __DIR__ . '/../firestore/patient_history.php';
require_once __DIR__ . '/../shared/pagination_helpers.php'; // hc_paginate(), hc_render_pagination_nav(), hc_matches_date_range_key()

foreach ($profileTabs as &$tab) {
    $tab['active'] = ($tab['key'] === 'history');
}
unset($tab);

// ---------------------------------------------------------------------
// Record History filters
// ---------------------------------------------------------------------
$historyDateRangeOptions = ['all' => 'All Dates', 'today' => 'Today', 'this_week' => 'This Week', 'this_month' => 'This Month'];
$recordTypeOptions = ['All Record Types', 'Patient Profile', 'Medical Record', 'Laboratory Result', 'Medication', 'Document', 'Vaccination Record', 'Allergy'];

// Record type -> icon/color meta.
$recordTypeMeta = [
    'patient_profile'    => ['label' => 'Patient Profile',    'icon' => 'bi-person-fill',            'color' => '#1A73E8'],
    'medical_record'      => ['label' => 'Medical Record',      'icon' => 'bi-folder2-open',           'color' => '#8B5CF6'],
    'laboratory_result'   => ['label' => 'Laboratory Result',   'icon' => 'bi-droplet-fill',            'color' => '#1E8E3E'],
    'medication'          => ['label' => 'Medication',          'icon' => 'bi-capsule',                'color' => '#E8710A'],
    'document'            => ['label' => 'Document',            'icon' => 'bi-file-earmark-text-fill', 'color' => '#1A73E8'],
    'vaccination_record'  => ['label' => 'Vaccination Record',  'icon' => 'bi-shield-fill-check',      'color' => '#8B5CF6'],
    'allergy'             => ['label' => 'Allergy',             'icon' => 'bi-exclamation-triangle-fill', 'color' => '#D93025'],
];

// Action -> badge color meta.
$actionMeta = [
    'updated'  => ['label' => 'Updated',  'bg' => '#E4F7EA', 'color' => '#1E8E3E'],
    'added'    => ['label' => 'Added',    'bg' => '#E8F0FE', 'color' => '#1A73E8'],
    'uploaded' => ['label' => 'Uploaded', 'bg' => '#F1E8FD', 'color' => '#8B5CF6'],
    'deleted'  => ['label' => 'Deleted',  'bg' => '#FCE4E4', 'color' => '#D93025'],
    'modified' => ['label' => 'Modified', 'bg' => '#FDECDD', 'color' => '#E8710A'],
];

// ---------------------------------------------------------------------
// Record History — real, from "patient_record_history". The Record Type
// and date-range dropdowns above the table are real filters.
// ---------------------------------------------------------------------
$allRecordHistory = hc_get_record_history_for_patient($patient['id']);

$historyRecordTypeFilter = in_array($_GET['hisType'] ?? '', $recordTypeOptions, true) ? $_GET['hisType'] : 'All Record Types';
$historyDateRangeFilter = array_key_exists($_GET['hisRange'] ?? '', $historyDateRangeOptions) ? $_GET['hisRange'] : 'all';
$historyDateRangeLabel = $historyDateRangeOptions[$historyDateRangeFilter];

$filteredRecordHistory = array_values(array_filter($allRecordHistory, function ($rec) use ($historyRecordTypeFilter, $historyDateRangeFilter, $recordTypeMeta) {
    if ($historyRecordTypeFilter !== 'All Record Types' && ($recordTypeMeta[$rec['type']]['label'] ?? '') !== $historyRecordTypeFilter) {
        return false;
    }
    if (!hc_matches_date_range_key($rec['date'] ?? '', $historyDateRangeFilter)) {
        return false;
    }
    return true;
}));

$recordHistoryPerPage = 5;
$recordHistoryPagination = hc_paginate($filteredRecordHistory, 'recordPage', $recordHistoryPerPage);
$recordHistory = $recordHistoryPagination['items'];
$recordHistoryTotal = $recordHistoryPagination['total'];
$recordHistoryTotalPages = $recordHistoryPagination['totalPages'];
$recordHistoryCurrentPage = $recordHistoryPagination['page'];

// ---------------------------------------------------------------------
// Activity Log — real, from "patient_activity_log".
// ---------------------------------------------------------------------
$allActivityLog = hc_get_activity_log_for_patient($patient['id']);
$activityLogPerPage = 5;
$activityLogPagination = hc_paginate($allActivityLog, 'activityPage', $activityLogPerPage);
$activityLog = $activityLogPagination['items'];
$activityLogTotal = $activityLogPagination['total'];
$activityLogTotalPages = $activityLogPagination['totalPages'];
$activityLogCurrentPage = $activityLogPagination['page'];
