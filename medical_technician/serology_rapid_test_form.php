<?php
/**
 * =====================================================================
 * HEALTrack — Health Information System
 * Serology Rapid Test Form (medical_technician/serology_rapid_test_form.php)
 * ---------------------------------------------------------------------
 * Reached via the "Fill Serology Form" row action on
 * lab_xray_consultation.php, for a patient with a pending HBsAg/
 * Syphilis/HIV referral. Mirrors the real Lipa City Health Office
 * "SEROLOGY / RAPID IMMUNOCHROMATOGRAPHIC TESTS" paper form — see
 * includes/medical_technician/serology_rapid_test_form_firestore.php's
 * header comment. Not a sidebar nav item, same as
 * laboratory_result_details.php.
 *
 * Saving posts to api/complete-serology-rapid-test.php, which marks
 * each entered test's real lab_results doc "completed" server-side.
 * Once at least one test has a saved result, "Print Form" opens
 * print_serology_rapid_test.php in a new tab, styled to match the
 * paper template.
 * =====================================================================
 */
session_start();

require_once __DIR__ . '/../config/constants.php'; // BASE_URL, asset_url()

// Only a signed-in medical technologist may view this page.
if (($_SESSION['role'] ?? null) !== 'medical_technician') {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

require_once __DIR__ . '/../includes/medical_technician/serology_rapid_test_form_firestore.php';
// $staffProfile, $dashboardDateLabel, $patientId, $patient, $serologyRows, $hasEditableRow

$staffNavItems = [
    ['icon' => 'bi-grid-fill',                 'label' => 'Dashboard',                       'href' => 'dashboard.php'],
    ['icon' => 'bi-clipboard2-pulse-fill',      'label' => 'Laboratory',              'href' => 'laboratory_xray.php'],
    ['icon' => 'bi-inboxes-fill',              'label' => 'Laboratory Management', 'href' => 'lab_xray_consultation.php', 'active' => true, 'badge' => hc_get_pending_lab_count()],
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Serology Rapid Test Form — HEALTrack</title>

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
            <h1>Serology Rapid Test Form</h1>
            <p class="adm-page-subtitle">Fill in results for HBsAg, Syphilis, and HIV rapid immunochromatographic tests, then print the official form.</p>
        </div>
        <div class="adm-topbar-right">
            <a href="lab_xray_consultation.php" class="mt-topbar-select-btn"><i class="bi bi-arrow-left"></i> Back to Consultation</a>
        </div>
    </div>

    <?php if ($patient === null): ?>
    <div class="adm-card" style="padding:32px; text-align:center;">
        <p class="adm-table-empty">No patient found for this request.</p>
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

    <div class="adm-card" style="padding:24px;">
        <h2 style="font-size:16px; font-weight:700; margin-bottom:4px;">Serology — Rapid Immunochromatographic Tests</h2>
        <p class="adm-page-subtitle" style="margin-bottom:18px;">Only tests actually ordered for this patient can be filled in.</p>

        <form id="serologyForm">
            <input type="hidden" name="patientId" value="<?= htmlspecialchars($patientId) ?>">
            <table class="adm-table" style="margin-bottom:18px;">
                <thead>
                    <tr>
                        <th>Test</th>
                        <th>Status</th>
                        <th style="width:260px;">Result</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($serologyRows as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['displayLabel']) ?></td>
                        <td>
                            <?php if ($row['state'] === 'not_requested'): ?>
                            <span class="lxr-badge" style="background:#EEF0F3; color:#6B7280;">Not Requested</span>
                            <?php elseif ($row['state'] === 'completed'): ?>
                            <span class="lxr-badge" style="background:#E4F7EA; color:#1E8E3E;">Completed</span>
                            <?php else: ?>
                            <span class="lxr-badge" style="background:#FDECDD; color:#E8710A;">Pending</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($row['state'] === 'pending'): ?>
                            <select class="form-select" name="result_<?= htmlspecialchars($row['testName']) ?>" data-test="<?= htmlspecialchars($row['testName']) ?>">
                                <option value="">— Select result —</option>
                                <option value="Non-Reactive">Non-Reactive</option>
                                <option value="Reactive">Reactive</option>
                            </select>
                            <?php elseif ($row['state'] === 'completed'): ?>
                            <strong><?= htmlspecialchars($row['result']) ?></strong>
                            <?php else: ?>
                            <span class="adm-page-subtitle">&mdash;</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div id="serologyFormMsg" style="display:none; margin-bottom:14px;"></div>

            <div style="display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
                <?php if ($hasEditableRow): ?>
                <button type="submit" class="lxr-btn-filter" id="btnSaveSerology"><i class="bi bi-save"></i> Save Results</button>
                <?php endif; ?>
                <button type="button" class="mt-topbar-select-btn" id="btnPrintSerology"><i class="bi bi-printer"></i> Print Form</button>
                <?php if ($hasCompletedRow && $routedToDoctorName === null): ?>
                <button type="button" class="mt-topbar-select-btn" id="btnProceedToDoctor" data-patient-id="<?= htmlspecialchars($patientId) ?>" data-test-type="serology"><i class="bi bi-arrow-right-circle"></i> Proceed to Doctor</button>
                <?php elseif ($routedToDoctorName !== null): ?>
                <span class="adm-page-subtitle"><i class="bi bi-check-circle-fill" style="color:#1E8E3E;"></i> Routed to <?= htmlspecialchars($routedToDoctorName) ?></span>
                <?php endif; ?>
            </div>
        </form>
    </div>

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
    var form = document.getElementById('serologyForm');
    var msg = document.getElementById('serologyFormMsg');
    var printBtn = document.getElementById('btnPrintSerology');

    if (printBtn) {
        printBtn.addEventListener('click', function () {
            window.open('print_serology_rapid_test.php?patientId=' + encodeURIComponent(patientId), '_blank');
        });
    }

    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();

            var results = {};
            form.querySelectorAll('select[data-test]').forEach(function (sel) {
                if (sel.value !== '') {
                    results[sel.dataset.test] = sel.value;
                }
            });

            if (Object.keys(results).length === 0) {
                msg.style.display = 'block';
                msg.className = 'adm-page-subtitle';
                msg.textContent = 'Select at least one result before saving.';
                return;
            }

            var btn = document.getElementById('btnSaveSerology');
            btn.disabled = true;

            fetch('<?= BASE_URL ?>/api/complete-serology-rapid-test.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ patientId: patientId, results: results })
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
