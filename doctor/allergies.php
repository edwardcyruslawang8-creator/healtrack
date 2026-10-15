<?php
/**
 * =====================================================================
 * HEALTrack — Health Information System
 * Patient Allergies (doctor/allergies.php)
 * ---------------------------------------------------------------------
 * This IS doctor/patient_profile.php's "Allergies" tab — reached by
 * clicking that tab — as its own page rather than an in-page tab pane,
 * since it needs its own summary cards + table + expandable details
 * panel. Reuses the exact same sidebar/topbar/profile-header/tab-strip
 * shell as patient_profile.php, patient_laboratory_xray.php,
 * medical_records.php, and vaccination_records.php (280px dark-red
 * gradient sidebar, "adm-"/"ppf-" prefixed atoms, "doc-" nav badge/
 * topbar select buttons), including the Edit Profile button staying
 * removed there, for consistency. Modeled on
 * medical-staff/allergies.php; reuses assets/css/allergies.css directly
 * since it's visually identical.
 *
 * CONNECTED to real Firestore data — see
 * includes/doctor/allergies_doctor_firestore.php's header comment. Each
 * row's View button now passes a real &recordId= to allergy_details.php,
 * so that page shows the actual clicked record. The "Allergy Details"
 * panel below the table on this page is a separate, always-expandable
 * summary of the most recently added record.
 * =====================================================================
 */
session_start();

require_once __DIR__ . '/../config/constants.php'; // BASE_URL, asset_url()

// Only a signed-in doctor may view this page.
if (($_SESSION['role'] ?? null) !== 'doctor') {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

require_once __DIR__ . '/../includes/doctor/allergies_doctor_firestore.php';
// $doctorProfile, $dashboardDateLabel, $facilityOptions, $patient, $profileTabs,
// $severityMeta, $allergyStatusMeta, $allergySummary, $allergyRecords,
// $allergyRecordsTotal, $selectedAllergy

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

$selectedSeverity = $severityMeta[$selectedAllergy['severity']] ?? $severityMeta['mild'];
$selectedStatus = $allergyStatusMeta[$selectedAllergy['status']] ?? $allergyStatusMeta['active'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Allergies — HEALTrack</title>

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
<!-- Doctor Dashboard styles, reused here for the nav badge + topbar select buttons -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/doctor_dashboard.css') ?>">
<!-- Patient Profile styles, reused here for the breadcrumb + profile header card -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/patient_profile.css') ?>">
<!-- Allergies page styles (summary cards, table, details panel) — shared verbatim with every other role's version -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/allergies.css') ?>">
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

    <!-- ============ BACK LINK ============ -->
    <a href="patient_profile.php?id=<?= urlencode($patient['id']) ?>" class="adm-back-link">
        <i class="bi bi-arrow-left"></i> Back to Patient Profile
    </a>

    <!-- ============ TOP HEADER ============ -->
    <div class="adm-topbar">
        <div>
            <h1>Patient Management <span class="ppf-title-sep">/</span> Allergies</h1>
            <nav class="ppf-breadcrumb" aria-label="Breadcrumb">
                <a href="dashboard.php">Dashboard</a>
                <span class="ppf-breadcrumb-sep">&gt;</span>
                <a href="patient_management.php">Patient Management</a>
                <span class="ppf-breadcrumb-sep">&gt;</span>
                <a href="patient_profile.php?id=<?= urlencode($patient['id']) ?>">Patient Profile</a>
                <span class="ppf-breadcrumb-sep">&gt;</span>
                <span class="ppf-breadcrumb-current">Allergies</span>
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

    <!-- ============ PROFILE TABS ============ -->
    <ul class="nav adm-tabs" role="tablist">
        <?php foreach ($profileTabs as $tab): ?>
        <li class="nav-item" role="presentation">
            <?php if ($tab['key'] === 'overview'): ?>
            <a href="patient_profile.php?id=<?= urlencode($patient['id']) ?>" class="nav-link">
                <i class="bi <?= htmlspecialchars($tab['icon']) ?>"></i> <?= htmlspecialchars($tab['label']) ?>
            </a>
            <?php elseif ($tab['key'] === 'medical_records'): ?>
            <a href="medical_history.php?id=<?= urlencode($patient['id']) ?>" class="nav-link">
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
            <button class="nav-link <?= !empty($tab['active']) ? 'active' : '' ?>" type="button" role="tab">
                <i class="bi <?= htmlspecialchars($tab['icon']) ?>"></i> <?= htmlspecialchars($tab['label']) ?>
            </button>
            <?php endif; ?>
        </li>
        <?php endforeach; ?>
    </ul>

    <!-- ============ ALLERGY SUMMARY SECTION HEADER ============ -->
    <div class="alg-section-header">
        <h2>Allergy Summary</h2>
        <p>View and manage patient's allergy information.</p>
    </div>

    <!-- ============ ALLERGY SUMMARY CARDS ============ -->
    <div class="alg-summary-row">
        <div class="adm-card alg-summary-card">
            <div class="alg-summary-icon" style="background:#FCE4E4; color:#D93025;"><i class="bi bi-exclamation-triangle-fill"></i></div>
            <div class="alg-summary-text">
                <span class="alg-summary-label">Total Allergies</span>
                <span class="alg-summary-value"><?= (int) $allergySummary['totalAllergies'] ?></span>
                <span class="alg-summary-sub" style="color:#D93025;">Active allergies</span>
            </div>
        </div>

        <div class="adm-card alg-summary-card">
            <div class="alg-summary-icon" style="background:#FDECDD; color:#E8710A;"><i class="bi bi-exclamation-circle-fill"></i></div>
            <div class="alg-summary-text">
                <span class="alg-summary-label">Severe Allergies</span>
                <span class="alg-summary-value"><?= (int) $allergySummary['severeAllergies'] ?></span>
                <span class="alg-summary-sub" style="color:#E8710A;">Requires caution</span>
            </div>
        </div>

        <div class="adm-card alg-summary-card">
            <div class="alg-summary-icon" style="background:#E8F0FE; color:#1A73E8;"><i class="bi bi-calendar-check-fill"></i></div>
            <div class="alg-summary-text">
                <span class="alg-summary-label">Last Updated</span>
                <span class="alg-summary-value alg-summary-value-sm"><?= htmlspecialchars($allergySummary['lastUpdated']) ?></span>
            </div>
        </div>

        <div class="adm-card alg-summary-card">
            <div class="alg-summary-icon" style="background:#E4F7EA; color:#1E8E3E;"><i class="bi bi-file-earmark-check-fill"></i></div>
            <div class="alg-summary-text">
                <span class="alg-summary-label">Status</span>
                <span class="alg-badge" style="background:#E4F7EA; color:#1E8E3E;"><?= htmlspecialchars($allergySummary['status']) ?></span>
            </div>
        </div>
    </div>

    <!-- ============ ACTIVE ALLERGIES TABLE ============ -->
    <div class="alg-list-header"><h3>Active Allergies</h3></div>

    <div class="adm-card alg-table-card">
        <div class="adm-table-wrap">
            <table class="adm-table alg-table">
                <thead>
                    <tr>
                        <th>Allergen</th>
                        <th>Type</th>
                        <th>Reaction</th>
                        <th>Severity</th>
                        <th>Onset Date</th>
                        <th>Status</th>
                        <th>Notes</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($activeAllergies)): ?>
                    <tr>
                        <td colspan="8" class="adm-table-empty">No active allergies recorded.</td>
                    </tr>
                    <?php endif; ?>
                    <?php foreach ($activeAllergies as $alg): $sev = $severityMeta[$alg['severity']] ?? $severityMeta['mild']; $stat = $allergyStatusMeta[$alg['status']] ?? $allergyStatusMeta['active']; ?>
                    <tr>
                        <td>
                            <div class="alg-name-cell">
                                <span class="alg-type-icon" style="background:<?= htmlspecialchars($alg['iconBg']) ?>; color:<?= htmlspecialchars($alg['iconColor']) ?>;">
                                    <i class="bi <?= htmlspecialchars($alg['icon']) ?>"></i>
                                </span>
                                <div class="alg-name-text">
                                    <span class="alg-strong"><?= htmlspecialchars($alg['allergen']) ?></span>
                                    <?php if ($alg['subtype']): ?>
                                    <span class="alg-subtype">(<?= htmlspecialchars($alg['subtype']) ?>)</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td><?= htmlspecialchars($alg['type']) ?></td>
                        <td><?= htmlspecialchars($alg['reaction']) ?></td>
                        <td><span class="alg-badge" style="background:<?= htmlspecialchars($sev['bg']) ?>; color:<?= htmlspecialchars($sev['color']) ?>;"><?= htmlspecialchars($sev['label']) ?></span></td>
                        <td><?= htmlspecialchars($alg['onsetDate']) ?></td>
                        <td><span class="alg-badge" style="background:<?= htmlspecialchars($stat['bg']) ?>; color:<?= htmlspecialchars($stat['color']) ?>;"><?= htmlspecialchars($stat['label']) ?></span></td>
                        <td class="alg-notes-cell"><?= htmlspecialchars($alg['notes']) ?></td>
                        <td>
                            <div class="adm-action-btns">
                                <a href="allergies.php?id=<?= urlencode($patient['id']) ?>&recordId=<?= urlencode($alg['id']) ?>#algDetailsPanel" class="adm-action-btn" aria-label="Select"><i class="bi bi-check2-square"></i></a>
                                <a href="allergy_details.php?id=<?= urlencode($patient['id']) ?>&recordId=<?= urlencode($alg['id']) ?>" class="adm-action-btn view" aria-label="View"><i class="bi bi-eye"></i></a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="adm-table-footer">
            <span class="adm-showing-text"><?= count($activeAllergies) > 0 ? 'Showing 1 to ' . count($activeAllergies) . ' of ' . count($activeAllergies) . ' entries' : 'No entries to show' ?></span>
        </div>
    </div>

    <!-- ============ INACTIVE / RESOLVED ALLERGIES TABLE ============ -->
    <div class="alg-list-header"><h3>Inactive / Resolved Allergies</h3></div>

    <div class="adm-card alg-table-card">
        <div class="adm-table-wrap">
            <table class="adm-table alg-table">
                <thead>
                    <tr>
                        <th>Allergen</th>
                        <th>Type</th>
                        <th>Reaction</th>
                        <th>Severity</th>
                        <th>Onset Date</th>
                        <th>Status</th>
                        <th>Notes</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($inactiveAllergies)): ?>
                    <tr>
                        <td colspan="8" class="adm-table-empty">No inactive or resolved allergies recorded.</td>
                    </tr>
                    <?php endif; ?>
                    <?php foreach ($inactiveAllergies as $alg): $sev = $severityMeta[$alg['severity']] ?? $severityMeta['mild']; $stat = $allergyStatusMeta[$alg['status']] ?? $allergyStatusMeta['active']; ?>
                    <tr>
                        <td>
                            <div class="alg-name-cell">
                                <span class="alg-type-icon" style="background:<?= htmlspecialchars($alg['iconBg']) ?>; color:<?= htmlspecialchars($alg['iconColor']) ?>;">
                                    <i class="bi <?= htmlspecialchars($alg['icon']) ?>"></i>
                                </span>
                                <div class="alg-name-text">
                                    <span class="alg-strong"><?= htmlspecialchars($alg['allergen']) ?></span>
                                    <?php if ($alg['subtype']): ?>
                                    <span class="alg-subtype">(<?= htmlspecialchars($alg['subtype']) ?>)</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td><?= htmlspecialchars($alg['type']) ?></td>
                        <td><?= htmlspecialchars($alg['reaction']) ?></td>
                        <td><span class="alg-badge" style="background:<?= htmlspecialchars($sev['bg']) ?>; color:<?= htmlspecialchars($sev['color']) ?>;"><?= htmlspecialchars($sev['label']) ?></span></td>
                        <td><?= htmlspecialchars($alg['onsetDate']) ?></td>
                        <td><span class="alg-badge" style="background:<?= htmlspecialchars($stat['bg']) ?>; color:<?= htmlspecialchars($stat['color']) ?>;"><?= htmlspecialchars($stat['label']) ?></span></td>
                        <td class="alg-notes-cell"><?= htmlspecialchars($alg['notes']) ?></td>
                        <td>
                            <div class="adm-action-btns">
                                <a href="allergies.php?id=<?= urlencode($patient['id']) ?>&recordId=<?= urlencode($alg['id']) ?>#algDetailsPanel" class="adm-action-btn" aria-label="Select"><i class="bi bi-check2-square"></i></a>
                                <a href="allergy_details.php?id=<?= urlencode($patient['id']) ?>&recordId=<?= urlencode($alg['id']) ?>" class="adm-action-btn view" aria-label="View"><i class="bi bi-eye"></i></a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="adm-table-footer">
            <span class="adm-showing-text"><?= count($inactiveAllergies) > 0 ? 'Showing 1 to ' . count($inactiveAllergies) . ' of ' . count($inactiveAllergies) . ' entries' : 'No entries to show' ?></span>
        </div>
    </div>

    <!-- ============ ALLERGY DETAILS (expandable) ============ -->
    <div class="adm-card alg-details-card" id="algDetailsPanel">
        <button type="button" class="alg-details-toggle" data-bs-toggle="collapse" data-bs-target="#algDetailsBody" aria-expanded="true" aria-controls="algDetailsBody">
            <span class="alg-details-title">
                <span class="alg-type-icon" style="background:<?= htmlspecialchars($selectedAllergy['iconBg']) ?>; color:<?= htmlspecialchars($selectedAllergy['iconColor']) ?>;">
                    <i class="bi bi-shield-fill-check"></i>
                </span>
                <h3>Allergy Details</h3>
            </span>
            <i class="bi bi-chevron-up alg-chevron"></i>
        </button>

        <div class="collapse show" id="algDetailsBody">
            <div class="alg-details-grid">
                <div class="alg-details-col">
                    <div class="alg-field"><span class="alg-field-label">Allergen</span><span class="alg-field-value"><?= htmlspecialchars($selectedAllergy['allergen']) ?></span></div>
                    <div class="alg-field"><span class="alg-field-label">Type</span><span class="alg-field-value"><?= htmlspecialchars($selectedAllergy['type']) ?><?= $selectedAllergy['subtype'] ? ' (' . htmlspecialchars($selectedAllergy['subtype']) . ')' : '' ?></span></div>
                    <div class="alg-field"><span class="alg-field-label">Severity</span><span class="alg-badge" style="background:<?= htmlspecialchars($selectedSeverity['bg']) ?>; color:<?= htmlspecialchars($selectedSeverity['color']) ?>;"><?= htmlspecialchars($selectedSeverity['label']) ?></span></div>
                    <div class="alg-field"><span class="alg-field-label">Status</span><span class="alg-badge" style="background:<?= htmlspecialchars($selectedStatus['bg']) ?>; color:<?= htmlspecialchars($selectedStatus['color']) ?>;"><?= htmlspecialchars($selectedStatus['label']) ?></span></div>
                </div>
                <div class="alg-details-col">
                    <div class="alg-field"><span class="alg-field-label">Reaction</span><span class="alg-field-value"><?= htmlspecialchars($selectedAllergy['reaction']) ?></span></div>
                    <div class="alg-field"><span class="alg-field-label">Onset Date</span><span class="alg-field-value"><?= htmlspecialchars($selectedAllergy['onsetDate']) ?></span></div>
                    <div class="alg-field"><span class="alg-field-label">Last Occurred</span><span class="alg-field-value"><?= htmlspecialchars($selectedAllergy['lastOccurred']) ?></span></div>
                    <div class="alg-field"><span class="alg-field-label">Added On</span><span class="alg-field-value"><?= htmlspecialchars($selectedAllergy['addedOn']) ?></span></div>
                </div>
                <div class="alg-details-col">
                    <div class="alg-field alg-field-stacked"><span class="alg-field-label">Notes</span><span class="alg-field-value"><?= htmlspecialchars($selectedAllergy['notes']) ?></span></div>
                    <div class="alg-field alg-field-stacked"><span class="alg-field-label">Recommendations</span><span class="alg-field-value"><?= htmlspecialchars($selectedAllergy['recommendations']) ?></span></div>
                </div>
            </div>
        </div>
    </div>

</main>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= asset_url('/assets/js/admin-sidebar-toggle.js') ?>"></script>
</body>
</html>
