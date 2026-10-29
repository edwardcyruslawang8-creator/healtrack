<?php
/**
 * =====================================================================
 * HEALTrack — Health Information System
 * Patient Profile (doctor/patient_profile.php)
 * ---------------------------------------------------------------------
 * Reached two ways: the "View Profile" button on
 * doctor/patient_management.php's table (patient_profile.php?id=<patient
 * id>), or "View Patient History" in doctor/consultations.php's
 * #startConsultationModal (assets/js/doctor_consultations.js), which
 * adds &from=consultation so the back link/breadcrumb/sidebar
 * highlight point back to Consultations instead of the default Patient
 * Management — see $cameFromConsultation below. Reuses the exact same
 * sidebar/topbar shell — and the same Doctor nav list — as the rest of
 * doctor/*.php (280px dark-red gradient sidebar, "adm-"/"doc-" prefixed
 * atoms, assets/css/admin-dashboard.css + assets/css/doctor_dashboard.css).
 * Reuses assets/css/patient_profile.css directly since it's visually
 * identical to medical-staff/patient_profile.php's (same "ppf-" prefix,
 * nothing role-specific in it). No Edit Profile button on the header
 * card, matching every other page that shares this component.
 *
 * CONNECTED to real Firestore data — see
 * includes/doctor/patient_profile_doctor_firestore.php's header
 * comment. $patient resolves from the real ?id= querystring, and the
 * "Overview" tab reads real consultation/condition/lab/x-ray/
 * vaccination data scoped to that patient — the SAME data every other
 * role's Patient Profile page reads for the same patient. Every OTHER
 * tab (Medical Records, Laboratory & X-ray, Vaccination Records,
 * Allergies, Medications, Documents, History) now has its own real
 * doctor/*.php page too (modeled on medical-staff's/subadmin's
 * versions) — only "Overview" stays an in-page tab pane; every other
 * tab strip entry is a real link out to its own page, same pattern
 * those other roles' patient_profile.php already use.
 * =====================================================================
 */
session_start();

require_once __DIR__ . '/../config/constants.php'; // BASE_URL, asset_url()

// Only a signed-in doctor may view this page.
if (($_SESSION['role'] ?? null) !== 'doctor') {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

require_once __DIR__ . '/../includes/doctor/patient_profile_doctor_firestore.php';
// $doctorProfile, $dashboardDateLabel, $facilityOptions, $patient,
// $profileTabs, $summary, $activeMedicalConditions, $latestConsultation,
// $labStatusMeta, $recentLabResults, $recentXray, $vaccineStatusMeta,
// $vaccinationHistory, $accessRestricted, $consentStatus

// A real PHI-access record every time this page actually resolves AND
// shows a patient (never for a blocked/not-found id, and never while
// $accessRestricted — nothing was really viewed then) — see
// subadmin/audit_logs.php and this account's own Audit Logs tab for
// where this shows up.
if ($patient !== null && !$accessRestricted) {
    require_once __DIR__ . '/../includes/shared/audit_log_writer.php'; // write_audit_log()
    write_audit_log('viewed', 'Patient Management', 'Viewed ' . $patient['name'] . "'s patient profile.");
}

// "View Patient History" in doctor/consultations.php's
// #startConsultationModal (assets/js/doctor_consultations.js) links
// here with &from=consultation — the back link/breadcrumb below (and
// which sidebar item lights up) point back to consultations.php in
// that case instead of the usual patient_management.php default, so a
// doctor checking history mid-flow has a real way back.
$cameFromConsultation = ($_GET['from'] ?? '') === 'consultation';

// Sidebar nav — same Doctor nav list as doctor/dashboard.php.
$doctorNavItems = [
    ['icon' => 'bi-grid-fill',           'label' => 'Dashboard',          'href' => 'dashboard.php'],
    ['icon' => 'bi-person-lines-fill',   'label' => 'Patient Management', 'href' => 'patient_management.php', 'active' => !$cameFromConsultation],
    ['icon' => 'bi-calendar2-heart-fill','label' => 'Consultations',      'href' => 'consultations.php', 'active' => $cameFromConsultation],
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
<title>Patient Profile — HEALTrack</title>

<!-- Google Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<!-- Bootstrap 5 -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<!-- Font Awesome (available per project spec; Bootstrap Icons cover this page's icons) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<!-- Shared admin/subadmin shell (sidebar, topbar, cards, tabs, etc.) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/admin-dashboard.css') ?>">
<!-- Doctor Dashboard styles, reused here for the nav badge + topbar select buttons -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/doctor_dashboard.css') ?>">
<!-- Patient Profile page styles (breadcrumb, profile header, overview cards) — shared verbatim with medical-staff/patient_profile.php -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/patient_profile.css') ?>">
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
    <?php if ($cameFromConsultation): ?>
    <a href="consultations.php" class="adm-back-link">
        <i class="bi bi-arrow-left"></i> Back to Consultations
    </a>
    <?php else: ?>
    <a href="patient_management.php" class="adm-back-link">
        <i class="bi bi-arrow-left"></i> Back to Patient Management
    </a>
    <?php endif; ?>

    <!-- ============ TOP HEADER ============ -->
    <div class="adm-topbar">
        <div>
            <h1><?= $cameFromConsultation ? 'Consultations' : 'Patient Management' ?> <span class="ppf-title-sep">/</span> Patient Profile</h1>
            <nav class="ppf-breadcrumb" aria-label="Breadcrumb">
                <a href="dashboard.php">Dashboard</a>
                <span class="ppf-breadcrumb-sep">&gt;</span>
                <?php if ($cameFromConsultation): ?>
                <a href="consultations.php">Consultations</a>
                <?php else: ?>
                <a href="patient_management.php">Patient Management</a>
                <?php endif; ?>
                <span class="ppf-breadcrumb-sep">&gt;</span>
                <span class="ppf-breadcrumb-current">Patient Profile</span>
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

    <?php if ($accessRestricted): ?>
    <!-- ============ PATIENT DETAILS RESTRICTED ============
         Real patient-consent gate — includes/shared/patient_consent.php's
         hc_patient_consent_status() found no same-day registrant/
         consultation exception and no accepted consent record for THIS
         doctor and this exact patient. -->
    <div class="adm-card ppf-profile-card">
        <div class="ppf-restricted">
            <i class="bi bi-shield-lock-fill ppf-restricted-icon"></i>
            <h2><?= htmlspecialchars($patient['name']) ?></h2>
            <p class="ppf-restricted-sub">This patient's full details are private. You need their consent before you can view this profile — unless you have a consultation assigned to you for them today.</p>

            <?php if ($consentStatus['pendingRequest'] !== null): ?>
            <div class="ppf-restricted-status pending">
                <i class="bi bi-hourglass-split"></i>
                Request sent on <?= htmlspecialchars(date('M j, Y', strtotime($consentStatus['pendingRequest']['givenOn']))) ?> — awaiting the patient's response.
            </div>
            <?php else: ?>

            <?php if ($consentStatus['declinedRequest'] !== null): ?>
            <div class="ppf-restricted-status declined">
                <i class="bi bi-x-circle-fill"></i>
                Previously declined on <?= htmlspecialchars(date('M j, Y', strtotime($consentStatus['declinedRequest']['respondedAt'] ?? $consentStatus['declinedRequest']['givenOn']))) ?>. You may send a new request.
            </div>
            <?php endif; ?>

            <div id="pcrError" class="alert alert-danger py-2 px-3 d-none" style="font-size:13px;"></div>
            <form id="patientConsentRequestForm">
                <div class="mb-3 text-start">
                    <label for="pcrReason" class="form-label fw-semibold">Why do you need to view this patient's details?</label>
                    <textarea class="form-control" id="pcrReason" rows="3" required placeholder="e.g. Reviewing history ahead of an upcoming referral"></textarea>
                </div>
                <button type="submit" class="ppf-restricted-btn" id="pcrSubmitBtn">
                    <i class="bi bi-send-fill"></i> Send Consent Request
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>
    <script>window.PATIENT_CONSENT_REQUEST = <?= json_encode(['patientId' => $requestedPatientId]) ?>;</script>
    <script src="<?= asset_url('/assets/js/patient-consent-request.js') ?>"></script>

    <?php else: ?>
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

    <!-- ============ PROFILE TABS ============
         Every tab now links to a real page (see this page's header
         comment) — only Overview stays a real in-page tab pane. -->
    <ul class="nav adm-tabs" role="tablist">
        <?php foreach ($profileTabs as $tab): ?>
        <li class="nav-item" role="presentation">
            <?php if ($tab['key'] === 'overview'): ?>
            <button class="nav-link <?= !empty($tab['active']) ? 'active' : '' ?>"
                    id="tab-<?= htmlspecialchars($tab['key']) ?>-btn"
                    data-bs-toggle="tab"
                    data-bs-target="#tab-<?= htmlspecialchars($tab['key']) ?>"
                    type="button" role="tab">
                <i class="bi <?= htmlspecialchars($tab['icon']) ?>"></i> <?= htmlspecialchars($tab['label']) ?>
            </button>
            <?php elseif ($tab['key'] === 'medical_records'): ?>
            <a href="medical_history.php?id=<?= urlencode($patient['id']) ?>" class="nav-link" data-ajax-tab>
                <i class="bi <?= htmlspecialchars($tab['icon']) ?>"></i> <?= htmlspecialchars($tab['label']) ?>
            </a>
            <?php elseif ($tab['key'] === 'lab_xray'): ?>
            <a href="patient_laboratory_xray.php?id=<?= urlencode($patient['id']) ?>" class="nav-link" data-ajax-tab>
                <i class="bi <?= htmlspecialchars($tab['icon']) ?>"></i> <?= htmlspecialchars($tab['label']) ?>
            </a>
            <?php elseif ($tab['key'] === 'vaccination'): ?>
            <a href="vaccination_records.php?id=<?= urlencode($patient['id']) ?>" class="nav-link" data-ajax-tab>
                <i class="bi <?= htmlspecialchars($tab['icon']) ?>"></i> <?= htmlspecialchars($tab['label']) ?>
            </a>
            <?php elseif ($tab['key'] === 'allergies'): ?>
            <a href="allergies.php?id=<?= urlencode($patient['id']) ?>" class="nav-link" data-ajax-tab>
                <i class="bi <?= htmlspecialchars($tab['icon']) ?>"></i> <?= htmlspecialchars($tab['label']) ?>
            </a>
            <?php elseif ($tab['key'] === 'medications'): ?>
            <a href="medications.php?id=<?= urlencode($patient['id']) ?>" class="nav-link" data-ajax-tab>
                <i class="bi <?= htmlspecialchars($tab['icon']) ?>"></i> <?= htmlspecialchars($tab['label']) ?>
            </a>
            <?php elseif ($tab['key'] === 'documents'): ?>
            <a href="documents.php?id=<?= urlencode($patient['id']) ?>" class="nav-link" data-ajax-tab>
                <i class="bi <?= htmlspecialchars($tab['icon']) ?>"></i> <?= htmlspecialchars($tab['label']) ?>
            </a>
            <?php elseif ($tab['key'] === 'history'): ?>
            <a href="history.php?id=<?= urlencode($patient['id']) ?>" class="nav-link" data-ajax-tab>
                <i class="bi <?= htmlspecialchars($tab['icon']) ?>"></i> <?= htmlspecialchars($tab['label']) ?>
            </a>
            <?php endif; ?>
        </li>
        <?php endforeach; ?>
    </ul>

    <div class="tab-content">
        <?php foreach ($profileTabs as $tab): ?>
        <div class="tab-pane fade <?= !empty($tab['active']) ? 'show active' : '' ?>" id="tab-<?= htmlspecialchars($tab['key']) ?>" role="tabpanel">
        <?php if ($tab['key'] === 'overview'): ?>

            <!-- ============ OVERVIEW: ROW 1 ============ -->
            <div class="ppf-row-3">

                <div class="adm-card ppf-ov-card">
                    <div class="ppf-ov-header"><i class="bi bi-clipboard2-fill"></i> <h3>Summary</h3></div>
                    <div class="ppf-summary-list">
                        <div class="ppf-summary-row"><span>Total Consultations</span><strong><?= (int) $summary['totalConsultations'] ?></strong></div>
                        <div class="ppf-summary-row"><span>Active Medical Conditions</span><strong><?= (int) $summary['activeMedicalConditions'] ?></strong></div>
                        <div class="ppf-summary-row"><span>Allergies</span><strong><?= (int) $summary['allergies'] ?></strong></div>
                        <div class="ppf-summary-row"><span>Current Medications</span><strong><?= (int) $summary['currentMedications'] ?></strong></div>
                    </div>
                    <a href="history.php?id=<?= urlencode($patient['id']) ?>" class="ppf-card-link">View Full History <i class="bi bi-arrow-right"></i></a>
                </div>

                <div class="adm-card ppf-ov-card">
                    <div class="ppf-ov-header"><i class="bi bi-heart-pulse-fill"></i> <h3>Active Medical Conditions</h3></div>
                    <?php foreach ($activeMedicalConditions as $c): ?>
                    <div class="ppf-condition-chip"><?= htmlspecialchars($c['label']) ?></div>
                    <div class="ppf-condition-meta">
                        <span>Diagnosed on: <?= htmlspecialchars($c['diagnosedOn']) ?></span>
                        <span>Status: <strong class="ppf-status-active"><?= htmlspecialchars($c['status']) ?></strong></span>
                    </div>
                    <?php endforeach; ?>
                    <a href="medical_history.php?id=<?= urlencode($patient['id']) ?>" class="ppf-card-link">View Medical History <i class="bi bi-arrow-right"></i></a>
                </div>

                <div class="adm-card ppf-ov-card">
                    <div class="ppf-ov-header"><i class="bi bi-mortarboard-fill"></i> <h3>Latest Consultation</h3></div>
                    <p class="ppf-consult-datetime"><?= htmlspecialchars($latestConsultation['date']) ?> &bull; <?= htmlspecialchars($latestConsultation['time']) ?></p>
                    <p class="ppf-consult-line"><span>Chief Complaint:</span><br><?= htmlspecialchars($latestConsultation['chiefComplaint']) ?></p>
                    <p class="ppf-consult-line"><span>Diagnosis:</span><br><?= htmlspecialchars($latestConsultation['diagnosis']) ?></p>
                    <p class="ppf-consult-line"><span>Physician:</span> <?= htmlspecialchars($latestConsultation['physician']) ?></p>
                    <?php if ($latestConsultation['vitals'] !== null): ?>
                    <p class="ppf-consult-line"><span>Vitals:</span><br><?= htmlspecialchars($latestConsultation['vitals']) ?></p>
                    <?php endif; ?>
                    <a href="history.php?id=<?= urlencode($patient['id']) ?>" class="ppf-card-link">View Consultation History <i class="bi bi-arrow-right"></i></a>
                </div>

            </div>

            <!-- ============ OVERVIEW: ROW 2 ============ -->
            <div class="ppf-row-3">

                <div class="adm-card ppf-ov-card">
                    <div class="ppf-ov-header with-action">
                        <div><i class="bi bi-eyedropper"></i> <h3>Recent Laboratory Results</h3></div>
                        <a href="patient_laboratory_xray.php?id=<?= urlencode($patient['id']) ?>" class="ppf-view-all">View All</a>
                    </div>
                    <div class="ppf-list">
                        <?php foreach ($recentLabResults as $lab): $status = $labStatusMeta[$lab['status']] ?? $labStatusMeta['normal']; ?>
                        <div class="ppf-list-row">
                            <div>
                                <div class="ppf-list-title"><?= htmlspecialchars($lab['name']) ?></div>
                                <div class="ppf-list-sub"><?= htmlspecialchars($lab['date']) ?></div>
                            </div>
                            <span class="ppf-badge" style="background:<?= htmlspecialchars($status['bg']) ?>; color:<?= htmlspecialchars($status['color']) ?>;"><?= htmlspecialchars($status['label']) ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <a href="patient_laboratory_xray.php?id=<?= urlencode($patient['id']) ?>" class="ppf-card-link">Go to Laboratory &amp; X-ray <i class="bi bi-arrow-right"></i></a>
                </div>

                <div class="adm-card ppf-ov-card">
                    <div class="ppf-ov-header with-action">
                        <div><i class="bi bi-lungs-fill"></i> <h3>Recent X-ray Results</h3></div>
                        <a href="patient_laboratory_xray.php?id=<?= urlencode($patient['id']) ?>" class="ppf-view-all">View All</a>
                    </div>
                    <div class="ppf-xray-row">
                        <div class="ppf-xray-thumb"><i class="bi bi-lungs"></i></div>
                        <div>
                            <div class="ppf-list-title"><?= htmlspecialchars($recentXray['name']) ?></div>
                            <div class="ppf-list-sub"><?= htmlspecialchars($recentXray['date']) ?></div>
                            <?php $xrayStatus = $labStatusMeta[$recentXray['status']] ?? $labStatusMeta['normal']; ?>
                            <span class="ppf-badge" style="background:<?= htmlspecialchars($xrayStatus['bg']) ?>; color:<?= htmlspecialchars($xrayStatus['color']) ?>;"><?= htmlspecialchars($xrayStatus['label']) ?></span>
                        </div>
                    </div>
                    <p class="ppf-consult-line"><span>Findings:</span><br><?= htmlspecialchars($recentXray['findings']) ?></p>
                    <a href="patient_laboratory_xray.php?id=<?= urlencode($patient['id']) ?>" class="ppf-card-link">Go to Laboratory &amp; X-ray <i class="bi bi-arrow-right"></i></a>
                </div>

                <div class="adm-card ppf-ov-card">
                    <div class="ppf-ov-header with-action">
                        <div><i class="bi bi-clipboard2-pulse-fill"></i> <h3>Vaccination History</h3></div>
                        <a href="vaccination_records.php?id=<?= urlencode($patient['id']) ?>" class="ppf-view-all">View All</a>
                    </div>
                    <div class="ppf-list">
                        <?php foreach ($vaccinationHistory as $v): $status = $vaccineStatusMeta[$v['status']] ?? $vaccineStatusMeta['completed']; ?>
                        <div class="ppf-list-row">
                            <div>
                                <div class="ppf-list-title"><?= htmlspecialchars($v['name']) ?></div>
                                <div class="ppf-list-sub"><?= htmlspecialchars($v['date']) ?></div>
                            </div>
                            <span class="ppf-badge" style="background:<?= htmlspecialchars($status['bg']) ?>; color:<?= htmlspecialchars($status['color']) ?>;"><?= htmlspecialchars($status['label']) ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <a href="vaccination_records.php?id=<?= urlencode($patient['id']) ?>" class="ppf-card-link">Go to Vaccination Records <i class="bi bi-arrow-right"></i></a>
                </div>

            </div>

        <?php else: ?>
            <div class="ppf-tab-placeholder">
                <i class="bi bi-cone-striped"></i>
                <p>The "<?= htmlspecialchars($tab['label']) ?>" section isn't built yet — check back soon.</p>
            </div>
        <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

</main>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<link rel="stylesheet" href="<?= asset_url('/assets/css/hc-dialogs.css') ?>">
<script src="<?= asset_url('/assets/js/hc-dialogs.js') ?>"></script>
<script src="<?= asset_url('/assets/js/admin-sidebar-toggle.js') ?>"></script>
<script src="<?= asset_url('/assets/js/ajax-tab-nav.js') ?>"></script>
</body>
</html>
