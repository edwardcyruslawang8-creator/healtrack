<?php
/**
 * =====================================================================
 * HEALTrack — Health Information System
 * Lab Test Catalog (medical_technician/lab_test_catalog.php)
 * ---------------------------------------------------------------------
 * Lets a medical technologist manage the real, live list of lab tests
 * the facility offers — add a new one, remove one, and upload a
 * reference template document for any of them (the real paper form,
 * once it's actually arrived at the facility — see
 * includes/firestore/lab_results.php's hc_get_lab_test_template()
 * header comment for what that is/isn't used for). Uploading a
 * template works for EVERY test, including the 6 with their own
 * dedicated, hardcoded result-entry forms (Urinalysis/CD4/Gram
 * Stain/the 3 Serology Rapid tests) — only Delete is locked for those,
 * since removing the catalog entry itself would orphan the literal
 * test-name routing their dedicated pages depend on. See
 * includes/medical_technician/lab_test_catalog_firestore.php's header
 * comment for the data model.
 * =====================================================================
 */
session_start();

require_once __DIR__ . '/../config/constants.php'; // BASE_URL, asset_url()

if (($_SESSION['role'] ?? null) !== 'medical_technician') {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

require_once __DIR__ . '/../includes/medical_technician/lab_test_catalog_firestore.php';
// $staffProfile, $dashboardDateLabel, $catalogByCategory, $catalogTotalCount

$staffNavItems = [
    ['icon' => 'bi-grid-fill',                 'label' => 'Dashboard',                       'href' => 'dashboard.php'],
    ['icon' => 'bi-clipboard2-pulse-fill',      'label' => 'Laboratory',              'href' => 'laboratory_xray.php'],
    ['icon' => 'bi-inboxes-fill',              'label' => 'Laboratory Management', 'href' => 'lab_xray_consultation.php', 'badge' => hc_get_pending_lab_count()],
    ['icon' => 'bi-journal-medical',           'label' => 'Lab Test Catalog',                'href' => 'lab_test_catalog.php', 'active' => true],
    [
        'icon' => 'bi-bar-chart-fill',
        'label' => 'Analytics & Reports',
        'id' => 'mtNavAnalyticsReports',
        'children' => [
            ['label' => 'Analytics', 'href' => 'analytics_reports.php'],
            ['label' => 'My Reports', 'href' => 'patients_report.php'],
        ],
    ],
    ['icon' => 'bi-bell-fill',                 'label' => 'Notifications',                   'href' => 'notifications.php', 'badge' => hc_medtech_unread_notification_count()],
    ['icon' => 'bi-clipboard-data-fill', 'label' => 'Audit Logs', 'href' => 'audit_logs.php'],
    ['icon' => 'bi-gear-fill',                 'label' => 'Profile / Settings',              'href' => 'profile_settings.php'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Lab Test Catalog — HEALTrack</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<link rel="stylesheet" href="<?= asset_url('/assets/css/admin-dashboard.css') ?>">
<link rel="stylesheet" href="<?= asset_url('/assets/css/medical_technician_dashboard.css') ?>">
<link rel="stylesheet" href="<?= asset_url('/assets/css/medical_technician_lab_xray.css') ?>">
<link rel="stylesheet" href="<?= asset_url('/assets/css/medical_history.css') ?>">
<link rel="stylesheet" href="<?= asset_url('/assets/css/hc-dialogs.css') ?>">
<style>
.ltc-badge{ display:inline-flex; align-items:center; font-size:11.5px; font-weight:700; border-radius:6px; padding:3px 10px; white-space:nowrap; }
.ltc-badge.builtin{ background:var(--adm-blue-bg); color:var(--adm-blue); }
.ltc-locked{ color:#C4C8CE; cursor:not-allowed; }
.ltc-template-name{ font-weight:600; color:var(--adm-text); }
.ltc-template-size{ color:var(--adm-text-muted); font-size:12px; margin-left:6px; }
.ltc-template-empty{ color:var(--adm-text-muted); font-size:12.5px; }
.ltc-dropzone{ border:2px dashed #D7DBE0; border-radius:10px; padding:24px; text-align:center; color:var(--adm-text-muted); cursor:pointer; transition:border-color .15s, background .15s; }
.ltc-dropzone:hover, .ltc-dropzone.ltc-dropzone-active{ border-color:var(--adm-red-bright); background:#FFF7F6; }
.ltc-dropzone i{ font-size:28px; color:var(--adm-red-bright); display:block; margin-bottom:8px; }
.ltc-dropzone-filename{ font-weight:600; color:var(--adm-text); margin-top:8px; }
</style>
</head>
<body class="adm-body">

<button class="adm-mobile-menu-btn" id="admSidebarToggle" aria-label="Open menu">
    <i class="bi bi-list"></i>
</button>
<div class="adm-sidebar-backdrop" id="admSidebarBackdrop"></div>

<aside class="adm-sidebar" id="admSidebar">
    <div class="adm-brand">
        <div class="adm-logo-badge"><i class="bi bi-heart-pulse-fill"></i></div>
        <div class="adm-brand-text">
            <h1>HEALTrack</h1>
            <p>Health Information System</p>
        </div>
    </div>

    <hr class="adm-sidebar-divider">

    <div class="adm-profile">
        <div class="adm-avatar"><?php require_once __DIR__ . '/../includes/shared/profile_photo_storage.php'; echo hc_render_sidebar_avatar(); ?></div>
        <div class="adm-profile-text">
            <div class="adm-profile-name"><?= htmlspecialchars($staffProfile['name']) ?></div>
            <div class="adm-profile-role"><?= htmlspecialchars($staffProfile['role']) ?></div>
        </div>
    </div>

    <hr class="adm-sidebar-divider">

    <ul class="adm-nav">
        <?php foreach ($staffNavItems as $item): ?>
        <?php if (!empty($item['children'])): ?>
        <li>
            <a href="#<?= htmlspecialchars($item['id'] ?? 'mtNavAnalyticsReports') ?>" class="adm-nav-link adm-nav-toggle <?= !empty($item['active']) ? 'active' : '' ?> <?= empty($item['open']) ? 'collapsed' : '' ?>" data-bs-toggle="collapse" role="button" aria-expanded="<?= !empty($item['open']) ? 'true' : 'false' ?>" aria-controls="<?= htmlspecialchars($item['id'] ?? 'mtNavAnalyticsReports') ?>">
                <i class="bi <?= htmlspecialchars($item['icon']) ?>"></i>
                <span><?= htmlspecialchars($item['label']) ?></span>
                <i class="bi bi-chevron-down adm-nav-caret"></i>
            </a>
            <div class="collapse <?= !empty($item['open']) ? 'show' : '' ?>" id="<?= htmlspecialchars($item['id'] ?? 'mtNavAnalyticsReports') ?>">
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
                <span class="mt-nav-badge"><?= (int) $item['badge'] ?></span>
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
            <span>Log Out</span>
        </a>
    </div>
</aside>

<main class="adm-main">

    <div class="adm-topbar">
        <div>
            <h1>Lab Test Catalog</h1>
            <p class="adm-page-subtitle">Manage the laboratory tests <?= htmlspecialchars($staffProfile['facility']) ?> offers, and upload each test's real reference form once it arrives.</p>
        </div>
        <div class="adm-topbar-right">
            <button type="button" class="mt-topbar-select-btn" id="btnAddLabTest"><i class="bi bi-plus-lg"></i> Add Laboratory</button>
        </div>
    </div>

    <?php if ($catalogTotalCount === 0): ?>
    <div class="adm-card" style="padding:32px; text-align:center;">
        <p class="adm-table-empty">No laboratory tests on record yet.</p>
    </div>
    <?php endif; ?>

    <?php foreach ($catalogByCategory as $category => $rows): ?>
    <div class="adm-card mhx-section">
        <div class="mhx-section-header">
            <h3><i class="bi bi-flask"></i> <?= htmlspecialchars($category) ?></h3>
        </div>
        <div class="adm-table-wrap">
            <table class="adm-table mhx-table">
                <thead>
                    <tr><th>Test Name</th><th>Reference Template</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                    <tr>
                        <td class="mhx-cond-label">
                            <?= htmlspecialchars($r['testName']) ?>
                            <?php if ($r['isBuiltIn']): ?>
                            <span class="ltc-badge builtin" style="margin-left:6px;">Built-in form</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($r['hasTemplate']): ?>
                            <span class="ltc-template-name"><i class="bi bi-file-earmark-text"></i> <?= htmlspecialchars($r['templateFileName']) ?></span>
                            <span class="ltc-template-size">(<?= htmlspecialchars($r['templateFileSizeLabel']) ?>)</span>
                            <?php else: ?>
                            <span class="ltc-template-empty">No template uploaded yet</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="adm-action-btns">
                                <button type="button" class="adm-action-btn edit" aria-label="<?= $r['hasTemplate'] ? 'Replace Template' : 'Upload Template' ?>" title="<?= $r['hasTemplate'] ? 'Replace reference template' : 'Upload reference template' ?>" data-ltc-upload data-id="<?= htmlspecialchars($r['id']) ?>" data-test-name="<?= htmlspecialchars($r['testName']) ?>"><i class="bi bi-upload"></i></button>
                                <?php if ($r['hasTemplate']): ?>
                                <a href="<?= BASE_URL ?>/api/view-lab-test-template.php?id=<?= urlencode($r['id']) ?>" target="_blank" rel="noopener" class="adm-action-btn view" aria-label="View Template" title="View reference template"><i class="bi bi-eye"></i></a>
                                <button type="button" class="adm-action-btn delete" aria-label="Remove Template" title="Remove reference template" data-ltc-delete-template data-id="<?= htmlspecialchars($r['id']) ?>" data-label="the template for &quot;<?= htmlspecialchars($r['testName']) ?>&quot;"><i class="bi bi-file-earmark-x"></i></button>
                                <?php endif; ?>
                                <?php if ($r['isBuiltIn']): ?>
                                <span class="adm-action-btn ltc-locked" aria-label="Built-in test, can't be removed" title="This test has a built-in result form"><i class="bi bi-trash"></i></span>
                                <?php else: ?>
                                <button type="button" class="adm-action-btn delete" aria-label="Delete" title="Remove this laboratory test" data-ltc-delete data-id="<?= htmlspecialchars($r['id']) ?>" data-label="&quot;<?= htmlspecialchars($r['testName']) ?>&quot;"><i class="bi bi-trash"></i></button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endforeach; ?>

</main>

<?php require_once __DIR__ . '/../includes/medical_technician/lab_test_catalog_modals.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= asset_url('/assets/js/hc-dialogs.js') ?>"></script>
<script src="<?= asset_url('/assets/js/admin-sidebar-toggle.js') ?>"></script>
<script src="<?= asset_url('/assets/js/lab_test_catalog.js') ?>"></script>
</body>
</html>
