<?php
/**
 * =====================================================================
 * HEALTrack — Health Information System
 * Disease Registry (admin/disease_registry.php)
 * ---------------------------------------------------------------------
 * The one real management UI for includes/shared/clinical_classification.php's
 * real "disease_registry" Firestore collection — every condition the
 * GIS/predictive-analytics pipeline recognizes, each with a real
 * classification, GIS eligibility flag, public-visibility flag, and
 * behavioral/environmental/community preventive-measures text. This is
 * disease-level METADATA configuration, never a per-case approval
 * system — every valid consultation/diagnosis is always preserved
 * exactly as recorded (api/complete-consultation.php, untouched by
 * this page). Admin-only — this is a system-wide registry, not scoped
 * to one facility, same as GIS Mapping itself.
 *
 * Reuses the exact same sidebar/topbar shell as every other admin/*.php
 * page (assets/css/admin-dashboard.css).
 * =====================================================================
 */
session_start();

require_once __DIR__ . '/../config/constants.php'; // BASE_URL, asset_url()
require_once __DIR__ . '/../includes/shared/theme_helper.php'; // admin_theme_data_attr(), admin_theme_head_script()

// Only a signed-in admin may view this page.
if (($_SESSION['role'] ?? null) !== 'admin') {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

require_once __DIR__ . '/../includes/admin/disease_registry_firestore.php';
// $adminProfile, $registryRows, $registryByClassification, $classificationMeta,
// $classificationColors, $registryTotalCount, $unclassifiedCount

$adminNavItems = [
    ['icon' => 'bi-grid-fill',           'label' => 'Dashboard',                    'href' => 'admin_dashboard.php'],
    ['icon' => 'bi-person-lines-fill',   'label' => 'Patient Management',           'href' => 'patient_management.php'],
    ['icon' => 'bi-capsule',             'label' => 'Medicine & Vaccine Inventory', 'href' => 'medicine_vaccine_inventory.php'],
    ['icon' => 'bi-building',            'label' => 'Facility Management',          'href' => 'facility_management.php'],
    ['icon' => 'bi-people-fill',         'label' => 'Sub-Admin Management',         'href' => 'subadmin_management.php'],
    ['icon' => 'bi-geo-alt-fill',        'label' => 'GIS Mapping',                  'href' => 'gis_mapping.php'],
    ['icon' => 'bi-virus',               'label' => 'Disease Registry',             'href' => 'disease_registry.php', 'active' => true, 'badge' => $unclassifiedCount],
    ['icon' => 'bi-shield-lock-fill',    'label' => 'Account Monitoring',           'href' => 'account_monitoring.php'],
    ['icon' => 'bi-bell-fill',           'label' => 'Notifications',                'href' => 'notifications.php', 'badge' => hc_admin_unread_notification_count()],
    ['icon' => 'bi-megaphone-fill',      'label' => 'Announcements',                'href' => 'announcements.php'],
    [
        'icon' => 'bi-clipboard-data-fill',
        'label' => 'Audit Logs',
        'children' => [
            ['label' => 'Admin', 'href' => 'audit_logs.php?role=admin'],
            ['label' => 'Sub Admin', 'href' => 'audit_logs.php?role=subadmin'],
            ['label' => 'Doctor', 'href' => 'audit_logs.php?role=doctor'],
            ['label' => 'Medical Staff', 'href' => 'audit_logs.php?role=healthcare_staff'],
            ['label' => 'Medical Technologist', 'href' => 'audit_logs.php?role=medical_technician'],
            ['label' => 'Radiologic Technologist', 'href' => 'audit_logs.php?role=radio_technologist'],
            ['label' => 'Pharmacist', 'href' => 'audit_logs.php?role=pharmacist'],
        ],
    ],
    ['icon' => 'bi-gear-fill',           'label' => 'Profile / Settings',           'href' => 'profile_settings.php'],
];
?>
<!DOCTYPE html>
<html lang="en"<?= admin_theme_data_attr() ?>>
<head>
<?= admin_theme_head_script() ?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Disease Registry — HEALTrack</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

<link rel="stylesheet" href="<?= asset_url('/assets/css/admin-dashboard.css') ?>">
<link rel="stylesheet" href="<?= asset_url('/assets/css/hc-dialogs.css') ?>">
</head>
<body class="adm-body">

<button class="adm-mobile-menu-btn" id="admSidebarToggle" aria-label="Open menu">
    <i class="bi bi-list"></i>
</button>
<div class="adm-sidebar-backdrop" id="admSidebarBackdrop"></div>

<aside class="adm-sidebar" id="admSidebar">

    <div class="adm-brand">
        <div class="adm-logo-badge"><img src="<?= asset_url('/assets/images/main_logo.png') ?>" alt="HEALTrack logo" class="adm-logo-img"></div>
        <div class="adm-brand-text">
            <h1>HEALTrack</h1>
            <p>Health Information System</p>
        </div>
    </div>

    <hr class="adm-sidebar-divider">

    <div class="adm-profile">
        <div class="adm-avatar"><?php require_once __DIR__ . '/../includes/shared/profile_photo_storage.php'; echo hc_render_sidebar_avatar(); ?></div>
        <div class="adm-profile-text">
            <div class="adm-profile-name"><?= htmlspecialchars($adminProfile['name']) ?></div>
            <div class="adm-profile-role"><?= htmlspecialchars($adminProfile['role']) ?></div>
        </div>
    </div>

    <hr class="adm-sidebar-divider">

    <ul class="adm-nav">
        <?php foreach ($adminNavItems as $item): ?>
        <?php if (!empty($item['children'])): ?>
        <li>
            <a href="#admNavAuditLogs" class="adm-nav-link adm-nav-toggle collapsed" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="admNavAuditLogs">
                <i class="bi <?= htmlspecialchars($item['icon']) ?>"></i>
                <span><?= htmlspecialchars($item['label']) ?></span>
                <i class="bi bi-chevron-down adm-nav-caret"></i>
            </a>
            <div class="collapse" id="admNavAuditLogs">
                <ul class="adm-nav-submenu">
                    <?php foreach ($item['children'] as $child): ?>
                    <li><a href="<?= htmlspecialchars($child['href']) ?>" class="adm-nav-sublink"><?= htmlspecialchars($child['label']) ?></a></li>
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
                <span class="adm-nav-badge"><?= (int) $item['badge'] ?></span>
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
            <span>Logout</span>
        </a>
    </div>
</aside>

<main class="adm-main">

    <div class="adm-topbar">
        <div>
            <h1>Disease Registry</h1>
            <p class="adm-page-subtitle">Disease-level metadata only — classification, GIS eligibility, public visibility, and preventive measures. Every valid consultation diagnosis is always preserved; nothing here approves or rejects an individual case.</p>
        </div>
        <div class="adm-topbar-right">
            <div class="adm-topbar-actions">
                <div class="adm-datetime-widget">
                    <span><i class="bi bi-calendar3"></i><span class="adm-dt-date"><?= date('F j, Y') ?></span></span>
                </div>
                <a href="notifications.php" class="adm-bell-btn" aria-label="Notifications">
                    <i class="bi bi-bell-fill"></i>
                    <?php if (hc_admin_unread_notification_count() > 0): ?><span class="adm-badge-count"><?= hc_admin_unread_notification_count() ?></span><?php endif; ?>
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
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/api/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- ============ SUMMARY ROW ============ -->
    <div class="adm-kpi-row" style="display:grid; grid-template-columns:repeat(auto-fit,minmax(190px,1fr)); gap:16px; margin-bottom:22px;">
        <?php foreach ($classificationMeta as $key => $label): $colors = $classificationColors[$key]; ?>
        <div class="adm-card" style="padding:18px;">
            <div style="font-size:12px; font-weight:600; color:<?= htmlspecialchars($colors['color']) ?>; background:<?= htmlspecialchars($colors['bg']) ?>; display:inline-block; padding:4px 10px; border-radius:6px; margin-bottom:8px;"><?= htmlspecialchars($label) ?></div>
            <div style="font-size:26px; font-weight:800;"><?= count($registryByClassification[$key]) ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- ============ REGISTRY TABLE ============ -->
    <div class="adm-card">
        <div class="sad-card-header">
            <h2>All Registry Entries (<?= $registryTotalCount ?>)</h2>
            <button type="button" class="btn btn-sm btn-outline-dark" data-add-entry><i class="bi bi-plus-lg"></i> Add Condition</button>
        </div>
        <div class="adm-table-wrap">
            <table class="adm-table">
                <thead>
                    <tr><th>Name</th><th>Synonyms</th><th>Classification</th><th>GIS Eligible</th><th>Public</th><th>Last Updated</th><th></th></tr>
                </thead>
                <tbody>
                    <?php if (empty($registryRows)): ?>
                    <tr><td colspan="7" class="adm-table-empty">No registry entries yet.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($registryRows as $r): $colors = $classificationColors[$r['classKey']]; ?>
                    <tr data-registry-id="<?= htmlspecialchars($r['id']) ?>">
                        <td><strong><?= htmlspecialchars($r['name']) ?></strong><?php if ($r['isAutoDetected']): ?> <span class="status-badge" style="background:#FDF3DC; color:#B7791F; font-size:10px;">auto-detected</span><?php endif; ?></td>
                        <td style="max-width:240px; font-size:12.5px; color:var(--adm-text-soft);"><?= htmlspecialchars(implode(', ', $r['synonyms'])) ?></td>
                        <td><span class="status-badge" style="background:<?= htmlspecialchars($colors['bg']) ?>; color:<?= htmlspecialchars($colors['color']) ?>;"><?= htmlspecialchars($r['classificationLabel']) ?></span></td>
                        <td><?= $r['gisEligible'] ? 'Yes' : 'No' ?></td>
                        <td><?= $r['publicVisibility'] ? 'Yes' : 'No' ?></td>
                        <td><?= $r['updatedAt'] !== '' ? htmlspecialchars(date('M j, Y', strtotime($r['updatedAt']))) : '—' ?> by <?= htmlspecialchars($r['updatedByName']) ?></td>
                        <td>
                            <button type="button" class="btn btn-sm btn-outline-dark" data-manage-registry
                                data-id="<?= htmlspecialchars($r['id']) ?>"
                                data-name="<?= htmlspecialchars($r['name']) ?>"
                                data-synonyms="<?= htmlspecialchars(implode(', ', $r['synonyms'])) ?>"
                                data-classification="<?= htmlspecialchars($r['classification'] ?? '') ?>"
                                data-gis-eligible="<?= $r['gisEligible'] ? '1' : '0' ?>"
                                data-public-visibility="<?= $r['publicVisibility'] ? '1' : '0' ?>"
                                data-preventable="<?= $r['isPreventable'] ? '1' : '0' ?>"
                                data-behavioral="<?= htmlspecialchars($r['behavioralMeasures']) ?>"
                                data-environmental="<?= htmlspecialchars($r['environmentalMeasures']) ?>"
                                data-community="<?= htmlspecialchars($r['communityMeasures']) ?>">
                                Manage
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</main>

<?php require __DIR__ . '/../includes/admin/manage_disease_registry_modal.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= asset_url('/assets/js/hc-dialogs.js') ?>"></script>
<script src="<?= asset_url('/assets/js/admin-sidebar-toggle.js') ?>"></script>
<script src="<?= asset_url('/assets/js/disease-registry.js') ?>"></script>
</body>
</html>
