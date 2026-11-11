<?php
/**
 * =====================================================================
 * HEALTrack — Health Information System
 * CBC Form (medical_technician/cbc_form.php)
 * ---------------------------------------------------------------------
 * Reached via the "Fill CBC Form" row action on
 * lab_xray_consultation.php, for a patient with a pending "CBC"
 * referral. Mirrors the real Lipa City Health Office "HEMATOLOGY" CBC
 * paper form — see includes/medical_technician/cbc_form_firestore.php's
 * header comment.
 *
 * Saving posts to api/complete-cbc-test.php, which requires all 15
 * fields (8 Hematology + 5 Differential Count + Blood Type/Rh) filled
 * in together and marks the real lab_results doc "completed" server-
 * side — each of the 13 numeric fields auto-classified Low/Normal/High
 * against its own fixed real reference range (same idea
 * api/complete-cd4-count-test.php already uses). "Print Form" then
 * opens print_cbc.php in a new tab, styled to match the paper template.
 * =====================================================================
 */
session_start();

require_once __DIR__ . '/../config/constants.php'; // BASE_URL, asset_url()
require_once __DIR__ . '/../includes/shared/lab_test_names.php'; // hc_lab_test_required_specimen()

// Only a signed-in medical technologist may view this page.
if (($_SESSION['role'] ?? null) !== 'medical_technician') {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

require_once __DIR__ . '/../includes/medical_technician/cbc_form_firestore.php';
// $staffProfile, $dashboardDateLabel, $patientId, $patient, $currentDoc, $isEditable, $routedToDoctorName, $labTemplate

$staffNavItems = [
    ['icon' => 'bi-grid-fill',                 'label' => 'Dashboard',                       'href' => 'dashboard.php'],
    ['icon' => 'bi-clipboard2-pulse-fill',      'label' => 'Laboratory',              'href' => 'laboratory_xray.php'],
    ['icon' => 'bi-inboxes-fill',              'label' => 'Laboratory Management', 'href' => 'lab_xray_consultation.php', 'active' => true, 'badge' => hc_get_pending_lab_count()],
    ['icon' => 'bi-journal-medical',           'label' => 'Lab Test Catalog',                'href' => 'lab_test_catalog.php'],
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

$hematologyFields = hc_cbc_hematology_fields();
$differentialFields = hc_cbc_differential_fields();
$hasCompletedRow = ($currentDoc['fields']['status'] ?? null) === 'completed';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/png" href="<?= asset_url('/assets/images/main_logo.png') ?>">
<title>CBC Form — HEALTrack</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<link rel="stylesheet" href="<?= asset_url('/assets/css/admin-dashboard.css') ?>">
<link rel="stylesheet" href="<?= asset_url('/assets/css/medical_technician_dashboard.css') ?>">
<link rel="stylesheet" href="<?= asset_url('/assets/css/medical_technician_lab_xray.css') ?>">
</head>
<body class="adm-body">

<button class="adm-mobile-menu-btn" id="admSidebarToggle" aria-label="Open menu">
    <i class="bi bi-list"></i>
</button>
<div class="adm-sidebar-backdrop" id="admSidebarBackdrop"></div>

<aside class="adm-sidebar" id="admSidebar">
    <div class="adm-brand">
        <div class="adm-logo-badge"><img src="<?= asset_url('/assets/images/main_logo.png') ?>" alt="HEALTrack logo" class="adm-logo-img"></div>
        <div class="adm-brand-text">
            <h1>HEALTrack</h1>
            <p>Health Information System</p>
        </div>
    </div>

    <hr class="adm-sidebar-divider">

    <div class="adm-profile">
        <div class="adm-avatar"><?php require_once __DIR__ . '/../includes/shared/profile_photo_storage.php'; echo hc_render_sidebar_avatar(); ?></div>
        <div class="adm-profile-text">
            <div class="adm-profile-name"><?= htmlspecialchars($staffProfile['name']) ?></div>
            <div class="adm-profile-role"><?= htmlspecialchars($staffProfile['role']) ?></div>
        </div>
    </div>

    <hr class="adm-sidebar-divider">

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

<main class="adm-main">

    <div class="adm-topbar">
        <div>
            <h1>CBC Form</h1>
            <p class="adm-page-subtitle">Fill in Hematology, Differential Count, and Blood Typing results, then print the official form.</p>
        </div>
        <div class="adm-topbar-right">
            <a href="lab_xray_consultation.php" class="mt-topbar-select-btn"><i class="bi bi-arrow-left"></i> Back to Consultation</a>
        </div>
    </div>

    <?php if ($patient === null || $currentDoc === null): ?>
    <div class="adm-card" style="padding:32px; text-align:center;">
        <p class="adm-table-empty">No CBC order found for this patient.</p>
    </div>
    <?php else: ?>

    <div class="adm-card" style="padding:24px; margin-bottom:22px;">
        <h2 style="font-size:16px; font-weight:700; margin-bottom:16px;">Patient Information</h2>
        <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:16px;">
            <div><div class="adm-page-subtitle" style="margin-bottom:4px;">Name</div><strong><?= htmlspecialchars($patient['name']) ?></strong></div>
            <div><div class="adm-page-subtitle" style="margin-bottom:4px;">Address</div><strong><?= htmlspecialchars($patient['address']) ?></strong></div>
            <div><div class="adm-page-subtitle" style="margin-bottom:4px;">Age</div><strong><?= (int) $patient['age'] ?></strong></div>
            <div><div class="adm-page-subtitle" style="margin-bottom:4px;">Sex</div><strong><?= htmlspecialchars($patient['gender']) ?></strong></div>
        </div>
    </div>

    <div class="adm-card" style="padding:24px; margin-bottom:22px;">
        <div class="adm-page-subtitle" style="margin-bottom:4px;">Specimen</div>
        <strong><?= htmlspecialchars(hc_lab_test_required_specimen('CBC')) ?></strong>
        <?php if ($labTemplate !== null): ?>
        <a href="<?= BASE_URL ?>/api/view-lab-test-template.php?id=<?= urlencode($labTemplate['id']) ?>" target="_blank" rel="noopener" title="View reference template" style="margin-left:10px;"><i class="bi bi-file-earmark-text"></i> View Template</a>
        <?php endif; ?>
    </div>

    <form id="cbcForm">
        <input type="hidden" name="patientId" value="<?= htmlspecialchars($patientId) ?>">

        <div class="adm-card" style="padding:24px; margin-bottom:22px;">
            <h2 style="font-size:16px; font-weight:700; margin-bottom:16px;">Hematology</h2>
            <table class="adm-table" style="margin-bottom:0;">
                <thead><tr><th>Test</th><th style="width:180px;">Result</th><th>Unit</th><th>Reference Range</th></tr></thead>
                <tbody>
                    <?php foreach ($hematologyFields as $key => $f): ?>
                    <tr>
                        <td><?= htmlspecialchars($f['label']) ?></td>
                        <td>
                            <?php if ($isEditable): ?>
                            <input type="text" inputmode="decimal" class="form-control" name="val_<?= htmlspecialchars($key) ?>" data-field="<?= htmlspecialchars($key) ?>">
                            <?php else: ?>
                            <strong><?= htmlspecialchars(hc_cbc_saved_value($currentDoc, $key, $hematologyFields) ?: '—') ?></strong>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($f['unit']) ?></td>
                        <td><?= htmlspecialchars($f['range']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="adm-card" style="padding:24px; margin-bottom:22px;">
            <h2 style="font-size:16px; font-weight:700; margin-bottom:16px;">Differential Count</h2>
            <table class="adm-table" style="margin-bottom:0;">
                <thead><tr><th>Test</th><th style="width:180px;">Result</th><th>Unit</th><th>Reference Range</th></tr></thead>
                <tbody>
                    <?php foreach ($differentialFields as $key => $f): ?>
                    <tr>
                        <td><?= htmlspecialchars($f['label']) ?></td>
                        <td>
                            <?php if ($isEditable): ?>
                            <input type="text" inputmode="decimal" class="form-control" name="val_<?= htmlspecialchars($key) ?>" data-field="<?= htmlspecialchars($key) ?>">
                            <?php else: ?>
                            <strong><?= htmlspecialchars(hc_cbc_saved_value($currentDoc, $key, $differentialFields) ?: '—') ?></strong>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($f['unit']) ?></td>
                        <td><?= htmlspecialchars($f['range']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="adm-card" style="padding:24px;">
            <h2 style="font-size:16px; font-weight:700; margin-bottom:16px;">Blood Typing</h2>
            <div style="display:grid; grid-template-columns:repeat(2,220px); gap:16px; margin-bottom:18px;">
                <div>
                    <label style="display:block; font-size:12.5px; font-weight:600; color:var(--adm-text-muted); margin-bottom:6px;">Blood Type</label>
                    <?php if ($isEditable): ?>
                    <select class="form-select" name="val_bloodType" data-field="bloodType">
                        <option value="">— Select —</option>
                        <option value="A">A</option>
                        <option value="B">B</option>
                        <option value="AB">AB</option>
                        <option value="O">O</option>
                    </select>
                    <?php else: ?>
                    <strong><?= htmlspecialchars(hc_cbc_saved_value($currentDoc, 'bloodType', ['bloodType' => 'Blood Type']) ?: '—') ?></strong>
                    <?php endif; ?>
                </div>
                <div>
                    <label style="display:block; font-size:12.5px; font-weight:600; color:var(--adm-text-muted); margin-bottom:6px;">Rh</label>
                    <?php if ($isEditable): ?>
                    <select class="form-select" name="val_rh" data-field="rh">
                        <option value="">— Select —</option>
                        <option value="Positive">Positive</option>
                        <option value="Negative">Negative</option>
                    </select>
                    <?php else: ?>
                    <strong><?= htmlspecialchars(hc_cbc_saved_value($currentDoc, 'rh', ['rh' => 'Rh']) ?: '—') ?></strong>
                    <?php endif; ?>
                </div>
            </div>

            <div id="cbcFormMsg" style="display:none; margin-bottom:14px;"></div>

            <div style="display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
                <?php if ($isEditable): ?>
                <button type="submit" class="lxr-btn-filter" id="btnSaveCbc"><i class="bi bi-save"></i> Save Results</button>
                <?php endif; ?>
                <button type="button" class="mt-topbar-select-btn" id="btnPrintCbc"><i class="bi bi-printer"></i> Print Form</button>
                <?php if ($hasCompletedRow && $routedToDoctorName === null): ?>
                <button type="button" class="mt-topbar-select-btn" id="btnProceedToDoctor" data-patient-id="<?= htmlspecialchars($patientId) ?>" data-test-type="cbc"><i class="bi bi-arrow-right-circle"></i> Proceed to Doctor</button>
                <?php elseif ($routedToDoctorName !== null): ?>
                <span class="adm-page-subtitle"><i class="bi bi-check-circle-fill" style="color:#1E8E3E;"></i> Routed to <?= htmlspecialchars($routedToDoctorName) ?></span>
                <?php endif; ?>
            </div>
        </div>
    </form>

    <?php endif; ?>

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<link rel="stylesheet" href="<?= asset_url('/assets/css/hc-dialogs.css') ?>">
<script src="<?= asset_url('/assets/js/hc-dialogs.js') ?>"></script>
<script src="<?= asset_url('/assets/js/admin-sidebar-toggle.js') ?>"></script>
<script src="<?= asset_url('/assets/js/proceed-to-doctor.js') ?>"></script>
<script>
(function () {
    var patientId = <?= json_encode($patientId) ?>;
    var form = document.getElementById('cbcForm');
    var msg = document.getElementById('cbcFormMsg');
    var printBtn = document.getElementById('btnPrintCbc');

    if (printBtn) {
        printBtn.addEventListener('click', function () {
            window.open('print_cbc.php?patientId=' + encodeURIComponent(patientId), '_blank');
        });
    }

    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();

            var values = {};
            var missing = false;
            form.querySelectorAll('[data-field]').forEach(function (el) {
                values[el.dataset.field] = el.value.trim();
                if (el.value.trim() === '') {
                    missing = true;
                }
            });

            if (missing) {
                msg.style.display = 'block';
                msg.className = 'adm-page-subtitle';
                msg.textContent = 'Fill in every field before saving.';
                return;
            }

            var btn = document.getElementById('btnSaveCbc');
            btn.disabled = true;

            fetch('<?= BASE_URL ?>/api/complete-cbc-test.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ patientId: patientId, values: values })
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.success) {
                        window.location.reload();
                    } else {
                        btn.disabled = false;
                        msg.style.display = 'block';
                        msg.className = 'adm-page-subtitle';
                        msg.textContent = data.error || 'Something went wrong. Please try again.';
                    }
                })
                .catch(function () {
                    btn.disabled = false;
                    msg.style.display = 'block';
                    msg.className = 'adm-page-subtitle';
                    msg.textContent = 'Something went wrong. Please try again.';
                });
        });
    }
})();
</script>
</body>
</html>
