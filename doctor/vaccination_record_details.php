<?php
/**
 * =====================================================================
 * HEALTrack — Health Information System
 * Vaccination Record Details (doctor/vaccination_record_details.php)
 * ---------------------------------------------------------------------
 * Reached via the "View" (eye) button on
 * doctor/vaccination_records.php's Vaccination History table. Like
 * doctor/laboratory_result_details.php, this is a drill-down detail
 * view, not one of the patient profile's own tabs — so it reuses the
 * sidebar/topbar/profile-header shell but has NO tab strip, and its
 * "Back" control sits inline with the page title instead of as its own
 * line above the topbar. Reuses assets/css/admin-dashboard.css and
 * assets/css/patient_profile.css for the shell + profile card.
 *
 * CONNECTED to real Firestore data — see
 * includes/doctor/vaccination_record_details_doctor_firestore.php's
 * header comment. Each "View" button on vaccination_records.php now
 * links here with a real &recordId=, so this shows the actual clicked
 * record.
 * =====================================================================
 */
session_start();

require_once __DIR__ . '/../config/constants.php'; // BASE_URL, asset_url()

// Only a signed-in doctor may view this page.
if (($_SESSION['role'] ?? null) !== 'doctor') {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

require_once __DIR__ . '/../includes/doctor/vaccination_record_details_doctor_firestore.php';
// $doctorProfile, $dashboardDateLabel, $facilityOptions, $patient,
// $vaccineTypeMeta, $vaccinationRecords, $vaccinationRecordsTotal,
// $selectedVaccineRecord, $vaccineStatusMeta

// Sidebar nav — same Doctor nav list as every other doctor/*.php page.
$doctorNavItems = [
    ['icon' => 'bi-grid-fill',           'label' => 'Dashboard',          'href' => 'dashboard.php'],
    ['icon' => 'bi-person-lines-fill',   'label' => 'Patient Management', 'href' => 'patient_management.php', 'active' => true],
    ['icon' => 'bi-calendar2-heart-fill','label' => 'Consultations',      'href' => 'consultations.php'],
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

$selectedMeta = $vaccineTypeMeta[$selectedVaccineRecord['type']] ?? ['label' => 'Vaccine', 'icon' => 'bi-shield-fill-check', 'bg' => '#EEF0F3', 'color' => '#6B7280'];
$selectedStatus = $vaccineStatusMeta[$selectedVaccineRecord['status']] ?? $vaccineStatusMeta['completed'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Vaccination Record Details — HEALTrack</title>

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
<!-- Doctor Dashboard styles, reused here for the nav badge + topbar select buttons -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/doctor_dashboard.css') ?>">
<!-- Patient Profile styles, reused here for the breadcrumb + profile header card -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/patient_profile.css') ?>">
<!-- Vaccination Record Details page styles (back+title row, vaccine info card, history table) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/vaccination_record_details.css') ?>">
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
            <div class="vrd-title-row">
                <a href="vaccination_records.php?id=<?= urlencode($patient['id']) ?>" class="vrd-back-btn">
                    <i class="bi bi-arrow-left"></i> Back
                </a>
                <h1>Vaccination Record Details</h1>
            </div>
            <nav class="ppf-breadcrumb" aria-label="Breadcrumb">
                <a href="dashboard.php">Dashboard</a>
                <span class="ppf-breadcrumb-sep">&gt;</span>
                <a href="patient_management.php">Patient Management</a>
                <span class="ppf-breadcrumb-sep">&gt;</span>
                <a href="patient_profile.php?id=<?= urlencode($patient['id']) ?>">Patient Profile</a>
                <span class="ppf-breadcrumb-sep">&gt;</span>
                <a href="vaccination_records.php?id=<?= urlencode($patient['id']) ?>">Vaccination Records</a>
                <span class="ppf-breadcrumb-sep">&gt;</span>
                <span class="ppf-breadcrumb-current">Vaccination Record Details</span>
            </nav>
        </div>

        <div class="adm-topbar-right">
            <div class="adm-topbar-actions">
                <!-- Fixed — display only, not a range picker; nothing
                     downstream reads a selected value yet. -->
                <div class="doc-topbar-select-btn">
                    <i class="bi bi-calendar3"></i> <?= htmlspecialchars($dashboardDateLabel) ?>
                </div>

                <!-- Fixed — shows only the facility this doctor is actually
                     assigned to (their real users/{uid} doc's "facility" field);
                     not a switcher, since a doctor can't reassign themselves. -->
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

    <!-- ============ VACCINE INFORMATION CARD ============ -->
    <div class="adm-card vrd-vaccine-card">
        <div class="vrd-card-header"><i class="bi bi-shield-fill-check"></i> <h3>Vaccine Information</h3></div>

        <div class="vrd-vaccine-hero">
            <div class="vrd-vaccine-icon" style="background:<?= htmlspecialchars($selectedMeta['bg']) ?>; color:<?= htmlspecialchars($selectedMeta['color']) ?>;">
                <i class="bi <?= htmlspecialchars($selectedMeta['icon']) ?>"></i>
            </div>
            <div class="vrd-vaccine-hero-text">
                <div class="vrd-vaccine-name-row">
                    <h2><?= htmlspecialchars($selectedVaccineRecord['name']) ?></h2>
                    <span class="vrd-badge" style="background:<?= htmlspecialchars($selectedStatus['bg']) ?>; color:<?= htmlspecialchars($selectedStatus['color']) ?>;"><?= htmlspecialchars($selectedStatus['label']) ?></span>
                </div>
                <p class="vrd-vaccine-description"><?= htmlspecialchars($selectedVaccineRecord['description']) ?></p>
            </div>
        </div>

        <div class="vrd-details-grid">
            <div class="vrd-details-col">
                <div class="vrd-field"><span class="vrd-field-label">Vaccine Type</span><span class="vrd-field-value"><?= htmlspecialchars($selectedMeta['label']) ?></span></div>
                <div class="vrd-field"><span class="vrd-field-label">Dose / Series</span><span class="vrd-field-value"><?= htmlspecialchars($selectedVaccineRecord['dose']) ?></span></div>
                <div class="vrd-field"><span class="vrd-field-label">Date Administered</span><span class="vrd-field-value"><?= htmlspecialchars($selectedVaccineRecord['dateAdministered']) ?></span></div>
                <div class="vrd-field"><span class="vrd-field-label">Next Dose Due</span><span class="vrd-field-value"><?= $selectedVaccineRecord['nextDoseDue'] ? htmlspecialchars($selectedVaccineRecord['nextDoseDue']) : '—' ?></span></div>
                <div class="vrd-field"><span class="vrd-field-label">Route of Administration</span><span class="vrd-field-value"><?= htmlspecialchars($selectedVaccineRecord['route']) ?></span></div>
                <div class="vrd-field"><span class="vrd-field-label">Injection Site</span><span class="vrd-field-value"><?= htmlspecialchars($selectedVaccineRecord['site']) ?></span></div>
                <div class="vrd-field"><span class="vrd-field-label">Given By</span><span class="vrd-field-value"><?= htmlspecialchars($selectedVaccineRecord['givenBy']) ?></span></div>
            </div>
            <div class="vrd-details-col">
                <div class="vrd-field"><span class="vrd-field-label">Manufacturer</span><span class="vrd-field-value"><?= htmlspecialchars($selectedVaccineRecord['manufacturer']) ?></span></div>
                <div class="vrd-field"><span class="vrd-field-label">Lot Number</span><span class="vrd-field-value"><?= htmlspecialchars($selectedVaccineRecord['lotNumber']) ?></span></div>
                <div class="vrd-field"><span class="vrd-field-label">Expiry Date</span><span class="vrd-field-value"><?= htmlspecialchars($selectedVaccineRecord['expiryDate']) ?></span></div>
                <div class="vrd-field"><span class="vrd-field-label">Facility</span><span class="vrd-field-value"><?= htmlspecialchars($selectedVaccineRecord['facility']) ?></span></div>
                <div class="vrd-field"><span class="vrd-field-label">Remarks</span><span class="vrd-field-value"><?= htmlspecialchars($selectedVaccineRecord['notes']) ?></span></div>
                <div class="vrd-field"><span class="vrd-field-label">Status</span><span class="vrd-badge" style="background:<?= htmlspecialchars($selectedStatus['bg']) ?>; color:<?= htmlspecialchars($selectedStatus['color']) ?>;"><?= htmlspecialchars($selectedStatus['label']) ?></span></div>
            </div>
        </div>
    </div>

    <!-- ============ VACCINATION HISTORY TABLE ============ -->
    <div class="adm-card vrd-history-card">
        <div class="vrd-card-header"><i class="bi bi-clock-history"></i> <h3>Vaccination History</h3></div>

        <div class="adm-table-wrap">
            <table class="adm-table vrd-history-table">
                <thead>
                    <tr>
                        <th>Vaccine Name</th>
                        <th>Vaccine Type</th>
                        <th>Date Administered</th>
                        <th>Dose / Series</th>
                        <th>Manufacturer</th>
                        <th>Next Dose Due</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($vaccinationRecords)): ?>
                    <tr>
                        <td colspan="7" class="adm-table-empty">No vaccination records yet.</td>
                    </tr>
                    <?php endif; ?>
                    <?php foreach ($vaccinationRecords as $vac): $meta = $vaccineTypeMeta[$vac['type']] ?? ['label' => ucfirst($vac['type'])]; $status = $vaccineStatusMeta[$vac['status']] ?? $vaccineStatusMeta['completed']; ?>
                    <tr>
                        <td class="vrd-strong"><?= htmlspecialchars($vac['name']) ?></td>
                        <td><?= htmlspecialchars($meta['label']) ?></td>
                        <td><?= htmlspecialchars($vac['dateAdministered']) ?></td>
                        <td><?= htmlspecialchars($vac['dose']) ?></td>
                        <td><?= htmlspecialchars($vac['manufacturer']) ?></td>
                        <td><?= $vac['nextDoseDue'] ? htmlspecialchars($vac['nextDoseDue']) : '—' ?></td>
                        <td><span class="vrd-badge" style="background:<?= htmlspecialchars($status['bg']) ?>; color:<?= htmlspecialchars($status['color']) ?>;"><?= htmlspecialchars($status['label']) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ============ BACK TO VACCINATION RECORDS ============ -->
    <div class="vrd-footer-actions">
        <a href="vaccination_records.php?id=<?= urlencode($patient['id']) ?>" class="vrd-btn-back">
            <i class="bi bi-arrow-left"></i> Back to Vaccination Records
        </a>
    </div>

</main>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= asset_url('/assets/js/admin-sidebar-toggle.js') ?>"></script>
</body>
</html>
