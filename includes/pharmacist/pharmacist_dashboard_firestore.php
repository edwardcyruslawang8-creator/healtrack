<?php
/**
 * =====================================================================
 * Pharmacist Dashboard — Data
 * ---------------------------------------------------------------------
 * Follows the same per-page "-data.php" convention as admin/, subadmin/,
 * and medical-staff/. Modeled directly on
 * includes/medical_staff_dashboard_firestore.php's shape (same stat-card /
 * trend-chart / doughnut / schedule-table / notification-list
 * structure), with every section reframed around pharmacy operations
 * — dispensing, medicine stock, and stock requests — instead of
 * consultations and patients.
 *
 * Dispensing Trend and Medicines by Category are CONNECTED to real
 * data (real dispensed pharmacy_orders docs, resolved to their real
 * medicines/{id} category) — see their own section comments below —
 * with the same preset/Last Year/Custom Date range dropdown every
 * other role's dashboard already has
 * (includes/shared/dashboard_range_dropdown.php). Every other section —
 * the stat cards, Stock Status, Expiring Soon, Stock Request Status,
 * Today's Stock Requests, Recent Notifications and Low Stock Medicines
 * — reads real data too (medicines/medicine_batches, pharmacy_orders,
 * this pharmacist's own stock_requests, and the same notification
 * sources as pharmacist/notifications.php); see each section's own
 * comment. $staffProfile is read from the
 * signed-in pharmacist's own Firestore users/{uid} document, same as
 * includes/medical_staff_dashboard_firestore.php.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore
require_once __DIR__ . '/../firestore/pharmacy_orders.php'; // hc_get_all_pharmacy_order_docs()
require_once __DIR__ . '/../firestore/medicines.php'; // hc_get_inventory_items()
require_once __DIR__ . '/../shared/query_helpers.php'; // hc_resolve_dashboard_range(), hc_dashboard_trend_series(), hc_matches_date_range_key()
require_once __DIR__ . '/../shared/inventory_match.php'; // hc_item_names_match()
require_once __DIR__ . '/../shared/inventory_movement_monitor.php'; // hc_compute_inventory_expiry_monitor()
require_once __DIR__ . '/../firestore/stock_requests.php'; // hc_get_all_stock_requests(), hc_stock_request_status_meta()
require_once __DIR__ . '/../../config/constants.php'; // hc_pharmacist_unread_notification_count()

$myUid = $_SESSION['uid'] ?? '';
$myDoc = $myUid !== '' ? $firestore->getDocument('users', $myUid) : null;

$staffProfile = [
    'name'     => $_SESSION['name'] ?? 'Pharmacist',
    'role'     => 'Pharmacist',
    'facility' => $myDoc['facility'] ?? 'North Super Health Center',
];

$dashboardDateLabel = date('F j, Y');

// Facility selector — mirrors includes/medical_staff_dashboard_firestore.php's names.
$facilityOptions = ['North Super Health Center', 'East Super Health Center', 'West Super Health Center'];

// ---------------------------------------------------------------------
// Summary stat cards — all CONNECTED:
//   - Prescriptions Dispensed Today: real pharmacy_orders dispensed
//     today (both dispense flows stamp dispensedDate), with a real
//     change vs. yesterday's count.
//   - Total Medicine Items / Low Stock Alerts: the real inventory (the
//     same non-archived items, and the same low + out-of-stock rule, as
//     the Sub-Admin dashboard's own Low Stock Alerts card). There's no
//     stored history to compare against, so they link to the inventory
//     instead of showing a trend line.
//   - Pending Stock Requests: this pharmacist's own requests still
//     awaiting a decision — the same scope pharmacist/stock_requests.php
//     shows.
//   - Unread Notifications: the same count as the sidebar/topbar badge.
// ---------------------------------------------------------------------
$dashboardMedicineItems = hc_get_inventory_items('medicine');
$dashboardInventoryItems = array_merge($dashboardMedicineItems, hc_get_inventory_items('vaccine'));
$dashboardLowStockAlerts = count(array_filter($dashboardInventoryItems, fn ($i) => in_array($i['status'], ['low', 'unavailable'], true)));

$dispensedTodayCount = 0;
$dispensedYesterdayCount = 0;
$yesterday = date('Y-m-d', strtotime('-1 day'));
foreach (hc_get_all_pharmacy_order_docs() as $doc) {
    if (($doc['fields']['status'] ?? '') !== 'dispensed') {
        continue;
    }
    $dispensedDate = (string) ($doc['fields']['dispensedDate'] ?? '');
    if ($dispensedDate === date('Y-m-d')) {
        $dispensedTodayCount++;
    } elseif ($dispensedDate === $yesterday) {
        $dispensedYesterdayCount++;
    }
}
if ($dispensedYesterdayCount > 0) {
    $dispensedChangePct = (int) round(abs($dispensedTodayCount - $dispensedYesterdayCount) / $dispensedYesterdayCount * 100);
    $dispensedSub = $dispensedChangePct . '% from yesterday';
} else {
    $dispensedSub = $dispensedTodayCount > 0 ? 'None dispensed yesterday' : 'Same as yesterday';
}

$myStockRequests = array_values(array_filter(hc_get_all_stock_requests(), fn ($r) => $myUid !== '' && $r['pharmacistUid'] === $myUid));
$myPendingStockRequests = count(array_filter($myStockRequests, fn ($r) => $r['status'] === 'pending'));

$statCards = [
    ['icon' => 'bi-capsule',                  'iconBg' => '#FCE4E4', 'iconColor' => '#D93025', 'label' => "Prescriptions Dispensed Today", 'value' => $dispensedTodayCount, 'sub' => $dispensedSub, 'trend' => $dispensedTodayCount < $dispensedYesterdayCount ? 'down' : 'up'],
    ['icon' => 'bi-boxes',                    'iconBg' => '#E4F7EA', 'iconColor' => '#1E8E3E', 'label' => 'Total Medicine Items',          'value' => count($dashboardMedicineItems), 'link' => ['label' => 'View inventory', 'href' => 'medicine_vaccine_inventory.php']],
    ['icon' => 'bi-exclamation-triangle-fill','iconBg' => '#EFE7FB', 'iconColor' => '#7C3AED', 'label' => 'Low Stock Alerts',               'value' => $dashboardLowStockAlerts, 'link' => ['label' => 'View inventory', 'href' => 'medicine_vaccine_inventory.php']],
    ['icon' => 'bi-box-seam-fill',            'iconBg' => '#E8F0FE', 'iconColor' => '#1A73E8', 'label' => 'Pending Stock Requests',         'value' => $myPendingStockRequests, 'link' => ['label' => 'View stock requests', 'href' => 'stock_requests.php']],
    ['icon' => 'bi-bell-fill',                'iconBg' => '#FDECDD', 'iconColor' => '#E8710A', 'label' => 'Unread Notifications',           'value' => hc_pharmacist_unread_notification_count(), 'link' => ['label' => 'View all notifications', 'href' => 'notifications.php']],
];

// ---------------------------------------------------------------------
// CONNECTED — real dispensed pharmacy_orders docs (status='dispensed',
// written by api/dispense-pharmacy-order.php's "Dispense" action AND
// api/dispense-without-prescription.php's walk-in dispense, the only
// two write paths that ever set this status), each resolved to its
// real inventory category via dispensedInventoryId (blank when the
// dispensed item wasn't linked to a tracked inventory record — a
// genuine, allowed choice at dispense time, same as
// hc_get_inventory_items()'s own "Uncategorized" fallback for an item
// with no real category set). Feeds both charts below with the same
// preset/Custom Date range dropdown every other role's dashboard
// already has (includes/shared/dashboard_range_dropdown.php).
// ---------------------------------------------------------------------
$allInventoryItems = array_merge(hc_get_inventory_items('medicine'), hc_get_inventory_items('vaccine'));
$categoryByInventoryId = [];
foreach ($allInventoryItems as $item) {
    $categoryByInventoryId[$item['id']] = $item['category'];
}

// Most dispensed orders never actually got linked to a tracked
// inventory record at dispense time (dispensedInventoryId blank is a
// genuine, allowed "don't adjust stock" choice on both dispense flows
// — see api/dispense-pharmacy-order.php/api/dispense-without-prescription.php's
// own comments) — trusting dispensedInventoryId alone left almost
// every real dispense bucketed as "Uncategorized", which produced a
// mostly-empty, not-very-useful chart. When it's blank, this falls
// back to the SAME approximate name match
// (includes/shared/inventory_match.php's hc_item_names_match(), "does
// either string's generic first word appear in the other as a whole word")
// — loose on purpose for a category chart (the Dispense modal's own
// auto-select uses the stricter hc_find_inventory_match()) — e.g. a
// doctor's free-text "Paracetamol" order still resolves to whatever
// real "Paracetamol ___ Tablet" inventory item exists right now, same
// approximate standard this app already applies elsewhere rather than
// a stricter one invented just for this chart.
$allDispensedOrders = [];
foreach (hc_get_all_pharmacy_order_docs() as $doc) {
    $f = $doc['fields'];
    if (($f['status'] ?? '') !== 'dispensed') {
        continue;
    }
    $inventoryId = $f['dispensedInventoryId'] ?? '';
    $category = $inventoryId !== '' ? ($categoryByInventoryId[$inventoryId] ?? null) : null;
    if ($category === null) {
        $itemName = (string) ($f['itemName'] ?? '');
        // Dispensed with "Not in inventory" and no inventory item by that
        // name (e.g. a brand like "Solmux" that isn't stocked) — there's
        // no category to take, so it's labelled for what it is.
        $category = 'Not in inventory';
        if ($itemName !== '') {
            foreach ($allInventoryItems as $invItem) {
                if (hc_item_names_match($invItem['name'], $itemName)) {
                    $category = $invItem['category'];
                    break;
                }
            }
        }
    }
    $allDispensedOrders[] = [
        'dispensedDateRaw' => $f['dispensedDate'] ?? '',
        'category' => $category,
    ];
}

// ---------------------------------------------------------------------
// Dispensing Trend — line chart. Real count of dispensed prescriptions
// per day/month, bucketed over the selected range (same shared helper
// every other role's own trend chart uses).
// ---------------------------------------------------------------------
$dispensingTrendRangeState = hc_resolve_dashboard_range('trendRange', ['this_week' => 'This Week', 'this_month' => 'This Month', 'this_year' => 'This Year'], 'this_week');
$dispensingTrendRangeKey = $dispensingTrendRangeState['key'];
$dispensingTrendRange = $dispensingTrendRangeState['label'];

$dispensingTrend = hc_dashboard_trend_series(
    $allDispensedOrders,
    fn ($o) => $o['dispensedDateRaw'],
    $dispensingTrendRangeKey,
    $dispensingTrendRangeState['customFrom'],
    $dispensingTrendRangeState['customTo']
);

// ---------------------------------------------------------------------
// Medicines by Category — bar chart. Real count of dispensed items per
// real inventory category, over the selected range — same "group real
// rows within a date range" shape as
// includes/medical_technician/medical_technician_dashboard_firestore.php's
// Tests by Type.
// ---------------------------------------------------------------------
$medicinesByCategoryRangeState = hc_resolve_dashboard_range('categoryRange', ['this_week' => 'This Week', 'this_month' => 'This Month', 'this_year' => 'This Year'], 'this_month');
$medicinesByCategoryRangeKey = $medicinesByCategoryRangeState['key'];
$medicinesByCategoryRange = $medicinesByCategoryRangeState['label'];

$categoryRangeOrders = array_filter($allDispensedOrders, fn ($o) => hc_matches_date_range_key($o['dispensedDateRaw'], $medicinesByCategoryRangeKey, $medicinesByCategoryRangeState['customFrom'], $medicinesByCategoryRangeState['customTo']));

// Every real inventory category is listed — at 0 when nothing in it was
// dispensed in this range — instead of only the ones that were, so the
// chart always shows the full category picture (previously a quiet month
// showed just 1-2 bars and every other category was missing entirely).
// Busiest first, then the rest alphabetically. "Not in inventory" (a
// dispense that couldn't be matched to any inventory item) only appears
// when it actually has dispenses.
$categoryCounts = array_fill_keys(hc_medicine_categories($allInventoryItems), 0);
foreach ($categoryRangeOrders as $o) {
    $categoryCounts[$o['category']] = ($categoryCounts[$o['category']] ?? 0) + 1;
}
$categoryNames = array_keys($categoryCounts);
usort($categoryNames, fn ($a, $b) => [$categoryCounts[$b], $a] <=> [$categoryCounts[$a], $b]);
$medicinesByCategory = [
    'labels' => $categoryNames,
    'values' => array_map(fn ($c) => $categoryCounts[$c], $categoryNames),
];

// ---------------------------------------------------------------------
// Stock Status — doughnut chart. CONNECTED — every non-archived
// medicine and vaccine by its real status; an overstocked item still
// has stock on hand, so it counts as In Stock in this 3-slice chart.
// ---------------------------------------------------------------------
$stockStatusCounts = ['available' => 0, 'low' => 0, 'unavailable' => 0];
foreach ($dashboardInventoryItems as $item) {
    $stockStatusCounts[$item['status'] === 'overstocked' ? 'available' : $item['status']]++;
}
$stockStatus = [
    ['label' => 'In Stock',    'value' => $stockStatusCounts['available'],   'color' => '#1E8E3E'],
    ['label' => 'Low Stock',   'value' => $stockStatusCounts['low'],         'color' => '#E8710A'],
    ['label' => 'Out of Stock','value' => $stockStatusCounts['unavailable'], 'color' => '#D93025'],
];

// ---------------------------------------------------------------------
// Expiring Soon — CONNECTED to the real per-batch expiry monitor
// (includes/shared/inventory_movement_monitor.php): the 5 batches with
// stock left that expire soonest within the next 30 days, already in
// that order. Already-expired batches aren't "expiring soon" — they're
// flagged Expired on the inventory table instead.
// ---------------------------------------------------------------------
$expiringSoonBatches = array_filter(hc_compute_inventory_expiry_monitor()['items'], fn ($r) => in_array($r['status'], ['today', 'expiring_soon'], true));
$expiringSoon = array_map(fn ($r) => [
    'name' => $r['name'],
    'when' => $r['daysFromToday'] === 0 ? 'Today' : 'In ' . $r['daysFromToday'] . ' day' . ($r['daysFromToday'] === 1 ? '' : 's'),
    'time' => 'Batch ' . $r['batchNo'],
], array_slice(array_values($expiringSoonBatches), 0, 5));

// ---------------------------------------------------------------------
// Stock Request Status — doughnut chart. CONNECTED — every one of this
// pharmacist's own requests by its real status, in the same colors
// hc_stock_request_status_meta() gives each status's badge.
// ---------------------------------------------------------------------
$stockRequestStatus = [];
foreach (['approved', 'pending', 'released', 'rejected'] as $statusKey) {
    $stockRequestStatus[] = [
        'label' => hc_stock_request_status_meta()[$statusKey]['label'],
        'value' => count(array_filter($myStockRequests, fn ($r) => $r['status'] === $statusKey)),
        'color' => hc_stock_request_status_meta()[$statusKey]['color'],
    ];
}

// Totals and legend percentages are derived from the same values the
// doughnuts draw, so the legend can never disagree with the chart.
$hc_with_pct = function (array $rows): array {
    $total = array_sum(array_column($rows, 'value'));
    foreach ($rows as &$row) {
        $row['pct'] = $total > 0 ? round($row['value'] / $total * 100, 1) : 0;
    }
    unset($row);
    return $rows;
};
$stockStatus = $hc_with_pct($stockStatus);
$stockRequestStatus = $hc_with_pct($stockRequestStatus);
$stockStatusTotal = (int) array_sum(array_column($stockStatus, 'value'));
$stockRequestStatusTotal = (int) array_sum(array_column($stockRequestStatus, 'value'));

// ---------------------------------------------------------------------
// Today's Stock Requests — CONNECTED: this pharmacist's own requests
// submitted today, newest first. stock_requests has no priority field,
// so Priority is derived from the real Date Needed: a request still
// open (pending/approved) that's needed within 3 days — or already
// overdue — is High, everything else Normal.
// ---------------------------------------------------------------------
$highPriorityCutoff = date('Y-m-d', strtotime('+3 days'));
$todaysStockRequests = [];
foreach ($myStockRequests as $r) {
    if ($r['requestedAtRaw'] === '' || date('Y-m-d', strtotime($r['requestedAtRaw'])) !== date('Y-m-d')) {
        continue;
    }
    $itemNames = array_values(array_filter(array_map(fn ($line) => (string) ($line['name'] ?? ''), $r['items'])));
    $isOpen = in_array($r['status'], ['pending', 'approved'], true);
    $todaysStockRequests[] = [
        'time' => $r['requestTime'],
        'item' => ($itemNames[0] ?? '—') . (count($itemNames) > 1 ? ' +' . (count($itemNames) - 1) . ' more' : ''),
        'requestedBy' => $r['pharmacist'],
        'priority' => $isOpen && $r['dateNeededRaw'] !== '' && $r['dateNeededRaw'] <= $highPriorityCutoff ? 'High' : 'Normal',
        'status' => $r['status'],
    ];
}
$stockRequestMeta = hc_stock_request_status_meta();
$priorityMeta = [
    'High'   => ['bg' => '#FCE4E4', 'color' => '#D93025'],
    'Normal' => ['bg' => '#EEF0F3', 'color' => '#6B7280'],
];

// ---------------------------------------------------------------------
// Recent Notifications — CONNECTED: the same real sources (and the
// same notification preferences) as pharmacist/notifications.php,
// minus any this pharmacist has archived. One source at a time in
// turn (unread first within each), so the 5 rows show a mix — low
// stock, expiry, pending orders, stock request outcomes, reminders —
// instead of 5 of whichever kind happens to have the most entries.
// ---------------------------------------------------------------------
require_once __DIR__ . '/../shared/two_fa_reminder.php'; // hc_get_2fa_reminder_notification()
require_once __DIR__ . '/../shared/phone_reminder.php'; // hc_get_phone_reminder_notification()
require_once __DIR__ . '/../shared/email_verification_reminder.php'; // hc_my_email_verification_notification()
require_once __DIR__ . '/../shared/notification_preferences.php'; // hc_notification_preference_enabled_for_doc()

$notificationSources = [];
if (hc_notification_preference_enabled_for_doc($myDoc ?? [], 'system')) {
    $inventoryEnabled = hc_notification_preference_enabled_for_doc($myDoc ?? [], 'inventory');
    $notificationSources = [
        $inventoryEnabled ? hc_get_low_stock_notifications() : [],
        $inventoryEnabled ? hc_get_medicine_expiry_notifications() : [],
        $inventoryEnabled ? hc_get_pending_pharmacy_order_notifications() : [],
        hc_get_decided_stock_request_notifications_for_pharmacist($myUid),
        $inventoryEnabled ? hc_get_overstock_notifications() : [],
        array_values(array_filter([
            hc_get_2fa_reminder_notification($myUid, $myDoc ?? []),
            hc_get_phone_reminder_notification($myUid, $myDoc ?? []),
            hc_my_email_verification_notification(),
        ])),
    ];
}

$ntfReadIds = $_SESSION['ntfReadIds'] ?? [];
$ntfDismissedIds = $_SESSION['ntfDismissedIds'] ?? [];
foreach ($notificationSources as &$source) {
    $source = array_values(array_filter($source, fn ($n) => !in_array($n['id'], $ntfDismissedIds, true)));
    usort($source, fn ($a, $b) => in_array($a['id'], $ntfReadIds, true) <=> in_array($b['id'], $ntfReadIds, true));
}
unset($source);

$recentNotifications = [];
$longestSource = $notificationSources === [] ? 0 : max(array_map('count', $notificationSources));
for ($i = 0; count($recentNotifications) < 5 && $i < $longestSource; $i++) {
    foreach ($notificationSources as $source) {
        if (isset($source[$i]) && count($recentNotifications) < 5) {
            $n = $source[$i];
            $recentNotifications[] = [
                'icon' => $n['icon'],
                'iconBg' => $n['iconBg'],
                'iconColor' => $n['iconColor'],
                'title' => $n['title'],
                'desc' => $n['description'],
                'timeAgo' => trim($n['date'] . ($n['time'] !== '' ? ' · ' . $n['time'] : '')),
            ];
        }
    }
}

// ---------------------------------------------------------------------
// Low Stock Medicines — CONNECTED: real medicines at Low Stock or Out of
// Stock, out-of-stock first, then furthest below their own reorder
// level. Top 5; "View All Medicines" links to the full inventory.
// ---------------------------------------------------------------------
$lowStockMedicineItems = array_values(array_filter($dashboardMedicineItems, fn ($m) => in_array($m['status'], ['low', 'unavailable'], true)));
usort($lowStockMedicineItems, fn ($a, $b) => $a['currentStock'] / max(1, $a['reorderLevel']) <=> $b['currentStock'] / max(1, $b['reorderLevel']) ?: strcasecmp($a['name'], $b['name']));
$lowStockMedicines = array_map(fn ($m) => [
    'id' => $m['id'],
    'name' => $m['name'],
    'category' => $m['category'],
    'currentStock' => $m['currentStock'],
    'reorderLevel' => $m['reorderLevel'],
    'expiryDate' => $m['expiryDateRaw'] !== '' ? date('M j, Y', strtotime($m['expiryDateRaw'])) : '—',
], array_slice($lowStockMedicineItems, 0, 5));
