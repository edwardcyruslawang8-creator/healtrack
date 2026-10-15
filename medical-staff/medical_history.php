<?php
/**
 * =====================================================================
 * HEALTrack — Health Information System
 * Patient Medical History (medical-staff/medical_history.php)
 * ---------------------------------------------------------------------
 * This IS medical-staff/patient_profile.php's "Medical History" tab
 * (was "Medical Records") — reached by clicking that tab (or the "View
 * Medical History" link on the Overview tab's Active Medical Conditions
 * card) — as its own page rather than an in-page tab pane. Reuses the
 * exact same sidebar/topbar/profile-header/tab-strip shell as
 * patient_profile.php and patient_laboratory_xray.php.
 *
 * CONNECTED to real Firestore data across all three sections — see
 * includes/medical-staff/medical_history_medstaff_firestore.php's
 * header comment. Modeled on subadmin/medical_history.php; Add/Edit/
 * Delete go through the same shared api/add-condition.php and its
 * siblings.
 * =====================================================================
 */
session_start();

require_once __DIR__ . '/../config/constants.php'; // BASE_URL, asset_url()

// Only a signed-in medical staff member may view this page.
if (($_SESSION['role'] ?? null) !== 'healthcare_staff') {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

require_once __DIR__ . '/../includes/medical-staff/medical_history_medstaff_firestore.php';
// $staffProfile, $dashboardDateLabel, $facilityOptions, $patient, $profileTabs,
// $chronicConditions, $surgicalHistory, $familyHistory

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
<title>Medical History — HEALTrack</title>

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
<!-- Medical History page styles (section headers, status badges) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/medical_history.css') ?>">
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
            <h1>Patient Management <span class="ppf-title-sep">/</span> Medical History</h1>
            <nav class="ppf-breadcrumb" aria-label="Breadcrumb">
                <a href="dashboard.php">Dashboard</a>
                <span class="ppf-breadcrumb-sep">&gt;</span>
                <a href="patient_management.php">Patient Management</a>
                <span class="ppf-breadcrumb-sep">&gt;</span>
                <a href="patient_profile.php?id=<?= urlencode($patient['id']) ?>">Patient Profile</a>
                <span class="ppf-breadcrumb-sep">&gt;</span>
                <span class="ppf-breadcrumb-current">Medical History</span>
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

    <!-- ============ PROFILE TABS ============ -->
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

    <!-- ============ MEDICAL HISTORY SECTION HEADER ============ -->
    <div class="mhx-intro">
        <h2>Medical History</h2>
        <p>View and manage patient's past and existing medical history.</p>
    </div>

    <!-- ============ CHRONIC CONDITIONS ============ -->
    <div class="adm-card mhx-section">
        <div class="mhx-section-header">
            <h3><i class="bi bi-heart-pulse-fill"></i> Chronic Conditions</h3>
            <button type="button" class="mhx-add-btn" data-mhx-add="condition"><i class="bi bi-plus-lg"></i> Add</button>
        </div>
        <div class="adm-table-wrap">
            <table class="adm-table mhx-table">
                <thead>
                    <tr><th>Condition</th><th>Date Diagnosed</th><th>Status</th><th>Notes</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($chronicConditions)): ?>
                    <tr><td colspan="5" class="adm-table-empty">No chronic conditions on record.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($chronicConditions as $c): $rowJson = htmlspecialchars(json_encode($c), ENT_QUOTES); ?>
                    <tr>
                        <td class="mhx-cond-label"><?= htmlspecialchars($c['label']) ?></td>
                        <td><?= htmlspecialchars($c['diagnosedOn']) ?></td>
                        <td><span class="mhx-status-badge <?= strtolower($c['status']) ?>"><?= htmlspecialchars($c['status']) ?></span></td>
                        <td class="mhx-notes-cell"><?= htmlspecialchars($c['details'] ?? '—') ?></td>
                        <td>
                            <div class="adm-action-btns">
                                <button type="button" class="adm-action-btn edit" aria-label="Edit" data-mhx-edit="condition" data-id="<?= htmlspecialchars($c['id']) ?>" data-row='<?= $rowJson ?>'><i class="bi bi-pencil"></i></button>
                                <button type="button" class="adm-action-btn delete" aria-label="Delete" data-mhx-delete="condition" data-id="<?= htmlspecialchars($c['id']) ?>" data-label="&quot;<?= htmlspecialchars($c['label']) ?>&quot;"><i class="bi bi-trash"></i></button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if (!empty($chronicConditions)): ?>
        <p class="mhx-footer-text">Showing 1 to <?= count($chronicConditions) ?> of <?= count($chronicConditions) ?> entries</p>
        <?php endif; ?>
    </div>

    <!-- ============ PAST SURGERIES / PROCEDURES ============ -->
    <div class="adm-card mhx-section">
        <div class="mhx-section-header">
            <h3><i class="bi bi-bandaid-fill"></i> Past Surgeries / Procedures</h3>
            <button type="button" class="mhx-add-btn" data-mhx-add="surgery"><i class="bi bi-plus-lg"></i> Add</button>
        </div>
        <div class="adm-table-wrap">
            <table class="adm-table mhx-table">
                <thead>
                    <tr><th>Procedure</th><th>Date</th><th>Surgeon / Facility</th><th>Notes</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($surgicalHistory)): ?>
                    <tr><td colspan="5" class="adm-table-empty">No past surgeries or procedures on record.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($surgicalHistory as $s): $rowJson = htmlspecialchars(json_encode($s), ENT_QUOTES); ?>
                    <tr>
                        <td class="mhx-cond-label"><?= htmlspecialchars($s['procedure']) ?></td>
                        <td><?= htmlspecialchars($s['date']) ?></td>
                        <td><?= htmlspecialchars($s['surgeonFacility'] ?? '—') ?></td>
                        <td class="mhx-notes-cell"><?= htmlspecialchars($s['details'] ?? '—') ?></td>
                        <td>
                            <div class="adm-action-btns">
                                <button type="button" class="adm-action-btn edit" aria-label="Edit" data-mhx-edit="surgery" data-id="<?= htmlspecialchars($s['id']) ?>" data-row='<?= $rowJson ?>'><i class="bi bi-pencil"></i></button>
                                <button type="button" class="adm-action-btn delete" aria-label="Delete" data-mhx-delete="surgery" data-id="<?= htmlspecialchars($s['id']) ?>" data-label="&quot;<?= htmlspecialchars($s['procedure']) ?>&quot;"><i class="bi bi-trash"></i></button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if (!empty($surgicalHistory)): ?>
        <p class="mhx-footer-text">Showing 1 to <?= count($surgicalHistory) ?> of <?= count($surgicalHistory) ?> entries</p>
        <?php endif; ?>
    </div>

    <!-- ============ FAMILY HISTORY ============ -->
    <div class="adm-card mhx-section">
        <div class="mhx-section-header">
            <h3><i class="bi bi-people-fill"></i> Family History</h3>
            <button type="button" class="mhx-add-btn" data-mhx-add="family"><i class="bi bi-plus-lg"></i> Add</button>
        </div>
        <div class="adm-table-wrap">
            <table class="adm-table mhx-table">
                <thead>
                    <tr><th>Condition</th><th>Relation</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($familyHistory)): ?>
                    <tr><td colspan="3" class="adm-table-empty">No family history on record.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($familyHistory as $f): $rowJson = htmlspecialchars(json_encode($f), ENT_QUOTES); ?>
                    <tr>
                        <td class="mhx-cond-label"><?= htmlspecialchars($f['condition']) ?></td>
                        <td><?= htmlspecialchars($f['relation']) ?></td>
                        <td>
                            <div class="adm-action-btns">
                                <button type="button" class="adm-action-btn edit" aria-label="Edit" data-mhx-edit="family" data-id="<?= htmlspecialchars($f['id']) ?>" data-row='<?= $rowJson ?>'><i class="bi bi-pencil"></i></button>
                                <button type="button" class="adm-action-btn delete" aria-label="Delete" data-mhx-delete="family" data-id="<?= htmlspecialchars($f['id']) ?>" data-label="&quot;<?= htmlspecialchars($f['condition']) ?>&quot;"><i class="bi bi-trash"></i></button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if (!empty($familyHistory)): ?>
        <p class="mhx-footer-text">Showing 1 to <?= count($familyHistory) ?> of <?= count($familyHistory) ?> entries</p>
        <?php endif; ?>
    </div>

</main>

<?php require __DIR__ . '/../includes/shared/medical_history_modals.php'; ?>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= asset_url('/assets/js/admin-sidebar-toggle.js') ?>"></script>
<script src="<?= asset_url('/assets/js/hc-dialogs.js') ?>"></script>
<script src="<?= asset_url('/assets/js/medical-history.js') ?>"></script>
</body>
</html>
