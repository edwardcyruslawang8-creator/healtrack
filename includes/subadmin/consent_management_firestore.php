<?php
/**
 * =====================================================================
 * Consent Management — Data
 * ---------------------------------------------------------------------
 * Reads live from the real "consent_records" Firestore collection — a
 * brand-new collection seeded this session, tied to the real
 * "patients" collection (see includes/patient_management_firestore.php) via
 * each record's patientId. Doc IDs are Firestore auto-IDs (addDocument())
 * since nothing needs to address a consent record by a human-typed id.
 *
 * The search box and status filter now really filter $consentRecords.
 * The date-range/type filters and pagination beyond page 1 are still
 * not wired up. The "Add Consent" button/modal and each row's
 * Accept/Reject buttons were removed (decorative, no write endpoint
 * behind them) — the "View" (eye) icon's Consent Details modal is the
 * only per-row interaction left, driven by
 * assets/js/consent_management.js.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore
require_once __DIR__ . '/../firestore/patients.php'; // hc_get_all_patients_summary()
require_once __DIR__ . '/../shared/fuzzy_search.php'; // hc_fuzzy_match()
require_once __DIR__ . '/../shared/pagination_helpers.php'; // hc_paginate(), hc_render_pagination_nav()

$myUid = $_SESSION['uid'] ?? '';
$myDoc = $myUid !== '' ? $firestore->getDocument('users', $myUid) : null;

$adminProfile = [
    'name' => $_SESSION['name'] ?? 'Sub-Administrator',
    'role' => match ($_SESSION['role'] ?? null) {
        'subadmin' => 'Sub-Admin',
        'healthcare_staff' => 'Healthcare Staff',
        default => 'Sub-Administrator',
    },
    'facility' => $myDoc['facility'] ?? 'Unassigned Facility',
];

$dashboardDateLabel = date('F j, Y');
$facilityOptions = ['North Super Health Center', 'East Super Health Center', 'West Super Health Center'];

$consentDateRangeLabel = 'All Date Ranges';
$consentTypeOptions = ['All Types', 'Data Sharing', 'Medical Treatment', 'Laboratory', 'Research', 'Insurance', 'Vaccination'];
$consentStatusOptions = ['All Status', 'Pending', 'Accepted', 'Rejected', 'Expired'];

$consentTypeMeta = [
    'data_sharing'      => ['label' => 'Data Sharing Consent',      'bg' => '#F1E8FD', 'color' => '#8B5CF6'],
    'medical_treatment' => ['label' => 'Medical Consent',           'bg' => '#E8F0FE', 'color' => '#2563EB'],
    'laboratory'        => ['label' => 'Laboratory Consent',        'bg' => '#E4F7EA', 'color' => '#1E8E3E'],
    'research'          => ['label' => 'Research Consent',          'bg' => '#FDECDD', 'color' => '#E8710A'],
    'insurance'         => ['label' => 'Insurance Consent',         'bg' => '#E3F6FB', 'color' => '#12A6C6'],
    'vaccination'       => ['label' => 'Vaccination Consent',       'bg' => '#FCE4E4', 'color' => '#D93025'],
];

$consentStatusMeta = [
    'pending'  => ['label' => 'Pending',  'bg' => '#FDF3DC', 'color' => '#B7791F'],
    'accepted' => ['label' => 'Accepted', 'bg' => '#E4F7EA', 'color' => '#1E8E3E'],
    'rejected' => ['label' => 'Rejected', 'bg' => '#FCE4E4', 'color' => '#D93025'],
    'expired'  => ['label' => 'Expired',  'bg' => '#EEF0F3', 'color' => '#6B7280'],
];

$avatarPalette = [
    ['bg' => '#DCEBFC', 'color' => '#1A73E8'],
    ['bg' => '#FCE4E4', 'color' => '#D93025'],
    ['bg' => '#F1E3FB', 'color' => '#8B5CF6'],
    ['bg' => '#FDECDD', 'color' => '#E8710A'],
    ['bg' => '#E4F7EA', 'color' => '#1E8E3E'],
];

// ---------------------------------------------------------------------
// Real consent records — every consent_records/{autoId} doc, newest
// "given on" date first, scoped to this facility. consent_records has
// no "facility" field of its own (only patientId), so this joins
// against this sub-admin's own real facility's patients instead — same
// join includes/subadmin/subadmin_dashboard_firestore.php uses for
// consultations.
// ---------------------------------------------------------------------
$cmFacilityPatientIds = array_column(
    array_filter(hc_get_all_patients_summary(), fn ($p) => $p['facility'] === $adminProfile['facility']),
    'id'
);
$rawConsents = array_values(array_filter(
    $firestore->listDocuments('consent_records'),
    fn ($doc) => in_array($doc['fields']['patientId'] ?? null, $cmFacilityPatientIds, true)
));
usort($rawConsents, fn ($a, $b) => strcmp($b['fields']['givenOn'] ?? '', $a['fields']['givenOn'] ?? ''));

$allConsents = [];
foreach ($rawConsents as $i => $doc) {
    $fields = $doc['fields'];
    $allConsents[] = [
        'id' => $doc['id'],
        'patientName' => $fields['patientName'] ?? '(unknown patient)',
        'patientId' => $fields['patientId'] ?? '',
        'type' => $fields['type'] ?? 'data_sharing',
        'purpose' => $fields['purpose'] ?? '',
        'status' => $fields['status'] ?? 'pending',
        'givenOn' => isset($fields['givenOn']) ? date('M j, Y', strtotime($fields['givenOn'])) : '—',
        'expirationDate' => !empty($fields['expirationDate']) ? date('M j, Y', strtotime($fields['expirationDate'])) : null,
        'approvedBy' => $fields['approvedBy'] ?? '—',
        'notes' => $fields['notes'] ?? '',
        'signature' => $fields['signature'] ?? null,
        'attachedFile' => $fields['attachedFile'] ?? null,
        'avatar' => $avatarPalette[$i % count($avatarPalette)],
    ];
}

// ---------------------------------------------------------------------
// Consent Summary — real counts over $allConsents.
// ---------------------------------------------------------------------
$consentSummary = [
    'totalConsents' => count($allConsents),
    'activeConsents' => count(array_filter($allConsents, fn ($c) => $c['status'] === 'accepted')),
    'pendingApproval' => count(array_filter($allConsents, fn ($c) => $c['status'] === 'pending')),
    'expiredConsents' => count(array_filter($allConsents, fn ($c) => $c['status'] === 'expired')),
];

// ---------------------------------------------------------------------
// Apply the search box (?q=) and status filter (?status=) for real.
// ---------------------------------------------------------------------
$searchTerm = trim($_GET['q'] ?? '');
$statusFilter = $_GET['status'] ?? 'All Status';

$filteredConsents = array_values(array_filter($allConsents, function ($c) use ($searchTerm, $statusFilter, $consentStatusMeta) {
    if ($searchTerm !== '' && !hc_fuzzy_match($c['patientName'] . ' ' . $c['patientId'], $searchTerm)) {
        return false;
    }
    if ($statusFilter !== 'All Status' && ($consentStatusMeta[$c['status']]['label'] ?? '') !== $statusFilter) {
        return false;
    }
    return true;
}));

// ---------------------------------------------------------------------
// Pagination — real total/page-count from the filtered result above.
// ---------------------------------------------------------------------
$consentsPerPage = 8;
$consentsPagination = hc_paginate($filteredConsents, 'page', $consentsPerPage);
$consentRecords = $consentsPagination['items'];
$consentsTotal = $consentsPagination['total'];
$consentsTotalPages = $consentsPagination['totalPages'];
$consentsCurrentPage = $consentsPagination['page'];
