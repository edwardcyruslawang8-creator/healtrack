<?php
/**
 * =====================================================================
 * HEALTrack — Health Information System
 * Patient Allergies (subadmin/allergies.php)
 * ---------------------------------------------------------------------
 * This IS subadmin/patient_profile.php's "Allergies" tab — reached by
 * clicking that tab — as its own page rather than an in-page tab pane,
 * since it needs its own summary cards + table + expandable details
 * panel. Reuses the exact same sidebar/topbar/profile-header/tab-strip
 * shell as patient_profile.php, patient_laboratory_xray.php,
 * medical_records.php, and vaccination_records.php (280px dark-red
 * gradient sidebar, "adm-"/"ppf-" prefixed atoms), including the Edit
 * Profile button staying removed there, for consistency — all five
 * pages render the literal same profile header component.
 *
 * CONNECTED to real Firestore data — see includes/subadmin/allergies_firestore.php's
 * header comment. Each row's View button now passes a real &recordId=
 * to allergy_details.php, so that page shows the actual clicked
 * record. The "Allergy Details" panel below the table on this page is
 * a separate, always-expandable summary of the most recently added
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

require_once __DIR__ . '/../includes/subadmin/allergies_firestore.php';
// $adminProfile, $dashboardDateLabel, $facilityOptions, $patient, $profileTabs,
// $severityMeta, $allergyStatusMeta, $allergySummary, $allergyRecords,
// $allergyRecordsTotal, $selectedAllergy

// Sidebar nav — same Sub-Admin nav list as subadmin/dashboard.php.
// Patient Management stays active (this page is reached from it).
$adminNavItems = [
    ['icon' => 'bi-grid-fill',              'label' => t('nav.dashboard'),                    'href' => 'dashboard.php'],
    ['icon' => 'bi-person-lines-fill',      'label' => t('nav.patient_management'),           'href' => 'patient_management.php', 'active' => true],
    ['icon' => 'bi-capsule',                'label' => t('nav.medicine_vaccine_inventory'), 'href' => 'medicine_vaccine_inventory.php'],
    ['icon' => 'bi-box-seam-fill',          'label' => t('nav.stock_requests'),               'href' => 'stock_requests.php'],
    ['icon' => 'bi-bar-chart-fill',         'label' => t('nav.analytics_reports'),          'href' => 'analytics_reports.php'],
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

$selectedSeverity = $severityMeta[$selectedAllergy['severity']] ?? $severityMeta['mild'];
$selectedStatus = $allergyStatusMeta[$selectedAllergy['status']] ?? $allergyStatusMeta['active'];
?>
<!DOCTYPE html>
<html lang="en"<?= admin_theme_data_attr() ?>>
<head>
<?= admin_theme_head_script() ?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Allergies — HEALTrack</title>

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
<!-- Allergies page styles (summary cards, table, details panel) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/allergies.css') ?>">
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
        <div class="adm-avatar"><i class="bi bi-person-circle"></i></div>
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
            <h1><?= htmlspecialchars(t('nav.patient_management')) ?> <span class="ppf-title-sep">/</span> <?= htmlspecialchars(t('page.pm_child.allergies')) ?></h1>
            <nav class="ppf-breadcrumb" aria-label="Breadcrumb">
                <a href="dashboard.php">Dashboard</a>
                <span class="ppf-breadcrumb-sep">&gt;</span>
                <a href="patient_management.php">Patient Management</a>
                <span class="ppf-breadcrumb-sep">&gt;</span>
                <a href="patient_profile.php?id=<?= urlencode($patient['id']) ?>">Patient Profile</a>
                <span class="ppf-breadcrumb-sep">&gt;</span>
                <span class="ppf-breadcrumb-current">Allergies</span>
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
                        <span class="adm-topbar-avatar"><i class="bi bi-person-fill"></i></span>
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
            <a href="medical_records.php?id=<?= urlencode($patient['id']) ?>" class="nav-link" data-ajax-tab>
                <i class="bi <?= htmlspecialchars($tab['icon']) ?>"></i> <?= htmlspecialchars($tab['label']) ?>
            </a>
            <?php elseif ($tab['key'] === 'lab_xray'): ?>
            <a href="patient_laboratory_xray.php?id=<?= urlencode($patient['id']) ?>" class="nav-link" data-ajax-tab>
                <i class="bi <?= htmlspecialchars($tab['icon']) ?>"></i> <?= htmlspecialchars($tab['label']) ?>
            </a>
            <?php elseif ($tab['key'] === 'vaccination'): ?>
            <a href="vaccination_records.php?id=<?= urlencode($patient['id']) ?>" class="nav-link" data-ajax-tab>
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

    <!-- ============ ALLERGY SUMMARY SECTION HEADER ============ -->
    <div class="alg-section-header">
        <h2>Allergy Summary</h2>
        <p>View and manage patient's allergy information.</p>
    </div>

    <!-- ============ ALLERGY SUMMARY CARDS ============ -->
    <div class="alg-summary-row">
        <div class="adm-card alg-summary-card">
            <div class="alg-summary-icon" style="background:#FCE4E4; color:#D93025;"><i class="bi bi-exclamation-triangle-fill"></i></div>
            <div class="alg-summary-text">
                <span class="alg-summary-label">Total Allergies</span>
                <span class="alg-summary-value"><?= (int) $allergySummary['totalAllergies'] ?></span>
                <span class="alg-summary-sub" style="color:#D93025;">Active allergies</span>
            </div>
        </div>

        <div class="adm-card alg-summary-card">
            <div class="alg-summary-icon" style="background:#FDECDD; color:#E8710A;"><i class="bi bi-exclamation-circle-fill"></i></div>
            <div class="alg-summary-text">
                <span class="alg-summary-label">Severe Allergies</span>
                <span class="alg-summary-value"><?= (int) $allergySummary['severeAllergies'] ?></span>
                <span class="alg-summary-sub" style="color:#E8710A;">Requires caution</span>
            </div>
        </div>

        <div class="adm-card alg-summary-card">
            <div class="alg-summary-icon" style="background:#E8F0FE; color:#1A73E8;"><i class="bi bi-calendar-check-fill"></i></div>
            <div class="alg-summary-text">
                <span class="alg-summary-label">Last Updated</span>
                <span class="alg-summary-value alg-summary-value-sm"><?= htmlspecialchars($allergySummary['lastUpdated']) ?></span>
            </div>
        </div>

        <div class="adm-card alg-summary-card">
            <div class="alg-summary-icon" style="background:#E4F7EA; color:#1E8E3E;"><i class="bi bi-file-earmark-check-fill"></i></div>
            <div class="alg-summary-text">
                <span class="alg-summary-label">Status</span>
                <span class="alg-badge" style="background:#E4F7EA; color:#1E8E3E;"><?= htmlspecialchars($allergySummary['status']) ?></span>
            </div>
        </div>
    </div>

    <!-- ============ ALLERGY LIST TABLE ============ -->
    <div class="alg-list-header"><h3>Allergy List</h3></div>

    <div class="adm-card alg-table-card">
        <div class="adm-table-wrap">
            <table class="adm-table alg-table">
                <thead>
                    <tr>
                        <th>Allergen</th>
                        <th>Type</th>
                        <th>Reaction</th>
                        <th>Severity</th>
                        <th>Onset Date</th>
                        <th>Status</th>
                        <th>Notes</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($allergyRecords)): ?>
                    <tr>
                        <td colspan="8" class="adm-table-empty">No allergy records yet.</td>
                    </tr>
                    <?php endif; ?>
                    <?php foreach ($allergyRecords as $alg): $sev = $severityMeta[$alg['severity']] ?? $severityMeta['mild']; $stat = $allergyStatusMeta[$alg['status']] ?? $allergyStatusMeta['active']; ?>
                    <tr>
                        <td>
                            <div class="alg-name-cell">
                                <span class="alg-type-icon" style="background:<?= htmlspecialchars($alg['iconBg']) ?>; color:<?= htmlspecialchars($alg['iconColor']) ?>;">
                                    <i class="bi <?= htmlspecialchars($alg['icon']) ?>"></i>
                                </span>
                                <div class="alg-name-text">
                                    <span class="alg-strong"><?= htmlspecialchars($alg['allergen']) ?></span>
                                    <?php if ($alg['subtype']): ?>
                                    <span class="alg-subtype">(<?= htmlspecialchars($alg['subtype']) ?>)</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td><?= htmlspecialchars($alg['type']) ?></td>
                        <td><?= htmlspecialchars($alg['reaction']) ?></td>
                        <td><span class="alg-badge" style="background:<?= htmlspecialchars($sev['bg']) ?>; color:<?= htmlspecialchars($sev['color']) ?>;"><?= htmlspecialchars($sev['label']) ?></span></td>
                        <td><?= htmlspecialchars($alg['onsetDate']) ?></td>
                        <td><span class="alg-badge" style="background:<?= htmlspecialchars($stat['bg']) ?>; color:<?= htmlspecialchars($stat['color']) ?>;"><?= htmlspecialchars($stat['label']) ?></span></td>
                        <td class="alg-notes-cell"><?= htmlspecialchars($alg['notes']) ?></td>
                        <td>
                            <div class="adm-action-btns">
                                <a href="allergy_details.php?id=<?= urlencode($patient['id']) ?>&recordId=<?= urlencode($alg['id']) ?>" class="adm-action-btn view" aria-label="View"><i class="bi bi-eye"></i></a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="adm-table-footer">
            <span class="adm-showing-text"><?= $allergyRecordsTotal > 0 ? 'Showing 1 to ' . count($allergyRecords) . ' of ' . $allergyRecordsTotal . ' entries' : 'No entries to show' ?></span>
        </div>
    </div>

    <!-- ============ ALLERGY DETAILS (expandable) ============ -->
    <div class="adm-card alg-details-card" id="algDetailsPanel">
        <button type="button" class="alg-details-toggle" data-bs-toggle="collapse" data-bs-target="#algDetailsBody" aria-expanded="true" aria-controls="algDetailsBody">
            <span class="alg-details-title">
                <span class="alg-type-icon" style="background:<?= htmlspecialchars($selectedAllergy['iconBg']) ?>; color:<?= htmlspecialchars($selectedAllergy['iconColor']) ?>;">
                    <i class="bi bi-shield-fill-check"></i>
                </span>
                <h3>Allergy Details</h3>
            </span>
            <i class="bi bi-chevron-up alg-chevron"></i>
        </button>

        <div class="collapse show" id="algDetailsBody">
            <div class="alg-details-grid">
                <div class="alg-details-col">
                    <div class="alg-field"><span class="alg-field-label">Allergen</span><span class="alg-field-value"><?= htmlspecialchars($selectedAllergy['allergen']) ?></span></div>
                    <div class="alg-field"><span class="alg-field-label">Type</span><span class="alg-field-value"><?= htmlspecialchars($selectedAllergy['type']) ?><?= $selectedAllergy['subtype'] ? ' (' . htmlspecialchars($selectedAllergy['subtype']) . ')' : '' ?></span></div>
                    <div class="alg-field"><span class="alg-field-label">Severity</span><span class="alg-badge" style="background:<?= htmlspecialchars($selectedSeverity['bg']) ?>; color:<?= htmlspecialchars($selectedSeverity['color']) ?>;"><?= htmlspecialchars($selectedSeverity['label']) ?></span></div>
                    <div class="alg-field"><span class="alg-field-label">Status</span><span class="alg-badge" style="background:<?= htmlspecialchars($selectedStatus['bg']) ?>; color:<?= htmlspecialchars($selectedStatus['color']) ?>;"><?= htmlspecialchars($selectedStatus['label']) ?></span></div>
                </div>
                <div class="alg-details-col">
                    <div class="alg-field"><span class="alg-field-label">Reaction</span><span class="alg-field-value"><?= htmlspecialchars($selectedAllergy['reaction']) ?></span></div>
                    <div class="alg-field"><span class="alg-field-label">Onset Date</span><span class="alg-field-value"><?= htmlspecialchars($selectedAllergy['onsetDate']) ?></span></div>
                    <div class="alg-field"><span class="alg-field-label">Last Occurred</span><span class="alg-field-value"><?= htmlspecialchars($selectedAllergy['lastOccurred']) ?></span></div>
                    <div class="alg-field"><span class="alg-field-label">Added On</span><span class="alg-field-value"><?= htmlspecialchars($selectedAllergy['addedOn']) ?></span></div>
                </div>
                <div class="alg-details-col">
                    <div class="alg-field alg-field-stacked"><span class="alg-field-label">Notes</span><span class="alg-field-value"><?= htmlspecialchars($selectedAllergy['notes']) ?></span></div>
                    <div class="alg-field alg-field-stacked"><span class="alg-field-label">Recommendations</span><span class="alg-field-value"><?= htmlspecialchars($selectedAllergy['recommendations']) ?></span></div>
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
