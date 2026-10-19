<?php
/**
 * =====================================================================
 * HEALTrack — Health Information System
 * Patient Medications (subadmin/medications.php)
 * ---------------------------------------------------------------------
 * This IS subadmin/patient_profile.php's "Medications" tab — reached
 * by clicking that tab — as its own page rather than an in-page tab
 * pane, since it needs its own summary cards + table + history
 * timeline. Reuses the exact same sidebar/topbar/profile-header/
 * tab-strip shell as patient_profile.php, patient_laboratory_xray.php,
 * medical_records.php, vaccination_records.php, and allergies.php
 * (280px dark-red gradient sidebar, "adm-"/"ppf-" prefixed atoms),
 * including the Edit Profile button staying removed there, for
 * consistency — all six pages render the literal same profile header
 * component.
 *
 * CONNECTED to real Firestore data — see includes/subadmin/medications_firestore.php's
 * header comment. Each row's View button still links to
 * medication_details.php?id=<id> (a page not built yet, out of scope
 * for this pass); the Edit button (active medications only) still
 * isn't wired up.
 *
 * Every profile-tab link (data-ajax-tab) is smooth-swapped by
 * assets/js/ajax-tab-nav.js instead of a real navigation — see that
 * file's own header comment for the full mechanism, including why
 * assets/js/medication-details.js exposes a callable
 * window.initMedicationDetailsModal() rather than only a
 * DOMContentLoaded listener.
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

require_once __DIR__ . '/../includes/subadmin/medications_firestore.php';
// $adminProfile, $dashboardDateLabel, $facilityOptions, $patient, $profileTabs,
// $medicationsSummary, $medications, $medicationsTotal, $medicationHistory

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
?>
<!DOCTYPE html>
<html lang="en"<?= admin_theme_data_attr() ?>>
<head>
<?= admin_theme_head_script() ?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Medications — HEALTrack</title>

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
<!-- Medications page styles (summary cards, table, history timeline) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/medications.css') ?>">
<!-- "am-" (Add Modal) shared modal styling, reused here for the read-only View Details modal -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/add_medicine_modal.css') ?>">
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
            <h1><?= htmlspecialchars(t('nav.patient_management')) ?> <span class="ppf-title-sep">/</span> <?= htmlspecialchars(t('page.pm_child.medications')) ?></h1>
            <nav class="ppf-breadcrumb" aria-label="Breadcrumb">
                <a href="dashboard.php">Dashboard</a>
                <span class="ppf-breadcrumb-sep">&gt;</span>
                <a href="patient_management.php">Patient Management</a>
                <span class="ppf-breadcrumb-sep">&gt;</span>
                <a href="patient_profile.php?id=<?= urlencode($patient['id']) ?>">Patient Profile</a>
                <span class="ppf-breadcrumb-sep">&gt;</span>
                <span class="ppf-breadcrumb-current">Medications</span>
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
            <?php elseif ($tab['key'] === 'vaccination'): ?>
            <a href="vaccination_records.php?id=<?= urlencode($patient['id']) ?>" class="nav-link" data-ajax-tab>
                <i class="bi <?= htmlspecialchars($tab['icon']) ?>"></i> <?= htmlspecialchars($tab['label']) ?>
            </a>
            <?php elseif ($tab['key'] === 'allergies'): ?>
            <a href="allergies.php?id=<?= urlencode($patient['id']) ?>" class="nav-link" data-ajax-tab>
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

    <!-- ============ MEDICATIONS SUMMARY SECTION HEADER ============ -->
    <div class="mdc-section-header">
        <h2>Medications Summary</h2>
        <p>Overview of the patient's medication status.</p>
    </div>

    <!-- ============ MEDICATIONS SUMMARY CARDS ============ -->
    <div class="mdc-summary-row">
        <div class="adm-card mdc-summary-card">
            <div class="mdc-summary-icon" style="background:#E8F0FE; color:#1A73E8;"><i class="bi bi-clipboard2-pulse-fill"></i></div>
            <div class="mdc-summary-text">
                <span class="mdc-summary-label">Active Medications</span>
                <span class="mdc-summary-value"><?= (int) $medicationsSummary['activeMedications'] ?></span>
                <span class="mdc-summary-sub">Currently taking</span>
            </div>
        </div>

        <div class="adm-card mdc-summary-card">
            <div class="mdc-summary-icon" style="background:#E4F7EA; color:#1E8E3E;"><i class="bi bi-file-earmark-medical-fill"></i></div>
            <div class="mdc-summary-text">
                <span class="mdc-summary-label">No Known Allergic Reactions</span>
                <span class="mdc-summary-value"><?= htmlspecialchars($medicationsSummary['noKnownAllergicReactions']) ?></span>
                <span class="mdc-summary-sub">To current medications</span>
            </div>
        </div>

        <div class="adm-card mdc-summary-card">
            <div class="mdc-summary-icon" style="background:#F1E8FD; color:#8B5CF6;"><i class="bi bi-calendar-check-fill"></i></div>
            <div class="mdc-summary-text">
                <span class="mdc-summary-label">Last Updated</span>
                <span class="mdc-summary-value mdc-summary-value-sm"><?= htmlspecialchars($medicationsSummary['lastUpdated']) ?></span>
                <span class="mdc-summary-sub">By <?= htmlspecialchars($medicationsSummary['lastUpdatedBy']) ?></span>
            </div>
        </div>
    </div>

    <!-- ============ CURRENT MEDICATIONS ============ -->
    <div class="mdc-list-header">
        <h3>Current Medications</h3>
        <p>Medications the patient is currently taking.</p>
    </div>

    <div class="adm-card mdc-table-card">
        <div class="adm-table-wrap">
            <table class="adm-table mdc-table">
                <thead>
                    <tr>
                        <th>Medication</th>
                        <th>Dosage</th>
                        <th>Frequency</th>
                        <th>Route</th>
                        <th>Indication / Reason</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Prescribed By</th>
                        <th>Notes</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($currentMedications)): ?>
                    <tr>
                        <td colspan="10" class="adm-table-empty">No current medications recorded.</td>
                    </tr>
                    <?php endif; ?>
                    <?php foreach ($currentMedications as $med): ?>
                    <tr
                        data-med-name="<?= htmlspecialchars($med['name']) ?>"
                        data-med-category="<?= htmlspecialchars($med['category']) ?>"
                        data-med-dosage="<?= htmlspecialchars($med['dosage']) ?>"
                        data-med-frequency="<?= htmlspecialchars($med['frequency']) ?>"
                        data-med-route="<?= htmlspecialchars($med['route']) ?>"
                        data-med-indication="<?= htmlspecialchars($med['indication']) ?>"
                        data-med-start-date="<?= htmlspecialchars($med['startDate']) ?>"
                        data-med-end-date="<?= htmlspecialchars($med['endDate'] ?? '') ?>"
                        data-med-end-date-note="<?= htmlspecialchars($med['endDateNote'] ?? '') ?>"
                        data-med-prescribed-by="<?= htmlspecialchars($med['prescribedBy']) ?>"
                        data-med-status="<?= htmlspecialchars(ucfirst($med['status'])) ?>"
                        data-med-notes="<?= htmlspecialchars($med['notes']) ?>"
                    >
                        <td>
                            <div class="mdc-name-cell">
                                <span class="mdc-strong"><?= htmlspecialchars($med['name']) ?></span>
                                <span class="mdc-category">(<?= htmlspecialchars($med['category']) ?>)</span>
                            </div>
                        </td>
                        <td><?= htmlspecialchars($med['dosage']) ?></td>
                        <td><?= htmlspecialchars($med['frequency']) ?></td>
                        <td><?= htmlspecialchars($med['route']) ?></td>
                        <td><?= htmlspecialchars($med['indication']) ?></td>
                        <td><?= htmlspecialchars($med['startDate']) ?></td>
                        <td>
                            <?php if ($med['endDate']): ?>
                            <?= htmlspecialchars($med['endDate']) ?>
                            <?php if ($med['endDateNote']): ?>
                            <span class="mdc-end-note">(<?= htmlspecialchars($med['endDateNote']) ?>)</span>
                            <?php endif; ?>
                            <?php else: ?>
                            &mdash;
                            <?php endif; ?>
                        </td>
                        <td class="mdc-strong"><?= htmlspecialchars($med['prescribedBy']) ?></td>
                        <td class="mdc-notes-cell"><?= htmlspecialchars($med['notes']) ?></td>
                        <td>
                            <div class="adm-action-btns">
                                <button type="button" class="mdc-view-details-btn med-view-details">View Details</button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="adm-table-footer">
            <span class="adm-showing-text"><?= count($currentMedications) > 0 ? 'Showing ' . count($currentMedications) . ' of ' . count($currentMedications) . ' entries' : 'No entries to show' ?></span>
        </div>
    </div>

    <!-- ============ PAST MEDICATIONS ============ -->
    <div class="mdc-list-header">
        <h3>Past Medications</h3>
        <p>Medications the patient has taken in the past.</p>
    </div>

    <div class="adm-card mdc-table-card">
        <div class="adm-table-wrap">
            <table class="adm-table mdc-table">
                <thead>
                    <tr>
                        <th>Medication</th>
                        <th>Dosage</th>
                        <th>Frequency</th>
                        <th>Route</th>
                        <th>Indication / Reason</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Prescribed By</th>
                        <th>Notes</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pastMedications)): ?>
                    <tr>
                        <td colspan="10" class="adm-table-empty">No past medications recorded.</td>
                    </tr>
                    <?php endif; ?>
                    <?php foreach ($pastMedications as $med): ?>
                    <tr
                        data-med-name="<?= htmlspecialchars($med['name']) ?>"
                        data-med-category="<?= htmlspecialchars($med['category']) ?>"
                        data-med-dosage="<?= htmlspecialchars($med['dosage']) ?>"
                        data-med-frequency="<?= htmlspecialchars($med['frequency']) ?>"
                        data-med-route="<?= htmlspecialchars($med['route']) ?>"
                        data-med-indication="<?= htmlspecialchars($med['indication']) ?>"
                        data-med-start-date="<?= htmlspecialchars($med['startDate']) ?>"
                        data-med-end-date="<?= htmlspecialchars($med['endDate'] ?? '') ?>"
                        data-med-end-date-note="<?= htmlspecialchars($med['endDateNote'] ?? '') ?>"
                        data-med-prescribed-by="<?= htmlspecialchars($med['prescribedBy']) ?>"
                        data-med-status="<?= htmlspecialchars(ucfirst($med['status'])) ?>"
                        data-med-notes="<?= htmlspecialchars($med['notes']) ?>"
                    >
                        <td>
                            <div class="mdc-name-cell">
                                <span class="mdc-strong"><?= htmlspecialchars($med['name']) ?></span>
                                <span class="mdc-category">(<?= htmlspecialchars($med['category']) ?>)</span>
                            </div>
                        </td>
                        <td><?= htmlspecialchars($med['dosage']) ?></td>
                        <td><?= htmlspecialchars($med['frequency']) ?></td>
                        <td><?= htmlspecialchars($med['route']) ?></td>
                        <td><?= htmlspecialchars($med['indication']) ?></td>
                        <td><?= htmlspecialchars($med['startDate']) ?></td>
                        <td>
                            <?php if ($med['endDate']): ?>
                            <?= htmlspecialchars($med['endDate']) ?>
                            <?php if ($med['endDateNote']): ?>
                            <span class="mdc-end-note">(<?= htmlspecialchars($med['endDateNote']) ?>)</span>
                            <?php endif; ?>
                            <?php else: ?>
                            &mdash;
                            <?php endif; ?>
                        </td>
                        <td class="mdc-strong"><?= htmlspecialchars($med['prescribedBy']) ?></td>
                        <td class="mdc-notes-cell"><?= htmlspecialchars($med['notes']) ?></td>
                        <td>
                            <div class="adm-action-btns">
                                <button type="button" class="mdc-view-details-btn med-view-details">View Details</button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="adm-table-footer">
            <span class="adm-showing-text"><?= count($pastMedications) > 0 ? 'Showing ' . count($pastMedications) . ' of ' . count($pastMedications) . ' entries' : 'No entries to show' ?></span>
        </div>
    </div>

    <!-- ============ MEDICATION HISTORY TIMELINE ============ -->
    <div class="adm-card mdc-history-card">
        <div class="mdc-card-header"><i class="bi bi-info-circle-fill"></i> <h3>Medication History</h3></div>
        <p class="mdc-history-sub">A timeline of changes made to the patient's medications.</p>

        <ul class="mdc-timeline">
            <?php foreach ($medicationHistory as $i => $entry): ?>
            <li class="mdc-timeline-item">
                <div class="mdc-timeline-marker">
                    <span class="mdc-timeline-dot mdc-dot-<?= htmlspecialchars($entry['indicatorColor']) ?>"></span>
                    <?php if ($i < count($medicationHistory) - 1): ?>
                    <span class="mdc-timeline-line"></span>
                    <?php endif; ?>
                </div>
                <div class="mdc-timeline-content">
                    <span class="mdc-timeline-date"><?= htmlspecialchars($entry['date']) ?></span>
                    <span class="mdc-timeline-event"><?= htmlspecialchars($entry['event']) ?></span>
                    <span class="mdc-timeline-detail"><?= htmlspecialchars($entry['detail']) ?></span>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <?php require __DIR__ . '/../includes/shared/medication_details_modal.php'; ?>

</main>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= asset_url('/assets/js/admin-sidebar-toggle.js') ?>"></script>
<script src="<?= asset_url('/assets/js/theme.js') ?>"></script>
<script src="<?= asset_url('/assets/js/ajax-tab-nav.js') ?>"></script>
<script src="<?= asset_url('/assets/js/medication-details.js') ?>"></script>
</body>
</html>
