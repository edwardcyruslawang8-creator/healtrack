<?php
/**
 * =====================================================================
 * Patient History (Medical Staff) — Data
 * ---------------------------------------------------------------------
 * Reuses includes/patient_profile_medstaff_firestore.php for everything
 * about the patient themselves ($staffProfile, $patient, $profileTabs)
 * — re-marks the "history" tab active here. This is the last tab in
 * the medical-staff patient profile family. The Consultation History
 * table reads includes/firestore/consultations.php's
 * hc_get_consultations_for_patient(), narrowed to 'completed' visits
 * only (there's nothing meaningful to review yet for a pending/ongoing/
 * cancelled one) — shared with subadmin's identical page.
 * =====================================================================
 */

require_once __DIR__ . '/patient_profile_medstaff_firestore.php';
// $staffProfile, $dashboardDateLabel, $facilityOptions, $patient, $profileTabs
require_once __DIR__ . '/../firestore/consultations.php'; // hc_get_consultations_for_patient(), hc_consultation_type_meta()
require_once __DIR__ . '/../shared/pagination_helpers.php'; // hc_paginate(), hc_render_pagination_nav()

foreach ($profileTabs as &$tab) {
    $tab['active'] = ($tab['key'] === 'history');
}
unset($tab);

// ---------------------------------------------------------------------
// Consultation History filters
// ---------------------------------------------------------------------
$consultationTypeMeta = hc_consultation_type_meta();
// Only Walk-in and Follow-up are offered, with Walk-in as the default.
$recordTypeOptions = [$consultationTypeMeta['walk_in']['label'], $consultationTypeMeta['follow_up']['label']];

// ---------------------------------------------------------------------
// Consultation History — real, from "consultations", only this
// patient's completed visits (every pending/ongoing/cancelled one is
// left out — nothing to review there yet). The Type dropdown and a
// real From/To date range above the table are real filters — any
// range, not just preset Today/This Week/This Month buckets.
// ---------------------------------------------------------------------
$allRecordHistory = array_map(function ($c) {
    $timestamp = strtotime((string) $c['dateTime']);

    return [
        'consultationCode' => $c['consultationCode'],
        'rawDate' => $timestamp !== false ? date('Y-m-d', $timestamp) : '',
        'date' => $timestamp !== false ? date('M j, Y', $timestamp) : '—',
        'time' => $timestamp !== false ? date('g:i A', $timestamp) : '—',
        'consultationType' => $c['consultationType'],
        'chiefComplaint' => $c['chiefComplaint'] !== '' ? $c['chiefComplaint'] : '—',
        'diagnosis' => trim((string) $c['diagnosis']) !== '' ? $c['diagnosis'] : '—',
        'detail' => trim((string) $c['diagnosis']) !== '' ? $c['diagnosis'] : ($c['chiefComplaint'] !== '' ? $c['chiefComplaint'] : '—'),
        'assignedPersonnel' => $c['assignedPersonnel'] !== '' ? $c['assignedPersonnel'] : '—',
        'notes' => trim((string) $c['notes']) !== '' ? $c['notes'] : '',
    ];
}, array_values(array_filter(hc_get_consultations_for_patient($patient['id']), fn ($c) => $c['status'] === 'completed')));

$historyRecordTypeFilter = in_array($_GET['hisType'] ?? '', $recordTypeOptions, true) ? $_GET['hisType'] : $recordTypeOptions[0];
// Both blank by default — every completed visit, not just one preset
// window — and either end can be left blank for an open-ended range.
$historyDateFrom = trim($_GET['hisFrom'] ?? '');
$historyDateTo = trim($_GET['hisTo'] ?? '');

$filteredRecordHistory = array_values(array_filter($allRecordHistory, function ($rec) use ($historyRecordTypeFilter, $historyDateFrom, $historyDateTo, $consultationTypeMeta) {
    if (($consultationTypeMeta[$rec['consultationType']]['label'] ?? '') !== $historyRecordTypeFilter) {
        return false;
    }
    if ($historyDateFrom !== '' && $rec['rawDate'] < $historyDateFrom) {
        return false;
    }
    if ($historyDateTo !== '' && $rec['rawDate'] > $historyDateTo) {
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
