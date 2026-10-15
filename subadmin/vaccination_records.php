<?php
/**
 * =====================================================================
 * HEALTrack — Health Information System
 * Patient Vaccination Records (subadmin/vaccination_records.php)
 * ---------------------------------------------------------------------
 * This IS subadmin/patient_profile.php's "Vaccination Records" tab —
 * reached by clicking that tab (or the "View All" / "Go to Vaccination
 * Records" links on the Overview tab's Vaccination History card) — as
 * its own page rather than an in-page tab pane, since it needs its own
 * table + expandable details panel. Reuses the exact same
 * sidebar/topbar/profile-header/tab-strip shell as patient_profile.php,
 * patient_laboratory_xray.php, and medical_records.php (280px dark-red
 * gradient sidebar, "adm-"/"ppf-" prefixed atoms), including the Edit
 * Profile button staying removed there, for consistency — all four
 * pages render the literal same profile header component.
 *
 * CONNECTED to real Firestore data — see includes/subadmin/vaccination_records_firestore.php's
 * header comment. The Filter button still isn't wired up. Each row's
 * View button now links to vaccination_record_details.php with a real
 * &recordId=, so that page shows the actual clicked record. The
 * "Vaccine Record Details" panel below the table on this page is a
 * separate, always-expandable summary of the most recently administered
 * record.
 *
 * Every profile-tab link (data-ajax-tab) is smooth-swapped by
 * assets/js/ajax-tab-nav.js instead of a real navigation — see that
 * file's own header comment for the full mechanism.
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

require_once __DIR__ . '/../includes/subadmin/vaccination_records_firestore.php';
// $adminProfile, $dashboardDateLabel, $facilityOptions, $patient, $profileTabs,
// $vaccineTypeMeta, $vaccinationRecords, $vaccinationRecordsTotal,
// $vaccinationRecordsPerPage, $vaccinationRecordsCurrentPage,
// $vaccinationRecordsTotalPages, $selectedVaccineRecord

// Sidebar nav — same Sub-Admin nav list as subadmin/dashboard.php.
// Patient Management stays active (this page is reached from it).
$adminNavItems = [
    ['icon' => 'bi-grid-fill',              'label' => t('nav.dashboard'),                    'href' => 'dashboard.php'],
    ['icon' => 'bi-person-lines-fill',      'label' => t('nav.patient_management'),           'href' => 'patient_management.php', 'active' => true],
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
            ['label' => t('nav.audit_logs_doctor'),             'href' => 'audit_logs.php?role=doctor'],
            ['label' => t('nav.audit_logs_medstaff'),      'href' => 'audit_logs.php?role=healthcare_staff'],
            ['label' => t('nav.audit_logs_medtech'), 'href' => 'audit_logs.php?role=medical_technician'],
            ['label' => t('nav.audit_logs_pharmacist'),         'href' => 'audit_logs.php?role=pharmacist'],
        ],
    ],
    ['icon' => 'bi-file-earmark-lock-fill', 'label' => t('nav.consent_management'),           'href' => 'consent_management.php'],
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

$selectedMeta = $vaccineTypeMeta[$selectedVaccineRecord['type']] ?? ['label' => 'Vaccine', 'icon' => 'bi-shield-fill-check', 'bg' => '#EEF0F3', 'color' => '#6B7280'];
?>
<!DOCTYPE html>
<html lang="en"<?= admin_theme_data_attr() ?>>
<head>
<?= admin_theme_head_script() ?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Vaccination Records — HEALTrack</title>

<!-- Google Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<!-- Bootstrap 5 -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<!-- Font Awesome (available per project spec; Bootstrap Icons cover this page's icons) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<!-- Shared admin shell (sidebar, topbar, cards, tabs, table, etc.) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/admin-dashboard.css') ?>">
<!-- Sub-Admin Dashboard styles, reused here for the topbar select buttons -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/subadmin_dashboard.css') ?>">
<!-- Patient Profile styles, reused here for the breadcrumb + profile header card -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/patient_profile.css') ?>">
<!-- Vaccination Records page styles (table, details panel) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/vaccination_records.css') ?>">
<link rel="stylesheet" href="<?= asset_url('/assets/css/view_details_modal.css') ?>">
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

    <!-- ============ BACK LINK ============ -->
    <a href="patient_profile.php?id=<?= urlencode($patient['id']) ?>" class="adm-back-link">
        <i class="bi bi-arrow-left"></i> Back to Patient Profile
    </a>

    <!-- ============ TOP HEADER ============ -->
    <div class="adm-topbar">
        <div>
            <h1><?= htmlspecialchars(t('nav.patient_management')) ?> <span class="ppf-title-sep">/</span> <?= htmlspecialchars(t('page.pm_child.vaccination_records')) ?></h1>
            <nav class="ppf-breadcrumb" aria-label="Breadcrumb">
                <a href="dashboard.php">Dashboard</a>
                <span class="ppf-breadcrumb-sep">&gt;</span>
                <a href="patient_management.php">Patient Management</a>
                <span class="ppf-breadcrumb-sep">&gt;</span>
                <a href="patient_profile.php?id=<?= urlencode($patient['id']) ?>">Patient Profile</a>
                <span class="ppf-breadcrumb-sep">&gt;</span>
                <span class="ppf-breadcrumb-current">Vaccination Records</span>
            </nav>
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

    <!-- ============ PATIENT PROFILE HEADER CARD ============ -->
    <div class="adm-card ppf-profile-card">
        <div class="ppf-identity">
            <h2 class="ppf-name"><?= htmlspecialchars($patient['name']) ?></h2>
            <p class="ppf-meta"><?= htmlspecialchars($patient['gender']) ?> &bull; <?= (int) $patient['age'] ?> years old (<?= htmlspecialchars($patient['birthDate']) ?>)</p>
            <div class="ppf-contact-row"><i class="bi bi-telephone-fill"></i> <?= htmlspecialchars($patient['phone']) ?><span class="ppf-contact-sep"></span><i class="bi bi-envelope-fill"></i> <?= htmlspecialchars($patient['email']) ?></div>
            <div class="ppf-contact-row"><i class="bi bi-geo-alt-fill"></i> <?= htmlspecialchars($patient['address']) ?></div>
        </div>

        <div class="ppf-info-col">
            <div class="ppf-info-item"><span class="ppf-info-label">Blood Type</span><span class="ppf-info-value"><?= htmlspecialchars($patient['bloodType']) ?></span></div>
            <div class="ppf-info-item"><span class="ppf-info-label">Civil Status</span><span class="ppf-info-value"><?= htmlspecialchars($patient['civilStatus']) ?></span></div>
            <div class="ppf-info-item"><span class="ppf-info-label">Occupation</span><span class="ppf-info-value"><?= htmlspecialchars($patient['occupation']) ?></span></div>
        </div>

        <div class="ppf-info-col">
            <div class="ppf-info-item"><span class="ppf-info-label">Registered On</span><span class="ppf-info-value"><?= htmlspecialchars($patient['registeredOn']) ?></span></div>
            <div class="ppf-info-item"><span class="ppf-info-label">Last Visit</span><span class="ppf-info-value"><?= htmlspecialchars($patient['lastVisit']) ?></span></div>
            <div class="ppf-info-item"><span class="ppf-info-label">Primary Physician</span><span class="ppf-info-value"><?= htmlspecialchars($patient['primaryPhysician']) ?></span></div>
        </div>
    </div>

    <!-- ============ PROFILE TABS ============ -->
    <ul class="nav adm-tabs" role="tablist">
        <?php foreach ($profileTabs as $tab): ?>
        <li class="nav-item" role="presentation">
            <?php if ($tab['key'] === 'overview'): ?>
            <a href="patient_profile.php?id=<?= urlencode($patient['id']) ?>" class="nav-link" data-ajax-tab>
                <i class="bi <?= htmlspecialchars($tab['icon']) ?>"></i> <?= htmlspecialchars($tab['label']) ?>
            </a>
            <?php elseif ($tab['key'] === 'medical_records'): ?>
            <a href="medical_history.php?id=<?= urlencode($patient['id']) ?>" class="nav-link" data-ajax-tab>
                <i class="bi <?= htmlspecialchars($tab['icon']) ?>"></i> <?= htmlspecialchars($tab['label']) ?>
            </a>
            <?php elseif ($tab['key'] === 'lab_xray'): ?>
            <a href="patient_laboratory_xray.php?id=<?= urlencode($patient['id']) ?>" class="nav-link" data-ajax-tab>
                <i class="bi <?= htmlspecialchars($tab['icon']) ?>"></i> <?= htmlspecialchars($tab['label']) ?>
            </a>
            <?php elseif ($tab['key'] === 'allergies'): ?>
            <a href="allergies.php?id=<?= urlencode($patient['id']) ?>" class="nav-link" data-ajax-tab>
                <i class="bi <?= htmlspecialchars($tab['icon']) ?>"></i> <?= htmlspecialchars($tab['label']) ?>
            </a>
            <?php elseif ($tab['key'] === 'medications'): ?>
            <a href="medications.php?id=<?= urlencode($patient['id']) ?>" class="nav-link" data-ajax-tab>
                <i class="bi <?= htmlspecialchars($tab['icon']) ?>"></i> <?= htmlspecialchars($tab['label']) ?>
            </a>
            <?php elseif ($tab['key'] === 'documents'): ?>
            <a href="documents.php?id=<?= urlencode($patient['id']) ?>" class="nav-link" data-ajax-tab>
                <i class="bi <?= htmlspecialchars($tab['icon']) ?>"></i> <?= htmlspecialchars($tab['label']) ?>
            </a>
            <?php elseif ($tab['key'] === 'history'): ?>
            <a href="history.php?id=<?= urlencode($patient['id']) ?>" class="nav-link" data-ajax-tab>
                <i class="bi <?= htmlspecialchars($tab['icon']) ?>"></i> <?= htmlspecialchars($tab['label']) ?>
            </a>
            <?php else: ?>
            <button class="nav-link <?= !empty($tab['active']) ? 'active' : '' ?>" type="button" role="tab">
                <i class="bi <?= htmlspecialchars($tab['icon']) ?>"></i> <?= htmlspecialchars($tab['label']) ?>
            </button>
            <?php endif; ?>
        </li>
        <?php endforeach; ?>
    </ul>

    <!-- ============ VACCINATION HISTORY SECTION HEADER ============ -->
    <div class="vrc-section-header">
        <div>
            <h2>Vaccination History</h2>
            <p>View and manage vaccination records of the patient.</p>
        </div>
        <div class="dropdown">
            <button class="vrc-btn-filter dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-funnel"></i> <?= htmlspecialchars($vrcDateRangeLabel) ?></button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="<?= htmlspecialchars(hc_url_with_params(['daterange' => 'all', 'page' => 1])) ?>">All Time</a></li>
                <li><a class="dropdown-item" href="<?= htmlspecialchars(hc_url_with_params(['daterange' => 'today', 'page' => 1])) ?>">Today</a></li>
                <li><a class="dropdown-item" href="<?= htmlspecialchars(hc_url_with_params(['daterange' => 'this_week', 'page' => 1])) ?>">This Week</a></li>
                <li><a class="dropdown-item" href="<?= htmlspecialchars(hc_url_with_params(['daterange' => 'this_month', 'page' => 1])) ?>">This Month</a></li>
            </ul>
        </div>
    </div>

    <!-- ============ VACCINATION HISTORY TABLE ============ -->
    <div class="adm-card vrc-table-card">
        <div class="adm-table-wrap">
            <table class="adm-table vrc-table">
                <thead>
                    <tr>
                        <th>Vaccine Name</th>
                        <th>Vaccine Type</th>
                        <th>Date Administered</th>
                        <th>Dose / Series</th>
                        <th>Manufacturer</th>
                        <th>Given By</th>
                        <th>Next Dose Due</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($vaccinationRecords)): ?>
                    <tr>
                        <td colspan="8" class="adm-table-empty">No vaccination records yet.</td>
                    </tr>
                    <?php endif; ?>
                    <?php foreach ($vaccinationRecords as $vac): $meta = $vaccineTypeMeta[$vac['type']] ?? ['label' => ucfirst($vac['type']), 'icon' => 'bi-shield-fill-check', 'bg' => '#EEF0F3', 'color' => '#6B7280']; ?>
                    <tr>
                        <td>
                            <div class="vrc-name-cell">
                                <span class="vrc-type-icon" style="background:<?= htmlspecialchars($meta['bg']) ?>; color:<?= htmlspecialchars($meta['color']) ?>;">
                                    <i class="bi <?= htmlspecialchars($meta['icon']) ?>"></i>
                                </span>
                                <span class="vrc-strong"><?= htmlspecialchars($vac['name']) ?></span>
                            </div>
                        </td>
                        <td><?= htmlspecialchars($meta['label']) ?></td>
                        <td><?= htmlspecialchars($vac['dateAdministered']) ?></td>
                        <td><?= htmlspecialchars($vac['dose']) ?></td>
                        <td><?= htmlspecialchars($vac['manufacturer']) ?></td>
                        <td>
                            <div class="vrc-provider-cell">
                                <span class="vrc-provider-name"><?= htmlspecialchars($vac['givenBy']) ?></span>
                                <span class="vrc-provider-facility"><?= htmlspecialchars($vac['facility']) ?></span>
                            </div>
                        </td>
                        <td class="vrc-next-dose"><?= $vac['nextDoseDue'] ? htmlspecialchars($vac['nextDoseDue']) : '—' ?></td>
                        <td>
                            <div class="adm-action-btns">
                                <a href="vaccination_record_details.php?id=<?= urlencode($patient['id']) ?>&recordId=<?= urlencode($vac['id']) ?>" class="adm-view-link-btn">View Details</a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="adm-table-footer">
            <span class="adm-showing-text"><?= $vaccinationRecordsTotal > 0 ? 'Showing ' . (($vaccinationRecordsCurrentPage - 1) * $vaccinationRecordsPerPage + 1) . ' to ' . (($vaccinationRecordsCurrentPage - 1) * $vaccinationRecordsPerPage + count($vaccinationRecords)) . ' of ' . $vaccinationRecordsTotal . ' entries' : 'No entries to show' ?></span>
            <?= hc_render_pagination_nav('Vaccination records pages', 'page', $vaccinationRecordsCurrentPage, $vaccinationRecordsTotalPages) ?>
        </div>
    </div>

    <!-- ============ VACCINE RECORD DETAILS (expandable) ============ -->
    <div class="adm-card vrc-details-card" id="vrcDetailsPanel">
        <button type="button" class="vrc-details-toggle" data-bs-toggle="collapse" data-bs-target="#vrcDetailsBody" aria-expanded="true" aria-controls="vrcDetailsBody">
            <span class="vrc-details-title">
                <span class="vrc-type-icon" style="background:<?= htmlspecialchars($selectedMeta['bg']) ?>; color:<?= htmlspecialchars($selectedMeta['color']) ?>;">
                    <i class="bi <?= htmlspecialchars($selectedMeta['icon']) ?>"></i>
                </span>
                <h3>Vaccine Record Details</h3>
            </span>
            <i class="bi bi-chevron-up vrc-chevron"></i>
        </button>

        <div class="collapse show" id="vrcDetailsBody">
            <div class="vrc-details-grid">
                <div class="vrc-details-col">
                    <div class="vrc-detail-item"><span class="vrc-detail-label">Vaccine Name</span><span class="vrc-detail-value"><?= htmlspecialchars($selectedVaccineRecord['name']) ?></span></div>
                    <div class="vrc-detail-item"><span class="vrc-detail-label">Vaccine Type</span><span class="vrc-detail-value"><?= htmlspecialchars($selectedMeta['label']) ?></span></div>
                    <div class="vrc-detail-item"><span class="vrc-detail-label">Date Administered</span><span class="vrc-detail-value"><?= htmlspecialchars($selectedVaccineRecord['dateAdministered']) ?></span></div>
                    <div class="vrc-detail-item"><span class="vrc-detail-label">Dose / Series</span><span class="vrc-detail-value"><?= htmlspecialchars($selectedVaccineRecord['dose']) ?></span></div>
                    <div class="vrc-detail-item"><span class="vrc-detail-label">Manufacturer</span><span class="vrc-detail-value"><?= htmlspecialchars($selectedVaccineRecord['manufacturer']) ?></span></div>
                    <div class="vrc-detail-item"><span class="vrc-detail-label">Lot Number</span><span class="vrc-detail-value"><?= htmlspecialchars($selectedVaccineRecord['lotNumber']) ?></span></div>
                    <div class="vrc-detail-item"><span class="vrc-detail-label">Expiry Date</span><span class="vrc-detail-value"><?= htmlspecialchars($selectedVaccineRecord['expiryDate']) ?></span></div>
                </div>
                <div class="vrc-details-col">
                    <div class="vrc-detail-item"><span class="vrc-detail-label">Given By</span><span class="vrc-detail-value"><?= htmlspecialchars($selectedVaccineRecord['givenBy']) ?></span></div>
                    <div class="vrc-detail-item"><span class="vrc-detail-label">Facility</span><span class="vrc-detail-value"><?= htmlspecialchars($selectedVaccineRecord['facility']) ?></span></div>
                    <div class="vrc-detail-item"><span class="vrc-detail-label">Route</span><span class="vrc-detail-value"><?= htmlspecialchars($selectedVaccineRecord['route']) ?></span></div>
                    <div class="vrc-detail-item"><span class="vrc-detail-label">Site</span><span class="vrc-detail-value"><?= htmlspecialchars($selectedVaccineRecord['site']) ?></span></div>
                    <div class="vrc-detail-item"><span class="vrc-detail-label">Notes</span><span class="vrc-detail-value"><?= htmlspecialchars($selectedVaccineRecord['notes']) ?></span></div>
                </div>
            </div>
        </div>
    </div>

</main>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= asset_url('/assets/js/admin-sidebar-toggle.js') ?>"></script>
<script src="<?= asset_url('/assets/js/theme.js') ?>"></script>
<script src="<?= asset_url('/assets/js/ajax-tab-nav.js') ?>"></script>
</body>
</html>
