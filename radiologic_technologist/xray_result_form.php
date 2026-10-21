<?php
/**
 * =====================================================================
 * HEALTrack — Health Information System
 * X-ray Result Form (radiologic_technologist/xray_result_form.php)
 * ---------------------------------------------------------------------
 * Reached via the "Consult" row action on xray_consultation.php for a
 * pending X-ray referral — see
 * includes/radiologic_technologist/xray_result_form_firestore.php's header
 * comment. Findings + Impression + a required result document upload
 * (the scanned report/exported image — see
 * includes/shared/xray_result_storage.php's header comment), submitted
 * together to api/complete-xray-result.php as multipart/form-data.
 * =====================================================================
 */
session_start();

require_once __DIR__ . '/../config/constants.php'; // BASE_URL, asset_url()

if (($_SESSION['role'] ?? null) !== 'radio_technologist') {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

require_once __DIR__ . '/../includes/radiologic_technologist/xray_result_form_firestore.php';
// $staffProfile, $dashboardDateLabel, $patientId, $recordId, $patient, $currentDoc, $isEditable, $routedToDoctorName

require_once __DIR__ . '/../includes/firestore/xray_results.php'; // hc_get_pending_xray_count() for the sidebar badge below

$staffNavItems = [
    ['icon' => 'bi-grid-fill',                 'label' => 'Dashboard',          'href' => 'dashboard.php'],
    ['icon' => 'bi-file-earmark-medical-fill', 'label' => 'X-ray Management', 'href' => 'xray_consultation.php', 'active' => true, 'badge' => hc_get_pending_xray_count()],
    [
        'icon' => 'bi-bar-chart-fill',
        'label' => 'Analytics & Reports',
        'id' => 'rtNavAnalyticsReports',
        'children' => [
            ['label' => 'Analytics', 'href' => 'analytics_reports.php'],
            ['label' => 'My Reports', 'href' => 'patients_report.php'],
        ],
    ],
    ['icon' => 'bi-bell-fill',                 'label' => 'Notifications',      'href' => 'notifications.php', 'badge' => hc_radiotech_unread_notification_count()],
    ['icon' => 'bi-clipboard-data-fill',       'label' => 'Audit Logs',         'href' => 'audit_logs.php'],
    ['icon' => 'bi-gear-fill',                 'label' => 'Profile / Settings', 'href' => 'profile_settings.php'],
];

$docFields = $currentDoc['fields'] ?? [];
$examName = $docFields['examName'] ?? '—';
$bodyPart = $docFields['bodyPart'] ?? '—';
$savedFindings = implode("\n", $docFields['findings'] ?? []);
$savedImpression = $docFields['impression'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>X-ray Result Form — HEALTrack</title>

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
            <a href="#<?= htmlspecialchars($item['id'] ?? 'rtNavAnalyticsReports') ?>" class="adm-nav-link adm-nav-toggle <?= !empty($item['active']) ? 'active' : '' ?> <?= empty($item['open']) ? 'collapsed' : '' ?>" data-bs-toggle="collapse" role="button" aria-expanded="<?= !empty($item['open']) ? 'true' : 'false' ?>" aria-controls="<?= htmlspecialchars($item['id'] ?? 'rtNavAnalyticsReports') ?>">
                <i class="bi <?= htmlspecialchars($item['icon']) ?>"></i>
                <span><?= htmlspecialchars($item['label']) ?></span>
                <i class="bi bi-chevron-down adm-nav-caret"></i>
            </a>
            <div class="collapse <?= !empty($item['open']) ? 'show' : '' ?>" id="<?= htmlspecialchars($item['id'] ?? 'rtNavAnalyticsReports') ?>">
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
            <h1>X-ray Result Form</h1>
            <p class="adm-page-subtitle">Enter Findings and Impression, then it's ready for the ordering doctor to review.</p>
        </div>
        <div class="adm-topbar-right">
            <a href="xray_consultation.php" class="mt-topbar-select-btn"><i class="bi bi-arrow-left"></i> Back to Consultation</a>
        </div>
    </div>

    <?php if ($patient === null || $currentDoc === null): ?>
    <div class="adm-card" style="padding:32px; text-align:center;">
        <p class="adm-table-empty">No pending X-ray order found for this patient.</p>
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
        <h2 style="font-size:16px; font-weight:700; margin-bottom:4px;"><?= htmlspecialchars($examName) ?></h2>
        <p class="adm-page-subtitle" style="margin-bottom:16px;">Body Part: <?= htmlspecialchars($bodyPart) ?></p>

        <form id="xrayResultForm" enctype="multipart/form-data">
            <input type="hidden" name="patientId" value="<?= htmlspecialchars($patientId) ?>">
            <input type="hidden" name="recordId" value="<?= htmlspecialchars($recordId) ?>">

            <div style="margin-bottom:18px;">
                <label for="f_findings" style="display:block; font-size:12.5px; font-weight:600; color:var(--adm-text-muted); margin-bottom:6px;">Findings<span class="text-danger">*</span> <span class="text-muted fw-normal">(one per line)</span></label>
                <?php if ($isEditable): ?>
                <textarea class="form-control" id="f_findings" name="findings" rows="5" placeholder="e.g.&#10;Lungs are clear.&#10;Heart size is normal.&#10;No pleural effusion."><?= htmlspecialchars($savedFindings) ?></textarea>
                <?php else: ?>
                <ul>
                    <?php foreach (($docFields['findings'] ?? []) as $finding): ?>
                    <li><strong><?= htmlspecialchars($finding) ?></strong></li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>

            <div style="margin-bottom:18px;">
                <label for="f_impression" style="display:block; font-size:12.5px; font-weight:600; color:var(--adm-text-muted); margin-bottom:6px;">Impression<span class="text-danger">*</span></label>
                <?php if ($isEditable): ?>
                <textarea class="form-control" id="f_impression" name="impression" rows="3" placeholder="e.g. Normal chest findings."><?= htmlspecialchars($savedImpression) ?></textarea>
                <?php else: ?>
                <div><strong><?= nl2br(htmlspecialchars($savedImpression ?: '—')) ?></strong></div>
                <?php endif; ?>
            </div>

            <div style="margin-bottom:18px;">
                <label for="f_resultFile" style="display:block; font-size:12.5px; font-weight:600; color:var(--adm-text-muted); margin-bottom:6px;">X-ray Result Document<?php if ($isEditable): ?><span class="text-danger">*</span><?php endif; ?></label>
                <?php if ($isEditable): ?>
                <input type="file" class="form-control" id="f_resultFile" name="resultFile" accept=".pdf,.jpg,.jpeg,.png" required>
                <div class="adm-page-subtitle" style="margin-top:4px;">PDF, JPG, or PNG — the scanned report or exported image for this exam, up to 10MB.</div>
                <?php elseif (!empty($docFields['attachedFileName'])): ?>
                <div style="display:flex; align-items:center; gap:10px;">
                    <i class="bi bi-file-earmark-medical-fill" style="font-size:20px; color:#1A73E8;"></i>
                    <div>
                        <strong><?= htmlspecialchars($docFields['attachedFileName']) ?></strong>
                        <div class="adm-page-subtitle"><?= htmlspecialchars($docFields['attachedFileSize'] ?? '') ?></div>
                    </div>
                    <a href="<?= BASE_URL ?>/api/download-xray-result.php?id=<?= urlencode($recordId) ?>&view=1" target="_blank" class="mt-topbar-select-btn" style="margin-left:auto;"><i class="bi bi-eye"></i> View</a>
                    <a href="<?= BASE_URL ?>/api/download-xray-result.php?id=<?= urlencode($recordId) ?>" class="mt-topbar-select-btn"><i class="bi bi-download"></i> Download</a>
                </div>
                <?php else: ?>
                <div class="adm-page-subtitle">No result document on file (completed before this was added).</div>
                <?php endif; ?>
            </div>

            <div id="xrayResultFormMsg" style="display:none; margin-bottom:14px;"></div>

            <div style="display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
                <?php if ($isEditable): ?>
                <button type="submit" class="lxr-btn-filter" id="btnSaveXrayResult"><i class="bi bi-save"></i> Save Result</button>
                <?php endif; ?>
                <?php if (!$isEditable && $currentDoc !== null && $routedToDoctorName === null): ?>
                <button type="button" class="mt-topbar-select-btn" id="btnProceedToDoctor" data-patient-id="<?= htmlspecialchars($patientId) ?>" data-record-id="<?= htmlspecialchars($recordId) ?>" data-test-type="xray_generic"><i class="bi bi-arrow-right-circle"></i> Proceed to Doctor</button>
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
    var recordId = <?= json_encode($recordId) ?>;
    var form = document.getElementById('xrayResultForm');
    var msg = document.getElementById('xrayResultFormMsg');

    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();

            var findings = form.querySelector('textarea[name="findings"]').value.trim();
            var impression = form.querySelector('textarea[name="impression"]').value.trim();
            var fileInput = form.querySelector('input[name="resultFile"]');
            var file = fileInput && fileInput.files.length ? fileInput.files[0] : null;

            if (findings === '' || impression === '') {
                msg.style.display = 'block';
                msg.className = 'adm-page-subtitle';
                msg.textContent = 'Please fill in both Findings and Impression before saving.';
                return;
            }
            if (!file) {
                msg.style.display = 'block';
                msg.className = 'adm-page-subtitle';
                msg.textContent = 'Please attach the X-ray result document.';
                return;
            }

            var btn = document.getElementById('btnSaveXrayResult');
            btn.disabled = true;

            var payload = new FormData();
            payload.append('patientId', patientId);
            payload.append('recordId', recordId);
            payload.append('findings', findings);
            payload.append('impression', impression);
            payload.append('resultFile', file);

            fetch('<?= BASE_URL ?>/api/complete-xray-result.php', {
                method: 'POST',
                body: payload,
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
