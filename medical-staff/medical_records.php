<?php
/**
 * =====================================================================
 * HEALTrack — Health Information System
 * Patient Medical Records (medical-staff/medical_records.php)
 * ---------------------------------------------------------------------
 * This IS medical-staff/patient_profile.php's "Medical Records" tab —
 * reached by clicking that tab (or the "View Medical Records" link on
 * the Overview tab's Active Medical Conditions card) — as its own page
 * rather than an in-page tab pane, since it needs its own category
 * panel + table. Reuses the exact same sidebar/topbar/profile-header/
 * tab-strip shell as patient_profile.php and patient_laboratory_xray.php
 * (280px dark-red gradient sidebar, "adm-"/"ppf-" prefixed atoms,
 * "mst-" nav badge/topbar select buttons), including the Edit Profile
 * button staying removed there, for consistency — all pages sharing
 * this profile header render the literal same component. Modeled on
 * subadmin/medical_records.php; reuses assets/css/medical_records.css
 * directly since it's visually identical.
 *
 * The "Documents" tab has no medical-staff page yet, so it stays an
 * inert placeholder button here (unlike the other tabs, which now all
 * link to real pages) — see medical-staff/patient_profile.php's header
 * comment.
 *
 * CONNECTED to real Firestore data — see includes/medical-staff/medical_records_medstaff_firestore.php's
 * header comment. The category panel is now a set of real filter links
 * (?category=). The Filter button and each row's View/Download buttons
 * are still decorative.
 * =====================================================================
 */
session_start();

require_once __DIR__ . '/../config/constants.php'; // BASE_URL, asset_url()

// Only a signed-in medical staff member may view this page.
if (($_SESSION['role'] ?? null) !== 'healthcare_staff') {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

require_once __DIR__ . '/../includes/medical-staff/medical_records_medstaff_firestore.php';
// $staffProfile, $dashboardDateLabel, $facilityOptions, $patient, $profileTabs,
// $recordTypeMeta, $recordCategories, $medicalRecords, $medicalRecordsTotal,
// $medicalRecordsPerPage, $medicalRecordsCurrentPage, $medicalRecordsTotalPages

// Sidebar nav — same Medical Staff nav list as medical-staff/dashboard.php.
// Patient Management stays active (this page is reached from it).
$staffNavItems = [
    ['icon' => 'bi-grid-fill',            'label' => 'Dashboard',          'href' => 'dashboard.php'],
    ['icon' => 'bi-person-lines-fill',    'label' => 'Patient Management', 'href' => 'patient_management.php', 'active' => true],
    ['icon' => 'bi-calendar2-heart-fill', 'label' => 'Consultations',      'href' => 'consultations.php'],
    ['icon' => 'bi-bar-chart-fill',      'label' => 'Analytics & Reports', 'href' => 'analytics_reports.php'],
    ['icon' => 'bi-bell-fill',            'label' => 'Notifications',      'href' => 'notifications.php', 'badge' => hc_medstaff_unread_notification_count()],
    ['icon' => 'bi-clipboard-data-fill', 'label' => 'Audit Logs', 'href' => 'audit_logs.php'],
    ['icon' => 'bi-gear-fill',            'label' => 'Profile / Settings', 'href' => 'profile_settings.php'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Medical Records — HEALTrack</title>

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
<!-- Medical Staff Dashboard styles, reused here for the nav badge + topbar select buttons -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/medical_staff_dashboard.css') ?>">
<!-- Patient Profile styles, reused here for the breadcrumb + profile header card -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/patient_profile.css') ?>">
<!-- Medical Records page styles (category panel, records table) — shared verbatim with subadmin/medical_records.php -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/medical_records.css') ?>">
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
            <p>Health Information System</p>
        </div>
    </div>

    <hr class="adm-sidebar-divider">

    <!-- Medical staff profile -->
    <div class="adm-profile">
        <div class="adm-avatar"><?php require_once __DIR__ . '/../includes/shared/profile_photo_storage.php'; echo hc_render_sidebar_avatar(); ?></div>
        <div class="adm-profile-text">
            <div class="adm-profile-name"><?= htmlspecialchars($staffProfile['name']) ?></div>
            <div class="adm-profile-role"><?= htmlspecialchars($staffProfile['role']) ?></div>
        </div>
    </div>

    <hr class="adm-sidebar-divider">

    <!-- Navigation -->
    <ul class="adm-nav">
        <?php foreach ($staffNavItems as $item): ?>
        <li>
            <a href="<?= htmlspecialchars($item['href']) ?>" class="adm-nav-link <?= !empty($item['active']) ? 'active' : '' ?>">
                <i class="bi <?= htmlspecialchars($item['icon']) ?>"></i>
                <span><?= htmlspecialchars($item['label']) ?></span>
                <?php if (!empty($item['badge'])): ?>
                <span class="mst-nav-badge"><?= (int) $item['badge'] ?></span>
                <?php endif; ?>
            </a>
        </li>
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
            <h1>Patient Management <span class="ppf-title-sep">/</span> Medical Records</h1>
            <nav class="ppf-breadcrumb" aria-label="Breadcrumb">
                <a href="dashboard.php">Dashboard</a>
                <span class="ppf-breadcrumb-sep">&gt;</span>
                <a href="patient_management.php">Patient Management</a>
                <span class="ppf-breadcrumb-sep">&gt;</span>
                <a href="patient_profile.php?id=<?= urlencode($patient['id']) ?>">Patient Profile</a>
                <span class="ppf-breadcrumb-sep">&gt;</span>
                <span class="ppf-breadcrumb-current">Medical Records</span>
            </nav>
        </div>

        <div class="adm-topbar-right">
            <div class="adm-topbar-actions">
                <!-- Static, same as subadmin/dashboard.php's date badge — not a
                     real filter (nothing on this page re-fetches by date), so
                     it's shown as a plain label rather than a dropdown that
                     would silently do nothing when clicked. -->
                <div class="mst-topbar-select-btn">
                    <i class="bi bi-calendar3"></i> <?= htmlspecialchars($dashboardDateLabel) ?>
                </div>

                <!-- Fixed — shows only the facility this staff member is
                     actually assigned to (their real users/{uid} doc's "facility"
                     field); not a switcher, since a staff member can't reassign
                     themselves. -->
                <div class="mst-topbar-select-btn">
                    <i class="bi bi-building"></i> <?= htmlspecialchars($staffProfile['facility']) ?>
                </div>

                <a href="notifications.php" class="adm-bell-btn" aria-label="Notifications">
                    <i class="bi bi-bell-fill"></i>
                    <?php if (hc_medstaff_unread_notification_count() > 0): ?><span class="adm-badge-count"><?= hc_medstaff_unread_notification_count() ?></span><?php endif; ?>
                </a>

                <div class="dropdown">
                    <button class="adm-profile-dropdown dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="adm-topbar-avatar"><?php require_once __DIR__ . '/../includes/shared/profile_photo_storage.php'; echo hc_render_sidebar_avatar(); ?></span>
                        <span>
                            <span class="d-block adm-topbar-name"><?= htmlspecialchars($staffProfile['name']) ?></span>
                            <span class="d-block adm-topbar-role"><?= htmlspecialchars($staffProfile['role']) ?></span>
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

    <!-- ============ PROFILE TABS ============
         "Documents" has no medical-staff page yet, so it stays an inert
         placeholder button (else branch) — every other tab now links to
         a real page. -->
    <ul class="nav adm-tabs" role="tablist">
        <?php foreach ($profileTabs as $tab): ?>
        <li class="nav-item" role="presentation">
            <?php if ($tab['key'] === 'overview'): ?>
            <a href="patient_profile.php?id=<?= urlencode($patient['id']) ?>" class="nav-link">
                <i class="bi <?= htmlspecialchars($tab['icon']) ?>"></i> <?= htmlspecialchars($tab['label']) ?>
            </a>
            <?php elseif ($tab['key'] === 'lab_xray'): ?>
            <a href="patient_laboratory_xray.php?id=<?= urlencode($patient['id']) ?>" class="nav-link">
                <i class="bi <?= htmlspecialchars($tab['icon']) ?>"></i> <?= htmlspecialchars($tab['label']) ?>
            </a>
            <?php elseif ($tab['key'] === 'vaccination'): ?>
            <a href="vaccination_records.php?id=<?= urlencode($patient['id']) ?>" class="nav-link">
                <i class="bi <?= htmlspecialchars($tab['icon']) ?>"></i> <?= htmlspecialchars($tab['label']) ?>
            </a>
            <?php elseif ($tab['key'] === 'allergies'): ?>
            <a href="allergies.php?id=<?= urlencode($patient['id']) ?>" class="nav-link">
                <i class="bi <?= htmlspecialchars($tab['icon']) ?>"></i> <?= htmlspecialchars($tab['label']) ?>
            </a>
            <?php elseif ($tab['key'] === 'medications'): ?>
            <a href="medications.php?id=<?= urlencode($patient['id']) ?>" class="nav-link">
                <i class="bi <?= htmlspecialchars($tab['icon']) ?>"></i> <?= htmlspecialchars($tab['label']) ?>
            </a>
            <?php elseif ($tab['key'] === 'documents'): ?>
            <a href="documents.php?id=<?= urlencode($patient['id']) ?>" class="nav-link">
                <i class="bi <?= htmlspecialchars($tab['icon']) ?>"></i> <?= htmlspecialchars($tab['label']) ?>
            </a>
            <?php elseif ($tab['key'] === 'history'): ?>
            <a href="history.php?id=<?= urlencode($patient['id']) ?>" class="nav-link">
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

    <!-- ============ MEDICAL RECORDS SECTION HEADER ============ -->
    <div class="mrc-section-header">
        <div>
            <h2>Medical Records</h2>
            <p>View and manage all medical records of the patient.</p>
        </div>
        <div class="dropdown">
            <button class="mrc-btn-filter dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-funnel"></i> <?= htmlspecialchars($mrcDateRangeLabel) ?></button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="<?= htmlspecialchars(hc_url_with_params(['daterange' => 'all', 'page' => 1])) ?>">All Time</a></li>
                <li><a class="dropdown-item" href="<?= htmlspecialchars(hc_url_with_params(['daterange' => 'today', 'page' => 1])) ?>">Today</a></li>
                <li><a class="dropdown-item" href="<?= htmlspecialchars(hc_url_with_params(['daterange' => 'this_week', 'page' => 1])) ?>">This Week</a></li>
                <li><a class="dropdown-item" href="<?= htmlspecialchars(hc_url_with_params(['daterange' => 'this_month', 'page' => 1])) ?>">This Month</a></li>
            </ul>
        </div>
    </div>

    <!-- ============ CATEGORY PANEL + RECORDS TABLE ============ -->
    <div class="mrc-layout">

        <!-- Record Categories -->
        <div class="adm-card mrc-categories-card">
            <h3>Record Categories</h3>
            <ul class="mrc-category-list">
                <?php foreach ($recordCategories as $cat): ?>
                <li>
                    <a href="<?= htmlspecialchars(hc_url_with_params(['category' => $cat['key'], 'page' => 1])) ?>" class="mrc-category-item <?= !empty($cat['active']) ? 'active' : '' ?>">
                        <span><?= htmlspecialchars($cat['label']) ?></span>
                        <span class="mrc-category-count"><?= (int) $cat['count'] ?></span>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <!-- Medical Records Table -->
        <div class="adm-card mrc-table-card">
            <div class="adm-table-wrap">
                <table class="adm-table mrc-table">
                    <thead>
                        <tr>
                            <th>Record Type</th>
                            <th>Title / Description</th>
                            <th>Date</th>
                            <th>Provider / Facility</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($medicalRecords)): ?>
                        <tr>
                            <td colspan="5" class="adm-table-empty">No medical records yet.</td>
                        </tr>
                        <?php endif; ?>
                        <?php foreach ($medicalRecords as $rec): $meta = $recordTypeMeta[$rec['type']] ?? ['label' => ucfirst($rec['type']), 'icon' => 'bi-file-earmark-text-fill', 'bg' => '#EEF0F3', 'color' => '#6B7280']; ?>
                        <tr>
                            <td>
                                <div class="mrc-type-cell">
                                    <span class="mrc-type-icon" style="background:<?= htmlspecialchars($meta['bg']) ?>; color:<?= htmlspecialchars($meta['color']) ?>;">
                                        <i class="bi <?= htmlspecialchars($meta['icon']) ?>"></i>
                                    </span>
                                    <span><?= htmlspecialchars($meta['label']) ?></span>
                                </div>
                            </td>
                            <td class="mrc-strong"><?= htmlspecialchars($rec['title']) ?></td>
                            <td class="mrc-datetime"><?= htmlspecialchars($rec['date']) ?> <span class="mrc-dot"></span> <?= htmlspecialchars($rec['time']) ?></td>
                            <td>
                                <div class="mrc-provider-cell">
                                    <?php if ($rec['provider']): ?>
                                    <span class="mrc-provider-name"><?= htmlspecialchars($rec['provider']) ?></span>
                                    <?php endif; ?>
                                    <span class="mrc-provider-facility"><?= htmlspecialchars($rec['facility']) ?></span>
                                </div>
                            </td>
                            <td>
                                <div class="adm-action-btns">
                                    <button type="button" class="adm-action-btn view" aria-label="View"><i class="bi bi-eye"></i></button>
                                    <button type="button" class="adm-action-btn mrc-download-btn" aria-label="Download"><i class="bi bi-download"></i></button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="adm-table-footer">
                <span class="adm-showing-text"><?= $medicalRecordsTotal > 0 ? 'Showing ' . (($medicalRecordsCurrentPage - 1) * $medicalRecordsPerPage + 1) . ' to ' . (($medicalRecordsCurrentPage - 1) * $medicalRecordsPerPage + count($medicalRecords)) . ' of ' . $medicalRecordsTotal . ' entries' : 'No entries to show' ?></span>
                <?= hc_render_pagination_nav('Medical records pages', 'page', $medicalRecordsCurrentPage, $medicalRecordsTotalPages) ?>
            </div>
        </div>

    </div>

</main>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= asset_url('/assets/js/admin-sidebar-toggle.js') ?>"></script>
</body>
</html>
