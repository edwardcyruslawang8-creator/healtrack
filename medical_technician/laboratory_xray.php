<?php
/**
 * =====================================================================
 * HEALTrack — Health Information System
 * Laboratory Processing (medical_technician/laboratory_xray.php)
 * ---------------------------------------------------------------------
 * The Medical Technologist role's request queue — every real
 * lab_results doc across every patient (see
 * includes/medical_technician/laboratory_xray_firestore.php's header
 * comment), not one patient's own history like medical-staff's/
 * subadmin's patient_laboratory_xray.php. Reuses the exact same visual
 * shell (280px dark-red gradient sidebar, "adm-" prefixed atoms,
 * assets/css/admin-dashboard.css) plus the same stat row look as
 * medical_technician/dashboard.php (assets/css/medical_technician_dashboard.css,
 * "mt-" prefixed) — page-specific bits (request table, filter toolbar,
 * recent reports row) are "lxr-" prefixed, in their own
 * assets/css/medical_technician_lab_xray.css.
 *
 * CONNECTED to real Firestore data for everything the schema actually
 * models (patient, examination, ordered by, date/time, status);
 * "Priority" is the one placeholder column — see the data file's
 * header comment for why.
 * =====================================================================
 */
session_start();

require_once __DIR__ . '/../config/constants.php'; // BASE_URL, asset_url()

// Only a signed-in medical technologist may view this page.
if (($_SESSION['role'] ?? null) !== 'medical_technician') {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

require_once __DIR__ . '/../includes/medical_technician/laboratory_xray_firestore.php';
// $staffProfile, $dashboardDateLabel, $facilityOptions, $requestsSummary,
// $activeTab, $statusOptions, $priorityOptions, $searchTerm, $statusFilter,
// $priorityFilter, $dateFrom, $dateTo, $statusMeta, $requests, $requestsTotal,
// $requestsPerPage, $requestsCurrentPage, $requestsTotalPages,
// $requestsPaginationWindow, $recentLabResults

// Sidebar nav — Laboratory is active on this page. None of the
// other not-yet-built links have changed — see dashboard.php's header
// comment.
$staffNavItems = [
    ['icon' => 'bi-grid-fill',                 'label' => 'Dashboard',                       'href' => 'dashboard.php'],
    ['icon' => 'bi-clipboard2-pulse-fill',      'label' => 'Laboratory',              'href' => 'laboratory_xray.php', 'active' => true],
    ['icon' => 'bi-inboxes-fill',              'label' => 'Laboratory Consultation', 'href' => 'lab_xray_consultation.php', 'badge' => hc_get_pending_lab_count()],
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

/** Builds a ?query string carrying every current filter except the ones in $overrides. */
function hc_medtech_lxr_query(array $overrides = []): string
{
    $params = array_merge([
        'tab' => $_GET['tab'] ?? 'all',
        'q' => $_GET['q'] ?? '',
        'status' => $_GET['status'] ?? 'All Status',
        'priority' => $_GET['priority'] ?? 'All Priority',
        'from' => $_GET['from'] ?? '',
        'to' => $_GET['to'] ?? '',
        'page' => $_GET['page'] ?? 1,
    ], $overrides);

    return http_build_query(array_filter($params, fn ($v) => $v !== '' && $v !== null));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Laboratory — HEALTrack</title>

<!-- Google Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<!-- Bootstrap 5 -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<!-- Font Awesome (available per project spec; Bootstrap Icons cover this page's icons) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<!-- Shared admin/subadmin/medical-staff/pharmacist shell (sidebar, topbar, cards, tabs, table, etc.) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/admin-dashboard.css') ?>">
<!-- Medical Technologist Dashboard styles, reused here for the nav badge + topbar select buttons + 5-card stat row -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/medical_technician_dashboard.css') ?>">
<!-- Laboratory Processing page styles (filter toolbar, request table, recent reports row) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/medical_technician_lab_xray.css') ?>">
</head>
<body class="adm-body laboratory-xray-page">

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

    <!-- Medical technologist profile -->
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

<!-- =============================================================
     MAIN CONTENT
     ============================================================= -->
<main class="adm-main">

    <!-- ============ TOP HEADER ============ -->
    <div class="adm-topbar">
        <div>
            <h1>Laboratory</h1>
            <p class="adm-page-subtitle">View and manage laboratory test requests, results, and reports.</p>
        </div>

        <div class="adm-topbar-right">
            <div class="adm-topbar-actions">
                <!-- Fixed — display only, not a range picker; nothing
                     downstream reads a selected value yet. -->
                <div class="mt-topbar-select-btn">
                    <i class="bi bi-calendar3"></i> <?= htmlspecialchars($dashboardDateLabel) ?>
                </div>

                <!-- Fixed — shows only the facility this technician is
                     actually assigned to (their real users/{uid} doc's "facility"
                     field); not a switcher, since a technician can't reassign
                     themselves. -->
                <div class="mt-topbar-select-btn">
                    <i class="bi bi-building"></i> <?= htmlspecialchars($staffProfile['facility']) ?>
                </div>

                <a href="notifications.php" class="adm-bell-btn" aria-label="Notifications">
                    <i class="bi bi-bell-fill"></i>
                    <?php if (hc_medtech_unread_notification_count() > 0): ?><span class="adm-badge-count"><?= hc_medtech_unread_notification_count() ?></span><?php endif; ?>
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

    <!-- ============ SUMMARY STAT CARDS ============ -->
    <div class="mt-stats-row lxr-stats-row">
        <div class="mt-stat-card">
            <div class="mt-stat-icon" style="background:#E8F0FE; color:#1A73E8;"><i class="bi bi-eyedropper"></i></div>
            <div class="mt-stat-label">Total Requests</div>
            <div class="mt-stat-value"><?= number_format($requestsSummary['total']) ?></div>
        </div>
        <div class="mt-stat-card">
            <div class="mt-stat-icon" style="background:#FDECDD; color:#E8710A;"><i class="bi bi-hourglass-split"></i></div>
            <div class="mt-stat-label">Pending</div>
            <div class="mt-stat-value"><?= number_format($requestsSummary['pending']) ?></div>
        </div>
        <div class="mt-stat-card">
            <div class="mt-stat-icon" style="background:#E4F7EA; color:#1E8E3E;"><i class="bi bi-check-circle-fill"></i></div>
            <div class="mt-stat-label">Completed</div>
            <div class="mt-stat-value"><?= number_format($requestsSummary['completed']) ?></div>
        </div>
    </div>

    <!-- ============ FILTER TOOLBAR ============ -->
    <div class="adm-card lxr-filter-card">
        <form method="get" class="lxr-filter-row">
            <input type="hidden" name="tab" value="<?= htmlspecialchars($activeTab) ?>">

            <div class="lxr-search">
                <i class="bi bi-search"></i>
                <input type="text" name="q" value="<?= htmlspecialchars($searchTerm) ?>" placeholder="Search by patient name, ID, or request ID...">
            </div>

            <select class="form-select lxr-filter-select" name="status" aria-label="Filter by status">
                <?php foreach ($statusOptions as $opt): ?>
                <option <?= $opt === $statusFilter ? 'selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
                <?php endforeach; ?>
            </select>

            <select class="form-select lxr-filter-select" name="priority" aria-label="Filter by priority">
                <?php foreach ($priorityOptions as $opt): ?>
                <option <?= $opt === $priorityFilter ? 'selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
                <?php endforeach; ?>
            </select>

            <div class="lxr-daterange">
                <i class="bi bi-calendar3"></i>
                <input type="date" name="from" value="<?= htmlspecialchars($dateFrom) ?>" aria-label="From date">
                <span class="lxr-dot"></span>
                <input type="date" name="to" value="<?= htmlspecialchars($dateTo) ?>" aria-label="To date">
            </div>

            <button type="submit" class="lxr-btn-filter"><i class="bi bi-funnel"></i> Filter</button>
        </form>
    </div>

    <!-- ============ REQUESTS TABLE ============ -->
    <div class="adm-card">
        <div class="adm-table-wrap">
            <table class="adm-table lxr-table">
                <thead>
                    <tr>
                        <th>Patient</th>
                        <th>Laboratory Test</th>
                        <th>Requested By</th>
                        <th>Date &amp; Time</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Result / Report</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($requests)): ?>
                    <tr>
                        <td colspan="7" class="adm-table-empty">No requests match these filters.</td>
                    </tr>
                    <?php endif; ?>
                    <?php foreach ($requests as $r): $status = $statusMeta[$r['status']] ?? $statusMeta['pending']; ?>
                    <tr>
                        <td>
                            <div class="lxr-strong"><?= htmlspecialchars($r['patientName']) ?></div>
                            <div class="lxr-patient-meta"><?= (int) $r['patientAge'] ?> years old</div>
                        </td>
                        <td><?= htmlspecialchars($r['examination']) ?></td>
                        <td><?= htmlspecialchars($r['requestedBy']) ?></td>
                        <td class="lxr-datetime"><?= htmlspecialchars($r['date']) ?><span class="lxr-dot"></span><?= htmlspecialchars($r['time']) ?></td>
                        <td><span class="lxr-badge" style="background:<?= htmlspecialchars($r['priority']['bg']) ?>; color:<?= htmlspecialchars($r['priority']['color']) ?>;"><?= htmlspecialchars($r['priority']['label']) ?></span></td>
                        <td><span class="lxr-badge" style="background:<?= htmlspecialchars($status['bg']) ?>; color:<?= htmlspecialchars($status['color']) ?>;"><?= htmlspecialchars($status['label']) ?></span></td>
                        <td>
                            <?php if ($r['hasReport']): ?>
                            <a href="laboratory_result_details.php?id=<?= urlencode($r['patientId']) ?>&type=<?= urlencode($r['type']) ?>&recordId=<?= urlencode($r['id']) ?>" class="lxr-consult-btn">View Result</a>
                            <?php else: ?>
                            <span class="lxr-report-none">&mdash;</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="adm-table-footer">
            <span class="adm-showing-text"><?= $requestsTotal > 0 ? 'Showing ' . (($requestsCurrentPage - 1) * $requestsPerPage + 1) . ' to ' . min($requestsCurrentPage * $requestsPerPage, $requestsTotal) . ' of ' . $requestsTotal . ' entries' : 'No entries to show' ?></span>
            <nav class="adm-pagination" aria-label="Requests pages">
                <a href="?<?= hc_medtech_lxr_query(['page' => 1]) ?>" class="page-btn" aria-label="First page"><i class="bi bi-chevron-bar-left"></i></a>
                <a href="?<?= hc_medtech_lxr_query(['page' => max(1, $requestsCurrentPage - 1)]) ?>" class="page-btn" aria-label="Previous page"><i class="bi bi-chevron-left"></i></a>
                <?php foreach ($requestsPaginationWindow as $p): ?>
                    <?php if ($p === '...'): ?>
                    <span class="page-ellipsis">&hellip;</span>
                    <?php else: ?>
                    <a href="?<?= hc_medtech_lxr_query(['page' => $p]) ?>" class="page-btn <?= $p === $requestsCurrentPage ? 'active' : '' ?>"><?= $p ?></a>
                    <?php endif; ?>
                <?php endforeach; ?>
                <a href="?<?= hc_medtech_lxr_query(['page' => min($requestsTotalPages, $requestsCurrentPage + 1)]) ?>" class="page-btn" aria-label="Next page"><i class="bi bi-chevron-right"></i></a>
                <a href="?<?= hc_medtech_lxr_query(['page' => $requestsTotalPages]) ?>" class="page-btn" aria-label="Last page"><i class="bi bi-chevron-bar-right"></i></a>
            </nav>
        </div>
    </div>

    <!-- ============ RECENT LABORATORY RESULTS ============ -->
    <div class="adm-card">
        <div class="adm-card-header">
            <h2>Recent Laboratory Results</h2>
            <a href="?<?= hc_medtech_lxr_query(['tab' => 'lab', 'page' => 1]) ?>" class="adm-card-footer-link" style="margin-top:0;">View All <i class="bi bi-arrow-right"></i></a>
        </div>
        <div class="lxr-reports-row">
            <?php if (empty($recentLabResults)): ?>
            <p class="adm-table-empty">No completed laboratory results yet.</p>
            <?php endif; ?>
            <?php foreach ($recentLabResults as $rep): ?>
            <div class="lxr-report-card">
                <div class="lxr-report-name"><?= htmlspecialchars($rep['patientName']) ?></div>
                <div class="lxr-report-exam"><?= htmlspecialchars($rep['examination']) ?></div>
                <?php if ($rep['resultBadge']): ?>
                <span class="lxr-badge" style="background:<?= htmlspecialchars($rep['resultBadge']['bg']) ?>; color:<?= htmlspecialchars($rep['resultBadge']['color']) ?>;"><?= htmlspecialchars($rep['resultBadge']['label']) ?></span>
                <?php endif; ?>
                <div class="lxr-report-date"><i class="bi bi-calendar3"></i> <?= htmlspecialchars($rep['date']) ?></div>
                <a href="laboratory_result_details.php?id=<?= urlencode($rep['patientId']) ?>&type=lab&recordId=<?= urlencode($rep['id']) ?>" class="lxr-report-link">View Result.</a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

</main>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= asset_url('/assets/js/admin-sidebar-toggle.js') ?>"></script>
</body>
</html>
