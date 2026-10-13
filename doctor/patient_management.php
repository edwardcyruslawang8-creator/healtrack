<?php
/**
 * =====================================================================
 * HEALTrack — Health Information System
 * Patient Management (doctor/patient_management.php)
 * ---------------------------------------------------------------------
 * Reuses the exact same sidebar/topbar shell — and the same Doctor nav
 * list — as doctor/dashboard.php (280px dark-red gradient sidebar,
 * "adm-"/"doc-" prefixed atoms, assets/css/admin-dashboard.css +
 * assets/css/doctor_dashboard.css for the nav badge/topbar select
 * buttons), so the sidebar doesn't change when navigating here from the
 * dashboard. Only the page content (stat cards, search/filter toolbar,
 * patient table) is unique to this page — reuses
 * assets/css/patient_management.css directly since it's visually
 * identical to medical-staff/patient_management.php's (same "pmg-"
 * prefix, no role-specific styling in it at all).
 *
 * CONNECTED to real Firestore data — see
 * includes/doctor/patient_management_doctor_firestore.php's header
 * comment. The search box and gender/age filters really filter the
 * table. Each row's "View Profile" button links to
 * doctor/patient_profile.php?id=<patient id>. "Add Patient" reuses
 * includes/medical-staff/add_patient_modal.php and
 * assets/js/add-patient.js unchanged (posts to api/create-patient.php,
 * which now accepts role=doctor too).
 * =====================================================================
 */
session_start();

require_once __DIR__ . '/../config/constants.php'; // BASE_URL, asset_url()

// Only a signed-in doctor may view this page.
if (($_SESSION['role'] ?? null) !== 'doctor') {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

require_once __DIR__ . '/../includes/doctor/patient_management_doctor_firestore.php';
// $doctorProfile, $dashboardDateLabel, $facilityOptions, $statCards,
// $genderOptions, $ageGroupOptions, $patients, $patientsTotal,
// $patientsPerPage, $patientsCurrentPage, $patientsTotalPages,
// $pendingOwnerRequests

// Sidebar nav — same Doctor nav list as doctor/dashboard.php.
// Patient Management is active here.
$doctorNavItems = [
    ['icon' => 'bi-grid-fill',           'label' => 'Dashboard',          'href' => 'dashboard.php'],
    ['icon' => 'bi-person-lines-fill',   'label' => 'Patient Management', 'href' => 'patient_management.php', 'active' => true],
    ['icon' => 'bi-calendar2-heart-fill','label' => 'Consultations',      'href' => 'consultations.php'],
    ['icon' => 'bi-bar-chart-fill',      'label' => 'Analytics & Reports', 'href' => 'analytics_reports.php'],
    ['icon' => 'bi-bell-fill',           'label' => 'Notifications',      'href' => 'notifications.php', 'badge' => hc_doctor_unread_notification_count()],
    ['icon' => 'bi-clipboard-data-fill', 'label' => 'Audit Logs', 'href' => 'audit_logs.php'],
    ['icon' => 'bi-gear-fill',           'label' => 'Profile / Settings', 'href' => 'profile_settings.php'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Patient Management — HEALTrack</title>

<!-- Google Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<!-- Bootstrap 5 -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<!-- Font Awesome (available per project spec; Bootstrap Icons cover this page's icons) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<!-- Shared admin/subadmin/medical-staff shell (sidebar, topbar, cards, table, etc.) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/admin-dashboard.css') ?>">
<!-- Doctor Dashboard styles, reused here for the nav badge + topbar select buttons -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/doctor_dashboard.css') ?>">
<!-- Patient Management page styles (stat cards, filter toolbar, table) — shared verbatim with medical-staff/patient_management.php -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/patient_management.css') ?>">
<!-- Quick Search dropdown under the search box (live typeahead, shared with subadmin/medical-staff's Patient Management) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/quick-search.css') ?>">
<!-- Add Patient modal styles (multi-step intake wizard) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/add_patient_modal.css') ?>">
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

    <!-- Doctor profile -->
    <div class="adm-profile">
        <div class="adm-avatar"><?php require_once __DIR__ . '/../includes/shared/profile_photo_storage.php'; echo hc_render_sidebar_avatar(); ?></div>
        <div class="adm-profile-text">
            <div class="adm-profile-name"><?= htmlspecialchars($doctorProfile['name']) ?></div>
            <div class="adm-profile-role"><?= htmlspecialchars($doctorProfile['role']) ?></div>
        </div>
    </div>

    <hr class="adm-sidebar-divider">

    <!-- Navigation -->
    <ul class="adm-nav">
        <?php foreach ($doctorNavItems as $item): ?>
        <li>
            <a href="<?= htmlspecialchars($item['href']) ?>" class="adm-nav-link <?= !empty($item['active']) ? 'active' : '' ?>">
                <i class="bi <?= htmlspecialchars($item['icon']) ?>"></i>
                <span><?= htmlspecialchars($item['label']) ?></span>
                <?php if (!empty($item['badge'])): ?>
                <span class="doc-nav-badge"><?= (int) $item['badge'] ?></span>
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

    <!-- ============ TOP HEADER ============ -->
    <div class="adm-topbar">
        <div>
            <h1>Patient Management</h1>
            <p class="adm-page-subtitle">Manage and view patients registered in the system.</p>
        </div>

        <div class="adm-topbar-right">
            <div class="adm-topbar-actions">
                <!-- Fixed — display only, not a range picker; nothing
                     downstream reads a selected value yet. -->
                <div class="doc-topbar-select-btn">
                    <i class="bi bi-calendar3"></i> <?= htmlspecialchars($dashboardDateLabel) ?>
                </div>

                <!-- Fixed — shows only the facility this doctor is
                     actually assigned to (their real users/{uid} doc's "facility"
                     field); not a switcher, since a doctor can't reassign
                     themselves. -->
                <div class="doc-topbar-select-btn">
                    <i class="bi bi-building"></i> <?= htmlspecialchars($doctorProfile['facility']) ?>
                </div>

                <a href="notifications.php" class="adm-bell-btn" aria-label="Notifications">
                    <i class="bi bi-bell-fill"></i>
                    <?php if (hc_doctor_unread_notification_count() > 0): ?><span class="adm-badge-count"><?= hc_doctor_unread_notification_count() ?></span><?php endif; ?>
                </a>

                <div class="dropdown">
                    <button class="adm-profile-dropdown dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="adm-topbar-avatar"><?php require_once __DIR__ . '/../includes/shared/profile_photo_storage.php'; echo hc_render_sidebar_avatar(); ?></span>
                        <span>
                            <span class="d-block adm-topbar-name"><?= htmlspecialchars(hc_doctor_display_name($doctorProfile['name'])) ?></span>
                            <span class="d-block adm-topbar-role"><?= htmlspecialchars($doctorProfile['role']) ?></span>
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

    <!-- ============ SUMMARY STAT CARDS ============ -->
    <div class="pmg-stats-row">
        <?php foreach ($statCards as $stat): ?>
        <div class="pmg-stat-card">
            <div class="pmg-stat-icon" style="background:<?= htmlspecialchars($stat['iconBg']) ?>; color:<?= htmlspecialchars($stat['iconColor']) ?>;">
                <i class="bi <?= htmlspecialchars($stat['icon']) ?>"></i>
            </div>
            <div>
                <div class="pmg-stat-label"><?= htmlspecialchars($stat['label']) ?></div>
                <div class="pmg-stat-value"><?= number_format((int) $stat['value']) ?></div>
                <?php if (isset($stat['sub'])): ?>
                <div class="pmg-stat-sub"><i class="bi bi-arrow-up-short"></i> <?= htmlspecialchars($stat['sub']) ?></div>
                <?php elseif (isset($stat['staticSub'])): ?>
                <div class="pmg-stat-sub muted"><?= htmlspecialchars($stat['staticSub']) ?></div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- ============ PENDING ACCESS REQUESTS (real, only shown when this
         doctor owns at least one patient a colleague has asked to view —
         see includes/shared/patient_access_requests.php's header comment) ============ -->
    <?php if (!empty($pendingOwnerRequests)): ?>
    <div class="adm-card pmg-table-card" style="margin-bottom:20px;">
        <h2 class="pmg-table-title">Pending Access Requests (<?= count($pendingOwnerRequests) ?>)</h2>
        <div class="adm-table-wrap">
            <table class="adm-table pmg-table">
                <thead>
                    <tr>
                        <th>Patient</th>
                        <th>Requested By</th>
                        <th>Reason</th>
                        <th>Requested</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pendingOwnerRequests as $req): ?>
                    <tr>
                        <td><?= htmlspecialchars($req['patientName']) ?></td>
                        <td><?= htmlspecialchars($req['requestedByName']) ?></td>
                        <td><?= htmlspecialchars($req['reason'] !== '' ? $req['reason'] : '—') ?></td>
                        <td><?= htmlspecialchars($req['createdAt']) ?></td>
                        <td>
                            <div class="adm-action-btns pmg-action-btns">
                                <button type="button" class="pmg-btn-view-profile pmg-access-decide-btn" data-request-id="<?= htmlspecialchars($req['id']) ?>" data-action="approve">Approve</button>
                                <button type="button" class="pmg-access-decide-btn pmg-access-deny-btn" data-request-id="<?= htmlspecialchars($req['id']) ?>" data-action="deny">Deny</button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- ============ SEARCH + FILTER TOOLBAR ============ -->
    <form class="pmg-toolbar" method="get" action="patient_management.php">
        <div class="pmg-search">
            <i class="bi bi-search"></i>
            <input type="text" name="q" value="<?= htmlspecialchars($searchTerm) ?>" id="patientSearchInput" placeholder="Search by name or contact number..." autocomplete="off" data-quick-search="<?= BASE_URL ?>/api/quick-search-patients.php" data-quick-search-view="patient_profile.php?id={id}">
        </div>

        <select class="form-select pmg-filter-select" name="gender" aria-label="Filter by gender" onchange="this.form.submit()">
            <?php foreach ($genderOptions as $g): ?>
            <option <?= $g === $genderFilter ? 'selected' : '' ?>><?= htmlspecialchars($g) ?></option>
            <?php endforeach; ?>
        </select>

        <select class="form-select pmg-filter-select" name="ageGroup" aria-label="Filter by age group" onchange="this.form.submit()">
            <?php foreach ($ageGroupOptions as $a): ?>
            <option <?= $a === $ageGroupFilter ? 'selected' : '' ?>><?= htmlspecialchars($a) ?></option>
            <?php endforeach; ?>
        </select>

        <button type="submit" class="pmg-btn-search"><i class="bi bi-search"></i> Search</button>
        <button type="button" class="pmg-btn-search" id="btnAddPatient"><i class="bi bi-person-plus-fill"></i> Add Patient</button>
    </form>

    <!-- ============ PATIENT TABLE ============ -->
    <div class="adm-card pmg-table-card">
        <h2 class="pmg-table-title">Patient List</h2>

        <?php if ($patientsLoadFailed): ?>
        <div class="alert alert-danger py-2 px-3 small" role="alert">
            <i class="bi bi-exclamation-triangle-fill"></i>
            Couldn't load patient data right now — Firestore didn't respond (this is usually a temporary
            outage or the daily quota being reached, not missing data). Please try again in a moment.
        </div>
        <?php endif; ?>

        <div class="adm-table-wrap">
            <table class="adm-table pmg-table">
                <thead>
                    <tr>
                        <th>Full Name</th>
                        <th>Age</th>
                        <th>Gender</th>
                        <th>Contact Number</th>
                        <th>Date Registered</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($patients)): ?>
                    <tr>
                        <td colspan="6" class="adm-table-empty"><?= $patientsLoadFailed ? 'Could not load patient data.' : 'No patients registered yet.' ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php foreach ($patients as $p): ?>
                    <tr>
                        <td><?= htmlspecialchars($p['name']) ?></td>
                        <td><?= (int) $p['age'] ?></td>
                        <td><?= htmlspecialchars($p['gender']) ?></td>
                        <td><?= htmlspecialchars($p['contact']) ?></td>
                        <td><?= htmlspecialchars($p['dateRegistered']) ?></td>
                        <td>
                            <div class="adm-action-btns pmg-action-btns">
                                <?php if ($p['hasAccess']): ?>
                                <a href="patient_profile.php?id=<?= urlencode($p['id']) ?>" class="pmg-btn-view-profile"><i class="bi bi-eye"></i> View Profile</a>
                                <?php else: ?>
                                <button type="button" class="pmg-btn-request-access" data-patient-id="<?= htmlspecialchars($p['id']) ?>" data-patient-name="<?= htmlspecialchars($p['name']) ?>" data-owner-name="<?= htmlspecialchars($p['ownerName'] ?? 'another doctor') ?>">
                                    <i class="bi bi-lock-fill"></i> Request Access
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="adm-table-footer">
            <span class="adm-showing-text"><?= $patientsTotal > 0 ? 'Showing ' . (($patientsCurrentPage - 1) * $patientsPerPage + 1) . ' to ' . min($patientsCurrentPage * $patientsPerPage, $patientsTotal) . ' of ' . number_format($patientsTotal) . ' entries' : 'No entries to show' ?></span>
            <nav class="adm-pagination" aria-label="Patient list pages">
                <a href="?<?= hc_pm_query(['page' => 1]) ?>" class="page-btn" aria-label="First page"><i class="bi bi-chevron-bar-left"></i></a>
                <a href="?<?= hc_pm_query(['page' => max(1, $patientsCurrentPage - 1)]) ?>" class="page-btn" aria-label="Previous page"><i class="bi bi-chevron-left"></i></a>
                <?php foreach ($patientsPaginationWindow as $p): ?>
                    <?php if ($p === '...'): ?>
                    <span class="page-ellipsis">&hellip;</span>
                    <?php else: ?>
                    <a href="?<?= hc_pm_query(['page' => $p]) ?>" class="page-btn <?= $p === $patientsCurrentPage ? 'active' : '' ?>"><?= $p ?></a>
                    <?php endif; ?>
                <?php endforeach; ?>
                <a href="?<?= hc_pm_query(['page' => min($patientsTotalPages, $patientsCurrentPage + 1)]) ?>" class="page-btn" aria-label="Next page"><i class="bi bi-chevron-right"></i></a>
                <a href="?<?= hc_pm_query(['page' => $patientsTotalPages]) ?>" class="page-btn" aria-label="Last page"><i class="bi bi-chevron-bar-right"></i></a>
            </nav>
        </div>
    </div>

</main>

<?php require __DIR__ . '/../includes/medical-staff/add_patient_modal.php'; ?>

<!-- ============ REQUEST ACCESS MODAL (real — posts to
     api/request-patient-access.php; see
     includes/shared/patient_access_requests.php's header comment) ============ -->
<div class="modal fade" id="requestAccessModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Request Patient Access</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p style="font-size:14px; margin-bottom:14px;">
                    <strong id="raPatientName"></strong> is currently owned by <strong id="raOwnerName"></strong>.
                    They'll be asked to approve this request — the patient will then also need to consent by email before you're given access.
                </p>
                <div id="raError" class="alert alert-danger py-2 px-3 d-none" style="font-size:13px;"></div>
                <label for="raReason" class="form-label" style="font-size:13px; font-weight:600;">Reason (optional)</label>
                <textarea id="raReason" class="form-control" rows="3" placeholder="e.g. Covering this patient's care this week"></textarea>
                <input type="hidden" id="raPatientId">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn" id="raSubmitBtn" style="background:var(--adm-red-bright); color:#fff;">Send Request</button>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<link rel="stylesheet" href="<?= asset_url('/assets/css/hc-dialogs.css') ?>">
<script src="<?= asset_url('/assets/js/hc-dialogs.js') ?>"></script>
<script src="<?= asset_url('/assets/js/admin-sidebar-toggle.js') ?>"></script>
<script src="<?= asset_url('/assets/js/quick-search.js') ?>"></script>
<script src="<?= asset_url('/assets/js/add-patient.js') ?>"></script>
<script src="<?= asset_url('/assets/js/patient-access-request.js') ?>"></script>
<script src="<?= asset_url('/assets/js/search-form-guard.js') ?>"></script>
</body>
</html>
