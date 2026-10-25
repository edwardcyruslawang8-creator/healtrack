<?php
/**
 * =====================================================================
 * HEALTrack — Health Information System
 * Prescription Queue (pharmacist/prescription_queue.php)
 * ---------------------------------------------------------------------
 * The Pharmacist role's dispensing queue — every real pharmacy_orders
 * doc across every patient (see
 * includes/pharmacist/prescription_queue_pharmacist_firestore.php's
 * header comment), created automatically when a doctor completes a
 * consultation routed to "Pharmacy" with medicine/vaccine rows
 * (api/complete-consultation.php). Reuses the exact same visual shell
 * (280px dark-red gradient sidebar, "adm-" prefixed atoms,
 * assets/css/admin-dashboard.css) plus the same stat-card-row look as
 * pharmacist/dashboard.php (assets/css/pharmacist_dashboard.css,
 * "phm-" prefixed) — page-specific bits (tabs, filter toolbar, queue
 * table) are "rxq-" prefixed, in their own assets/css/prescription_queue.css.
 * =====================================================================
 */
session_start();

require_once __DIR__ . '/../config/constants.php'; // BASE_URL, asset_url()

// Only a signed-in pharmacist may view this page.
if (($_SESSION['role'] ?? null) !== 'pharmacist') {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

require_once __DIR__ . '/../includes/pharmacist/prescription_queue_pharmacist_firestore.php';
// $staffProfile, $dashboardDateLabel, $facilityOptions, $statusMeta,
// $ordersSummary, $activeTab, $typeFilter, $searchTerm, $dateFrom, $dateTo,
// $orders, $ordersTotal, $ordersPerPage, $ordersCurrentPage,
// $ordersTotalPages, $ordersPaginationWindow

// Sidebar nav — Prescription Queue is active on this page.
$staffNavItems = [
    ['icon' => 'bi-grid-fill',              'label' => 'Dashboard',                    'href' => 'dashboard.php'],
    ['icon' => 'bi-capsule',                'label' => 'Medicine & Vaccine Inventory', 'href' => 'medicine_vaccine_inventory.php'],
    ['icon' => 'bi-clipboard2-pulse-fill',  'label' => 'Prescription Queue',           'href' => 'prescription_queue.php', 'active' => true, 'badge' => hc_get_pending_pharmacy_order_count()],
    ['icon' => 'bi-box-seam-fill',          'label' => 'Stock Requests',               'href' => 'stock_requests.php'],
    ['icon' => 'bi-bar-chart-fill',        'label' => 'Analytics & Reports',          'href' => 'analytics_reports.php'],
    ['icon' => 'bi-bell-fill',              'label' => 'Notifications',                'href' => 'notifications.php', 'badge' => hc_pharmacist_unread_notification_count()],
    ['icon' => 'bi-clipboard-data-fill', 'label' => 'Audit Logs', 'href' => 'audit_logs.php'],
    ['icon' => 'bi-gear-fill',              'label' => 'Profile / Settings',           'href' => 'profile_settings.php'],
];

/** Builds a ?query string carrying every current filter except the ones in $overrides. */
function hc_rxq_query(array $overrides = []): string
{
    $params = array_merge([
        'tab' => $_GET['tab'] ?? 'pending',
        'q' => $_GET['q'] ?? '',
        'type' => $_GET['type'] ?? 'all',
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
<title>Prescription Queue — HEALTrack</title>

<!-- Google Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<!-- Bootstrap 5 -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<!-- Font Awesome (available per project spec; Bootstrap Icons cover this page's icons) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<!-- Shared admin/subadmin/medical-staff/medical_technician shell (sidebar, topbar, cards, tabs, table, etc.) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/admin-dashboard.css') ?>">
<!-- Pharmacist Dashboard styles, reused here for the nav badge + topbar select buttons + stat card row -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/pharmacist_dashboard.css') ?>">
<!-- Prescription Queue page styles (filter toolbar, queue table) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/prescription_queue.css') ?>">
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

    <!-- Pharmacist profile -->
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
                <span class="phm-nav-badge"><?= (int) $item['badge'] ?></span>
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
            <h1>Prescription Queue</h1>
            <p class="adm-page-subtitle">Patients sent by doctors for medicine or vaccine dispensing.</p>
        </div>

        <div class="adm-topbar-right">
            <div class="adm-topbar-actions">
                <!-- Fixed — display only, not a range picker; nothing
                     downstream reads a selected value yet. -->
                <div class="phm-topbar-select-btn">
                    <i class="bi bi-calendar3"></i> <?= htmlspecialchars($dashboardDateLabel) ?>
                </div>

                <!-- Fixed — shows only the facility this pharmacist is
                     actually assigned to (their real users/{uid} doc's "facility"
                     field); not a switcher. -->
                <div class="phm-topbar-select-btn">
                    <i class="bi bi-building"></i> <?= htmlspecialchars($staffProfile['facility']) ?>
                </div>

                <a href="notifications.php" class="adm-bell-btn" aria-label="Notifications">
                    <i class="bi bi-bell-fill"></i>
                    <?php if (hc_pharmacist_unread_notification_count() > 0): ?><span class="adm-badge-count"><?= hc_pharmacist_unread_notification_count() ?></span><?php endif; ?>
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
    <div class="phm-stats-row rxq-stats-row">
        <div class="phm-stat-card">
            <div class="phm-stat-icon" style="background:#FDECDD; color:#E8710A;"><i class="bi bi-hourglass-split"></i></div>
            <div class="phm-stat-label">Total Pending</div>
            <div class="phm-stat-value"><?= number_format($ordersSummary['pending']) ?></div>
        </div>
        <div class="phm-stat-card">
            <div class="phm-stat-icon" style="background:#EFE7FB; color:#7C3AED;"><i class="bi bi-capsule"></i></div>
            <div class="phm-stat-label">Medicines Pending</div>
            <div class="phm-stat-value"><?= number_format($ordersSummary['medicines']) ?></div>
        </div>
        <div class="phm-stat-card">
            <div class="phm-stat-icon" style="background:#E8F0FE; color:#1A73E8;"><i class="bi bi-shield-plus"></i></div>
            <div class="phm-stat-label">Vaccines Pending</div>
            <div class="phm-stat-value"><?= number_format($ordersSummary['vaccines']) ?></div>
        </div>
    </div>

    <!-- ============ STATUS TABS ============ -->
    <ul class="nav adm-tabs" role="tablist">
        <li class="nav-item" role="presentation">
            <a href="?<?= hc_rxq_query(['tab' => 'pending', 'page' => 1]) ?>" class="nav-link <?= $activeTab === 'pending' ? 'active' : '' ?>">Pending</a>
        </li>
        <li class="nav-item" role="presentation">
            <a href="?<?= hc_rxq_query(['tab' => 'dispensed', 'page' => 1]) ?>" class="nav-link <?= $activeTab === 'dispensed' ? 'active' : '' ?>">Dispensed</a>
        </li>
    </ul>

    <!-- ============ FILTER TOOLBAR ============ -->
    <div class="adm-card rxq-filter-card">
        <form method="get" class="rxq-filter-row">
            <input type="hidden" name="tab" value="<?= htmlspecialchars($activeTab) ?>">

            <div class="rxq-search">
                <i class="bi bi-search"></i>
                <input type="text" name="q" value="<?= htmlspecialchars($searchTerm) ?>" placeholder="Search by patient name, ID, or item...">
            </div>

            <select class="form-select rxq-filter-select" name="type" aria-label="Filter by type">
                <option value="all" <?= $typeFilter === 'all' ? 'selected' : '' ?>>All Types</option>
                <option value="medication" <?= $typeFilter === 'medication' ? 'selected' : '' ?>>Medicine</option>
                <option value="vaccine" <?= $typeFilter === 'vaccine' ? 'selected' : '' ?>>Vaccine</option>
            </select>

            <div class="rxq-daterange">
                <i class="bi bi-calendar3"></i>
                <input type="date" name="from" value="<?= htmlspecialchars($dateFrom) ?>" aria-label="From date">
                <span class="rxq-dot"></span>
                <input type="date" name="to" value="<?= htmlspecialchars($dateTo) ?>" aria-label="To date">
            </div>

            <button type="submit" class="rxq-btn-filter"><i class="bi bi-funnel"></i> Filter</button>
        </form>
    </div>

    <!-- ============ QUEUE TABLE ============ -->
    <div class="adm-card">
        <div class="adm-table-wrap">
            <table class="adm-table rxq-table">
                <thead>
                    <tr>
                        <th>Patient</th>
                        <th>Type</th>
                        <th>Item</th>
                        <th>Dosage / Instructions</th>
                        <th>Prescribed By</th>
                        <th>Date &amp; Time</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($orders)): ?>
                    <tr>
                        <td colspan="8" class="adm-table-empty">No <?= htmlspecialchars($activeTab) ?> prescriptions match these filters.</td>
                    </tr>
                    <?php endif; ?>
                    <?php foreach ($orders as $r): $status = $statusMeta[$r['status']] ?? $statusMeta['pending']; ?>
                    <tr>
                        <td>
                            <div class="rxq-strong"><?= htmlspecialchars($r['patientName']) ?></div>
                        </td>
                        <td>
                            <span class="rxq-badge" style="background:<?= $r['type'] === 'vaccine' ? '#E8F0FE' : '#EFE7FB' ?>; color:<?= $r['type'] === 'vaccine' ? '#1A73E8' : '#7C3AED' ?>;"><?= htmlspecialchars($r['typeLabel']) ?></span>
                            <?php if ($r['isWalkIn']): ?>
                            <span class="rxq-badge" style="background:#FDF3DC; color:#B7791F; margin-left:4px;" title="Dispensed directly, no doctor's prescription">Walk-in</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($r['itemName']) ?></td>
                        <td>
                            <div><?= htmlspecialchars($r['dosage']) ?> &bull; <?= htmlspecialchars($r['frequency']) ?></div>
                            <?php if (!empty($r['instructions'])): ?>
                            <div class="rxq-patient-meta"><?= htmlspecialchars($r['instructions']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($r['prescribedBy']) ?></td>
                        <td class="rxq-datetime"><?= htmlspecialchars($r['date']) ?><span class="rxq-dot"></span><?= htmlspecialchars($r['time']) ?></td>
                        <td><span class="rxq-badge" style="background:<?= htmlspecialchars($status['bg']) ?>; color:<?= htmlspecialchars($status['color']) ?>;"><?= htmlspecialchars($status['label']) ?></span></td>
                        <td>
                            <div class="adm-action-btns">
                                <button type="button" class="rxq-btn-view"
                                    data-patient-name="<?= htmlspecialchars($r['patientName']) ?>"
                                    data-type-label="<?= htmlspecialchars($r['typeLabel']) ?>"
                                    data-item-name="<?= htmlspecialchars($r['itemName']) ?>"
                                    data-dosage="<?= htmlspecialchars($r['dosage']) ?>"
                                    data-frequency="<?= htmlspecialchars($r['frequency']) ?>"
                                    data-duration="<?= htmlspecialchars($r['duration']) ?>"
                                    data-route="<?= htmlspecialchars($r['route']) ?>"
                                    data-instructions="<?= htmlspecialchars($r['instructions'] ?? '') ?>"
                                    data-prescribed-by="<?= htmlspecialchars($r['prescribedBy']) ?>"
                                    data-facility="<?= htmlspecialchars($r['facility']) ?>"
                                    data-date="<?= htmlspecialchars($r['date']) ?>"
                                    data-time="<?= htmlspecialchars($r['time']) ?>"
                                    data-status-label="<?= htmlspecialchars($status['label']) ?>"
                                    data-status-bg="<?= htmlspecialchars($status['bg']) ?>"
                                    data-status-color="<?= htmlspecialchars($status['color']) ?>"
                                    data-dispensed-date="<?= htmlspecialchars($r['dispensedDate']) ?>"
                                    data-dispensed-time="<?= htmlspecialchars($r['dispensedTime']) ?>"
                                    data-dispensed-by="<?= htmlspecialchars($r['dispensedBy']) ?>"
                                    data-order-id="<?= htmlspecialchars($r['id']) ?>"
                                    data-is-pending="<?= $r['status'] === 'pending' ? '1' : '0' ?>"
                                    data-suggested-item-id="<?= htmlspecialchars($r['suggestedInventoryId']) ?>"
                                    data-dispensed-quantity="<?= htmlspecialchars((string) ($r['dispensedQuantity'] ?? '')) ?>"
                                    data-dispensed-item-name="<?= htmlspecialchars($r['dispensedItemName'] ?? '') ?>">View Details</button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="adm-table-footer">
            <span class="adm-showing-text"><?= $ordersTotal > 0 ? 'Showing ' . (($ordersCurrentPage - 1) * $ordersPerPage + 1) . ' to ' . min($ordersCurrentPage * $ordersPerPage, $ordersTotal) . ' of ' . $ordersTotal . ' entries' : 'No entries to show' ?></span>
            <nav class="adm-pagination" aria-label="Queue pages">
                <a href="?<?= hc_rxq_query(['page' => 1]) ?>" class="page-btn" aria-label="First page"><i class="bi bi-chevron-bar-left"></i></a>
                <a href="?<?= hc_rxq_query(['page' => max(1, $ordersCurrentPage - 1)]) ?>" class="page-btn" aria-label="Previous page"><i class="bi bi-chevron-left"></i></a>
                <?php foreach ($ordersPaginationWindow as $p): ?>
                    <?php if ($p === '...'): ?>
                    <span class="page-ellipsis">&hellip;</span>
                    <?php else: ?>
                    <a href="?<?= hc_rxq_query(['page' => $p]) ?>" class="page-btn <?= $p === $ordersCurrentPage ? 'active' : '' ?>"><?= $p ?></a>
                    <?php endif; ?>
                <?php endforeach; ?>
                <a href="?<?= hc_rxq_query(['page' => min($ordersTotalPages, $ordersCurrentPage + 1)]) ?>" class="page-btn" aria-label="Next page"><i class="bi bi-chevron-right"></i></a>
                <a href="?<?= hc_rxq_query(['page' => $ordersTotalPages]) ?>" class="page-btn" aria-label="Last page"><i class="bi bi-chevron-bar-right"></i></a>
            </nav>
        </div>
    </div>

</main>

<!-- =============================================================
     MODAL: Prescription Details
     -------------------------------------------------------------
     Populated purely from the clicked "View Details" button's own
     data-* attributes (set server-side above from $orders/$statusMeta
     rendering it row by row) — no extra network request, same pattern
     as doctor/consultations.php's #startConsultationModal. Dispensing
     only happens from inside this modal (its own Dispense button,
     shown only for a still-pending order) — assets/js/prescription-queue.js.
     ============================================================= -->
<div class="modal fade" id="rxqDetailModal" tabindex="-1" aria-labelledby="rxqDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="rxqDetailModalLabel">Prescription Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:16px;">
                    <div style="font-size:16px; font-weight:700;" id="rxqDetailPatientName"></div>
                    <span class="rxq-badge" id="rxqDetailStatusBadge"></span>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:14px;">
                    <div>
                        <div class="rxq-patient-meta">Type</div>
                        <div id="rxqDetailType"></div>
                    </div>
                    <div>
                        <div class="rxq-patient-meta">Item</div>
                        <div id="rxqDetailItemName" style="font-weight:600;"></div>
                    </div>
                    <div>
                        <div class="rxq-patient-meta">Dosage</div>
                        <div id="rxqDetailDosage"></div>
                    </div>
                    <div>
                        <div class="rxq-patient-meta">Frequency</div>
                        <div id="rxqDetailFrequency"></div>
                    </div>
                    <div>
                        <div class="rxq-patient-meta">Duration</div>
                        <div id="rxqDetailDuration"></div>
                    </div>
                    <div>
                        <div class="rxq-patient-meta">Route</div>
                        <div id="rxqDetailRoute"></div>
                    </div>
                    <div>
                        <div class="rxq-patient-meta">Prescribed By</div>
                        <div id="rxqDetailPrescribedBy"></div>
                    </div>
                    <div>
                        <div class="rxq-patient-meta">Facility</div>
                        <div id="rxqDetailFacility"></div>
                    </div>
                    <div>
                        <div class="rxq-patient-meta">Date &amp; Time Prescribed</div>
                        <div id="rxqDetailDateTime"></div>
                    </div>
                </div>

                <div id="rxqDetailInstructionsWrap" style="margin-bottom:14px;">
                    <div class="rxq-patient-meta">Instructions</div>
                    <div id="rxqDetailInstructions"></div>
                </div>

                <div id="rxqDetailDispensedWrap" style="display:none; padding-top:10px; border-top:1px solid var(--adm-border);">
                    <div class="rxq-patient-meta">Dispensed</div>
                    <div id="rxqDetailDispensed"></div>
                </div>

                <!-- Only shown for a still-pending order — links this order's
                     free-text item to a real medicines/{id} doc and collects
                     how much to subtract from its currentStock. "Not in
                     inventory" stays a valid choice (assets/js/prescription-queue.js) —
                     prescribed items aren't guaranteed to have a matching
                     tracked inventory record, and dispensing shouldn't be
                     blocked when they don't. -->
                <div id="rxqDispenseFormWrap" style="display:none; padding-top:10px; border-top:1px solid var(--adm-border);">
                    <div class="rxq-patient-meta" style="margin-bottom:6px;">Dispense From Inventory</div>
                    <div style="display:flex; gap:10px; align-items:flex-start; flex-wrap:wrap;">
                        <div style="flex:1; min-width:180px;">
                            <label for="rxqDispenseItemSelect" style="font-size:11px; color:var(--adm-text-muted); display:block; margin-bottom:4px;">Inventory Item</label>
                            <select class="form-select form-select-sm" id="rxqDispenseItemSelect"></select>
                            <div id="rxqDispenseStockNote" style="font-size:11px; color:var(--adm-text-muted); margin-top:4px;"></div>
                        </div>
                        <div style="width:110px;">
                            <label for="rxqDispenseQtyInput" style="font-size:11px; color:var(--adm-text-muted); display:block; margin-bottom:4px;">Quantity</label>
                            <input type="number" min="1" step="1" class="form-control form-control-sm" id="rxqDispenseQtyInput" placeholder="e.g. 10">
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="rxq-btn-dispense" id="rxqDetailDispenseBtn" style="display:none;"><i class="bi bi-check-circle"></i> Dispense</button>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<link rel="stylesheet" href="<?= asset_url('/assets/css/hc-dialogs.css') ?>">
<script src="<?= asset_url('/assets/js/hc-dialogs.js') ?>"></script>
<script>
    // Real, live medicine/vaccine inventory — the Dispense form's item
    // dropdown builds from this (assets/js/prescription-queue.js), same
    // hand-off pattern as admin/facility_analytics.php's own chart data.
    window.PRESCRIPTION_QUEUE_DATA = <?= json_encode([
        'medicines' => $medicineInventoryOptions,
        'vaccines' => $vaccineInventoryOptions,
    ]) ?>;
</script>
<script src="<?= asset_url('/assets/js/admin-sidebar-toggle.js') ?>"></script>
<script src="<?= asset_url('/assets/js/prescription-queue.js') ?>"></script>
</body>
</html>
