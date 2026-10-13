<?php
/**
 * =====================================================================
 * HEALTrack — Health Information System
 * Patient Documents (doctor/documents.php)
 * ---------------------------------------------------------------------
 * This IS doctor/patient_profile.php's "Documents" tab — reached by
 * clicking that tab — as its own page rather than an in-page tab pane,
 * since it needs its own summary cards + table + upload panel. Reuses
 * the exact same sidebar/topbar/profile-header/tab-strip shell as
 * patient_profile.php, patient_laboratory_xray.php, medical_records.php,
 * vaccination_records.php, allergies.php, and medications.php (280px
 * dark-red gradient sidebar, "adm-"/"ppf-" prefixed atoms, "doc-" nav
 * badge/topbar select buttons), including the Edit Profile button
 * staying removed there, for consistency. Modeled on
 * subadmin/documents.php; reuses assets/css/documents.css directly
 * since it's visually identical.
 *
 * CONNECTED to real Firestore data — see
 * includes/doctor/documents_doctor_firestore.php's header comment.
 * Upload (api/upload-document.php), Download, View (both real
 * api/download-document.php requests — View just adds &view=1 for an
 * inline Content-Disposition, opened in a new tab), and Delete
 * (api/delete-document.php) are all real now — see
 * includes/shared/document_storage.php's header comment for the local-
 * disk storage this app uses instead of Firebase Cloud Storage.
 * =====================================================================
 */
session_start();

require_once __DIR__ . '/../config/constants.php'; // BASE_URL, asset_url()

// Only a signed-in doctor may view this page.
if (($_SESSION['role'] ?? null) !== 'doctor') {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

require_once __DIR__ . '/../includes/doctor/documents_doctor_firestore.php';
// $doctorProfile, $dashboardDateLabel, $facilityOptions, $patient, $profileTabs,
// $documentsSummary, $documentCategoryMeta, $documents, $documentsTotal,
// $documentsPerPage, $documentsCurrentPage, $documentsTotalPages, $uploadSettings

// Sidebar nav — same Doctor nav list as every other doctor/*.php page.
$doctorNavItems = [
    ['icon' => 'bi-grid-fill',           'label' => 'Dashboard',          'href' => 'dashboard.php'],
    ['icon' => 'bi-person-lines-fill',   'label' => 'Patient Management', 'href' => 'patient_management.php', 'active' => true],
    ['icon' => 'bi-calendar2-heart-fill','label' => 'Consultations',      'href' => 'consultations.php'],
    ['icon' => 'bi-bar-chart-fill',      'label' => 'Analytics & Reports', 'href' => 'analytics_reports.php'],
    ['icon' => 'bi-bell-fill',           'label' => 'Notifications',      'href' => 'notifications.php', 'badge' => hc_doctor_unread_notification_count()],
    ['icon' => 'bi-clipboard-data-fill', 'label' => 'Audit Logs', 'href' => 'audit_logs.php'],
    ['icon' => 'bi-gear-fill',           'label' => 'Profile / Settings', 'href' => 'profile_settings.php'],
];

$fileTypeIconMeta = [
    'pdf' => ['icon' => 'bi-file-earmark-pdf-fill', 'color' => '#D93025'],
    'jpg' => ['icon' => 'bi-file-earmark-image-fill', 'color' => '#1A73E8'],
    'png' => ['icon' => 'bi-file-earmark-image-fill', 'color' => '#1A73E8'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Documents — HEALTrack</title>

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
<!-- Documents page styles (summary cards, table, upload panel) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/documents.css') ?>">
<!-- "am-" (Add Modal) shared modal styling, reused here for the Upload Document modal -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/add_medicine_modal.css') ?>">
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
        <div class="adm-avatar"><i class="bi bi-person-circle"></i></div>
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

    <!-- ============ BACK LINK ============ -->
    <a href="patient_profile.php?id=<?= urlencode($patient['id']) ?>" class="adm-back-link">
        <i class="bi bi-arrow-left"></i> Back to Patient Profile
    </a>

    <!-- ============ TOP HEADER ============ -->
    <div class="adm-topbar">
        <div>
            <h1>Patient Management <span class="ppf-title-sep">/</span> Documents</h1>
            <nav class="ppf-breadcrumb" aria-label="Breadcrumb">
                <a href="dashboard.php">Dashboard</a>
                <span class="ppf-breadcrumb-sep">&gt;</span>
                <a href="patient_management.php">Patient Management</a>
                <span class="ppf-breadcrumb-sep">&gt;</span>
                <a href="patient_profile.php?id=<?= urlencode($patient['id']) ?>">Patient Profile</a>
                <span class="ppf-breadcrumb-sep">&gt;</span>
                <span class="ppf-breadcrumb-current">Documents</span>
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
                        <span class="adm-topbar-avatar"><i class="bi bi-person-fill"></i></span>
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
            <a href="medical_records.php?id=<?= urlencode($patient['id']) ?>" class="nav-link">
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

    <!-- ============ DOCUMENTS SECTION HEADER ============ -->
    <div class="doc-section-header">
        <h2>Documents</h2>
        <p>Manage and view patient-related documents.</p>
    </div>

    <!-- ============ DOCUMENTS SUMMARY CARDS ============ -->
    <div class="doc-summary-row">
        <div class="adm-card doc-summary-card">
            <div class="doc-summary-icon" style="background:#E8F0FE; color:#1A73E8;"><i class="bi bi-file-earmark-text-fill"></i></div>
            <div class="doc-summary-text">
                <span class="doc-summary-label">Total Documents</span>
                <span class="doc-summary-value"><?= (int) $documentsSummary['totalDocuments'] ?></span>
                <span class="doc-summary-sub">All time</span>
            </div>
        </div>

        <div class="adm-card doc-summary-card">
            <div class="doc-summary-icon" style="background:#F1E8FD; color:#8B5CF6;"><i class="bi bi-calendar-check-fill"></i></div>
            <div class="doc-summary-text">
                <span class="doc-summary-label">Last Uploaded</span>
                <span class="doc-summary-value doc-summary-value-sm"><?= htmlspecialchars($documentsSummary['lastUploadedDate']) ?></span>
                <span class="doc-summary-sub"><?= htmlspecialchars($documentsSummary['lastUploadedFile']) ?></span>
            </div>
        </div>
    </div>

    <!-- ============ DOCUMENTS LIST ============ -->
    <div class="doc-list-header"><h3>Documents List</h3></div>

    <div class="adm-card doc-table-card">
        <div class="adm-table-wrap">
            <table class="adm-table doc-table">
                <thead>
                    <tr>
                        <th>Document Name</th>
                        <th>Category</th>
                        <th>Uploaded By</th>
                        <th>Upload Date</th>
                        <th>Description</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($documents)): ?>
                    <tr>
                        <td colspan="6" class="adm-table-empty">No documents uploaded yet.</td>
                    </tr>
                    <?php endif; ?>
                    <?php foreach ($documents as $doc): $cat = $documentCategoryMeta[$doc['category']] ?? ['label' => ucfirst($doc['category']), 'bg' => '#EEF0F3', 'color' => '#6B7280']; $fileIcon = $fileTypeIconMeta[$doc['fileType']] ?? $fileTypeIconMeta['pdf']; ?>
                    <tr>
                        <td>
                            <div class="doc-name-cell">
                                <i class="bi <?= htmlspecialchars($fileIcon['icon']) ?> doc-file-icon" style="color:<?= htmlspecialchars($fileIcon['color']) ?>;"></i>
                                <span class="doc-file-name"><?= htmlspecialchars($doc['name']) ?></span>
                            </div>
                        </td>
                        <td><span class="doc-badge" style="background:<?= htmlspecialchars($cat['bg']) ?>; color:<?= htmlspecialchars($cat['color']) ?>;"><?= htmlspecialchars($cat['label']) ?></span></td>
                        <td class="doc-strong"><?= htmlspecialchars($doc['uploadedBy']) ?></td>
                        <td class="doc-datetime"><?= htmlspecialchars($doc['uploadDate']) ?> <span class="doc-dot"></span> <?= htmlspecialchars($doc['uploadTime']) ?></td>
                        <td class="doc-desc-cell"><?= htmlspecialchars($doc['description']) ?></td>
                        <td>
                            <div class="adm-action-btns">
                                <a href="../api/download-document.php?id=<?= urlencode((string) $doc['id']) ?>&view=1" target="_blank" rel="noopener" class="adm-action-btn view" aria-label="View"><i class="bi bi-eye"></i></a>
                                <button type="button" class="adm-action-btn doc-download-btn" data-doc-id="<?= htmlspecialchars((string) $doc['id']) ?>" aria-label="Download"><i class="bi bi-download"></i></button>
                                <button type="button" class="adm-action-btn delete doc-delete-btn" data-doc-id="<?= htmlspecialchars((string) $doc['id']) ?>" data-doc-name="<?= htmlspecialchars($doc['name']) ?>" aria-label="Delete"><i class="bi bi-trash"></i></button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="adm-table-footer">
            <span class="adm-showing-text"><?= $documentsTotal > 0 ? 'Showing ' . (($documentsCurrentPage - 1) * $documentsPerPage + 1) . ' to ' . (($documentsCurrentPage - 1) * $documentsPerPage + count($documents)) . ' of ' . $documentsTotal . ' entries' : 'No entries to show' ?></span>
            <?= hc_render_pagination_nav('Documents pages', 'page', $documentsCurrentPage, $documentsTotalPages) ?>
        </div>
    </div>

    <!-- ============ UPLOAD DOCUMENT PANEL ============ -->
    <div class="doc-upload-panel" id="docUploadPanel" data-patient-id="<?= htmlspecialchars((string) $patient['id']) ?>" data-max-size-mb="<?= (int) $uploadSettings['maxSizeMB'] ?>" data-allowed-ext="<?= htmlspecialchars(implode(',', array_map('strtolower', $uploadSettings['supportedFormats']))) ?>">
        <input type="file" id="docFileInput" class="doc-upload-input" accept="<?= htmlspecialchars($uploadSettings['acceptAttr']) ?>" hidden>
        <div class="doc-upload-icon"><i class="bi bi-cloud-arrow-up-fill"></i></div>
        <h3>Upload Document</h3>
        <p class="doc-upload-instruction">Drag and drop files here, or <label for="docFileInput" class="doc-upload-browse">click to browse</label></p>
        <p class="doc-upload-formats">Supported formats: <?= htmlspecialchars(implode(', ', $uploadSettings['supportedFormats'])) ?> (Max. <?= (int) $uploadSettings['maxSizeMB'] ?>MB)</p>
    </div>

</main>

<?php require __DIR__ . '/../includes/shared/upload_document_modal.php'; ?>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= asset_url('/assets/js/admin-sidebar-toggle.js') ?>"></script>
<link rel="stylesheet" href="<?= asset_url('/assets/css/hc-dialogs.css') ?>">
<script src="<?= asset_url('/assets/js/hc-dialogs.js') ?>"></script>
<script src="<?= asset_url('/assets/js/document-upload.js') ?>"></script>
</body>
</html>
