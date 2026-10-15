<?php
/**
 * =====================================================================
 * HEALTrack — Health Information System
 * Consultations (doctor/consultations.php)
 * ---------------------------------------------------------------------
 * Fulfills the "Consultations" item in doctor/dashboard.php's sidebar
 * nav — reuses the exact same sidebar/topbar shell and Doctor nav list
 * as the rest of doctor/*.php (280px dark-red gradient sidebar,
 * "adm-"/"doc-" prefixed atoms). Unlike medical-staff/consultations.php
 * (a facility-wide log with no patient header card), this page is
 * scoped to the signed-in doctor's own caseload and centers on
 * whichever patient is next in their queue — see
 * includes/doctor/consultations_doctor_firestore.php's header comment
 * for exactly how "Next to Consult" and the "Patient Information" card
 * are derived (intentionally the SAME patient, so the page never shows
 * two unrelated people at once).
 *
 * Reuses THREE existing stylesheets unchanged rather than writing new
 * CSS: assets/css/patient_profile.css for the "Patient Information"
 * card (same "ppf-" prefixed component medical-staff/patient_profile.php
 * uses), assets/css/consultations.css for the filter toolbar/"Next to
 * Consult" highlight card/table badges (already "cns-" prefixed and
 * generic — no medical-staff-specific styling in it), and
 * assets/css/doctor_dashboard.css for the nav badge + topbar select
 * buttons every other doctor/*.php page uses.
 *
 * CONNECTED to real Firestore data throughout — no placeholders. "Start
 * Consultation" (assets/js/doctor_consultations.js) calls
 * api/update-consultation.php to flip a pending consultation to
 * "ongoing".
 * =====================================================================
 */
session_start();

require_once __DIR__ . '/../config/constants.php'; // BASE_URL, asset_url()

// Only a signed-in doctor may view this page.
if (($_SESSION['role'] ?? null) !== 'doctor') {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

require_once __DIR__ . '/../includes/doctor/consultations_doctor_firestore.php';
// $doctorProfile, $dashboardDateLabel, $facilityOptions,
// $consultationTypeMeta, $consultationStatusMeta, $consultationTypeOptions,
// $consultationStatusOptions, $patientsById, $nextConsult, $nextPatient,
// $ongoingConsult, $ongoingPatient, $patient, $searchTerm, $typeFilter,
// $statusFilter, $dateFrom, $dateTo, $consultations, $consultationsTotal,
// $consultationsPerPage, $consultationsCurrentPage, $consultationsTotalPages

// Sidebar nav — same Doctor nav list as doctor/dashboard.php.
// Consultations is active here.
$doctorNavItems = [
    ['icon' => 'bi-grid-fill',           'label' => 'Dashboard',          'href' => 'dashboard.php'],
    ['icon' => 'bi-person-lines-fill',   'label' => 'Patient Management', 'href' => 'patient_management.php'],
    ['icon' => 'bi-calendar2-heart-fill','label' => 'Consultations',      'href' => 'consultations.php', 'active' => true],
    [
        'icon' => 'bi-bar-chart-fill',
        'label' => 'Analytics & Reports',
        'id' => 'docNavAnalyticsReports',
        'children' => [
            ['label' => 'Analytics', 'href' => 'analytics_reports.php'],
            ['label' => 'My Reports', 'href' => 'patients_report.php'],
        ],
    ],
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
<!-- Doctor Dashboard styles, reused here for the nav badge + topbar select buttons -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/doctor_dashboard.css') ?>">
<!-- Patient Profile styles, reused here for the "Patient Information" header card -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/patient_profile.css') ?>">
<!-- Consultations page styles (filter toolbar, "Next to Consult" card, table badges) — shared verbatim with medical-staff/consultations.php -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/consultations.css') ?>">
<!-- "Starting Consultation" modal styles — doctor-only, no other role has this modal -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/start_consultation_modal.css') ?>">
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
        <?php if (!empty($item['children'])): ?>
        <li>
            <a href="#<?= htmlspecialchars($item['id'] ?? 'docNavAnalyticsReports') ?>" class="adm-nav-link adm-nav-toggle <?= !empty($item['active']) ? 'active' : '' ?> <?= empty($item['open']) ? 'collapsed' : '' ?>" data-bs-toggle="collapse" role="button" aria-expanded="<?= !empty($item['open']) ? 'true' : 'false' ?>" aria-controls="<?= htmlspecialchars($item['id'] ?? 'docNavAnalyticsReports') ?>">
                <i class="bi <?= htmlspecialchars($item['icon']) ?>"></i>
                <span><?= htmlspecialchars($item['label']) ?></span>
                <i class="bi bi-chevron-down adm-nav-caret"></i>
            </a>
            <div class="collapse <?= !empty($item['open']) ? 'show' : '' ?>" id="<?= htmlspecialchars($item['id'] ?? 'docNavAnalyticsReports') ?>">
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
                <span class="doc-nav-badge"><?= (int) $item['badge'] ?></span>
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
            <nav class="ppf-breadcrumb" aria-label="Breadcrumb">
                <a href="dashboard.php">Dashboard</a>
                <span class="ppf-breadcrumb-sep">&gt;</span>
                <span class="ppf-breadcrumb-current">Consultations</span>
            </nav>
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

    <!-- ============ PATIENT INFORMATION / ONGOING / NEXT TO CONSULT ============
         Polled by assets/js/doctor_consultations.js (api/doctor-queue-status.php)
         so this whole section refreshes itself in place — e.g. once
         medical staff sets a new consultation — without the doctor
         needing to reload the page. data-next-id/data-ongoing-id let
         that script tell whether anything actually changed before
         touching the DOM. See includes/doctor/_consultation_queue_cards.php's
         header comment — same partial either way, so a live refresh
         can never show something different than a real page load
         would have. -->
    <div id="cnsQueueStatus" data-next-id="<?= htmlspecialchars($nextConsult['id'] ?? '') ?>" data-ongoing-id="<?= htmlspecialchars($ongoingConsult['id'] ?? '') ?>">
        <?php require __DIR__ . '/../includes/doctor/_consultation_queue_cards.php'; ?>
    </div>

    <!-- ============ CONSULTATION LOGS ============ -->
    <div class="cns-section-header">
        <h2>Consultation Logs</h2>
        <p>Track and manage your patient consultations.</p>
    </div>

    <!-- ============ FILTER TOOLBAR ============ -->
    <form class="cns-filter-row" method="get" action="consultations.php">
        <div class="cns-daterange">
            <i class="bi bi-calendar3"></i>
            <input type="date" name="from" value="<?= htmlspecialchars($dateFrom) ?>" aria-label="From date" onchange="this.form.submit()">
            <span class="cns-dot"></span>
            <input type="date" name="to" value="<?= htmlspecialchars($dateTo) ?>" aria-label="To date" onchange="this.form.submit()">
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

        <div class="cns-search">
            <i class="bi bi-search"></i>
            <input type="text" name="q" value="<?= htmlspecialchars($searchTerm) ?>" placeholder="Search patient name, ID or reason...">
        </div>

        <button type="submit" class="cns-btn-search"><i class="bi bi-search"></i></button>
    </form>

    <!-- ============ CONSULTATION RECORDS TABLE ============ -->
    <div class="adm-card">
        <div class="adm-table-wrap">
            <table class="adm-table">
                <thead>
                    <tr>
                        <th>Patient</th>
                        <th>Age / Gender</th>
                        <th>Consultation Type</th>
                        <th>Arrival Time</th>
                        <th>Reason for Visit</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($consultations)): ?>
                    <tr>
                        <td colspan="6" class="adm-table-empty">No consultations found.</td>
                    </tr>
                    <?php endif; ?>
                    <?php foreach ($consultations as $c):
                        $type = $consultationTypeMeta[$c['consultationType']] ?? $consultationTypeMeta['walk_in'];
                        $status = $consultationStatusMeta[$c['status']] ?? $consultationStatusMeta['pending'];
                        $rowPatient = $patientsById[$c['patientId']] ?? null;
                    ?>
                    <tr>
                        <td>
                            <div class="cns-name-cell">
                                <div class="cns-avatar-sm"><i class="bi bi-person-fill"></i></div>
                                <div class="cns-name-text">
                                    <span class="cns-strong"><?= htmlspecialchars($c['patientName']) ?></span>
                                    <span class="cns-patient-id"><?= htmlspecialchars($c['patientId']) ?></span>
                                </div>
                            </div>
                        </td>
                        <td><?= $rowPatient !== null ? (int) $rowPatient['age'] . ' years / ' . htmlspecialchars($rowPatient['gender']) : '—' ?></td>
                        <td><span class="cns-badge" style="background:<?= htmlspecialchars($type['bg']) ?>; color:<?= htmlspecialchars($type['color']) ?>;"><?= htmlspecialchars($type['label']) ?></span></td>
                        <td><?= $c['dateTime'] !== '' ? htmlspecialchars(date('g:i A', strtotime($c['dateTime']))) : '—' ?></td>
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
                if (!function_exists('doc_cns_page_url')) {
                    function doc_cns_page_url($qs, $page) { $qs['page'] = $page; return 'consultations.php?' . http_build_query($qs); }
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
                <a href="<?= doc_cns_page_url($qs, max(1, $consultationsCurrentPage - 1)) ?>" class="page-btn" aria-label="Previous page"><i class="bi bi-chevron-left"></i></a>
                <?php if ($cnsWindowStart > 1): ?>
                <a href="<?= doc_cns_page_url($qs, 1) ?>" class="page-btn">1</a>
                <?php if ($cnsWindowStart > 2): ?>
                <span class="page-ellipsis">&hellip;</span>
                <?php endif; ?>
                <?php endif; ?>
                <?php for ($p = $cnsWindowStart; $p <= $cnsWindowEnd; $p++): ?>
                <a href="<?= doc_cns_page_url($qs, $p) ?>" class="page-btn <?= $p === $consultationsCurrentPage ? 'active' : '' ?>"><?= $p ?></a>
                <?php endfor; ?>
                <?php if ($cnsWindowEnd < $consultationsTotalPages): ?>
                <?php if ($cnsWindowEnd < $consultationsTotalPages - 1): ?>
                <span class="page-ellipsis">&hellip;</span>
                <?php endif; ?>
                <a href="<?= doc_cns_page_url($qs, $consultationsTotalPages) ?>" class="page-btn"><?= $consultationsTotalPages ?></a>
                <?php endif; ?>
                <a href="<?= doc_cns_page_url($qs, min($consultationsTotalPages, $consultationsCurrentPage + 1)) ?>" class="page-btn" aria-label="Next page"><i class="bi bi-chevron-right"></i></a>
            </nav>
        </div>
    </div>

</main>

<!-- =============================================================
     MODAL: Starting Consultation
     -------------------------------------------------------------
     Opened by "Start Consultation" (assets/js/doctor_consultations.js),
     populated purely from that button's own data-* attributes (set
     server-side above from $nextConsult/$nextPatient/$nextType/
     $nextStatus) — no extra network request. Two real actions:
     "Proceed with Consultation" (the option card AND the footer button
     both do the exact same thing — POSTs to api/update-consultation.php
     flipping this consultation to "ongoing", same as before this modal
     existed) and "View Patient History" (navigates to
     patient_profile.php?id=... without starting anything, so a doctor
     can check history first and come back).
     ============================================================= -->
<div class="modal fade scm-modal" id="startConsultationModal" tabindex="-1" aria-labelledby="startConsultationModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <div class="scm-header-title">
          <span class="scm-header-icon"><i class="bi bi-person-fill"></i></span>
          <h5 class="modal-title" id="startConsultationModalLabel">Starting Consultation</h5>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="scm-subtitle">You are about to start a consultation with the following patient.</p>

        <div class="scm-patient-card">
          <div class="scm-patient-identity">
            <div class="cns-next-avatar"><i class="bi bi-person-fill"></i></div>
            <div>
              <div class="scm-patient-name" id="scmPatientName"></div>
              <div class="scm-patient-meta" id="scmPatientMeta"></div>
            </div>
          </div>
          <span class="cns-badge" id="scmTypeBadge"></span>
        </div>

        <div class="scm-fields">
          <div class="scm-field">
            <i class="bi bi-clock scm-field-icon"></i>
            <div>
              <div class="scm-field-label">Arrival Time</div>
              <div class="scm-field-value" id="scmArrivalTime"></div>
            </div>
          </div>
          <div class="scm-field">
            <i class="bi bi-file-earmark-text scm-field-icon"></i>
            <div>
              <div class="scm-field-label">Reason for Visit</div>
              <div class="scm-field-value" id="scmReason"></div>
            </div>
          </div>
          <div class="scm-field">
            <i class="bi bi-circle-fill scm-field-icon" id="scmStatusDot"></i>
            <div>
              <div class="scm-field-label">Current Status</div>
              <div class="scm-field-value" id="scmStatusLabel"></div>
            </div>
          </div>
        </div>

        <div class="scm-info-banner">
          <i class="bi bi-info-circle-fill"></i>
          Please review the patient's information before proceeding with the consultation.
        </div>

        <div class="scm-choices-label">What would you like to do?</div>
        <div class="scm-choices">
          <button type="button" class="scm-choice-card" id="scmProceedCard">
            <span class="scm-choice-icon scm-choice-icon-blue"><i class="bi bi-play-fill"></i></span>
            <span class="scm-choice-text">
              <span class="scm-choice-title">Proceed with Consultation</span>
              <span class="scm-choice-desc">Start the consultation and record patient information.</span>
            </span>
            <i class="bi bi-arrow-right scm-choice-arrow"></i>
          </button>
          <a href="#" class="scm-choice-card" id="scmHistoryCard">
            <span class="scm-choice-icon scm-choice-icon-purple"><i class="bi bi-file-earmark-medical"></i></span>
            <span class="scm-choice-text">
              <span class="scm-choice-title">View Patient History</span>
              <span class="scm-choice-desc">Review patient's previous consultations and records.</span>
            </span>
            <i class="bi bi-arrow-right scm-choice-arrow"></i>
          </a>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="cns-btn-start" id="scmProceedBtn"><i class="bi bi-play-fill"></i> Proceed with Consultation</button>
      </div>
    </div>
  </div>
</div>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<link rel="stylesheet" href="<?= asset_url('/assets/css/hc-dialogs.css') ?>">
<script src="<?= asset_url('/assets/js/hc-dialogs.js') ?>"></script>
<script src="<?= asset_url('/assets/js/admin-sidebar-toggle.js') ?>"></script>
<script src="<?= asset_url('/assets/js/doctor_consultations.js') ?>"></script>
<script src="<?= asset_url('/assets/js/search-form-guard.js') ?>"></script>
</body>
</html>
