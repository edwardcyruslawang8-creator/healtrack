<?php
/**
 * =====================================================================
 * HEALTrack — Health Information System
 * Urinalysis Form (medical_technician/urinalysis_form.php)
 * ---------------------------------------------------------------------
 * Reached via the "Fill Urinalysis Form" row action on
 * lab_xray_consultation.php, for a patient with a pending Urinalysis
 * referral. Mirrors the real Lipa City Health Office "URINALYSIS"
 * paper form — see
 * includes/medical_technician/urinalysis_form_firestore.php's header
 * comment. Not a sidebar nav item, same as laboratory_result_details.php.
 *
 * Saving posts to api/complete-urinalysis-test.php, which writes all
 * 14 fields (6 physical + 8 microscopic) plus an overall Normal/
 * Abnormal result onto the real lab_results doc and marks it
 * "completed". "Print Form" then opens print_urinalysis.php in a new
 * tab, styled to match the paper template.
 * =====================================================================
 */
session_start();

require_once __DIR__ . '/../config/constants.php'; // BASE_URL, asset_url()

if (($_SESSION['role'] ?? null) !== 'medical_technician') {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

require_once __DIR__ . '/../includes/medical_technician/urinalysis_form_firestore.php';
// $staffProfile, $dashboardDateLabel, $patientId, $patient, $currentDoc, $isEditable

$staffNavItems = [
    ['icon' => 'bi-grid-fill',                 'label' => 'Dashboard',                       'href' => 'dashboard.php'],
    ['icon' => 'bi-clipboard2-pulse-fill',      'label' => 'Laboratory & X-ray',              'href' => 'laboratory_xray.php'],
    ['icon' => 'bi-inboxes-fill',              'label' => 'Laboratory & X-ray Consultation', 'href' => 'lab_xray_consultation.php', 'active' => true, 'badge' => hc_get_pending_lab_xray_count()],
    ['icon' => 'bi-bar-chart-fill',           'label' => 'Analytics & Reports',              'href' => 'analytics_reports.php'],
    ['icon' => 'bi-bell-fill',                 'label' => 'Notifications',                   'href' => 'notifications.php', 'badge' => hc_medtech_unread_notification_count()],
    ['icon' => 'bi-clipboard-data-fill', 'label' => 'Audit Logs', 'href' => 'audit_logs.php'],
    ['icon' => 'bi-gear-fill',                 'label' => 'Profile / Settings',              'href' => 'profile_settings.php'],
];

$physicalFields = hc_urinalysis_physical_fields();
$microscopicFields = hc_urinalysis_microscopic_fields();
$currentOverall = $currentDoc['fields']['resultBadgeLabel'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Urinalysis Form — HEALTrack</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<link rel="stylesheet" href="<?= asset_url('/assets/css/admin-dashboard.css') ?>">
<link rel="stylesheet" href="<?= asset_url('/assets/css/medical_technician_dashboard.css') ?>">
<style>
  .urn-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; margin-bottom: 22px; }
  .urn-grid.two-col { grid-template-columns: repeat(2, 1fr); }
  .urn-field label { display: block; font-size: 12.5px; font-weight: 600; color: var(--adm-text-muted); margin-bottom: 6px; }
</style>
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
        <div class="adm-avatar"><i class="bi bi-person-circle"></i></div>
        <div class="adm-profile-text">
            <div class="adm-profile-name"><?= htmlspecialchars($staffProfile['name']) ?></div>
            <div class="adm-profile-role"><?= htmlspecialchars($staffProfile['role']) ?></div>
        </div>
    </div>

    <hr class="adm-sidebar-divider">

    <ul class="adm-nav">
        <?php foreach ($staffNavItems as $item): ?>
        <li>
            <a href="<?= htmlspecialchars($item['href']) ?>" class="adm-nav-link <?= !empty($item['active']) ? 'active' : '' ?>">
                <i class="bi <?= htmlspecialchars($item['icon']) ?>"></i>
                <span><?= htmlspecialchars($item['label']) ?></span>
                <?php if (!empty($item['badge'])): ?>
                <span class="mt-nav-badge"><?= (int) $item['badge'] ?></span>
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

<main class="adm-main">

    <div class="adm-topbar">
        <div>
            <h1>Urinalysis Form</h1>
            <p class="adm-page-subtitle">Fill in Physical &amp; Microscopic Examination results, then print the official form.</p>
        </div>
        <div class="adm-topbar-right">
            <a href="lab_xray_consultation.php" class="mt-topbar-select-btn"><i class="bi bi-arrow-left"></i> Back to Consultation</a>
        </div>
    </div>

    <?php if ($patient === null || $currentDoc === null): ?>
    <div class="adm-card" style="padding:32px; text-align:center;">
        <p class="adm-table-empty">No Urinalysis order found for this patient.</p>
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

    <form id="urinalysisForm">
        <input type="hidden" name="patientId" value="<?= htmlspecialchars($patientId) ?>">

        <div class="adm-card" style="padding:24px; margin-bottom:22px;">
            <h2 style="font-size:16px; font-weight:700; margin-bottom:16px;">Physical Examination</h2>
            <div class="urn-grid">
                <?php foreach ($physicalFields as $key => $label): ?>
                <div class="urn-field">
                    <label for="f_<?= $key ?>"><?= htmlspecialchars($label) ?></label>
                    <?php if ($isEditable): ?>
                    <input type="text" class="form-control" id="f_<?= $key ?>" name="<?= $key ?>" value="<?= htmlspecialchars(hc_urinalysis_saved_value($currentDoc, '1', $key, $physicalFields)) ?>">
                    <?php else: ?>
                    <div><strong><?= htmlspecialchars(hc_urinalysis_saved_value($currentDoc, '1', $key, $physicalFields) ?: '—') ?></strong></div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="adm-card" style="padding:24px; margin-bottom:22px;">
            <h2 style="font-size:16px; font-weight:700; margin-bottom:16px;">Microscopic Examination</h2>
            <div class="urn-grid two-col">
                <?php foreach ($microscopicFields as $key => $label): ?>
                <div class="urn-field">
                    <label for="f_<?= $key ?>"><?= htmlspecialchars($label) ?></label>
                    <?php if ($isEditable): ?>
                    <input type="text" class="form-control" id="f_<?= $key ?>" name="<?= $key ?>" value="<?= htmlspecialchars(hc_urinalysis_saved_value($currentDoc, '2', $key, $microscopicFields)) ?>">
                    <?php else: ?>
                    <div><strong><?= htmlspecialchars(hc_urinalysis_saved_value($currentDoc, '2', $key, $microscopicFields) ?: '—') ?></strong></div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="adm-card" style="padding:24px;">
            <h2 style="font-size:16px; font-weight:700; margin-bottom:16px;">Overall Result</h2>
            <?php if ($isEditable): ?>
            <select class="form-select" name="overallResult" style="max-width:260px; margin-bottom:18px;">
                <option value="">— Select overall result —</option>
                <option value="Normal">Normal</option>
                <option value="Abnormal">Abnormal</option>
            </select>
            <?php else: ?>
            <p style="margin-bottom:18px;"><strong><?= htmlspecialchars($currentOverall ?: '—') ?></strong></p>
            <?php endif; ?>

            <div id="urinalysisFormMsg" style="display:none; margin-bottom:14px;"></div>

            <div style="display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
                <?php if ($isEditable): ?>
                <button type="submit" class="lxr-btn-filter" id="btnSaveUrinalysis"><i class="bi bi-save"></i> Save Results</button>
                <?php endif; ?>
                <button type="button" class="mt-topbar-select-btn" id="btnPrintUrinalysis"><i class="bi bi-printer"></i> Print Form</button>
                <?php if (!$isEditable && $currentDoc !== null && $routedToDoctorName === null): ?>
                <button type="button" class="mt-topbar-select-btn" id="btnProceedToDoctor" data-patient-id="<?= htmlspecialchars($patientId) ?>" data-test-type="urinalysis"><i class="bi bi-arrow-right-circle"></i> Proceed to Doctor</button>
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
    var form = document.getElementById('urinalysisForm');
    var msg = document.getElementById('urinalysisFormMsg');
    var printBtn = document.getElementById('btnPrintUrinalysis');

    if (printBtn) {
        printBtn.addEventListener('click', function () {
            window.open('print_urinalysis.php?patientId=' + encodeURIComponent(patientId), '_blank');
        });
    }

    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();

            var overallSelect = form.querySelector('select[name="overallResult"]');
            if (overallSelect && overallSelect.value === '') {
                msg.style.display = 'block';
                msg.className = 'adm-page-subtitle';
                msg.textContent = 'Please select an overall result before saving.';
                return;
            }

            var values = {};
            form.querySelectorAll('input[type="text"]').forEach(function (input) {
                values[input.name] = input.value;
            });

            var btn = document.getElementById('btnSaveUrinalysis');
            btn.disabled = true;

            fetch('<?= BASE_URL ?>/api/complete-urinalysis-test.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    patientId: patientId,
                    values: values,
                    overallResult: overallSelect ? overallSelect.value : ''
                })
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
