<?php
/**
 * =====================================================================
 * HEALTrack — Health Information System
 * Active Consultation (doctor/active_consultation.php)
 * ---------------------------------------------------------------------
 * The clinical documentation workspace a doctor lands on after
 * "Proceed with Consultation" in doctor/consultations.php's
 * #startConsultationModal (assets/js/doctor_consultations.js). Reuses
 * the exact same sidebar/topbar shell as the rest of doctor/*.php.
 *
 * CONNECTED throughout — see
 * includes/doctor/active_consultation_doctor_firestore.php's header
 * comment for exactly what's real (patient header, Chief Complaint,
 * Past Medical History, Allergies, Vitals prefill) vs. brand new to
 * this page (History of Present Illness, Review of Symptoms,
 * Diagnosis, Prescription, this-visit Vitals, Medications/Vaccines
 * given, "Patient Will Be Next In" routing, doctor's own Additional
 * Notes) — all captured here and persisted only on "Save & End
 * Consultation" (assets/js/active_consultation.js -> api/complete-consultation.php).
 * "Cancel Consultation" reuses api/update-consultation.php unchanged
 * (status -> "cancelled").
 * =====================================================================
 */
session_start();

require_once __DIR__ . '/../config/constants.php'; // BASE_URL, asset_url()

// Only a signed-in doctor may view this page.
if (($_SESSION['role'] ?? null) !== 'doctor') {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

require_once __DIR__ . '/../includes/doctor/active_consultation_doctor_firestore.php';
// $doctorProfile, $dashboardDateLabel, $facilityOptions, $consultation,
// $patient, $consultationType, $consultationStatus, $medicalConditions,
// $allergies, $vitalsPrefill, $createdByName, $startedAtLabel,
// $linkedLabResults, $labTestsAlreadyOrdered, $routingOptions,
// $labTestCategories, $medicineNames, $vaccineNames

// Sidebar nav — same Doctor nav list as every other doctor/*.php page;
// Consultations stays "active" since this page is reached only from
// there.
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
<title>Consultation — HEALTrack</title>

<!-- Google Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<!-- Bootstrap 5 -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<!-- Font Awesome (available per project spec) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<!-- Shared admin shell (sidebar, topbar, cards, table, pagination, etc.) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/admin-dashboard.css') ?>">
<!-- Doctor Dashboard styles, reused here for the nav badge + topbar select buttons -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/doctor_dashboard.css') ?>">
<!-- Reused for .ppf-breadcrumb -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/patient_profile.css') ?>">
<!-- Reused for .cns-badge -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/consultations.css') ?>">
<!-- This page's own styles -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/active_consultation.css') ?>">
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

    <div class="adm-brand">
        <div class="adm-logo-badge"><i class="bi bi-heart-pulse-fill"></i></div>
        <div class="adm-brand-text">
            <h1>HEALTrack</h1>
            <p>Health Information System</p>
        </div>
    </div>

    <hr class="adm-sidebar-divider">

    <div class="adm-profile">
        <div class="adm-avatar"><?php require_once __DIR__ . '/../includes/shared/profile_photo_storage.php'; echo hc_render_sidebar_avatar(); ?></div>
        <div class="adm-profile-text">
            <div class="adm-profile-name"><?= htmlspecialchars($doctorProfile['name']) ?></div>
            <div class="adm-profile-role"><?= htmlspecialchars($doctorProfile['role']) ?></div>
        </div>
    </div>

    <hr class="adm-sidebar-divider">

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
            <h1>Consultation <span class="cns-badge" style="background:<?= htmlspecialchars($consultationStatus['bg']) ?>; color:<?= htmlspecialchars($consultationStatus['color']) ?>;"><?= htmlspecialchars($consultationStatus['label']) ?></span></h1>
            <nav class="ppf-breadcrumb" aria-label="Breadcrumb">
                <a href="dashboard.php">Dashboard</a>
                <span class="ppf-breadcrumb-sep">&gt;</span>
                <a href="consultations.php">Consultation Logs</a>
                <span class="ppf-breadcrumb-sep">&gt;</span>
                <span class="ppf-breadcrumb-current">Active Consultation</span>
            </nav>
        </div>

        <div class="adm-topbar-right">
            <div class="adm-topbar-actions">
                <div class="doc-topbar-select-btn">
                    <i class="bi bi-calendar3"></i> <?= htmlspecialchars($dashboardDateLabel) ?>
                </div>
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

    <!-- ============ PAGE ACTIONS ============ -->
    <div class="acv-page-actions">
        <button type="button" class="acv-btn-cancel" id="acvCancelBtn"><i class="bi bi-x-lg"></i> Cancel Consultation</button>
        <?php if ($consultation['nextRouting'] === 'laboratory'): ?>
        <button type="button" class="acv-btn-save" id="acvSaveBtn"><i class="bi bi-send-fill"></i> Send to Medical Technologist</button>
        <?php else: ?>
        <button type="button" class="acv-btn-save" id="acvSaveBtn"><i class="bi bi-save"></i> Save &amp; End Consultation</button>
        <?php endif; ?>
    </div>

    <!-- ============ PATIENT HEADER CARD ============ -->
    <div class="adm-card acv-patient-card">
        <div class="acv-patient-top">
            <div class="acv-patient-identity">
                <div class="acv-patient-avatar"><i class="bi bi-person-fill"></i></div>
                <div>
                    <div class="acv-patient-name-row">
                        <span class="acv-patient-name"><?= htmlspecialchars($patient['name']) ?></span>
                        <span class="cns-badge" style="background:<?= htmlspecialchars($consultationType['bg']) ?>; color:<?= htmlspecialchars($consultationType['color']) ?>;"><?= htmlspecialchars($consultationType['label']) ?></span>
                    </div>
                    <div class="acv-patient-meta"><?= (int) $patient['age'] ?> years old (<?= htmlspecialchars($patient['gender']) ?>)</div>
                    <div class="acv-patient-contact"><i class="bi bi-telephone-fill"></i> <?= htmlspecialchars($patient['phone']) ?><span class="acv-contact-sep"></span><i class="bi bi-envelope-fill"></i> <?= htmlspecialchars($patient['email']) ?></div>
                    <div class="acv-patient-contact"><i class="bi bi-geo-alt-fill"></i> <?= htmlspecialchars($patient['address']) ?></div>
                </div>
            </div>
            <div class="acv-patient-info-col">
                <div class="acv-info-item"><span class="acv-info-label">Blood Type</span><span class="acv-info-value"><?= htmlspecialchars($patient['bloodType']) ?></span></div>
                <div class="acv-info-item"><span class="acv-info-label">Civil Status</span><span class="acv-info-value"><?= htmlspecialchars($patient['civilStatus']) ?></span></div>
                <div class="acv-info-item"><span class="acv-info-label">Occupation</span><span class="acv-info-value"><?= htmlspecialchars($patient['occupation']) ?></span></div>
            </div>
        </div>
        <div class="acv-patient-bottom">
            <div class="acv-info-item"><span class="acv-info-label">Arrival Time</span><span class="acv-info-value"><?= $consultation['dateTime'] !== '' ? htmlspecialchars(date('g:i A', strtotime($consultation['dateTime']))) : '—' ?></span></div>
            <div class="acv-info-item"><span class="acv-info-label">Reason for Visit</span><span class="acv-info-value"><?= htmlspecialchars($consultation['chiefComplaint']) ?></span></div>
            <div class="acv-info-item"><span class="acv-info-label">Current Status</span><span class="acv-info-value" style="color:<?= htmlspecialchars($consultationStatus['color']) ?>;"><?= htmlspecialchars($consultationStatus['label']) ?></span></div>
        </div>
    </div>

    <!-- ============ WORKSPACE ============ -->
    <form id="acvForm">
    <div class="acv-workspace">

        <!-- ============ LEFT COLUMN ============ -->
        <div class="acv-main-col">

            <div class="acv-grid-2">
                <div class="adm-card">
                    <div class="acv-field-label">Chief Complaint <span class="acv-source-tag">(from Front Desk)</span></div>
                    <div class="acv-readonly-box"><?= htmlspecialchars($consultation['chiefComplaint']) ?></div>
                </div>
                <div class="adm-card">
                    <label class="acv-field-label" for="acvHpi">History of Present Illness</label>
                    <textarea class="acv-textarea" id="acvHpi" maxlength="2000" placeholder="Describe the history of present illness..."><?= htmlspecialchars($consultation['historyOfPresentIllness']) ?></textarea>
                    <div class="acv-char-count" data-for="acvHpi">0/2000</div>
                </div>
            </div>

            <?php if (!empty($linkedLabResults)): ?>
            <div class="adm-card">
                <div class="acv-field-label"><i class="bi bi-clipboard2-pulse-fill"></i> Lab &amp; X-ray Results <span class="acv-source-tag">(from Medical Technologist)</span></div>
                <div style="display:flex; flex-direction:column; gap:10px; margin-top:10px;">
                    <?php foreach ($linkedLabResults as $lr): ?>
                    <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; padding:12px 14px; border:1px solid var(--adm-border); border-radius:10px;">
                        <div>
                            <strong><?= htmlspecialchars($lr['testName']) ?></strong>
                            <div class="acv-source-tag" style="margin-top:2px;">Completed <?= htmlspecialchars($lr['completedDate']) ?> at <?= htmlspecialchars($lr['completedTime']) ?></div>
                        </div>
                        <div style="display:flex; align-items:center; gap:10px; flex-shrink:0;">
                            <span class="cns-badge" style="background:<?= htmlspecialchars($lr['resultBadgeBg']) ?>; color:<?= htmlspecialchars($lr['resultBadgeColor']) ?>;"><?= htmlspecialchars($lr['resultBadgeLabel']) ?></span>
                            <a href="laboratory_result_details.php?id=<?= urlencode($patient['id']) ?>&recordId=<?= urlencode($lr['id']) ?>&type=<?= urlencode($lr['type']) ?>&consultationId=<?= urlencode($consultation['id']) ?>" class="acv-view-result-btn" target="_blank" rel="noopener"><i class="bi bi-eye"></i> View Full Result <i class="bi bi-box-arrow-up-right"></i></a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="acv-grid-2">
                <div class="adm-card">
                    <div class="acv-field-label">Past Medical History <span class="acv-source-tag">(from Front Desk)</span></div>
                    <div class="acv-readonly-box">
                        <?php if (empty($medicalConditions)): ?>
                        No significant past medical history.
                        <?php else: ?>
                        <?= htmlspecialchars(implode(', ', array_column($medicalConditions, 'label'))) ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="adm-card">
                    <div class="acv-field-label acv-field-label-row">
                        <span>Allergies <span class="acv-source-tag">(from Front Desk)</span></span>
                        <button type="button" class="acv-btn-add-row" id="acvAddAllergyToggle"><i class="bi bi-plus-lg"></i> Add Allergy</button>
                    </div>
                    <div class="acv-readonly-box" id="acvAllergyList">
                        <?php if (empty($allergies)): ?>
                        No known allergies.
                        <?php else: ?>
                        <?= htmlspecialchars(implode(', ', array_map(fn ($a) => $a['allergen'] . ' (' . $a['severity'] . ')', $allergies))) ?>
                        <?php endif; ?>
                    </div>
                    <!-- Same real "allergies" collection every other role's
                         Allergies page reads (includes/firestore/allergies.php) —
                         this is the first WRITE path it's ever had outside
                         of patient registration (api/create-patient.php),
                         see api/add-allergy.php. Hidden by default; toggled
                         by #acvAddAllergyToggle. -->
                    <div class="acv-add-allergy-form d-none" id="acvAddAllergyForm">
                        <input type="hidden" id="acvAllergyPatientId" value="<?= htmlspecialchars($patient['id']) ?>">
                        <div class="acv-add-allergy-row">
                            <input type="text" class="form-control form-control-sm" id="acvAllergyAllergen" placeholder="Allergen (e.g. Penicillin)">
                            <input type="text" class="form-control form-control-sm" id="acvAllergyReaction" placeholder="Reaction (e.g. Rash)">
                            <select class="form-select form-select-sm" id="acvAllergySeverity">
                                <option value="mild">Mild</option>
                                <option value="moderate">Moderate</option>
                                <option value="severe">Severe</option>
                            </select>
                        </div>
                        <div class="acv-add-allergy-actions">
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="acvAddAllergyCancel">Cancel</button>
                            <button type="button" class="btn btn-sm acv-btn-save" id="acvAddAllergySave">Save Allergy</button>
                        </div>
                        <div class="acv-add-allergy-error text-danger small d-none" id="acvAddAllergyError"></div>
                    </div>
                </div>
            </div>

            <div class="adm-card">
                <label class="acv-field-label" for="acvRos">Review of Symptoms (ROS)</label>
                <textarea class="acv-textarea" id="acvRos" maxlength="2000" placeholder="Document the review of symptoms..."><?= htmlspecialchars($consultation['reviewOfSymptoms']) ?></textarea>
                <div class="acv-char-count" data-for="acvRos">0/2000</div>
            </div>

            <div class="acv-section-header"><i class="bi bi-prescription2"></i> Prescription / Treatment Plan</div>

            <div class="adm-card">
                <label class="acv-field-label" for="acvDiagnosis">Clinical Impression / Diagnosis <span class="text-muted fw-normal">(Optional)</span></label>
                <textarea class="acv-textarea" id="acvDiagnosis" maxlength="1000" placeholder="Type diagnosis or clinical impression..."><?= htmlspecialchars($consultation['diagnosis']) ?></textarea>
                <div class="acv-char-count" data-for="acvDiagnosis">0/1000</div>
            </div>

            <div class="adm-card">
                <label class="acv-field-label">Prescription / Treatment <span class="text-muted fw-normal">(Type manually)</span></label>
                <div class="acv-rte-toolbar">
                    <button type="button" class="acv-rte-btn" data-cmd="bold"><i class="bi bi-type-bold"></i></button>
                    <button type="button" class="acv-rte-btn" data-cmd="italic"><i class="bi bi-type-italic"></i></button>
                    <button type="button" class="acv-rte-btn" data-cmd="underline"><i class="bi bi-type-underline"></i></button>
                    <button type="button" class="acv-rte-btn" data-cmd="insertUnorderedList"><i class="bi bi-list-ul"></i></button>
                    <button type="button" class="acv-rte-btn" data-cmd="insertOrderedList"><i class="bi bi-list-ol"></i></button>
                    <button type="button" class="acv-rte-btn" data-cmd="createLink"><i class="bi bi-link-45deg"></i></button>
                </div>
                <div class="acv-rte-editor" id="acvPrescription" contenteditable="true" data-placeholder="Type prescription or treatment plan here..."><?= $consultation['prescriptionHtml'] !== '' ? $consultation['prescriptionHtml'] : '' ?></div>
                <div class="acv-char-count" data-for="acvPrescription">0/2000</div>
            </div>

            <!-- Hidden until "Patient Will Be Next In" (right column,
                 below) is set to Pharmacy or Vaccination — same
                 routing-driven show/hide as the "Select Laboratory
                 Tests" panel under the Laboratory option
                 (assets/js/active_consultation.js), just without that
                 panel's confirm/summary step since there's nothing to
                 collapse here. Shown by default only when re-opening a
                 consultation already routed to one of those two. -->
            <div class="adm-card <?= in_array($consultation['nextRouting'], ['pharmacy', 'vaccination'], true) ? '' : 'd-none' ?>" id="acvMedVaccinePanel">
                <div class="acv-field-label"><i class="bi bi-capsule"></i> Medications &amp; Vaccines</div>
                <ul class="nav nav-tabs acv-tabs" role="tablist">
                    <li class="nav-item"><button type="button" class="nav-link active acv-tab-btn" data-type="medication">Medications</button></li>
                    <li class="nav-item"><button type="button" class="nav-link acv-tab-btn" data-type="vaccine">Vaccines</button></li>
                </ul>
                <div class="adm-table-wrap">
                    <table class="adm-table acv-med-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Medicine / Vaccine</th>
                                <th>Dosage</th>
                                <th>Frequency</th>
                                <th>Duration</th>
                                <th>Route</th>
                                <th>Instructions</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="acvMedRows"></tbody>
                    </table>
                </div>
                <button type="button" class="acv-btn-add-row" id="acvAddMedRow"><i class="bi bi-plus-lg"></i> Add Another</button>
            </div>

        </div>

        <!-- ============ RIGHT COLUMN ============ -->
        <div class="acv-side-col">

            <div class="adm-card">
                <div class="acv-field-label">Vital Signs <span class="acv-source-tag">(from Front Desk)</span></div>
                <div class="acv-vitals-grid">
                    <div class="acv-vital-field">
                        <label for="acvVitalTemp">Temperature</label>
                        <div class="input-group input-group-sm"><input type="number" step="0.1" min="25" max="45" class="form-control" id="acvVitalTemp" value="<?= htmlspecialchars((string) $vitalsPrefill['temperature']) ?>"><span class="input-group-text">°C</span></div>
                    </div>
                    <div class="acv-vital-field">
                        <label for="acvVitalBpSys">Blood Pressure</label>
                        <div class="input-group input-group-sm">
                            <input type="number" step="1" min="40" max="300" class="form-control" id="acvVitalBpSys" placeholder="Sys" value="<?= htmlspecialchars((string) $vitalsPrefill['bloodPressureSystolic']) ?>">
                            <span class="input-group-text">/</span>
                            <input type="number" step="1" min="20" max="200" class="form-control" id="acvVitalBpDia" placeholder="Dia" value="<?= htmlspecialchars((string) $vitalsPrefill['bloodPressureDiastolic']) ?>">
                            <span class="input-group-text">mmHg</span>
                        </div>
                    </div>
                    <div class="acv-vital-field">
                        <label for="acvVitalHr">Heart Rate</label>
                        <div class="input-group input-group-sm"><input type="number" step="1" min="20" max="250" class="form-control" id="acvVitalHr" value="<?= htmlspecialchars((string) $vitalsPrefill['heartRate']) ?>"><span class="input-group-text">bpm</span></div>
                    </div>
                    <!-- Weight/Height/BMI are read-only here — set once by
                         medical staff at check-in (Set Consultation's own
                         Vital Signs section), not something a doctor
                         re-measures mid-visit the way temp/BP/HR often get
                         re-checked. Not submitted back by
                         assets/js/active_consultation.js — api/complete-consultation.php
                         preserves whatever this consultation already has for
                         these three. -->
                    <div class="acv-vital-field">
                        <label for="acvVitalWeight">Weight</label>
                        <div class="input-group input-group-sm"><input type="text" class="form-control" id="acvVitalWeight" value="<?= htmlspecialchars((string) $vitalsPrefill['weight']) ?>" readonly><span class="input-group-text">kg</span></div>
                    </div>
                    <div class="acv-vital-field">
                        <label for="acvVitalHeight">Height</label>
                        <div class="input-group input-group-sm"><input type="text" class="form-control" id="acvVitalHeight" value="<?= htmlspecialchars((string) $vitalsPrefill['height']) ?>" readonly><span class="input-group-text">cm</span></div>
                    </div>
                    <div class="acv-vital-field">
                        <label for="acvVitalBmi">BMI</label>
                        <div class="input-group input-group-sm"><input type="text" class="form-control" id="acvVitalBmi" value="<?= htmlspecialchars((string) $vitalsPrefill['bmi']) ?>" readonly><span class="input-group-text">kg/m&sup2;</span></div>
                    </div>
                </div>
                <!-- Opens in a new tab deliberately — this form has no
                     autosave/draft, so navigating away in the SAME tab
                     would silently lose everything typed so far
                     (HPI/ROS/diagnosis/prescription/medications/vitals/
                     notes), with no way back via the sidebar (this
                     workspace isn't a nav item, only reachable via
                     "Proceed with Consultation"). -->
                <a href="patient_profile.php?id=<?= urlencode($patient['id']) ?>" class="acv-view-full-link" target="_blank" rel="noopener">View Full Vital Signs <i class="bi bi-box-arrow-up-right"></i></a>
            </div>

            <div class="adm-card">
                <div class="acv-field-label">Patient Will Be Next In</div>
                <div class="acv-field-help">Select where the patient will proceed next.</div>
                <div class="acv-routing-list">
                    <?php foreach ($routingOptions as $key => $opt): ?>
                    <label class="acv-routing-item">
                        <span class="acv-routing-icon"><i class="bi <?= htmlspecialchars($opt['icon']) ?>"></i></span>
                        <span class="acv-routing-text">
                            <span class="acv-routing-title"><?= htmlspecialchars($opt['label']) ?></span>
                            <span class="acv-routing-desc"><?= htmlspecialchars($opt['desc']) ?></span>
                        </span>
                        <input type="radio" name="acvRouting" value="<?= htmlspecialchars($key) ?>" <?= $consultation['nextRouting'] === $key ? 'checked' : '' ?>>
                    </label>
                    <?php if ($key === 'laboratory'): ?>
                    <!-- Nested directly under the "Laboratory" option
                         itself (not after the whole routing list), so it
                         reads as that option expanding in place rather
                         than a disconnected block below Pharmacy/
                         Vaccination/Follow-up. Shown only when
                         "Laboratory" is selected
                         (assets/js/active_consultation.js) — at least
                         one test required in that case. Each checked
                         test becomes its own REAL pending lab_results
                         doc on Confirm/Save (api/complete-consultation.php),
                         which the medical technologist's own queue/
                         notifications already pick up (status "pending"
                         — see includes/firestore/lab_results.php's
                         hc_get_pending_lab_xray_notifications()), no
                         other wiring needed on their side. -->
                    <div class="acv-lab-test-panel d-none" id="acvLabTestPanel">
                        <div class="acv-lab-test-header">
                            <span class="acv-lab-test-icon"><i class="bi bi-clipboard2-pulse"></i></span>
                            <div>
                                <div class="acv-lab-test-title">Select Laboratory Tests</div>
                                <div class="acv-lab-test-desc">Choose the laboratory tests needed for this patient.</div>
                            </div>
                        </div>
                        <div class="acv-lab-test-grid">
                            <?php foreach ($labTestCategories as $category => $tests): ?>
                            <div class="acv-lab-test-category">
                                <div class="acv-lab-test-category-title"><?= htmlspecialchars($category) ?></div>
                                <?php foreach ($tests as $test):
                                    $orderedStatus = $labTestsAlreadyOrdered[$test] ?? null;
                                ?>
                                <?php if ($orderedStatus !== null): ?>
                                <!-- Already has a real lab_results doc from
                                     THIS consultation — locked (checked +
                                     disabled) so re-saving with Laboratory
                                     still selected can't create a second,
                                     duplicate order for the same test. A
                                     disabled checkbox's "checked" state still
                                     reads correctly in JS (checkedLabTests()),
                                     it just can't be toggled. -->
                                <label class="acv-lab-test-checkbox acv-lab-test-checkbox-locked" title="<?= $orderedStatus === 'completed' ? 'Result already received' : 'Already sent — awaiting result' ?>">
                                    <input type="checkbox" value="<?= htmlspecialchars($test) ?>" checked disabled>
                                    <?= htmlspecialchars($test) ?>
                                    <span class="acv-source-tag">(<?= $orderedStatus === 'completed' ? 'result received' : 'awaiting result' ?>)</span>
                                </label>
                                <?php else: ?>
                                <label class="acv-lab-test-checkbox">
                                    <input type="checkbox" value="<?= htmlspecialchars($test) ?>" <?= in_array($test, $consultation['labTests'], true) ? 'checked' : '' ?>>
                                    <?= htmlspecialchars($test) ?>
                                </label>
                                <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Staff picker — "who should actually get this
                             order" (api/complete-consultation.php's
                             assignedToUid/assignedToName). Only shown as
                             an actual choice when there's more than one
                             active medical technologist at this doctor's
                             facility to pick from; with exactly one,
                             they're auto-assigned; with none, the order
                             is left unassigned (stays in the general
                             queue, same as before this existed). See
                             includes/doctor/active_consultation_doctor_firestore.php's
                             $availableLabStaff. -->
                        <?php if (count($availableLabStaff) > 1): ?>
                        <div class="acv-staff-picker">
                            <div class="acv-staff-picker-title">Assign To</div>
                            <div class="acv-staff-picker-desc">Choose which medical technologist will receive this order.</div>
                            <div class="acv-staff-picker-list">
                                <?php foreach ($availableLabStaff as $staff): ?>
                                <label class="acv-staff-picker-option">
                                    <input type="radio" name="acvLabAssignTo" value="<?= htmlspecialchars($staff['uid']) ?>">
                                    <?= htmlspecialchars($staff['name']) ?>
                                </label>
                                <?php endforeach; ?>
                            </div>
                            <div class="acv-staff-picker-error text-danger small d-none" id="acvLabStaffError">Please choose who this should be sent to.</div>
                        </div>
                        <?php elseif (count($availableLabStaff) === 1): ?>
                        <input type="hidden" id="acvLabAssignToSingle" value="<?= htmlspecialchars($availableLabStaff[0]['uid']) ?>">
                        <div class="acv-staff-picker-note"><i class="bi bi-person-check"></i> Will be sent to <strong><?= htmlspecialchars($availableLabStaff[0]['name']) ?></strong> — the only active medical technologist on staff.</div>
                        <?php else: ?>
                        <div class="acv-staff-picker-note acv-staff-picker-note-muted"><i class="bi bi-exclamation-circle"></i> No active medical technologist on staff right now — this will stay in the general queue.</div>
                        <?php endif; ?>

                        <div class="acv-lab-test-error text-danger small d-none" id="acvLabTestError"></div>
                        <div class="acv-lab-test-actions">
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="acvLabTestCancel">Cancel</button>
                            <button type="button" class="btn btn-sm acv-btn-save" id="acvLabTestConfirm">Confirm</button>
                        </div>
                    </div>
                    <!-- Compact confirmed-state summary — shown instead
                         of the panel above once Confirm has been
                         clicked. -->
                    <div class="acv-lab-test-summary d-none" id="acvLabTestSummary">
                        <i class="bi bi-check-circle-fill"></i>
                        <span id="acvLabTestSummaryText"></span>
                        <button type="button" class="acv-lab-test-change-btn" id="acvLabTestChange">Change</button>
                    </div>
                    <?php endif; ?>
                    <?php if ($key === 'xray'): ?>
                    <!-- X-ray counterpart of the Laboratory panel above —
                         same structure/classes (acv-lab-test-*, reused
                         rather than duplicated), own catalog
                         (hc_xray_exam_catalog(), includes/firestore/xray_results.php)
                         and own staff picker ($availableXrayStaff, real
                         active radiologic technologists at this facility). -->
                    <div class="acv-lab-test-panel d-none" id="acvXrayTestPanel">
                        <div class="acv-lab-test-header">
                            <span class="acv-lab-test-icon"><i class="bi bi-file-earmark-medical"></i></span>
                            <div>
                                <div class="acv-lab-test-title">Select X-ray Exams</div>
                                <div class="acv-lab-test-desc">Choose the X-ray exams needed for this patient.</div>
                            </div>
                        </div>
                        <div class="acv-lab-test-grid">
                            <div class="acv-lab-test-category">
                                <?php foreach ($xrayExamCatalog as $examName => $bodyPart):
                                    $orderedStatus = $xrayExamsAlreadyOrdered[$examName] ?? null;
                                ?>
                                <?php if ($orderedStatus !== null): ?>
                                <label class="acv-lab-test-checkbox acv-lab-test-checkbox-locked" title="<?= $orderedStatus === 'completed' ? 'Result already received' : 'Already sent — awaiting result' ?>">
                                    <input type="checkbox" value="<?= htmlspecialchars($examName) ?>" checked disabled>
                                    <?= htmlspecialchars($examName) ?>
                                    <span class="acv-source-tag">(<?= $orderedStatus === 'completed' ? 'result received' : 'awaiting result' ?>)</span>
                                </label>
                                <?php else: ?>
                                <label class="acv-lab-test-checkbox">
                                    <input type="checkbox" value="<?= htmlspecialchars($examName) ?>" <?= in_array($examName, $consultation['xrayExams'], true) ? 'checked' : '' ?>>
                                    <?= htmlspecialchars($examName) ?>
                                </label>
                                <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <?php if (count($availableXrayStaff) > 1): ?>
                        <div class="acv-staff-picker">
                            <div class="acv-staff-picker-title">Assign To</div>
                            <div class="acv-staff-picker-desc">Choose which radiologic technologist will receive this order.</div>
                            <div class="acv-staff-picker-list">
                                <?php foreach ($availableXrayStaff as $staff): ?>
                                <label class="acv-staff-picker-option">
                                    <input type="radio" name="acvXrayAssignTo" value="<?= htmlspecialchars($staff['uid']) ?>">
                                    <?= htmlspecialchars($staff['name']) ?>
                                </label>
                                <?php endforeach; ?>
                            </div>
                            <div class="acv-staff-picker-error text-danger small d-none" id="acvXrayStaffError">Please choose who this should be sent to.</div>
                        </div>
                        <?php elseif (count($availableXrayStaff) === 1): ?>
                        <input type="hidden" id="acvXrayAssignToSingle" value="<?= htmlspecialchars($availableXrayStaff[0]['uid']) ?>">
                        <div class="acv-staff-picker-note"><i class="bi bi-person-check"></i> Will be sent to <strong><?= htmlspecialchars($availableXrayStaff[0]['name']) ?></strong> — the only active radiologic technologist on staff.</div>
                        <?php else: ?>
                        <div class="acv-staff-picker-note acv-staff-picker-note-muted"><i class="bi bi-exclamation-circle"></i> No active radiologic technologist on staff right now — this will stay in the general queue.</div>
                        <?php endif; ?>

                        <div class="acv-lab-test-error text-danger small d-none" id="acvXrayTestError"></div>
                        <div class="acv-lab-test-actions">
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="acvXrayTestCancel">Cancel</button>
                            <button type="button" class="btn btn-sm acv-btn-save" id="acvXrayTestConfirm">Confirm</button>
                        </div>
                    </div>
                    <div class="acv-lab-test-summary d-none" id="acvXrayTestSummary">
                        <i class="bi bi-check-circle-fill"></i>
                        <span id="acvXrayTestSummaryText"></span>
                        <button type="button" class="acv-lab-test-change-btn" id="acvXrayTestChange">Change</button>
                    </div>
                    <?php endif; ?>
                    <?php endforeach; ?>

                    <hr class="acv-routing-divider">

                    <!-- Independent of the three above — a patient can
                         need a follow-up visit scheduled AND still be
                         routed to the pharmacy today for medicine from
                         THIS visit, so this can't be mutually exclusive
                         with them the way Laboratory/Pharmacy/
                         Vaccination are with each other. Posted as its
                         own real field (needsFollowUp,
                         api/complete-consultation.php), independent of
                         nextRouting. -->
                    <label class="acv-routing-item">
                        <span class="acv-routing-icon"><i class="bi bi-calendar2-check"></i></span>
                        <span class="acv-routing-text">
                            <span class="acv-routing-title">Also needs a follow-up visit</span>
                            <span class="acv-routing-desc">Schedule another consultation later — can be checked together with any option above.</span>
                        </span>
                        <input type="checkbox" id="acvNeedsFollowUp" <?= $consultation['needsFollowUp'] ? 'checked' : '' ?>>
                    </label>
                </div>
            </div>

            <div class="adm-card">
                <label class="acv-field-label" for="acvNotes">Additional Notes <span class="text-muted fw-normal">(Optional)</span></label>
                <textarea class="acv-textarea" id="acvNotes" maxlength="1000" placeholder="Add any additional remarks or notes..."><?= htmlspecialchars($consultation['doctorNotes']) ?></textarea>
                <div class="acv-char-count" data-for="acvNotes">0/1000</div>
            </div>

        </div>

    </div>
    </form>

    <div class="acv-footer-meta">Created by: <?= htmlspecialchars($createdByName) ?> &bull; Started at: <?= htmlspecialchars($startedAtLabel) ?></div>

</main>

<datalist id="acvMedicineList">
    <?php foreach ($medicineNames as $name): ?>
    <option value="<?= htmlspecialchars($name) ?>">
    <?php endforeach; ?>
</datalist>
<datalist id="acvVaccineList">
    <?php foreach ($vaccineNames as $name): ?>
    <option value="<?= htmlspecialchars($name) ?>">
    <?php endforeach; ?>
</datalist>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<link rel="stylesheet" href="<?= asset_url('/assets/css/hc-dialogs.css') ?>">
<script src="<?= asset_url('/assets/js/hc-dialogs.js') ?>"></script>
<script src="<?= asset_url('/assets/js/admin-sidebar-toggle.js') ?>"></script>
<script>
    window.ACV_CONSULTATION_ID = <?= json_encode($consultation['id']) ?>;
    window.ACV_MEDICATIONS = <?= json_encode($consultation['medications']) ?>;
</script>
<script src="<?= asset_url('/assets/js/active_consultation.js') ?>"></script>
</body>
</html>
