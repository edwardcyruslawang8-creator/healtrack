<?php
/**
 * =====================================================================
 * HEALTrack — Health Information System
 * Chatbot Reports (subadmin/chatbot_reports.php)
 * ---------------------------------------------------------------------
 * Reached via its own "Chatbot Reports" link in the sidebar, nested
 * under "Analytics & Reports" alongside "Facility Reports" (the other
 * 7 pages in this family) — $adminNavItems below expands that parent
 * item with 'open' => true and marks whichever child matches the
 * current page 'active' => true, same collapsible pattern "Audit Logs"
 * already uses elsewhere in this same sidebar. Reuses the exact same
 * shell as the rest of subadmin/*.php (280px dark-red gradient
 * sidebar, "adm-" prefixed atoms) and the same breadcrumb/KPI-row/
 * footer-notice styling as the rest of this family ("arp-" prefixed,
 * assets/css/analytics_reports.css) — plus its own small set of rules
 * ("cbr-" prefixed, assets/css/chatbot_reports.css).
 *
 * See includes/subadmin/chatbot_reports_firestore.php's header comment
 * for what's real here and why this page is NOT facility-scoped the
 * way its 7 siblings are (one public chatbot for the whole site, not
 * one per facility) — it has no .arp-tabs strip of its own for that
 * same reason.
 * =====================================================================
 */
session_start();

require_once __DIR__ . '/../config/constants.php'; // BASE_URL, asset_url()
require_once __DIR__ . '/../includes/shared/theme_helper.php'; // admin_theme_data_attr(), admin_theme_head_script()
require_once __DIR__ . '/../includes/shared/i18n.php'; // t()

// Only a signed-in sub-admin may view this page.
if (($_SESSION['role'] ?? null) !== 'subadmin') {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

require_once __DIR__ . '/../includes/subadmin/chatbot_reports_firestore.php';
// $adminProfile, $analyticsDateRangeLabel, $facilityOptions, $reportTypeOptions,
// $chatbotMostAsked, $chatbotNeedsAnswer, $chatbotKpiCards

// Sidebar nav — same Sub-Admin nav list as subadmin/dashboard.php.
// Analytics & Reports stays active — this page is one of its sub-pages.
$adminNavItems = [
    ['icon' => 'bi-grid-fill',              'label' => t('nav.dashboard'),                    'href' => 'dashboard.php'],
    ['icon' => 'bi-person-lines-fill',      'label' => t('nav.patient_management'),           'href' => 'patient_management.php'],
    ['icon' => 'bi-capsule',                'label' => t('nav.medicine_vaccine_inventory'), 'href' => 'medicine_vaccine_inventory.php'],
    ['icon' => 'bi-box-seam-fill',          'label' => t('nav.stock_requests'),               'href' => 'stock_requests.php'],
    [
        'icon' => 'bi-bar-chart-fill',
        'label' => t('nav.analytics_reports'),
        'id' => 'admNavAnalyticsReports',
        'active' => true,
        'open' => true,
        'children' => [
            ['label' => t('nav.analytics_reports_facility'), 'href' => 'analytics_reports.php'],
            ['label' => t('nav.analytics_reports_chatbot'),  'href' => 'chatbot_reports.php', 'active' => true],
        ],
    ],
    ['icon' => 'bi-people-fill',            'label' => t('nav.user_management'),              'href' => 'user_management.php'],
    ['icon' => 'bi-bell-fill',              'label' => t('nav.notifications'),                'href' => 'notifications.php', 'badge' => subadmin_unread_notification_count()],
    [
        'icon' => 'bi-clipboard-data-fill',
        'label' => t('nav.audit_logs'),
        'children' => [
            ['label' => t('nav.audit_logs_doctor'),             'href' => 'audit_logs.php?role=doctor'],
            ['label' => t('nav.audit_logs_medstaff'),      'href' => 'audit_logs.php?role=healthcare_staff'],
            ['label' => t('nav.audit_logs_medtech'), 'href' => 'audit_logs.php?role=medical_technician'],
            ['label' => t('nav.audit_logs_pharmacist'),         'href' => 'audit_logs.php?role=pharmacist'],
        ],
    ],
    ['icon' => 'bi-file-earmark-lock-fill', 'label' => t('nav.consent_management'),           'href' => 'consent_management.php'],
    ['icon' => 'bi-megaphone-fill',         'label' => t('nav.announcements'),                'href' => 'announcements.php'],
    [
        'icon' => 'bi-gear-fill',
        'label' => t('nav.profile_settings'),
        'id' => 'admNavProfileSettings',
        'children' => [
            ['label' => t('nav.profile_settings'), 'href' => 'profile_settings.php'],
            ['label' => t('nav.homepage_settings'), 'href' => 'homepage_settings.php'],
        ],
    ],
];
?>
<!DOCTYPE html>
<html lang="en"<?= admin_theme_data_attr() ?>>
<head>
<?= admin_theme_head_script() ?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Chatbot Reports — HEALTrack</title>

<!-- Poppins (self-hosted — see assets/css/fonts-poppins.css header comment) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/fonts-poppins.css') ?>">

<!-- Bootstrap 5 -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<!-- Font Awesome (available per project spec; Bootstrap Icons cover this page's icons) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<!-- Shared admin shell (sidebar, topbar, cards, table, pagination, etc.) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/admin-dashboard.css') ?>">
<!-- Sub-Admin Dashboard styles, reused here for the chart-card pattern -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/subadmin_dashboard.css') ?>">
<!-- Analytics & Reports styles, reused here for the breadcrumb/dropdown/KPI-row/footer notice -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/analytics_reports.css') ?>">
<!-- Chatbot Reports page styles (needs-answer table + Answer modal) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/chatbot_reports.css') ?>">
</head>
<body class="adm-body">

<!-- Mobile sidebar trigger -->
<button class="adm-mobile-menu-btn" id="admSidebarToggle" aria-label="Open menu">
    <i class="bi bi-list"></i>
</button>
<div class="adm-sidebar-backdrop" id="admSidebarBackdrop"></div>

<!-- =============================================================
     SIDEBAR — reused verbatim from analytics_reports.php /
     dashboard.php, no structural changes.
     ============================================================= -->
<aside class="adm-sidebar" id="admSidebar">

    <!-- Brand -->
    <div class="adm-brand">
        <div class="adm-logo-badge"><i class="bi bi-heart-pulse-fill"></i></div>
        <div class="adm-brand-text">
            <h1>HEALTrack</h1>
            <p><?= htmlspecialchars(t('app.tagline')) ?></p>
        </div>
    </div>

    <hr class="adm-sidebar-divider">

    <!-- Sub-admin profile -->
    <div class="adm-profile">
        <div class="adm-avatar"><i class="bi bi-person-circle"></i></div>
        <div class="adm-profile-text">
            <div class="adm-profile-name"><?= htmlspecialchars($adminProfile['name']) ?></div>
            <div class="adm-profile-role"><?= htmlspecialchars($adminProfile['role']) ?></div>
        </div>
    </div>

    <hr class="adm-sidebar-divider">

    <!-- Navigation -->
    <ul class="adm-nav">
        <?php foreach ($adminNavItems as $item): ?>
        <?php if (!empty($item['children'])): ?>
        <li>
            <a href="#<?= htmlspecialchars($item['id'] ?? 'admNavAuditLogs') ?>" class="adm-nav-link adm-nav-toggle <?= !empty($item['active']) ? 'active' : '' ?> <?= empty($item['open']) ? 'collapsed' : '' ?>" data-bs-toggle="collapse" role="button" aria-expanded="<?= !empty($item['open']) ? 'true' : 'false' ?>" aria-controls="<?= htmlspecialchars($item['id'] ?? 'admNavAuditLogs') ?>">
                <i class="bi <?= htmlspecialchars($item['icon']) ?>"></i>
                <span><?= htmlspecialchars($item['label']) ?></span>
                <i class="bi bi-chevron-down adm-nav-caret"></i>
            </a>
            <div class="collapse <?= !empty($item['open']) ? 'show' : '' ?>" id="<?= htmlspecialchars($item['id'] ?? 'admNavAuditLogs') ?>">
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
                <span class="sad-nav-badge"><?= (int) $item['badge'] ?></span>
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
            <span><?= htmlspecialchars(t('nav.logout')) ?></span>
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
            <h1><?= htmlspecialchars(t('nav.analytics_reports')) ?></h1>
            <nav class="arp-breadcrumb" aria-label="Breadcrumb">
                <a href="dashboard.php">Dashboard</a>
                <span class="arp-breadcrumb-sep">&gt;</span>
                <a href="analytics_reports.php">Analytics &amp; Reports</a>
                <span class="arp-breadcrumb-sep">&gt;</span>
                <span class="arp-breadcrumb-current arp-breadcrumb-current-red">Chatbot Reports</span>
            </nav>
        </div>

        <div class="adm-topbar-right">
            <div class="adm-topbar-actions">
                <div class="sad-topbar-select-btn">
                    <i class="bi bi-calendar3"></i> <?= htmlspecialchars($analyticsDateRangeLabel) ?>
                </div>

                <a href="notifications.php" class="adm-bell-btn" aria-label="Notifications">
                    <i class="bi bi-bell-fill"></i>
                    <?php if (subadmin_unread_notification_count() > 0): ?><span class="adm-badge-count"><?= subadmin_unread_notification_count() ?></span><?php endif; ?>
                </a>

                <div class="dropdown">
                    <button class="adm-profile-dropdown dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="adm-topbar-avatar"><i class="bi bi-person-fill"></i></span>
                        <span>
                            <span class="d-block adm-topbar-name"><?= htmlspecialchars($adminProfile['name']) ?></span>
                            <span class="d-block adm-topbar-role"><?= htmlspecialchars($adminProfile['role']) ?></span>
                        </span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="profile_settings.php"><i class="bi bi-gear me-2"></i>Profile / Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/api/logout.php"><i class="bi bi-box-arrow-right me-2"></i><?= htmlspecialchars(t('nav.logout')) ?></a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- ============ SECTION INTRO ============ -->
    <div class="cbr-section-intro">
        <h2>Chatbot Reports</h2>
        <p>See what people are really asking the HEALTrack chatbot, and teach it the answers it doesn't have yet.</p>
    </div>

    <!-- ============ KPI SUMMARY CARDS ============ -->
    <div class="arp-kpi-row cbr-kpi-row-4">
        <?php foreach ($chatbotKpiCards as $kpi): ?>
        <div class="adm-card arp-kpi-card">
            <div class="arp-kpi-icon" style="background:<?= htmlspecialchars($kpi['iconBg']) ?>; color:<?= htmlspecialchars($kpi['iconColor']) ?>;">
                <i class="bi <?= htmlspecialchars($kpi['icon']) ?>"></i>
            </div>
            <div class="arp-kpi-text">
                <span class="arp-kpi-label"><?= htmlspecialchars($kpi['label']) ?></span>
                <span class="arp-kpi-value"><?= number_format($kpi['value']) ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- ============ NEEDS YOUR ANSWER ============ -->
    <div class="adm-card cbr-card">
        <div class="adm-card-header">
            <h2><i class="bi bi-exclamation-triangle-fill" style="color:#E8710A;"></i> Needs Your Answer</h2>
        </div>
        <p class="cbr-card-subtitle">Real questions the chatbot couldn't confidently answer from its own built-in intents, or that a real person flagged as wrong — answer one here and it's matched automatically on every future message, no code changes needed.</p>
        <?php if (empty($chatbotNeedsAnswer)): ?>
        <div class="cbr-empty"><i class="bi bi-check-circle"></i> Nothing needs your attention right now.</div>
        <?php else: ?>
        <div class="cbr-table-scroll">
        <table class="adm-table">
            <thead>
                <tr>
                    <th>Question</th>
                    <th>Why</th>
                    <th>Asked</th>
                    <th>Last Asked</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($chatbotNeedsAnswer as $row): ?>
                <tr>
                    <td class="cbr-question-cell"><?= htmlspecialchars($row['sampleMessage']) ?></td>
                    <td>
                        <?php if (!empty($row['flagged'])): ?>
                        <span class="cbr-reason-badge flagged"><i class="bi bi-hand-thumbs-down-fill"></i> Flagged by user</span>
                        <?php endif; ?>
                        <?php if (!empty($row['missed'])): ?>
                        <span class="cbr-reason-badge missed"><i class="bi bi-question-diamond-fill"></i> Couldn't answer</span>
                        <?php endif; ?>
                    </td>
                    <td><?= number_format($row['count']) ?>×</td>
                    <td><?= $row['lastAskedAt'] !== '' ? htmlspecialchars(date('M j, Y g:i A', strtotime($row['lastAskedAt']))) : '—' ?></td>
                    <td>
                        <button type="button" class="cbr-answer-btn" data-question-normalized="<?= htmlspecialchars($row['questionNormalized']) ?>" data-question-sample="<?= htmlspecialchars($row['sampleMessage']) ?>">
                            Answer
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- ============ MOST ASKED QUESTIONS ============ -->
    <div class="adm-card cbr-card">
        <div class="adm-card-header">
            <h2>Top 5 Most Asked Questions</h2>
        </div>
        <?php if (empty($chatbotMostAsked)): ?>
        <div class="cbr-empty"><i class="bi bi-chat-dots"></i> No question has been asked more than once yet.</div>
        <?php else: ?>
        <table class="adm-table">
            <thead>
                <tr>
                    <th>Question</th>
                    <th>Times Asked</th>
                    <th>Last Asked</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($chatbotMostAsked as $row): ?>
                <tr>
                    <td class="cbr-question-cell"><?= htmlspecialchars($row['sampleMessage']) ?></td>
                    <td><?= number_format($row['count']) ?>×</td>
                    <td><?= $row['lastAskedAt'] !== '' ? htmlspecialchars(date('M j, Y g:i A', strtotime($row['lastAskedAt']))) : '—' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <!-- ============ ANSWERED QUESTIONS ============
         The other side of "Needs Your Answer" above — once a subadmin
         answers a question there, includes/subadmin/chatbot_reports_firestore.php
         excludes it from that table (same md5(questionNormalized) id
         both filters key off) and it appears here instead, newest
         first. Not a separate action, just a visible record of what the
         chatbot has actually been taught. -->
    <div class="adm-card cbr-card">
        <div class="adm-card-header">
            <h2><i class="bi bi-mortarboard-fill" style="color:#1E8E3E;"></i> Answered Questions</h2>
        </div>
        <p class="cbr-card-subtitle">Questions you've already taught the chatbot — matched automatically from now on, no Gemini call needed.</p>
        <?php if (empty($chatbotAnsweredQuestions)): ?>
        <div class="cbr-empty"><i class="bi bi-mortarboard"></i> Nothing taught yet — answer a question above and it'll show up here.</div>
        <?php else: ?>
        <div class="cbr-table-scroll">
        <table class="adm-table">
            <thead>
                <tr>
                    <th>Question</th>
                    <th>Answer</th>
                    <th>Taught By</th>
                    <th>When</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($chatbotAnsweredQuestions as $row): ?>
                <tr>
                    <td class="cbr-question-cell"><?= htmlspecialchars($row['questionSample']) ?></td>
                    <td class="cbr-answer-cell"><?= htmlspecialchars($row['answer']) ?></td>
                    <td><?= htmlspecialchars($row['createdBy']) ?></td>
                    <td><?= $row['answeredAt'] !== '' ? htmlspecialchars(date('M j, Y g:i A', strtotime($row['answeredAt']))) : '—' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- ============ FOOTER NOTICE ============ -->
    <div class="arp-footer-notice">
        <i class="bi bi-info-circle-fill"></i>
        <span>Chatbot activity is real-time and system-wide (not scoped to a single facility).</span>
    </div>

    <!-- ============ ANSWER MODAL ============
         .adm-modal — the same shared modal theme used throughout the
         admin side (see assets/css/admin-dashboard.css's .adm-modal
         rules and e.g. includes/admin/add_facility_modal.php for
         another real usage of it), not a bare Bootstrap default modal. -->
    <div class="modal fade adm-modal" id="cbrAnswerModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-mortarboard-fill"></i> Teach the Chatbot</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <label class="cbr-modal-label">Question asked</label>
                    <p class="cbr-modal-question" id="cbrAnswerModalQuestion"></p>

                    <label class="cbr-modal-label" for="cbrAnswerText">Your answer</label>
                    <textarea class="form-control" id="cbrAnswerText" rows="5" placeholder="Type the answer the chatbot should give from now on..."></textarea>
                    <div class="cbr-modal-error" id="cbrAnswerModalError"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="adm-modal-btn-cancel" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="cbr-save-answer-btn" id="cbrSaveAnswerBtn">Save Answer</button>
                </div>
            </div>
        </div>
    </div>

</main>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= asset_url('/assets/js/admin-sidebar-toggle.js') ?>"></script>
<script src="<?= asset_url('/assets/js/theme.js') ?>"></script>
<script src="<?= asset_url('/assets/js/chatbot-reports.js') ?>"></script>
</body>
</html>
