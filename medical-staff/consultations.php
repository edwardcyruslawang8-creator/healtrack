<?php
/**
 * =====================================================================
 * HEALTrack — Health Information System
 * Consultations (medical-staff/consultations.php)
 * ---------------------------------------------------------------------
 * Fulfills the "Consultations" item in medical-staff/dashboard.php's
 * sidebar nav — reuses the exact same sidebar/topbar shell and Medical
 * Staff nav list as the rest of medical-staff/*.php (280px dark-red
 * gradient sidebar, "adm-"/"mst-" prefixed atoms). This is a
 * TOP-LEVEL page: a facility-wide log of consultations across every
 * patient, not a single patient's own history — so unlike
 * allergies.php/medications.php/etc it has no patient-profile header
 * card. Page-specific pieces (stat cards, filter toolbar, table cells,
 * modals) are "cns-" prefixed in assets/css/consultations.css.
 *
 * CONNECTED to the real "consultations" Firestore collection — see
 * includes/medical-staff/consultations_medstaff_firestore.php's header
 * comment and includes/firestore/consultations.php. The search box, type/
 * status filters, and date range now really filter the table. "Set
 * Consultation" creates a real consultation record; each row's Edit
 * modal updates one; "Complete" flips its status to Completed — all
 * via assets/js/consultations_medstaff.js and
 * api/create-consultation.php / api/update-consultation.php.
 * =====================================================================
 */
session_start();

require_once __DIR__ . '/../config/constants.php'; // BASE_URL, asset_url()

// Only a signed-in medical staff member may view this page.
if (($_SESSION['role'] ?? null) !== 'healthcare_staff') {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

require_once __DIR__ . '/../includes/medical-staff/consultations_medstaff_firestore.php';
// $staffProfile, $dashboardDateLabel, $facilityOptions,
// $consultationTypeMeta, $consultationStatusMeta, $consultationPriorityMeta,
// $consultationTypeOptions, $consultationStatusOptions, $patientOptions,
// $patientsById, $physicianOptions, $statCards,
// $searchTerm, $typeFilter, $statusFilter, $dateFrom, $dateTo,
// $consultations, $consultationsTotal, $consultationsPerPage,
// $consultationsCurrentPage, $consultationsTotalPages

// Sidebar nav — same Medical Staff nav list as medical-staff/dashboard.php.
// Consultations is active here.
$staffNavItems = [
    ['icon' => 'bi-grid-fill',            'label' => 'Dashboard',          'href' => 'dashboard.php'],
    ['icon' => 'bi-person-lines-fill',    'label' => 'Patient Management', 'href' => 'patient_management.php'],
    ['icon' => 'bi-calendar2-heart-fill', 'label' => 'Consultations',      'href' => 'consultations.php', 'active' => true],
    [
        'icon' => 'bi-bar-chart-fill',
        'label' => 'Analytics & Reports',
        'id' => 'mstNavAnalyticsReports',
        'children' => [
            ['label' => 'Analytics', 'href' => 'analytics_reports.php'],
            ['label' => 'My Reports', 'href' => 'patients_report.php'],
        ],
    ],
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
<title>Consultations — HEALTrack</title>

<!-- Google Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<!-- Bootstrap 5 -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<!-- Font Awesome (available per project spec; Bootstrap Icons cover this page's icons) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<!-- Shared admin shell (sidebar, topbar, cards, table, pagination, etc.) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/admin-dashboard.css') ?>">
<!-- Medical Staff Dashboard styles, reused here for the nav badge + topbar select buttons -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/medical_staff_dashboard.css') ?>">
<!-- Consultations page styles (stat cards, filter toolbar, table cells, modals) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/consultations.css') ?>">
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
        <?php if (!empty($item['children'])): ?>
        <li>
            <a href="#<?= htmlspecialchars($item['id'] ?? 'mstNavAnalyticsReports') ?>" class="adm-nav-link adm-nav-toggle <?= !empty($item['active']) ? 'active' : '' ?> <?= empty($item['open']) ? 'collapsed' : '' ?>" data-bs-toggle="collapse" role="button" aria-expanded="<?= !empty($item['open']) ? 'true' : 'false' ?>" aria-controls="<?= htmlspecialchars($item['id'] ?? 'mstNavAnalyticsReports') ?>">
                <i class="bi <?= htmlspecialchars($item['icon']) ?>"></i>
                <span><?= htmlspecialchars($item['label']) ?></span>
                <i class="bi bi-chevron-down adm-nav-caret"></i>
            </a>
            <div class="collapse <?= !empty($item['open']) ? 'show' : '' ?>" id="<?= htmlspecialchars($item['id'] ?? 'mstNavAnalyticsReports') ?>">
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
                <span class="mst-nav-badge"><?= (int) $item['badge'] ?></span>
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
            <h1>Consultations</h1>
            <p class="adm-page-subtitle">Manage and monitor patient consultations.</p>
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

    <!-- ============ SUMMARY STAT CARDS ============ -->
    <div class="cns-stats-row">
        <?php foreach ($statCards as $stat): ?>
        <div class="cns-stat-card">
            <div class="cns-stat-icon" style="background:<?= htmlspecialchars($stat['iconBg']) ?>; color:<?= htmlspecialchars($stat['iconColor']) ?>;">
                <i class="bi <?= htmlspecialchars($stat['icon']) ?>"></i>
            </div>
            <div class="cns-stat-label"><?= htmlspecialchars($stat['label']) ?></div>
            <div class="cns-stat-value"><?= number_format((int) $stat['value']) ?></div>
            <?php if ($stat['trendLabel'] !== null): ?>
            <div class="cns-stat-sub <?= $stat['trendDir'] === 'down' ? 'down' : 'up' ?>">
                <i class="bi <?= $stat['trendDir'] === 'down' ? 'bi-arrow-down-short' : 'bi-arrow-up-short' ?>"></i> <?= htmlspecialchars($stat['trendLabel']) ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- ============ FILTER TOOLBAR ============ -->
    <form class="cns-filter-row" method="get" action="consultations.php">
        <div class="cns-search">
            <i class="bi bi-search"></i>
            <input type="text" name="q" value="<?= htmlspecialchars($searchTerm) ?>" placeholder="Search by patient name, ID, or phone...">
        </div>

        <select class="form-select cns-filter-select" name="type" aria-label="Filter by consultation type" onchange="this.form.submit()">
            <?php foreach ($consultationTypeOptions as $key => $label): ?>
            <option value="<?= htmlspecialchars($key) ?>" <?= $key === $typeFilter ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
            <?php endforeach; ?>
        </select>

        <select class="form-select cns-filter-select" name="status" aria-label="Filter by status" onchange="this.form.submit()">
            <?php foreach ($consultationStatusOptions as $key => $label): ?>
            <option value="<?= htmlspecialchars($key) ?>" <?= $key === $statusFilter ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
            <?php endforeach; ?>
        </select>

        <div class="cns-daterange">
            <i class="bi bi-calendar3"></i>
            <input type="date" name="from" value="<?= htmlspecialchars($dateFrom) ?>" aria-label="From date" onchange="this.form.submit()">
            <span class="cns-dot"></span>
            <input type="date" name="to" value="<?= htmlspecialchars($dateTo) ?>" aria-label="To date" onchange="this.form.submit()">
        </div>

        <button type="submit" class="cns-btn-search"><i class="bi bi-search"></i></button>
        <button type="button" class="cns-btn-set" id="btnSetConsultation"><i class="bi bi-plus-lg"></i> Set Consultation</button>
    </form>

    <!-- ============ CONSULTATIONS TABLE ============ -->
    <div class="adm-card">
        <div class="adm-table-wrap">
            <table class="adm-table">
                <thead>
                    <tr>
                        <th>Patient Name</th>
                        <th>Consultation Type</th>
                        <th>Date &amp; Time</th>
                        <th>Assigned Personnel</th>
                        <th>Chief Complaint</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody id="cnsTableBody">
                    <?php if (empty($consultations)): ?>
                    <tr>
                        <td colspan="6" class="adm-table-empty">No consultations found.</td>
                    </tr>
                    <?php endif; ?>
                    <?php foreach ($consultations as $c): $type = $consultationTypeMeta[$c['consultationType']] ?? $consultationTypeMeta['walk_in']; $status = $consultationStatusMeta[$c['status']] ?? $consultationStatusMeta['pending']; ?>
                    <tr data-id="<?= htmlspecialchars($c['id']) ?>"
                        data-code="<?= htmlspecialchars($c['consultationCode']) ?>"
                        data-patient-id="<?= htmlspecialchars($c['patientId']) ?>"
                        data-patient-name="<?= htmlspecialchars($c['patientName']) ?>"
                        data-type="<?= htmlspecialchars($c['consultationType']) ?>"
                        data-datetime="<?= htmlspecialchars($c['dateTime']) ?>"
                        data-personnel="<?= htmlspecialchars($c['assignedPersonnel']) ?>"
                        data-complaint="<?= htmlspecialchars($c['chiefComplaint']) ?>"
                        data-status="<?= htmlspecialchars($c['status']) ?>"
                        data-priority="<?= htmlspecialchars($c['priority']) ?>"
                        data-followup="<?= htmlspecialchars($c['nextFollowUp']) ?>"
                        data-notes="<?= htmlspecialchars($c['notes']) ?>"
                        data-vital-temp="<?= htmlspecialchars((string) ($c['vitals']['temperature'] ?? '')) ?>"
                        data-vital-bpsys="<?= htmlspecialchars((string) ($c['vitals']['bloodPressureSystolic'] ?? '')) ?>"
                        data-vital-bpdia="<?= htmlspecialchars((string) ($c['vitals']['bloodPressureDiastolic'] ?? '')) ?>"
                        data-vital-hr="<?= htmlspecialchars((string) ($c['vitals']['heartRate'] ?? '')) ?>"
                        data-vital-weight="<?= htmlspecialchars((string) ($c['vitals']['weight'] ?? '')) ?>"
                        data-vital-height="<?= htmlspecialchars((string) ($c['vitals']['height'] ?? '')) ?>"
                        data-vital-bmi="<?= htmlspecialchars((string) ($c['vitals']['bmi'] ?? '')) ?>"
                        data-patient-gender="<?= htmlspecialchars($patientsById[$c['patientId']]['gender'] ?? '') ?>"
                        data-patient-age="<?= htmlspecialchars((string) ($patientsById[$c['patientId']]['age'] ?? '')) ?>"
                        data-patient-birthdate="<?= htmlspecialchars($patientsById[$c['patientId']]['birthDate'] ?? '') ?>">
                        <td><?= htmlspecialchars($c['patientName']) ?></td>
                        <td><span class="cns-badge" style="background:<?= htmlspecialchars($type['bg']) ?>; color:<?= htmlspecialchars($type['color']) ?>;"><?= htmlspecialchars($type['label']) ?></span></td>
                        <td><?= $c['dateTime'] !== '' ? htmlspecialchars(date('M j, Y g:i A', strtotime($c['dateTime']))) : '—' ?></td>
                        <td><?= htmlspecialchars($c['assignedPersonnel']) ?></td>
                        <td><?= htmlspecialchars($c['chiefComplaint']) ?></td>
                        <td><span class="cns-badge" style="background:<?= htmlspecialchars($status['bg']) ?>; color:<?= htmlspecialchars($status['color']) ?>;"><?= htmlspecialchars($status['label']) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="adm-table-footer">
            <span class="adm-showing-text"><?= $consultationsTotal > 0 ? 'Showing ' . (($consultationsCurrentPage - 1) * $consultationsPerPage + 1) . ' to ' . (($consultationsCurrentPage - 1) * $consultationsPerPage + count($consultations)) . ' of ' . $consultationsTotal . ' entries' : 'No entries to show' ?></span>
            <nav class="adm-pagination" aria-label="Consultation log pages">
                <?php
                $qs = $_GET;
                if (!function_exists('cns_page_url')) {
                    function cns_page_url($qs, $page) { $qs['page'] = $page; return 'consultations.php?' . http_build_query($qs); }
                }
                ?>
                <?php
                // Windowed page numbers (current page +/- 1, with
                // first/last plus an ellipsis when truncated) instead of
                // a raw 1..N loop — with up to ~75 pages of real
                // consultation history, printing every page number
                // inline overflowed this card's width and spilled past
                // its right edge (same windowing hc_render_pagination_nav()
                // in includes/shared/pagination_helpers.php already uses
                // for the Analytics & Reports tables).
                $cnsWindowStart = max(1, min($consultationsCurrentPage - 1, max(1, $consultationsTotalPages - 2)));
                $cnsWindowEnd = min($consultationsTotalPages, $cnsWindowStart + 2);
                ?>
                <a href="<?= cns_page_url($qs, max(1, $consultationsCurrentPage - 1)) ?>" class="page-btn" aria-label="Previous page"><i class="bi bi-chevron-left"></i></a>
                <?php if ($cnsWindowStart > 1): ?>
                <a href="<?= cns_page_url($qs, 1) ?>" class="page-btn">1</a>
                <?php if ($cnsWindowStart > 2): ?>
                <span class="page-ellipsis">&hellip;</span>
                <?php endif; ?>
                <?php endif; ?>
                <?php for ($p = $cnsWindowStart; $p <= $cnsWindowEnd; $p++): ?>
                <a href="<?= cns_page_url($qs, $p) ?>" class="page-btn <?= $p === $consultationsCurrentPage ? 'active' : '' ?>"><?= $p ?></a>
                <?php endfor; ?>
                <?php if ($cnsWindowEnd < $consultationsTotalPages): ?>
                <?php if ($cnsWindowEnd < $consultationsTotalPages - 1): ?>
                <span class="page-ellipsis">&hellip;</span>
                <?php endif; ?>
                <a href="<?= cns_page_url($qs, $consultationsTotalPages) ?>" class="page-btn"><?= $consultationsTotalPages ?></a>
                <?php endif; ?>
                <a href="<?= cns_page_url($qs, min($consultationsTotalPages, $consultationsCurrentPage + 1)) ?>" class="page-btn" aria-label="Next page"><i class="bi bi-chevron-right"></i></a>
            </nav>
        </div>
    </div>

</main>

<!-- =============================================================
     MODAL: Set / Edit Consultation (shared form)
     -------------------------------------------------------------
     Patient picker is a card-style custom dropdown (not a native
     <select>) since a <select>'s closed state can't show the
     avatar/gender/age/birth-date line the design calls for — Patient
     ID itself isn't shown anywhere in this picker (only tracked
     internally via the hidden #cnsFormPatientId input), and a search
     box pinned to the top of the list (assets/js/consultations_medstaff.js's
     filterPatientOptions()) finds a patient by name faster than
     scrolling a long roster. Picking a dropdown-item fills the card
     and two hidden inputs (#cnsFormPatientId/#cnsFormPatientName);
     that hidden id is validated manually since hidden inputs are
     exempt from native form constraint validation (see
     validatePatientCard()). Assigned Physician options are real doctor
     accounts at this staff member's own facility
     (includes/firestore/consultations.php's hc_get_physician_options()),
     not fabricated names.
     ============================================================= -->
<div class="modal fade cns-modal" id="consultationFormModal" tabindex="-1" aria-labelledby="consultationFormModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form id="consultationForm" novalidate>
                <input type="hidden" id="cnsFormId" value="">
                <input type="hidden" id="cnsFormPatientId" value="">
                <input type="hidden" id="cnsFormPatientName" value="">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="consultationFormModalLabel">Set Consultation</h5>
                        <p class="cns-modal-subtitle" id="consultationFormModalSubtitle">Create a new consultation record for the patient.</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="consultationFormError" class="alert alert-danger py-2 px-3 small d-none" role="alert"></div>

                    <div class="mb-3">
                        <label class="cns-form-label">Patient<span class="text-danger">*</span></label>
                        <div class="dropdown">
                            <button type="button" class="cns-patient-card" id="cnsPatientCardBtn" data-bs-toggle="dropdown" aria-expanded="false">
                                <div class="cns-patient-avatar"><i class="bi bi-person-fill"></i></div>
                                <div class="cns-patient-info">
                                    <div class="cns-patient-name" id="cnsPatientCardName">Select a patient…</div>
                                    <div class="cns-patient-meta" id="cnsPatientCardMeta"></div>
                                </div>
                                <i class="bi bi-chevron-down cns-patient-chevron"></i>
                            </button>
                            <ul class="dropdown-menu cns-patient-menu" id="cnsPatientMenu">
                                <li class="cns-patient-search-wrap">
                                    <i class="bi bi-search"></i>
                                    <input type="text" class="cns-patient-search" id="cnsPatientSearchInput" placeholder="Search patient by name…" autocomplete="off">
                                </li>
                                <?php if (empty($patientOptions)): ?>
                                <li><span class="dropdown-item-text text-muted small">No patients registered yet.</span></li>
                                <?php endif; ?>
                                <?php foreach ($patientOptions as $p):
                                    $pIntakeVitals = $p['intakeVitals'];
                                    $pRegisteredToday = $p['dateRegisteredRaw'] !== '' && substr($p['dateRegisteredRaw'], 0, 10) === date('Y-m-d');
                                ?>
                                <li>
                                    <button type="button" class="dropdown-item cns-patient-option"
                                        data-id="<?= htmlspecialchars($p['id']) ?>"
                                        data-name="<?= htmlspecialchars($p['name']) ?>"
                                        data-gender="<?= htmlspecialchars($p['gender']) ?>"
                                        data-age="<?= (int) $p['age'] ?>"
                                        data-birthdate="<?= htmlspecialchars($p['birthDate']) ?>"
                                        data-registered-today="<?= $pRegisteredToday ? '1' : '0' ?>"
                                        data-intake-temp="<?= htmlspecialchars((string) ($pIntakeVitals['temperature'] ?? '')) ?>"
                                        data-intake-bpsys="<?= htmlspecialchars((string) ($pIntakeVitals['bloodPressureSystolic'] ?? '')) ?>"
                                        data-intake-bpdia="<?= htmlspecialchars((string) ($pIntakeVitals['bloodPressureDiastolic'] ?? '')) ?>"
                                        data-intake-hr="<?= htmlspecialchars((string) ($pIntakeVitals['heartRate'] ?? '')) ?>"
                                        data-intake-weight="<?= htmlspecialchars((string) ($pIntakeVitals['weight'] ?? '')) ?>"
                                        data-intake-height="<?= htmlspecialchars((string) ($pIntakeVitals['height'] ?? '')) ?>"
                                        data-intake-bmi="<?= htmlspecialchars((string) ($pIntakeVitals['bmi'] ?? '')) ?>">
                                        <?= htmlspecialchars($p['name']) ?>
                                    </button>
                                </li>
                                <?php endforeach; ?>
                                <li class="cns-patient-no-results d-none"><span class="dropdown-item-text text-muted small">No matching patients.</span></li>
                            </ul>
                        </div>
                        <div class="invalid-feedback" id="cnsPatientCardError">Please select a patient.</div>
                    </div>

                    <!-- ============ VITAL SIGNS ============
                         Same field set (and same required-ness) as the
                         "Add Patient" modal's own Vital Signs step
                         (includes/medical-staff/add_patient_modal.php) —
                         BMI is the only exception, auto-calculated from
                         Weight/Height so it's never itself a required
                         input. Required here too: editing an existing
                         consultation that predates this field will force
                         medical staff to fill it in once before saving
                         again, same tradeoff Add Patient already makes.
                         Saved onto this consultation's own "vitals" field
                         (api/create-consultation.php / api/update-consultation.php)
                         — doctor/active_consultation.php's "Vital Signs
                         (from Front Desk)" card reads it back for THIS
                         visit, instead of only ever showing the patient's
                         stale registration-time intake snapshot. Placed
                         first (right after picking the patient) to match
                         the real front-desk workflow: take vitals, THEN
                         record why the patient is here. -->
                    <div class="mb-3">
                        <label class="cns-form-label">Vital Signs<span class="text-danger">*</span></label>
                        <div class="cns-form-grid">
                            <div class="cns-form-col">
                                <div class="mb-3">
                                    <label class="cns-form-label" for="cnsFormVitalBpSys">Blood Pressure (mmHg)<span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="number" class="form-control cns-form-control" id="cnsFormVitalBpSys" placeholder="Systolic" min="1" max="300" inputmode="numeric" required>
                                        <span class="input-group-text">/</span>
                                        <input type="number" class="form-control cns-form-control" id="cnsFormVitalBpDia" placeholder="Diastolic" min="1" max="300" inputmode="numeric" required>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="cns-form-label" for="cnsFormVitalHr">Heart Rate (bpm)<span class="text-danger">*</span></label>
                                    <input type="number" class="form-control cns-form-control" id="cnsFormVitalHr" placeholder="Enter heart rate" min="30" max="220" inputmode="numeric" required>
                                </div>
                                <div class="mb-0">
                                    <label class="cns-form-label" for="cnsFormVitalTemp">Temperature (&deg;C)<span class="text-danger">*</span></label>
                                    <input type="number" step="0.1" class="form-control cns-form-control" id="cnsFormVitalTemp" placeholder="Enter temperature" min="30" max="45" inputmode="decimal" required>
                                </div>
                            </div>
                            <div class="cns-form-col">
                                <div class="mb-3">
                                    <label class="cns-form-label" for="cnsFormVitalWeight">Weight (kg)<span class="text-danger">*</span></label>
                                    <input type="number" step="0.1" class="form-control cns-form-control" id="cnsFormVitalWeight" placeholder="Enter weight" min="0.1" inputmode="decimal" required>
                                </div>
                                <div class="mb-3">
                                    <label class="cns-form-label" for="cnsFormVitalHeight">Height (cm)<span class="text-danger">*</span></label>
                                    <input type="number" step="0.1" class="form-control cns-form-control" id="cnsFormVitalHeight" placeholder="Enter height" min="1" inputmode="decimal" required>
                                </div>
                                <div class="mb-0">
                                    <label class="cns-form-label" for="cnsFormVitalBmi">BMI (kg/m&sup2;)</label>
                                    <input type="text" class="form-control cns-form-control" id="cnsFormVitalBmi" placeholder="Auto-calculated" readonly>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="cns-form-label" for="cnsFormComplaint">Chief Complaint<span class="text-danger">*</span></label>
                        <textarea class="form-control cns-form-control" id="cnsFormComplaint" rows="3" placeholder="Enter chief complaint" required></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="cns-form-label" for="cnsFormPriority">Priority <span class="text-muted fw-normal">(Optional)</span></label>
                        <select class="form-select cns-form-control" id="cnsFormPriority">
                            <option value="" selected>None</option>
                            <?php foreach ($consultationPriorityMeta as $key => $meta): ?>
                            <option value="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($meta['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="cns-form-grid">
                        <div class="cns-form-col">
                            <div class="mb-3">
                                <label class="cns-form-label" for="cnsFormType">Consultation Type<span class="text-danger">*</span></label>
                                <!-- This facility's medical staff only ever handle walk-in
                                     consultations — there's nothing else to choose, so this
                                     is a single fixed option rather than a real choice.
                                     hc_consultation_type_meta() still has Follow-up/
                                     Emergency/Scheduled/Teleconsultation for badge lookups
                                     elsewhere (existing/historical data, filters), just not
                                     offered here. -->
                                <select class="form-select cns-form-control" id="cnsFormType" required>
                                    <option value="walk_in" selected><?= htmlspecialchars($consultationTypeMeta['walk_in']['label']) ?></option>
                                </select>
                            </div>
                            <div class="mb-0">
                                <label class="cns-form-label" for="cnsFormDateTime">Date &amp; Time<span class="text-danger">*</span></label>
                                <input type="datetime-local" class="form-control cns-form-control" id="cnsFormDateTime" min="<?= date('Y-m-d\TH:i') ?>" required>
                            </div>
                        </div>
                        <div class="cns-form-col">
                            <div class="mb-3">
                                <label class="cns-form-label" for="cnsFormPersonnel">Assigned Physician<span class="text-danger">*</span></label>
                                <select class="form-select cns-form-control" id="cnsFormPersonnel" required>
                                    <option value="" selected disabled>Select assigned physician</option>
                                    <?php foreach ($physicianOptions as $doc): ?>
                                    <option value="<?= htmlspecialchars($doc['name']) ?>"><?= htmlspecialchars($doc['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (empty($physicianOptions)): ?>
                                <div class="cns-form-help">No doctor accounts found at your facility yet.</div>
                                <?php endif; ?>
                            </div>
                            <div class="mb-0">
                                <label class="cns-form-label" for="cnsFormFollowUp">Next Follow-up <span class="text-muted fw-normal">(Optional)</span></label>
                                <input type="datetime-local" class="form-control cns-form-control" id="cnsFormFollowUp" min="<?= date('Y-m-d\TH:i') ?>">
                            </div>
                        </div>
                    </div>

                    <div class="mb-0">
                        <label class="cns-form-label" for="cnsFormNotes">Notes <span class="text-muted fw-normal">(Optional)</span></label>
                        <textarea class="form-control cns-form-control" id="cnsFormNotes" rows="4" placeholder="Add any additional notes here…"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="cns-btn-set" id="cnsFormSubmitBtn"><i class="bi bi-check-lg"></i> <span id="cnsFormSubmitLabel">Set Consultation</span></button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- =============================================================
     MODAL: Consultation Details (View)
     ============================================================= -->
<div class="modal fade cns-modal" id="consultationDetailsModal" tabindex="-1" aria-labelledby="consultationDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="consultationDetailsModalLabel">Consultation Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="cns-modal-section">
                    <div class="cns-field-grid">
                        <div class="cns-field"><span class="cns-field-label">Patient</span><span class="cns-field-value" id="cnsViewPatient">—</span></div>
                        <div class="cns-field"><span class="cns-field-label">Consultation Type</span><span class="cns-field-value" id="cnsViewType">—</span></div>
                        <div class="cns-field"><span class="cns-field-label">Priority</span><span class="cns-field-value" id="cnsViewPriority">—</span></div>
                        <div class="cns-field"><span class="cns-field-label">Date &amp; Time</span><span class="cns-field-value" id="cnsViewDateTime">—</span></div>
                        <div class="cns-field"><span class="cns-field-label">Next Follow-up</span><span class="cns-field-value" id="cnsViewFollowUp">—</span></div>
                        <div class="cns-field"><span class="cns-field-label">Assigned Physician</span><span class="cns-field-value" id="cnsViewPersonnel">—</span></div>
                        <div class="cns-field"><span class="cns-field-label">Status</span><span class="cns-field-value" id="cnsViewStatus">—</span></div>
                    </div>
                </div>
                <div class="cns-modal-section">
                    <h4><i class="bi bi-chat-left-text-fill"></i> Chief Complaint</h4>
                    <p id="cnsViewComplaint">—</p>
                </div>
                <div class="cns-modal-section">
                    <h4><i class="bi bi-journal-text"></i> Notes</h4>
                    <p id="cnsViewNotes">—</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
    window.CNS_TYPE_META = <?= json_encode($consultationTypeMeta) ?>;
    window.CNS_STATUS_META = <?= json_encode($consultationStatusMeta) ?>;
    window.CNS_PRIORITY_META = <?= json_encode($consultationPriorityMeta) ?>;
    window.CNS_STAFF_NAME = <?= json_encode($staffProfile['name']) ?>;
</script>
<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<link rel="stylesheet" href="<?= asset_url('/assets/css/hc-dialogs.css') ?>">
<script src="<?= asset_url('/assets/js/hc-dialogs.js') ?>"></script>
<script src="<?= asset_url('/assets/js/admin-sidebar-toggle.js') ?>"></script>
<script src="<?= asset_url('/assets/js/consultations_medstaff.js') ?>"></script>
<script src="<?= asset_url('/assets/js/search-form-guard.js') ?>"></script>
</body>
</html>
