<?php
/**
 * =====================================================================
 * HEALTrack — Health Information System
 * Laboratory Result Details (medical-staff/laboratory_result_details.php)
 * ---------------------------------------------------------------------
 * Reached via the "View" (eye) button on
 * medical-staff/patient_laboratory_xray.php's Laboratory Results /
 * X-ray Results tables. Like patient_laboratory_xray.php itself, this
 * is a drill-down detail view, not one of the patient profile's own
 * tabs — so it reuses the sidebar/topbar/profile-header shell but has
 * NO tab strip, and its "Back" control sits inline with the page title
 * instead of as its own line above the topbar. Modeled on
 * subadmin/laboratory_result_details.php; reuses
 * assets/css/laboratory_result_details.css directly since it's
 * visually identical.
 *
 * CONNECTED to real Firestore data — see
 * includes/medical-staff/laboratory_result_details_medstaff_firestore.php's header
 * comment for how the &type=lab|xray&recordId= querystring (now sent
 * by patient_laboratory_xray.php's View buttons) resolves which lab
 * test and which x-ray exam this page shows.
 * =====================================================================
 */
session_start();

require_once __DIR__ . '/../config/constants.php'; // BASE_URL, asset_url()

// Only a signed-in medical staff member may view this page.
if (($_SESSION['role'] ?? null) !== 'healthcare_staff') {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

require_once __DIR__ . '/../includes/medical-staff/laboratory_result_details_medstaff_firestore.php';
// $staffProfile, $dashboardDateLabel, $facilityOptions, $patient, $statusMeta,
// $labTest, $labResultBadge, $labResultGroups, $labInterpretation,
// $xrayExam, $xrayFindings, $xrayImpression

// Sidebar nav — same Medical Staff nav list as medical-staff/dashboard.php.
// Patient Management stays active (this page is reached from it).
$staffNavItems = [
    ['icon' => 'bi-grid-fill',            'label' => 'Dashboard',          'href' => 'dashboard.php'],
    ['icon' => 'bi-person-lines-fill',    'label' => 'Patient Management', 'href' => 'patient_management.php', 'active' => true],
    ['icon' => 'bi-calendar2-heart-fill', 'label' => 'Consultations',      'href' => 'consultations.php'],
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
<title>Laboratory Result Details — HEALTrack</title>

<!-- Google Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<!-- Bootstrap 5 -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<!-- Font Awesome (available per project spec; Bootstrap Icons cover this page's icons) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<!-- Shared admin shell (sidebar, topbar, cards, table, etc.) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/admin-dashboard.css') ?>">
<!-- Medical Staff Dashboard styles, reused here for the nav badge + topbar select buttons -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/medical_staff_dashboard.css') ?>">
<!-- Patient Profile styles, reused here for the profile header card -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/patient_profile.css') ?>">
<!-- Laboratory Result Details page styles (back+title row, detail cards, results table) — shared verbatim with subadmin/laboratory_result_details.php -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/laboratory_result_details.css') ?>">
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
            <div class="lrd-title-row">
                <a href="patient_laboratory_xray.php?id=<?= urlencode($patient['id']) ?>" class="lrd-back-btn">
                    <i class="bi bi-arrow-left"></i> Back
                </a>
                <h1>Laboratory Result Details</h1>
            </div>
            <nav class="ppf-breadcrumb" aria-label="Breadcrumb">
                <a href="dashboard.php">Dashboard</a>
                <span class="ppf-breadcrumb-sep">&gt;</span>
                <a href="patient_management.php">Patient Management</a>
                <span class="ppf-breadcrumb-sep">&gt;</span>
                <a href="patient_profile.php?id=<?= urlencode($patient['id']) ?>">Patient Profile</a>
                <span class="ppf-breadcrumb-sep">&gt;</span>
                <a href="patient_laboratory_xray.php?id=<?= urlencode($patient['id']) ?>">Laboratory &amp; X-ray</a>
                <span class="ppf-breadcrumb-sep">&gt;</span>
                <span class="ppf-breadcrumb-current">Laboratory Result Details</span>
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
        </div>
    </div>

    <?php if ($resultType === 'lab'): ?>
    <!-- ============ DETAIL LAYOUT: LEFT (test details) + RIGHT (results table) ============
         Only the lab half — see includes/medical-staff/laboratory_result_details_medstaff_firestore.php's
         header comment for why the x-ray half is never rendered here. -->
    <div class="lrd-layout">

        <!-- ============ LEFT COLUMN ============ -->
        <div class="lrd-col">

            <!-- Laboratory Test Details -->
            <div class="adm-card lrd-detail-card">
                <div class="lrd-card-header"><i class="bi bi-eyedropper"></i> <h3>Laboratory Test Details</h3></div>

                <div class="lrd-field-list">
                    <div class="lrd-field"><span class="lrd-field-label">Test Name</span><span class="lrd-field-value"><?= htmlspecialchars($labTest['testName']) ?></span></div>
                    <div class="lrd-field"><span class="lrd-field-label">Test Type</span><span class="lrd-field-value"><?= htmlspecialchars($labTest['testType']) ?></span></div>
                    <div class="lrd-field"><span class="lrd-field-label">Date &amp; Time Ordered</span><span class="lrd-field-value"><?= htmlspecialchars($labTest['orderedDate']) ?> <span class="lrd-dot"></span> <?= htmlspecialchars($labTest['orderedTime']) ?></span></div>
                    <div class="lrd-field"><span class="lrd-field-label">Date &amp; Time Completed</span><span class="lrd-field-value"><?= htmlspecialchars($labTest['completedDate']) ?> <span class="lrd-dot"></span> <?= htmlspecialchars($labTest['completedTime']) ?></span></div>
                    <div class="lrd-field"><span class="lrd-field-label">Ordered By</span><span class="lrd-field-value"><?= htmlspecialchars($labTest['orderedBy']) ?></span></div>
                    <div class="lrd-field"><span class="lrd-field-label">Facility / Department</span><span class="lrd-field-value"><?= htmlspecialchars($labTest['facility']) ?></span></div>
                    <?php $labStatus = $statusMeta[$labTest['status']] ?? $statusMeta['completed']; ?>
                    <div class="lrd-field"><span class="lrd-field-label">Status</span><span class="lrd-badge" style="background:<?= htmlspecialchars($labStatus['bg']) ?>; color:<?= htmlspecialchars($labStatus['color']) ?>;"><?= htmlspecialchars($labStatus['label']) ?></span></div>
                    <div class="lrd-field"><span class="lrd-field-label">Specimen Type</span><span class="lrd-field-value"><?= htmlspecialchars($labTest['specimenType']) ?></span></div>
                    <div class="lrd-field"><span class="lrd-field-label">Remarks</span><span class="lrd-field-value"><?= $labTest['remarks'] ? htmlspecialchars($labTest['remarks']) : '—' ?></span></div>
                </div>

                <div class="lrd-notes-box">
                    <div class="lrd-notes-title">Clinical Notes (if any)</div>
                    <p><?= htmlspecialchars($labTest['clinicalNotes']) ?></p>
                </div>

                <div class="lrd-attached">
                    <div class="lrd-attached-title">Attached File</div>
                    <div class="lrd-file-row">
                        <span class="lrd-file-icon"><i class="bi bi-file-earmark-pdf-fill"></i></span>
                        <div class="lrd-file-info">
                            <span class="lrd-file-name"><?= htmlspecialchars($labTest['attachedFile']['name']) ?></span>
                            <span class="lrd-file-size"><?= htmlspecialchars($labTest['attachedFile']['size']) ?></span>
                        </div>
                        <button type="button" class="lrd-download-btn" aria-label="Download"><i class="bi bi-download"></i></button>
                    </div>
                </div>
            </div>

        </div>

        <!-- ============ RIGHT COLUMN ============ -->
        <div class="lrd-col wide">

            <!-- Laboratory Results -->
            <div class="adm-card lrd-detail-card">
                <div class="lrd-card-header with-action">
                    <div><i class="bi bi-eyedropper"></i> <h3>Laboratory Results</h3></div>
                    <span class="lrd-badge" style="background:<?= htmlspecialchars($labResultBadge['bg']) ?>; color:<?= htmlspecialchars($labResultBadge['color']) ?>;">
                        <i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($labResultBadge['label']) ?>
                    </span>
                </div>

                <div class="adm-table-wrap">
                    <table class="adm-table lrd-results-table">
                        <thead>
                            <tr>
                                <th>Test</th>
                                <th>Result</th>
                                <th>Unit</th>
                                <th>Reference Range</th>
                                <th>Interpretation</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($labResultGroups as $groupName => $rows): ?>
                            <tr class="lrd-group-row">
                                <td colspan="5"><?= htmlspecialchars(strtoupper($groupName)) ?></td>
                            </tr>
                            <?php foreach ($rows as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['test']) ?></td>
                                <td class="lrd-strong"><?= htmlspecialchars($row['result']) ?></td>
                                <td><?= htmlspecialchars($row['unit']) ?></td>
                                <td><?= htmlspecialchars($row['range']) ?></td>
                                <td class="lrd-interpretation-normal"><?= htmlspecialchars($row['interpretation']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="lrd-interpretation-panel">
                    <i class="bi bi-info-circle-fill"></i>
                    <div>
                        <div class="lrd-interpretation-title">Interpretation</div>
                        <p><?= htmlspecialchars($labInterpretation) ?></p>
                    </div>
                </div>
            </div>

        </div>

    </div>
    <?php else: ?>
    <!-- ============ DETAIL LAYOUT: X-ray only (single column) ============ -->
    <div class="lrd-layout" style="grid-template-columns: 1fr;">
        <div class="lrd-col">

            <!-- X-ray Examination Details -->
            <div class="adm-card lrd-detail-card">
                <div class="lrd-card-header"><i class="bi bi-clipboard2-pulse-fill"></i> <h3>X-ray Examination Details</h3></div>

                <div class="lrd-field-list">
                    <div class="lrd-field"><span class="lrd-field-label">Exam Name</span><span class="lrd-field-value"><?= htmlspecialchars($xrayExam['examName']) ?></span></div>
                    <div class="lrd-field"><span class="lrd-field-label">Body Part</span><span class="lrd-field-value"><?= htmlspecialchars($xrayExam['bodyPart']) ?></span></div>
                    <div class="lrd-field"><span class="lrd-field-label">Date &amp; Time Ordered</span><span class="lrd-field-value"><?= htmlspecialchars($xrayExam['orderedDate']) ?> <span class="lrd-dot"></span> <?= htmlspecialchars($xrayExam['orderedTime']) ?></span></div>
                    <div class="lrd-field"><span class="lrd-field-label">Date &amp; Time Completed</span><span class="lrd-field-value"><?= htmlspecialchars($xrayExam['completedDate']) ?> <span class="lrd-dot"></span> <?= htmlspecialchars($xrayExam['completedTime']) ?></span></div>
                    <div class="lrd-field"><span class="lrd-field-label">Ordered By</span><span class="lrd-field-value"><?= htmlspecialchars($xrayExam['orderedBy']) ?></span></div>
                </div>
            </div>

        </div>
    </div>
    <?php endif; ?>

    <?php if ($resultType === 'xray'): ?>
    <!-- ============ X-RAY FINDINGS (full width) ============ -->
    <div class="adm-card lrd-detail-card lrd-xray-findings-card">
        <div class="lrd-card-header"><h3>X-ray Findings</h3></div>

        <div class="lrd-xray-findings-layout">
            <div class="lrd-xray-image">
                <i class="bi bi-lungs"></i>
                <span>X-ray image preview</span>
            </div>

            <div class="lrd-xray-details">
                <div class="lrd-xray-details-main">
                    <div class="lrd-findings-title">Findings</div>
                    <ul class="lrd-findings-list">
                        <?php foreach ($xrayFindings as $finding): ?>
                        <li><?= htmlspecialchars($finding) ?></li>
                        <?php endforeach; ?>
                    </ul>

                    <div class="lrd-findings-title">Impression</div>
                    <p class="lrd-impression"><?= htmlspecialchars($xrayImpression) ?></p>
                </div>

                <div class="lrd-xray-details-side">
                    <div class="lrd-field"><span class="lrd-field-label">Facility / Department</span><span class="lrd-field-value"><?= htmlspecialchars($xrayExam['facility']) ?></span></div>
                    <?php $xrayStatus = $statusMeta[$xrayExam['status']] ?? $statusMeta['completed']; ?>
                    <div class="lrd-field"><span class="lrd-field-label">Status</span><span class="lrd-badge" style="background:<?= htmlspecialchars($xrayStatus['bg']) ?>; color:<?= htmlspecialchars($xrayStatus['color']) ?>;"><?= htmlspecialchars($xrayStatus['label']) ?></span></div>

                    <div class="lrd-attached">
                        <div class="lrd-attached-title">Attached File</div>
                        <div class="lrd-file-row">
                            <span class="lrd-file-icon"><i class="bi bi-file-earmark-pdf-fill"></i></span>
                            <div class="lrd-file-info">
                                <span class="lrd-file-name"><?= htmlspecialchars($xrayExam['attachedFile']['name']) ?></span>
                                <span class="lrd-file-size"><?= htmlspecialchars($xrayExam['attachedFile']['size']) ?></span>
                            </div>
                            <button type="button" class="lrd-download-btn" aria-label="Download"><i class="bi bi-download"></i></button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; // $resultType === 'xray' ?>

</main>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= asset_url('/assets/js/admin-sidebar-toggle.js') ?>"></script>
</body>
</html>
