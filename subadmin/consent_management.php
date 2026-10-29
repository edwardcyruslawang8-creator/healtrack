<?php
/**
 * =====================================================================
 * HEALTrack — Health Information System
 * Consent Management (subadmin/consent_management.php)
 * ---------------------------------------------------------------------
 * Top-level Sub-Admin section (its own sidebar nav item) — same shape
 * as subadmin/user_management.php and its siblings. Reuses the exact
 * same sidebar/topbar shell as the rest of subadmin/*.php (280px
 * dark-red gradient sidebar, "adm-" prefixed atoms,
 * assets/css/admin-dashboard.css), including its existing
 * .adm-action-btn/.adm-pagination/.page-btn/.page-ellipsis atoms
 * verbatim. Consent Management stays active in the sidebar, since
 * this IS that page — and this is the LAST sidebar link in the
 * Sub-Admin section, completing it end to end.
 *
 * UNLIKE most subadmin/*.php pages built earlier this session, this one
 * is NOT front-end only: $consentRecords reads LIVE from the real
 * "consent_records" Firestore collection (see
 * includes/subadmin/consent_management_firestore.php's header comment). The search
 * box and status filter now really filter the table. The date-range/
 * type filters and pagination beyond page 1 are still not wired up.
 * The "Add Consent" button/modal and each row's Accept/Reject buttons
 * were removed (decorative, no write endpoint behind them). The "View"
 * (eye) icon still opens a real Consent Details modal populated from
 * that row's data, via assets/js/consent_management.js.
 * =====================================================================
 */
session_start();

require_once __DIR__ . '/../config/constants.php'; // BASE_URL, asset_url()
require_once __DIR__ . '/../includes/shared/theme_helper.php'; // admin_theme_data_attr(), admin_theme_head_script()
require_once __DIR__ . '/../includes/shared/i18n.php'; // t()

// Only a signed-in sub-admin may view this page.
if (($_SESSION['role'] ?? null) !== 'subadmin') {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

require_once __DIR__ . '/../includes/subadmin/consent_management_firestore.php';
// $adminProfile, $dashboardDateLabel, $facilityOptions, $consentSummary,
// $consentDateRangeLabel, $consentTypeOptions, $consentStatusOptions,
// $consentTypeMeta, $consentStatusMeta, $consentRecords, $consentsTotal,
// $consentsPerPage, $consentsCurrentPage, $consentsTotalPages

// Sidebar nav — same Sub-Admin nav list as subadmin/dashboard.php.
// Consent Management stays active (this IS that page).
$adminNavItems = [
    ['icon' => 'bi-grid-fill',              'label' => t('nav.dashboard'),                    'href' => 'dashboard.php'],
    ['icon' => 'bi-person-lines-fill',      'label' => t('nav.patient_management'),           'href' => 'patient_management.php'],
    ['icon' => 'bi-capsule',                'label' => t('nav.medicine_vaccine_inventory'), 'href' => 'medicine_vaccine_inventory.php'],
    ['icon' => 'bi-box-seam-fill',          'label' => t('nav.stock_requests'),               'href' => 'stock_requests.php'],
    [
        'icon' => 'bi-bar-chart-fill',
        'label' => t('nav.analytics_reports'),
        'id' => 'admNavAnalyticsReports',
        'children' => [
            ['label' => t('nav.analytics_reports_facility'), 'href' => 'analytics_reports.php'],
            ['label' => t('nav.analytics_reports_chatbot'),  'href' => 'chatbot_reports.php'],
        ],
    ],
    ['icon' => 'bi-people-fill',            'label' => t('nav.user_management'),              'href' => 'user_management.php'],
    ['icon' => 'bi-bell-fill',              'label' => t('nav.notifications'),                'href' => 'notifications.php', 'badge' => subadmin_unread_notification_count()],
    [
        'icon' => 'bi-clipboard-data-fill',
        'label' => t('nav.audit_logs'),
        'children' => [
            ['label' => t('nav.audit_logs_subadmin'),           'href' => 'audit_logs.php?role=subadmin'],
            ['label' => t('nav.audit_logs_doctor'),             'href' => 'audit_logs.php?role=doctor'],
            ['label' => t('nav.audit_logs_medstaff'),      'href' => 'audit_logs.php?role=healthcare_staff'],
            ['label' => t('nav.audit_logs_medtech'), 'href' => 'audit_logs.php?role=medical_technician'],
            ['label' => t('nav.audit_logs_radiotech'), 'href' => 'audit_logs.php?role=radio_technologist'],
            ['label' => t('nav.audit_logs_pharmacist'),         'href' => 'audit_logs.php?role=pharmacist'],
        ],
    ],
    ['icon' => 'bi-file-earmark-lock-fill', 'label' => t('nav.consent_management'),           'href' => 'consent_management.php', 'active' => true],
    ['icon' => 'bi-megaphone-fill',         'label' => t('nav.announcements'),                'href' => 'announcements.php'],
    [
        'icon' => 'bi-gear-fill',
        'label' => t('nav.profile_settings'),
        'id' => 'admNavProfileSettings',
        'children' => [
            ['label' => t('nav.profile_settings'), 'href' => 'profile_settings.php'],
            ['label' => t('nav.homepage_settings'), 'href' => 'homepage_settings.php'],
        ],
    ],
];
?>
<!DOCTYPE html>
<html lang="en"<?= admin_theme_data_attr() ?>>
<head>
<?= admin_theme_head_script() ?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Consent Management — HEALTrack</title>

<!-- Google Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<!-- Bootstrap 5 -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<!-- Font Awesome (available per project spec; Bootstrap Icons cover this page's icons) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<!-- Shared admin shell (sidebar, topbar, cards, table, pagination, etc.) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/admin-dashboard.css') ?>">
<!-- Sub-Admin Dashboard styles, reused here for the topbar select buttons -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/subadmin_dashboard.css') ?>">
<!-- Consent Management page styles (summary cards, toolbar, table, modals) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/consent_management.css') ?>">
<link rel="stylesheet" href="<?= asset_url('/assets/css/hc-dialogs.css') ?>">
</head>
<body class="adm-body">

<!-- Mobile sidebar trigger -->
<button class="adm-mobile-menu-btn" id="admSidebarToggle" aria-label="Open menu">
    <i class="bi bi-list"></i>
</button>
<div class="adm-sidebar-backdrop" id="admSidebarBackdrop"></div>

<!-- =============================================================
     SIDEBAR
     ============================================================= -->
<aside class="adm-sidebar" id="admSidebar">

    <!-- Brand -->
    <div class="adm-brand">
        <div class="adm-logo-badge"><i class="bi bi-heart-pulse-fill"></i></div>
        <div class="adm-brand-text">
            <h1>HEALTrack</h1>
            <p><?= htmlspecialchars(t('app.tagline')) ?></p>
        </div>
    </div>

    <hr class="adm-sidebar-divider">

    <!-- Sub-admin profile -->
    <div class="adm-profile">
        <div class="adm-avatar"><?php require_once __DIR__ . '/../includes/shared/profile_photo_storage.php'; echo hc_render_sidebar_avatar(); ?></div>
        <div class="adm-profile-text">
            <div class="adm-profile-name"><?= htmlspecialchars($adminProfile['name']) ?></div>
            <div class="adm-profile-role"><?= htmlspecialchars($adminProfile['role']) ?></div>
        </div>
    </div>

    <hr class="adm-sidebar-divider">

    <!-- Navigation -->
    <ul class="adm-nav">
        <?php foreach ($adminNavItems as $item): ?>
        <?php if (!empty($item['children'])): ?>
        <li>
            <a href="#<?= htmlspecialchars($item['id'] ?? 'admNavAuditLogs') ?>" class="adm-nav-link adm-nav-toggle <?= !empty($item['active']) ? 'active' : '' ?> <?= empty($item['open']) ? 'collapsed' : '' ?>" data-bs-toggle="collapse" role="button" aria-expanded="<?= !empty($item['open']) ? 'true' : 'false' ?>" aria-controls="<?= htmlspecialchars($item['id'] ?? 'admNavAuditLogs') ?>">
                <i class="bi <?= htmlspecialchars($item['icon']) ?>"></i>
                <span><?= htmlspecialchars($item['label']) ?></span>
                <i class="bi bi-chevron-down adm-nav-caret"></i>
            </a>
            <div class="collapse <?= !empty($item['open']) ? 'show' : '' ?>" id="<?= htmlspecialchars($item['id'] ?? 'admNavAuditLogs') ?>">
                <ul class="adm-nav-submenu">
                    <?php foreach ($item['children'] as $child): ?>
                    <li><a href="<?= htmlspecialchars($child['href']) ?>" class="adm-nav-sublink <?= !empty($child['active']) ? 'active' : '' ?>"><?= htmlspecialchars($child['label']) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </li>
        <?php else: ?>
        <li>
            <a href="<?= htmlspecialchars($item['href']) ?>" class="adm-nav-link <?= !empty($item['active']) ? 'active' : '' ?>">
                <i class="bi <?= htmlspecialchars($item['icon']) ?>"></i>
                <span><?= htmlspecialchars($item['label']) ?></span>
                <?php if (!empty($item['badge'])): ?>
                <span class="sad-nav-badge"><?= (int) $item['badge'] ?></span>
                <?php endif; ?>
            </a>
        </li>
        <?php endif; ?>
        <?php endforeach; ?>
    </ul>

    <div class="adm-nav-bottom">
        <hr class="adm-sidebar-divider" style="margin-bottom:12px;">
        <a href="<?= BASE_URL ?>/api/logout.php" class="adm-nav-link">
            <i class="bi bi-box-arrow-right"></i>
            <span><?= htmlspecialchars(t('nav.logout')) ?></span>
        </a>
    </div>
</aside>

<!-- =============================================================
     MAIN CONTENT
     ============================================================= -->
<main class="adm-main">

    <!-- ============ TOP HEADER ============ -->
    <div class="adm-topbar">
        <div>
            <h1><?= htmlspecialchars(t('nav.consent_management')) ?></h1>
            <p class="adm-page-subtitle"><?= htmlspecialchars(t('page.consent_management.subtitle')) ?></p>
        </div>

        <div class="adm-topbar-right">
            <div class="adm-topbar-actions">
                <!-- Fixed — display only, not a range picker; nothing
                     downstream reads a selected value yet. -->
                <div class="sad-topbar-select-btn">
                    <i class="bi bi-calendar3"></i> <?= htmlspecialchars($dashboardDateLabel) ?>
                </div>

                <!-- Fixed — shows only the facility this sub-admin is actually
                     assigned to (their real users/{uid} doc's "facility" field);
                     not a switcher, since a sub-admin can't reassign themselves. -->
                <div class="sad-topbar-select-btn">
                    <i class="bi bi-building"></i> <?= htmlspecialchars($adminProfile['facility']) ?>
                </div>

                <a href="notifications.php" class="adm-bell-btn" aria-label="Notifications">
                    <i class="bi bi-bell-fill"></i>
                    <?php if (subadmin_unread_notification_count() > 0): ?><span class="adm-badge-count"><?= subadmin_unread_notification_count() ?></span><?php endif; ?>
                </a>

                <div class="dropdown">
                    <button class="adm-profile-dropdown dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="adm-topbar-avatar"><?php require_once __DIR__ . '/../includes/shared/profile_photo_storage.php'; echo hc_render_sidebar_avatar(); ?></span>
                        <span>
                            <span class="d-block adm-topbar-name"><?= htmlspecialchars($adminProfile['name']) ?></span>
                            <span class="d-block adm-topbar-role"><?= htmlspecialchars($adminProfile['role']) ?></span>
                        </span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="profile_settings.php"><i class="bi bi-gear me-2"></i>Profile / Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/api/logout.php"><i class="bi bi-box-arrow-right me-2"></i><?= htmlspecialchars(t('nav.logout')) ?></a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- ============ SUMMARY CARDS ============ -->
    <div class="csm-summary-row">
        <div class="adm-card csm-summary-card">
            <div class="csm-summary-icon" style="background:#F1E8FD; color:#8B5CF6;"><i class="bi bi-file-earmark-text-fill"></i></div>
            <div class="csm-summary-text">
                <span class="csm-summary-label">Total Consents</span>
                <span class="csm-summary-value"><?= number_format($consentSummary['totalConsents']) ?></span>
                <span class="csm-summary-sub">Total Records</span>
            </div>
        </div>

        <div class="adm-card csm-summary-card">
            <div class="csm-summary-icon" style="background:#E4F7EA; color:#1E8E3E;"><i class="bi bi-check-circle-fill"></i></div>
            <div class="csm-summary-text">
                <span class="csm-summary-label">Active Consents</span>
                <span class="csm-summary-value"><?= number_format($consentSummary['activeConsents']) ?></span>
                <span class="csm-summary-sub">Currently Valid</span>
            </div>
        </div>

        <div class="adm-card csm-summary-card">
            <div class="csm-summary-icon" style="background:#FDF3DC; color:#B7791F;"><i class="bi bi-hourglass-split"></i></div>
            <div class="csm-summary-text">
                <span class="csm-summary-label">Pending Approval</span>
                <span class="csm-summary-value"><?= number_format($consentSummary['pendingApproval']) ?></span>
                <span class="csm-summary-sub">Awaiting Review</span>
            </div>
        </div>

        <div class="adm-card csm-summary-card">
            <div class="csm-summary-icon" style="background:#FCE4E4; color:#D93025;"><i class="bi bi-exclamation-triangle-fill"></i></div>
            <div class="csm-summary-text">
                <span class="csm-summary-label">Expired Consents</span>
                <span class="csm-summary-value"><?= number_format($consentSummary['expiredConsents']) ?></span>
                <span class="csm-summary-sub">Require Renewal</span>
            </div>
        </div>
    </div>

    <!-- ============ SEARCH & FILTER TOOLBAR ============ -->
    <form class="csm-toolbar" method="get" action="consent_management.php">
        <div class="csm-search">
            <i class="bi bi-search"></i>
            <input type="text" name="q" value="<?= htmlspecialchars($searchTerm) ?>" placeholder="Search by patient name or ID...">
        </div>
        <select class="form-select csm-filter-btn" name="status" aria-label="Filter by status" onchange="this.form.submit()">
            <?php foreach ($consentStatusOptions as $opt): ?>
            <option <?= $opt === $statusFilter ? 'selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="csm-filter-btn">
            <i class="bi bi-search"></i> Search
        </button>
    </form>

    <!-- ============ CONSENT RECORDS TABLE ============ -->
    <div class="adm-card csm-table-card">
        <div class="adm-table-wrap">
            <table class="adm-table csm-table">
                <thead>
                    <tr>
                        <th>Patient</th>
                        <th>Consent Type</th>
                        <th>Purpose</th>
                        <th>Requested By</th>
                        <th>Status</th>
                        <th>Given On</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="csmTableBody">
                    <?php if (empty($consentRecords)): ?>
                    <tr>
                        <td colspan="7">
                            <div class="csm-empty-state">
                                <i class="bi bi-file-earmark-text"></i>
                                <p>No consent records found.</p>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php foreach ($consentRecords as $c): $type = $consentTypeMeta[$c['type']] ?? ['label' => 'Consent', 'bg' => '#EEF0F3', 'color' => '#6B7280']; $status = $consentStatusMeta[$c['status']] ?? $consentStatusMeta['pending']; ?>
                    <tr class="csm-row"
                        data-status="<?= htmlspecialchars($c['status']) ?>"
                        data-patient-name="<?= htmlspecialchars($c['patientName']) ?>"
                        data-patient-id="<?= htmlspecialchars($c['patientId']) ?>"
                        data-type-label="<?= htmlspecialchars($type['label']) ?>"
                        data-purpose="<?= htmlspecialchars($c['purpose']) ?>"
                        data-given-on="<?= htmlspecialchars($c['givenOn']) ?>"
                        data-expiration="<?= htmlspecialchars($c['expirationDate'] ?? '') ?>"
                        data-approved-by="<?= htmlspecialchars($c['approvedBy']) ?>"
                        data-notes="<?= htmlspecialchars($c['notes']) ?>"
                        data-signature="<?= htmlspecialchars($c['signature'] ?? '') ?>"
                        data-attached-file="<?= htmlspecialchars($c['attachedFile'] ?? '') ?>"
                        data-requested-by-name="<?= htmlspecialchars($c['requestedByName'] ?? '') ?>"
                        data-requested-by-role="<?= htmlspecialchars($c['requestedByRoleLabel'] ?? '') ?>">
                        <td>
                            <div class="csm-patient-cell">
                                <span class="csm-avatar" style="background:<?= htmlspecialchars($c['avatar']['bg']) ?>; color:<?= htmlspecialchars($c['avatar']['color']) ?>;">
                                    <i class="bi bi-person-fill"></i>
                                </span>
                                <div class="csm-patient-text">
                                    <span class="csm-patient-name"><?= htmlspecialchars($c['patientName']) ?></span>
                                </div>
                            </div>
                        </td>
                        <td><span class="csm-type-badge" style="background:<?= htmlspecialchars($type['bg']) ?>; color:<?= htmlspecialchars($type['color']) ?>;"><?= htmlspecialchars($type['label']) ?></span></td>
                        <td class="csm-purpose-cell"><?= htmlspecialchars($c['purpose']) ?></td>
                        <td><?= $c['requestedByName'] !== null ? htmlspecialchars($c['requestedByName']) . ' <span class="text-muted small">(' . htmlspecialchars($c['requestedByRoleLabel']) . ')</span>' : '—' ?></td>
                        <td>
                            <span class="csm-status-badge" data-status-badge style="background:<?= htmlspecialchars($status['bg']) ?>; color:<?= htmlspecialchars($status['color']) ?>;">
                                <i class="bi <?= $c['status'] === 'accepted' ? 'bi-check-circle-fill' : ($c['status'] === 'rejected' ? 'bi-x-circle-fill' : 'bi-clock-fill') ?>"></i>
                                <?= htmlspecialchars($status['label']) ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($c['givenOn']) ?></td>
                        <td>
                            <div class="adm-action-btns">
                                <button type="button" class="adm-action-btn view csm-view-btn" aria-label="View" data-bs-toggle="modal" data-bs-target="#csmDetailsModal">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="adm-table-footer">
            <span class="adm-showing-text"><?= $consentsTotal > 0 ? 'Showing ' . (($consentsCurrentPage - 1) * $consentsPerPage + 1) . ' to ' . (($consentsCurrentPage - 1) * $consentsPerPage + count($consentRecords)) . ' of ' . number_format($consentsTotal) . ' entries' : 'No entries to show' ?></span>
            <?= hc_render_pagination_nav('Consent records pages', 'page', $consentsCurrentPage, $consentsTotalPages) ?>
        </div>
    </div>

</main>

<!-- ============ CONSENT DETAILS MODAL (View) ============ -->
<div class="modal fade" id="csmDetailsModal" tabindex="-1" aria-labelledby="csmDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content csm-details-modal">
            <div class="modal-header">
                <h5 class="modal-title" id="csmDetailsModalLabel">Consent Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="csm-details-patient">
                    <span class="csm-avatar csm-avatar-lg"><i class="bi bi-person-fill"></i></span>
                    <div>
                        <div class="csm-details-patient-name" id="csmDetailPatientName">—</div>
                        <div class="csm-details-patient-id" id="csmDetailPatientId">—</div>
                    </div>
                </div>

                <div class="csm-details-grid">
                    <div class="csm-detail-field"><span class="csm-detail-label">Consent Type</span><span class="csm-detail-value" id="csmDetailType">—</span></div>
                    <div class="csm-detail-field"><span class="csm-detail-label">Status</span><span class="csm-detail-value" id="csmDetailStatus">—</span></div>
                    <div class="csm-detail-field csm-detail-field-full" id="csmDetailRequestedByRow"><span class="csm-detail-label">Requested By</span><span class="csm-detail-value" id="csmDetailRequestedBy">—</span></div>
                    <div class="csm-detail-field csm-detail-field-full"><span class="csm-detail-label">Purpose</span><span class="csm-detail-value" id="csmDetailPurpose">—</span></div>
                    <div class="csm-detail-field"><span class="csm-detail-label">Date Granted</span><span class="csm-detail-value" id="csmDetailGivenOn">—</span></div>
                    <div class="csm-detail-field"><span class="csm-detail-label">Expiration Date</span><span class="csm-detail-value" id="csmDetailExpiration">—</span></div>
                    <div class="csm-detail-field"><span class="csm-detail-label">Authorized By</span><span class="csm-detail-value" id="csmDetailApprovedBy">—</span></div>
                    <div class="csm-detail-field"><span class="csm-detail-label">Digital Signature</span><span class="csm-detail-value csm-signature-preview" id="csmDetailSignature">—</span></div>
                    <div class="csm-detail-field csm-detail-field-full"><span class="csm-detail-label">Notes</span><span class="csm-detail-value" id="csmDetailNotes">—</span></div>
                </div>

                <div class="csm-attached-file" id="csmDetailAttachedWrap">
                    <span class="csm-file-icon"><i class="bi bi-file-earmark-pdf-fill"></i></span>
                    <span id="csmDetailAttached">—</span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="csm-modal-btn-outline" data-bs-dismiss="modal">Close</button>
                <button type="button" class="csm-modal-btn-outline"><i class="bi bi-download"></i> Download PDF</button>
                <button type="button" class="csm-modal-btn-primary"><i class="bi bi-printer"></i> Print Consent</button>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= asset_url('/assets/js/admin-sidebar-toggle.js') ?>"></script>
<script src="<?= asset_url('/assets/js/theme.js') ?>"></script>
<script src="<?= asset_url('/assets/js/consent_management.js') ?>"></script>
<script src="<?= asset_url('/assets/js/hc-dialogs.js') ?>"></script>
<script src="<?= asset_url('/assets/js/search-form-guard.js') ?>"></script>
</body>
</html>
