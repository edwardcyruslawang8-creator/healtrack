<?php
/**
 * =====================================================================
 * HEALTrack — Health Information System
 * Lab Result Form (medical_technician/lab_result_form.php)
 * ---------------------------------------------------------------------
 * Reached via the "Consult" row action on lab_xray_consultation.php for
 * any pending lab test that isn't one of the 4 tests with their own
 * dedicated paper-form page (Serology/Rapid, Urinalysis, CD4, Gram
 * Stain — see includes/medical_technician/lab_result_form_firestore.php's
 * header comment). Not a mirror of a specific real paper form the way
 * those 4 are — this is a general-purpose result entry form (Specimen,
 * Result, Unit, Reference Range, Interpretation, Remarks) that covers
 * every other test in hc_lab_test_catalog() (CBC, Blood Typing, Fasting
 * Blood Sugar, Viral Load, etc.).
 *
 * Saving posts to api/complete-lab-result.php, which writes onto the
 * exact lab_results doc (&recordId=) and marks it "completed" — same
 * shape laboratory_result_details.php already renders (resultGroups,
 * interpretation, resultBadgeLabel/Color), so results show up there
 * identically to the 4 named forms' output, just without a matching
 * print template (none of these tests have one real paper form to
 * mirror).
 * =====================================================================
 */
session_start();

require_once __DIR__ . '/../config/constants.php'; // BASE_URL, asset_url()

if (($_SESSION['role'] ?? null) !== 'medical_technician') {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

require_once __DIR__ . '/../includes/medical_technician/lab_result_form_firestore.php';
// $staffProfile, $dashboardDateLabel, $patientId, $recordId, $patient, $currentDoc, $isEditable, $routedToDoctorName

$staffNavItems = [
    ['icon' => 'bi-grid-fill',                 'label' => 'Dashboard',                       'href' => 'dashboard.php'],
    ['icon' => 'bi-clipboard2-pulse-fill',      'label' => 'Laboratory & X-ray',              'href' => 'laboratory_xray.php'],
    ['icon' => 'bi-inboxes-fill',              'label' => 'Laboratory & X-ray Consultation', 'href' => 'lab_xray_consultation.php', 'active' => true, 'badge' => hc_get_pending_lab_xray_count()],
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

$docFields = $currentDoc['fields'] ?? [];
$testName = $docFields['testName'] ?? '—';
$savedSpecimen = $docFields['specimenType'] ?? '';
$savedSpecimen = $savedSpecimen === 'Not specified' ? '' : $savedSpecimen;
$savedRow = $docFields['resultGroups']['group1_rows'][0] ?? [];
$savedResult = $savedRow['result'] ?? '';
$savedUnit = $savedRow['unit'] ?? '';
$savedRange = $savedRow['range'] ?? '';
$savedInterpretation = $savedRow['interpretation'] ?? '';
$savedRemarks = $docFields['remarks'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Lab Result Form — HEALTrack</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<link rel="stylesheet" href="<?= asset_url('/assets/css/admin-dashboard.css') ?>">
<link rel="stylesheet" href="<?= asset_url('/assets/css/medical_technician_dashboard.css') ?>">
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
            <h1>Lab Result Form</h1>
            <p class="adm-page-subtitle">Enter this test's result, then it's ready for the ordering doctor to review.</p>
        </div>
        <div class="adm-topbar-right">
            <a href="lab_xray_consultation.php" class="mt-topbar-select-btn"><i class="bi bi-arrow-left"></i> Back to Consultation</a>
        </div>
    </div>

    <?php if ($patient === null || $currentDoc === null): ?>
    <div class="adm-card" style="padding:32px; text-align:center;">
        <p class="adm-table-empty">No pending lab order found for this patient.</p>
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
        <h2 style="font-size:16px; font-weight:700; margin-bottom:16px;"><?= htmlspecialchars($testName) ?></h2>

        <form id="labResultForm">
            <input type="hidden" name="patientId" value="<?= htmlspecialchars($patientId) ?>">
            <input type="hidden" name="recordId" value="<?= htmlspecialchars($recordId) ?>">

            <div style="margin-bottom:18px;">
                <label for="f_specimen" style="display:block; font-size:12.5px; font-weight:600; color:var(--adm-text-muted); margin-bottom:6px;">Specimen Type</label>
                <?php if ($isEditable): ?>
                <input type="text" class="form-control" id="f_specimen" name="specimen" value="<?= htmlspecialchars($savedSpecimen) ?>" placeholder="e.g. Whole Blood, Serum, Urine" style="max-width:420px;">
                <?php else: ?>
                <div><strong><?= htmlspecialchars($savedSpecimen ?: '—') ?></strong></div>
                <?php endif; ?>
            </div>

            <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:18px;">
                <div>
                    <label for="f_result" style="display:block; font-size:12.5px; font-weight:600; color:var(--adm-text-muted); margin-bottom:6px;">Result<span class="text-danger">*</span></label>
                    <?php if ($isEditable): ?>
                    <input type="text" class="form-control" id="f_result" name="result" value="<?= htmlspecialchars($savedResult) ?>" placeholder="e.g. 95, O+, Reactive">
                    <?php else: ?>
                    <div><strong><?= htmlspecialchars($savedResult ?: '—') ?></strong></div>
                    <?php endif; ?>
                </div>
                <div>
                    <label for="f_unit" style="display:block; font-size:12.5px; font-weight:600; color:var(--adm-text-muted); margin-bottom:6px;">Unit <span class="text-muted fw-normal">(Optional)</span></label>
                    <?php if ($isEditable): ?>
                    <input type="text" class="form-control" id="f_unit" name="unit" value="<?= htmlspecialchars($savedUnit) ?>" placeholder="e.g. mg/dL">
                    <?php else: ?>
                    <div><strong><?= htmlspecialchars($savedUnit ?: '—') ?></strong></div>
                    <?php endif; ?>
                </div>
                <div>
                    <label for="f_range" style="display:block; font-size:12.5px; font-weight:600; color:var(--adm-text-muted); margin-bottom:6px;">Reference Range <span class="text-muted fw-normal">(Optional)</span></label>
                    <?php if ($isEditable): ?>
                    <input type="text" class="form-control" id="f_range" name="range" value="<?= htmlspecialchars($savedRange) ?>" placeholder="e.g. 70 - 100">
                    <?php else: ?>
                    <div><strong><?= htmlspecialchars($savedRange ?: '—') ?></strong></div>
                    <?php endif; ?>
                </div>
                <div>
                    <label for="f_interpretation" style="display:block; font-size:12.5px; font-weight:600; color:var(--adm-text-muted); margin-bottom:6px;">Interpretation <span class="text-muted fw-normal">(Optional)</span></label>
                    <?php if ($isEditable): ?>
                    <input type="text" class="form-control" id="f_interpretation" name="interpretation" value="<?= htmlspecialchars($savedInterpretation) ?>" placeholder="e.g. Normal, High">
                    <?php else: ?>
                    <div><strong><?= htmlspecialchars($savedInterpretation ?: '—') ?></strong></div>
                    <?php endif; ?>
                </div>
            </div>

            <div style="margin-bottom:18px;">
                <label for="f_remarks" style="display:block; font-size:12.5px; font-weight:600; color:var(--adm-text-muted); margin-bottom:6px;">Remarks <span class="text-muted fw-normal">(Optional)</span></label>
                <?php if ($isEditable): ?>
                <textarea class="form-control" id="f_remarks" name="remarks" rows="3" placeholder="Any additional notes for the ordering doctor"><?= htmlspecialchars($savedRemarks) ?></textarea>
                <?php else: ?>
                <div><strong><?= nl2br(htmlspecialchars($savedRemarks ?: '—')) ?></strong></div>
                <?php endif; ?>
            </div>

            <div id="labResultFormMsg" style="display:none; margin-bottom:14px;"></div>

            <div style="display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
                <?php if ($isEditable): ?>
                <button type="submit" class="lxr-btn-filter" id="btnSaveLabResult"><i class="bi bi-save"></i> Save Result</button>
                <?php endif; ?>
                <?php if (!$isEditable && $currentDoc !== null && $routedToDoctorName === null): ?>
                <button type="button" class="mt-topbar-select-btn" id="btnProceedToDoctor" data-patient-id="<?= htmlspecialchars($patientId) ?>" data-record-id="<?= htmlspecialchars($recordId) ?>" data-test-type="lab_generic"><i class="bi bi-arrow-right-circle"></i> Proceed to Doctor</button>
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
    var form = document.getElementById('labResultForm');
    var msg = document.getElementById('labResultFormMsg');

    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();

            var result = form.querySelector('input[name="result"]').value.trim();
            if (result === '') {
                msg.style.display = 'block';
                msg.className = 'adm-page-subtitle';
                msg.textContent = 'Please fill in the Result before saving.';
                return;
            }

            var btn = document.getElementById('btnSaveLabResult');
            btn.disabled = true;

            fetch('<?= BASE_URL ?>/api/complete-lab-result.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    patientId: patientId,
                    recordId: recordId,
                    specimen: form.querySelector('input[name="specimen"]').value.trim(),
                    result: result,
                    unit: form.querySelector('input[name="unit"]').value.trim(),
                    range: form.querySelector('input[name="range"]').value.trim(),
                    interpretation: form.querySelector('input[name="interpretation"]').value.trim(),
                    remarks: form.querySelector('textarea[name="remarks"]').value.trim(),
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
