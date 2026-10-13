<?php
/**
 * =====================================================================
 * HEALTrack — Health Information System
 * Patient Profile (subadmin/patient_profile.php)
 * ---------------------------------------------------------------------
 * Reached via the "View" (eye) button on subadmin/patient_management.php's
 * table, which links here as patient_profile.php?id=<patient id>.
 * Reuses the exact same sidebar/topbar shell — and the same Sub-Admin
 * nav list — as the rest of subadmin/*.php (280px dark-red gradient
 * sidebar, "adm-" prefixed atoms, assets/css/admin-dashboard.css).
 * Patient Management stays active in the sidebar, since this page is
 * reached from it. Only the page content (profile header, tabs,
 * overview cards) is unique to this page, in assets/css/patient_profile.css.
 *
 * CONNECTED to real Firestore data — see includes/subadmin/patient_profile_firestore.php's
 * header comment. $patient now resolves from the real ?id= querystring
 * (patient_management.php's View button), and the "Overview" tab reads
 * real consultation/condition/lab/x-ray/vaccination data scoped to that
 * patient. The other 7 tabs link out to their own standalone pages
 * (medical_records.php, patient_laboratory_xray.php, etc.), each now
 * also connected — see their own header comments.
 *
 * Every profile-tab link (data-ajax-tab) is smooth-swapped by
 * assets/js/ajax-tab-nav.js instead of a real navigation — see that
 * file's own header comment for the full mechanism (it fetches the
 * target file, swaps just <main>, and loads whatever stylesheet/script
 * that page needs that this one doesn't already have).
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

require_once __DIR__ . '/../includes/subadmin/patient_profile_firestore.php';
// $adminProfile, $dashboardDateLabel, $facilityOptions, $patient,
// $profileTabs, $summary, $activeMedicalConditions, $latestConsultation,
// $labStatusMeta, $recentLabResults, $recentXray, $vaccineStatusMeta,
// $vaccinationHistory

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
<title>Patient Profile — HEALTrack</title>

<!-- Google Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<!-- Bootstrap 5 -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<!-- Font Awesome (available per project spec; Bootstrap Icons cover this page's icons) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<!-- Shared admin shell (sidebar, topbar, cards, tabs, etc.) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/admin-dashboard.css') ?>">
<!-- Sub-Admin Dashboard styles, reused here for the topbar select buttons -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/subadmin_dashboard.css') ?>">
<!-- Patient Profile page styles (breadcrumb, profile header, overview cards) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/patient_profile.css') ?>">
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
    <a href="patient_management.php" class="adm-back-link">
        <i class="bi bi-arrow-left"></i> Back to Patient Management
    </a>

    <!-- ============ TOP HEADER ============ -->
    <div class="adm-topbar">
        <div>
            <h1><?= htmlspecialchars(t('nav.patient_management')) ?> <span class="ppf-title-sep">/</span> <?= htmlspecialchars(t('page.pm_child.patient_profile')) ?></h1>
            <nav class="ppf-breadcrumb" aria-label="Breadcrumb">
                <a href="dashboard.php">Dashboard</a>
                <span class="ppf-breadcrumb-sep">&gt;</span>
                <a href="patient_management.php">Patient Management</a>
                <span class="ppf-breadcrumb-sep">&gt;</span>
                <span class="ppf-breadcrumb-current">Patient Profile</span>
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
            <?php if ($tab['key'] === 'lab_xray'): ?>
            <a href="patient_laboratory_xray.php?id=<?= urlencode($patient['id']) ?>" class="nav-link" data-ajax-tab>
                <i class="bi <?= htmlspecialchars($tab['icon']) ?>"></i> <?= htmlspecialchars($tab['label']) ?>
            </a>
            <?php elseif ($tab['key'] === 'medical_records'): ?>
            <a href="medical_records.php?id=<?= urlencode($patient['id']) ?>" class="nav-link" data-ajax-tab>
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
            <button class="nav-link <?= !empty($tab['active']) ? 'active' : '' ?>"
                    id="tab-<?= htmlspecialchars($tab['key']) ?>-btn"
                    data-bs-toggle="tab"
                    data-bs-target="#tab-<?= htmlspecialchars($tab['key']) ?>"
                    type="button" role="tab">
                <i class="bi <?= htmlspecialchars($tab['icon']) ?>"></i> <?= htmlspecialchars($tab['label']) ?>
            </button>
            <?php endif; ?>
        </li>
        <?php endforeach; ?>
    </ul>

    <div class="tab-content">
        <?php foreach ($profileTabs as $tab): ?>
        <div class="tab-pane fade <?= !empty($tab['active']) ? 'show active' : '' ?>" id="tab-<?= htmlspecialchars($tab['key']) ?>" role="tabpanel">
        <?php if ($tab['key'] === 'overview'): ?>

            <!-- ============ OVERVIEW: ROW 1 ============ -->
            <div class="ppf-row-3">

                <div class="adm-card ppf-ov-card">
                    <div class="ppf-ov-header"><i class="bi bi-clipboard2-fill"></i> <h3>Summary</h3></div>
                    <div class="ppf-summary-list">
                        <div class="ppf-summary-row"><span>Total Consultations</span><strong><?= (int) $summary['totalConsultations'] ?></strong></div>
                        <div class="ppf-summary-row"><span>Active Medical Conditions</span><strong><?= (int) $summary['activeMedicalConditions'] ?></strong></div>
                        <div class="ppf-summary-row"><span>Allergies</span><strong><?= (int) $summary['allergies'] ?></strong></div>
                        <div class="ppf-summary-row"><span>Current Medications</span><strong><?= (int) $summary['currentMedications'] ?></strong></div>
                    </div>
                    <a href="history.php?id=<?= urlencode($patient['id']) ?>" class="ppf-card-link">View Full History <i class="bi bi-arrow-right"></i></a>
                </div>

                <div class="adm-card ppf-ov-card">
                    <div class="ppf-ov-header"><i class="bi bi-heart-pulse-fill"></i> <h3>Active Medical Conditions</h3></div>
                    <?php foreach ($activeMedicalConditions as $c): ?>
                    <div class="ppf-condition-chip"><?= htmlspecialchars($c['label']) ?></div>
                    <div class="ppf-condition-meta">
                        <span>Diagnosed on: <?= htmlspecialchars($c['diagnosedOn']) ?></span>
                        <span>Status: <strong class="ppf-status-active"><?= htmlspecialchars($c['status']) ?></strong></span>
                    </div>
                    <?php endforeach; ?>
                    <a href="medical_records.php?id=<?= urlencode($patient['id']) ?>" class="ppf-card-link">View Medical Records <i class="bi bi-arrow-right"></i></a>
                </div>

                <div class="adm-card ppf-ov-card">
                    <div class="ppf-ov-header"><i class="bi bi-mortarboard-fill"></i> <h3>Latest Consultation</h3></div>
                    <p class="ppf-consult-datetime"><?= htmlspecialchars($latestConsultation['date']) ?> &bull; <?= htmlspecialchars($latestConsultation['time']) ?></p>
                    <p class="ppf-consult-line"><span>Chief Complaint:</span><br><?= htmlspecialchars($latestConsultation['chiefComplaint']) ?></p>
                    <p class="ppf-consult-line"><span>Diagnosis:</span><br><?= htmlspecialchars($latestConsultation['diagnosis']) ?></p>
                    <p class="ppf-consult-line"><span>Physician:</span> <?= htmlspecialchars($latestConsultation['physician']) ?></p>
                    <?php if ($latestConsultation['vitals'] !== null): ?>
                    <p class="ppf-consult-line"><span>Vitals:</span><br><?= htmlspecialchars($latestConsultation['vitals']) ?></p>
                    <?php endif; ?>
                    <a href="history.php?id=<?= urlencode($patient['id']) ?>&hisType=Medical+Record" class="ppf-card-link">View Consultation Log <i class="bi bi-arrow-right"></i></a>
                </div>

            </div>

            <!-- ============ OVERVIEW: ROW 2 ============ -->
            <div class="ppf-row-3">

                <div class="adm-card ppf-ov-card">
                    <div class="ppf-ov-header with-action">
                        <div><i class="bi bi-eyedropper"></i> <h3>Recent Laboratory Results</h3></div>
                        <a href="patient_laboratory_xray.php?id=<?= urlencode($patient['id']) ?>" class="ppf-view-all">View All</a>
                    </div>
                    <div class="ppf-list">
                        <?php foreach ($recentLabResults as $lab): $status = $labStatusMeta[$lab['status']] ?? $labStatusMeta['normal']; ?>
                        <div class="ppf-list-row">
                            <div>
                                <div class="ppf-list-title"><?= htmlspecialchars($lab['name']) ?></div>
                                <div class="ppf-list-sub"><?= htmlspecialchars($lab['date']) ?></div>
                            </div>
                            <span class="ppf-badge" style="background:<?= htmlspecialchars($status['bg']) ?>; color:<?= htmlspecialchars($status['color']) ?>;"><?= htmlspecialchars($status['label']) ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <a href="patient_laboratory_xray.php?id=<?= urlencode($patient['id']) ?>" class="ppf-card-link">Go to Laboratory &amp; X-ray <i class="bi bi-arrow-right"></i></a>
                </div>

                <div class="adm-card ppf-ov-card">
                    <div class="ppf-ov-header with-action">
                        <div><i class="bi bi-lungs-fill"></i> <h3>Recent X-ray Results</h3></div>
                        <a href="patient_laboratory_xray.php?id=<?= urlencode($patient['id']) ?>" class="ppf-view-all">View All</a>
                    </div>
                    <div class="ppf-xray-row">
                        <div class="ppf-xray-thumb"><i class="bi bi-lungs"></i></div>
                        <div>
                            <div class="ppf-list-title"><?= htmlspecialchars($recentXray['name']) ?></div>
                            <div class="ppf-list-sub"><?= htmlspecialchars($recentXray['date']) ?></div>
                            <?php $xrayStatus = $labStatusMeta[$recentXray['status']] ?? $labStatusMeta['normal']; ?>
                            <span class="ppf-badge" style="background:<?= htmlspecialchars($xrayStatus['bg']) ?>; color:<?= htmlspecialchars($xrayStatus['color']) ?>;"><?= htmlspecialchars($xrayStatus['label']) ?></span>
                        </div>
                    </div>
                    <p class="ppf-consult-line"><span>Findings:</span><br><?= htmlspecialchars($recentXray['findings']) ?></p>
                    <a href="patient_laboratory_xray.php?id=<?= urlencode($patient['id']) ?>" class="ppf-card-link">Go to Laboratory &amp; X-ray <i class="bi bi-arrow-right"></i></a>
                </div>

                <div class="adm-card ppf-ov-card">
                    <div class="ppf-ov-header with-action">
                        <div><i class="bi bi-clipboard2-pulse-fill"></i> <h3>Vaccination History</h3></div>
                        <a href="vaccination_records.php?id=<?= urlencode($patient['id']) ?>" class="ppf-view-all">View All</a>
                    </div>
                    <div class="ppf-list">
                        <?php foreach ($vaccinationHistory as $v): $status = $vaccineStatusMeta[$v['status']] ?? $vaccineStatusMeta['completed']; ?>
                        <div class="ppf-list-row">
                            <div>
                                <div class="ppf-list-title"><?= htmlspecialchars($v['name']) ?></div>
                                <div class="ppf-list-sub"><?= htmlspecialchars($v['date']) ?></div>
                            </div>
                            <span class="ppf-badge" style="background:<?= htmlspecialchars($status['bg']) ?>; color:<?= htmlspecialchars($status['color']) ?>;"><?= htmlspecialchars($status['label']) ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <a href="vaccination_records.php?id=<?= urlencode($patient['id']) ?>" class="ppf-card-link">Go to Vaccination Records <i class="bi bi-arrow-right"></i></a>
                </div>

            </div>

        <?php else: ?>
            <div class="ppf-tab-placeholder">
                <i class="bi bi-cone-striped"></i>
                <p>The "<?= htmlspecialchars($tab['label']) ?>" section isn't built yet — check back soon.</p>
            </div>
        <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

</main>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= asset_url('/assets/js/admin-sidebar-toggle.js') ?>"></script>
<script src="<?= asset_url('/assets/js/theme.js') ?>"></script>
<script src="<?= asset_url('/assets/js/ajax-tab-nav.js') ?>"></script>
</body>
</html>
