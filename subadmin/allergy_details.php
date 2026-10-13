<?php
/**
 * =====================================================================
 * HEALTrack — Health Information System
 * Allergy Details (subadmin/allergy_details.php)
 * ---------------------------------------------------------------------
 * Reached via the "View" (eye) button on subadmin/allergies.php's
 * Allergy List table. Like subadmin/laboratory_result_details.php and
 * subadmin/vaccination_record_details.php, this is a drill-down detail
 * view, not one of the patient profile's own tabs — so it reuses the
 * sidebar/topbar/profile-header shell but has NO tab strip, and its
 * "Back" control sits inline with the page title instead of as its
 * own line above the topbar. Reuses assets/css/admin-dashboard.css
 * and assets/css/patient_profile.css for the shell + profile card.
 *
 * CONNECTED to real Firestore data — see
 * includes/subadmin/allergy_details_firestore.php's header comment. Every "View"
 * button on allergies.php now links here with a real &recordId=, so
 * this shows the actual clicked record.
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

require_once __DIR__ . '/../includes/subadmin/allergy_details_firestore.php';
// $adminProfile, $dashboardDateLabel, $facilityOptions, $patient,
// $severityMeta, $allergyStatusMeta, $allergyRecords, $allergyRecordsTotal,
// $selectedAllergy

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
$typeLabel = $selectedAllergy['type'] . ($selectedAllergy['subtype'] ? ' (' . $selectedAllergy['subtype'] . ')' : '');
?>
<!DOCTYPE html>
<html lang="en"<?= admin_theme_data_attr() ?>>
<head>
<?= admin_theme_head_script() ?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Allergy Details — HEALTrack</title>

<!-- Google Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<!-- Bootstrap 5 -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<!-- Font Awesome (available per project spec; Bootstrap Icons cover this page's icons) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<!-- Shared admin shell (sidebar, topbar, cards, table, etc.) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/admin-dashboard.css') ?>">
<!-- Sub-Admin Dashboard styles, reused here for the topbar select buttons -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/subadmin_dashboard.css') ?>">
<!-- Patient Profile styles, reused here for the breadcrumb + profile header card -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/patient_profile.css') ?>">
<!-- Allergy Details page styles (back+title row, overview card, detailed info, history) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/allergy_details.css') ?>">
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

    <!-- ============ TOP HEADER ============ -->
    <div class="adm-topbar">
        <div>
            <div class="ald-title-row">
                <a href="allergies.php?id=<?= urlencode($patient['id']) ?>" class="ald-back-btn">
                    <i class="bi bi-arrow-left"></i> Back
                </a>
                <h1><?= htmlspecialchars(t('page.allergy_details.title')) ?></h1>
            </div>
            <nav class="ppf-breadcrumb" aria-label="Breadcrumb">
                <a href="dashboard.php">Dashboard</a>
                <span class="ppf-breadcrumb-sep">&gt;</span>
                <a href="patient_management.php">Patient Management</a>
                <span class="ppf-breadcrumb-sep">&gt;</span>
                <a href="patient_profile.php?id=<?= urlencode($patient['id']) ?>">Patient Profile</a>
                <span class="ppf-breadcrumb-sep">&gt;</span>
                <a href="allergies.php?id=<?= urlencode($patient['id']) ?>">Allergies</a>
                <span class="ppf-breadcrumb-sep">&gt;</span>
                <span class="ppf-breadcrumb-current">Allergy Details</span>
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

    <!-- ============ ALLERGY OVERVIEW CARD ============ -->
    <div class="adm-card ald-overview-card">
        <div class="ald-card-header"><i class="bi bi-shield-fill-check"></i> <h3>Allergy Overview</h3></div>

        <div class="ald-overview-grid">
            <div class="ald-overview-identity">
                <div class="ald-overview-icon" style="background:<?= htmlspecialchars($selectedAllergy['iconBg']) ?>; color:<?= htmlspecialchars($selectedAllergy['iconColor']) ?>;">
                    <i class="bi <?= htmlspecialchars($selectedAllergy['icon']) ?>"></i>
                </div>
                <div class="ald-overview-text">
                    <div class="ald-overview-name-row">
                        <h2><?= htmlspecialchars($selectedAllergy['allergen']) ?></h2>
                        <span class="ald-badge" style="background:<?= htmlspecialchars($selectedSeverity['bg']) ?>; color:<?= htmlspecialchars($selectedSeverity['color']) ?>;"><?= htmlspecialchars($selectedSeverity['label']) ?></span>
                    </div>
                    <p class="ald-overview-type"><?= htmlspecialchars($typeLabel) ?></p>
                    <p class="ald-overview-summary"><?= htmlspecialchars($selectedAllergy['summary']) ?></p>
                </div>
            </div>

            <div class="ald-overview-col">
                <div class="ald-field"><span class="ald-field-label">Status</span><span class="ald-badge" style="background:<?= htmlspecialchars($selectedStatus['bg']) ?>; color:<?= htmlspecialchars($selectedStatus['color']) ?>;"><?= htmlspecialchars($selectedStatus['label']) ?></span></div>
                <div class="ald-field"><span class="ald-field-label">Onset Date</span><span class="ald-field-value"><?= htmlspecialchars($selectedAllergy['onsetDate']) ?></span></div>
                <div class="ald-field"><span class="ald-field-label">Last Occurred</span><span class="ald-field-value"><?= htmlspecialchars($selectedAllergy['lastOccurred']) ?></span></div>
            </div>

            <div class="ald-overview-col">
                <div class="ald-field ald-field-stacked"><span class="ald-field-label">Added On</span><span class="ald-field-value"><?= htmlspecialchars($selectedAllergy['addedOn']) ?></span></div>
                <div class="ald-field ald-field-stacked"><span class="ald-field-label">Notes</span><span class="ald-field-value"><?= htmlspecialchars($selectedAllergy['notes']) ?></span></div>
                <div class="ald-field ald-field-stacked"><span class="ald-field-label">Recommendations</span><span class="ald-field-value"><?= htmlspecialchars($selectedAllergy['recommendations']) ?></span></div>
            </div>
        </div>
    </div>

    <!-- ============ DETAILED INFORMATION ============ -->
    <div class="adm-card ald-detail-card">
        <div class="ald-card-header"><i class="bi bi-info-circle-fill"></i> <h3>Detailed Information</h3></div>

        <div class="ald-detail-grid">
            <div class="ald-detail-fields">
                <div class="ald-field"><span class="ald-field-label">Allergen</span><span class="ald-field-value"><?= htmlspecialchars($selectedAllergy['allergen']) ?></span></div>
                <div class="ald-field"><span class="ald-field-label">Type</span><span class="ald-field-value"><?= htmlspecialchars($typeLabel) ?></span></div>
                <div class="ald-field"><span class="ald-field-label">Severity</span><span class="ald-badge" style="background:<?= htmlspecialchars($selectedSeverity['bg']) ?>; color:<?= htmlspecialchars($selectedSeverity['color']) ?>;"><?= htmlspecialchars($selectedSeverity['label']) ?></span></div>
                <div class="ald-field"><span class="ald-field-label">Reaction</span><span class="ald-field-value"><?= htmlspecialchars($selectedAllergy['reaction']) ?></span></div>
                <div class="ald-field ald-field-stacked"><span class="ald-field-label">Description</span><span class="ald-field-value"><?= htmlspecialchars($selectedAllergy['description']) ?></span></div>
                <div class="ald-field"><span class="ald-field-label">Onset Date</span><span class="ald-field-value"><?= htmlspecialchars($selectedAllergy['onsetDate']) ?></span></div>
                <div class="ald-field"><span class="ald-field-label">Last Occurred</span><span class="ald-field-value"><?= htmlspecialchars($selectedAllergy['lastOccurred']) ?></span></div>
                <div class="ald-field"><span class="ald-field-label">Status</span><span class="ald-badge" style="background:<?= htmlspecialchars($selectedStatus['bg']) ?>; color:<?= htmlspecialchars($selectedStatus['color']) ?>;"><?= htmlspecialchars($selectedStatus['label']) ?></span></div>
            </div>

            <div class="ald-symptoms-panel">
                <h4>Common Symptoms</h4>
                <ul>
                    <?php foreach ($selectedAllergy['symptoms'] as $symptom): ?>
                    <li><?= htmlspecialchars($symptom) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>

    <!-- ============ ALLERGY HISTORY ============ -->
    <div class="adm-card ald-history-card">
        <div class="ald-card-header"><i class="bi bi-clock-history"></i> <h3>Allergy History</h3></div>

        <div class="adm-table-wrap">
            <table class="adm-table ald-history-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Event</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($selectedAllergy['history'])): ?>
                    <tr>
                        <td colspan="3" class="adm-table-empty">No history recorded yet.</td>
                    </tr>
                    <?php endif; ?>
                    <?php foreach ($selectedAllergy['history'] as $entry): ?>
                    <tr>
                        <td class="ald-strong"><?= htmlspecialchars($entry['date']) ?></td>
                        <td class="ald-event-cell"><?= htmlspecialchars($entry['event']) ?></td>
                        <td><?= htmlspecialchars($entry['details']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</main>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= asset_url('/assets/js/admin-sidebar-toggle.js') ?>"></script>
<script src="<?= asset_url('/assets/js/theme.js') ?>"></script>
</body>
</html>
