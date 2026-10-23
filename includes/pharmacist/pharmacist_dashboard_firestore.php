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
 * (includes/shared/dashboard_range_dropdown.php). Every other section
 * below is still a static stand-in: there's no per-pharmacist stock-
 * request/notification collection in this app yet
 * (subadmin/medicine_vaccine_inventory.php and
 * subadmin/stock_requests.php are facility-wide Sub-Admin views, not a
 * per-pharmacist model). $staffProfile IS real though — read from the
 * signed-in pharmacist's own Firestore users/{uid} document, same as
 * includes/medical_staff_dashboard_firestore.php.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore
require_once __DIR__ . '/../firestore/pharmacy_orders.php'; // hc_get_all_pharmacy_order_docs()
require_once __DIR__ . '/../firestore/medicines.php'; // hc_get_inventory_items()
require_once __DIR__ . '/../shared/query_helpers.php'; // hc_resolve_dashboard_range(), hc_dashboard_trend_series(), hc_matches_date_range_key()
require_once __DIR__ . '/../shared/inventory_match.php'; // hc_item_names_match()

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
// Summary stat cards
// ---------------------------------------------------------------------
$statCards = [
    ['icon' => 'bi-capsule',                  'iconBg' => '#FCE4E4', 'iconColor' => '#D93025', 'label' => "Prescriptions Dispensed Today", 'value' => 42,   'sub' => '14% from yesterday',  'trend' => 'up'],
    ['icon' => 'bi-boxes',                    'iconBg' => '#E4F7EA', 'iconColor' => '#1E8E3E', 'label' => 'Total Medicine Items',          'value' => 386,  'sub' => '3% from last month',  'trend' => 'up'],
    ['icon' => 'bi-exclamation-triangle-fill','iconBg' => '#EFE7FB', 'iconColor' => '#7C3AED', 'label' => 'Low Stock Alerts',               'value' => 9,    'sub' => '2 more than yesterday','trend' => 'up'],
    ['icon' => 'bi-box-seam-fill',            'iconBg' => '#E8F0FE', 'iconColor' => '#1A73E8', 'label' => 'Pending Stock Requests',         'value' => 6,    'sub' => '1 fewer than yesterday','trend' => 'down'],
    ['icon' => 'bi-bell-fill',                'iconBg' => '#FDECDD', 'iconColor' => '#E8710A', 'label' => 'Unread Notifications',           'value' => 5,    'link' => ['label' => 'View all notifications', 'href' => 'notifications.php']],
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
// either string's generic first word appear in the other") the
// dispense flow's own suggested-item pre-fill already uses — e.g. a
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
        $category = 'Uncategorized';
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

$categoryCounts = [];
foreach ($categoryRangeOrders as $o) {
    $categoryCounts[$o['category']] = ($categoryCounts[$o['category']] ?? 0) + 1;
}
arsort($categoryCounts);
$medicinesByCategory = [
    'labels' => array_keys($categoryCounts),
    'values' => array_values($categoryCounts),
];

// ---------------------------------------------------------------------
// Stock Status — doughnut chart
// ---------------------------------------------------------------------
$stockStatusTotal = 386;
$stockStatus = [
    ['label' => 'In Stock',    'value' => 312, 'pct' => 80.8, 'color' => '#1E8E3E'],
    ['label' => 'Low Stock',   'value' => 55,  'pct' => 14.2, 'color' => '#E8710A'],
    ['label' => 'Out of Stock','value' => 19,  'pct' => 5.0,  'color' => '#D93025'],
];

// ---------------------------------------------------------------------
// Expiring Soon medicines
// ---------------------------------------------------------------------
$expiringSoon = [
    ['name' => 'Amoxicillin 500mg',      'when' => 'In 5 days',  'time' => 'Batch AX500-0125'],
    ['name' => 'Cetirizine 10mg',        'when' => 'In 9 days',  'time' => 'Batch CT10-0225'],
    ['name' => 'Metformin 500mg',        'when' => 'In 12 days', 'time' => 'Batch MF500-0325'],
    ['name' => 'Salbutamol Inhaler',     'when' => 'In 18 days', 'time' => 'Batch SB-0425'],
    ['name' => 'Paracetamol 500mg',      'when' => 'In 21 days', 'time' => 'Batch PC500-0525'],
];

// ---------------------------------------------------------------------
// Stock Request Status — doughnut chart
// ---------------------------------------------------------------------
$stockRequestStatusTotal = 48;
$stockRequestStatus = [
    ['label' => 'Approved', 'value' => 30, 'pct' => 63, 'color' => '#1E8E3E'],
    ['label' => 'Pending',  'value' => 12, 'pct' => 25, 'color' => '#E8710A'],
    ['label' => 'Rejected', 'value' => 6,  'pct' => 12, 'color' => '#D93025'],
];

// ---------------------------------------------------------------------
// Today's Stock Requests
// ---------------------------------------------------------------------
$todaysStockRequests = [
    ['time' => '08:00 AM', 'item' => 'Paracetamol 500mg',   'requestedBy' => 'Dr. Ana Reyes',       'priority' => 'High',   'status' => 'approved'],
    ['time' => '09:15 AM', 'item' => 'Amoxicillin 500mg',   'requestedBy' => 'Nurse Juan Dela Cruz','priority' => 'High',   'status' => 'in_progress'],
    ['time' => '10:00 AM', 'item' => 'Cetirizine 10mg',     'requestedBy' => 'Dr. Ana Reyes',       'priority' => 'Normal', 'status' => 'pending'],
    ['time' => '11:00 AM', 'item' => 'IV Fluids (D5W)',     'requestedBy' => 'Nurse Maria Santos',  'priority' => 'High',   'status' => 'pending'],
    ['time' => '01:00 PM', 'item' => 'Insulin Vials',       'requestedBy' => 'Dr. Ana Reyes',       'priority' => 'Normal', 'status' => 'pending'],
];
$stockRequestMeta = [
    'approved'    => ['label' => 'Approved',    'bg' => '#E4F7EA', 'color' => '#1E8E3E'],
    'in_progress' => ['label' => 'In Progress', 'bg' => '#E8F0FE', 'color' => '#1A73E8'],
    'pending'     => ['label' => 'Pending',     'bg' => '#FDECDD', 'color' => '#E8710A'],
];
$priorityMeta = [
    'High'   => ['bg' => '#FCE4E4', 'color' => '#D93025'],
    'Normal' => ['bg' => '#EEF0F3', 'color' => '#6B7280'],
];

// ---------------------------------------------------------------------
// Recent Notifications
// ---------------------------------------------------------------------
$recentNotifications = [
    ['icon' => 'bi-exclamation-triangle-fill', 'iconBg' => '#FCE4E4', 'iconColor' => '#D93025', 'title' => 'Low stock alert',           'desc' => 'Amoxicillin 500mg is running low',      'timeAgo' => '15 minutes ago'],
    ['icon' => 'bi-box-seam-fill',             'iconBg' => '#FDECDD', 'iconColor' => '#E8710A', 'title' => 'New stock request',         'desc' => 'Requested by Dr. Ana Reyes',            'timeAgo' => '45 minutes ago'],
    ['icon' => 'bi-calendar2-x-fill',          'iconBg' => '#EFE7FB', 'iconColor' => '#7C3AED', 'title' => 'Medicine expiring soon',     'desc' => 'Cetirizine 10mg — Batch CT10-0225',     'timeAgo' => '2 hours ago'],
    ['icon' => 'bi-check-circle-fill',         'iconBg' => '#E4F7EA', 'iconColor' => '#1E8E3E', 'title' => 'Stock request approved',    'desc' => 'REQ-2026-00031 approved by Sub-Admin',  'timeAgo' => '3 hours ago'],
    ['icon' => 'bi-capsule',                   'iconBg' => '#E8F0FE', 'iconColor' => '#1A73E8', 'title' => 'Prescription dispensed',    'desc' => 'Juan Dela Cruz — Amoxicillin 500mg',    'timeAgo' => '4 hours ago'],
];

// ---------------------------------------------------------------------
// Low Stock Medicines
// ---------------------------------------------------------------------
$lowStockMedicines = [
    ['id' => 'MED-000112', 'name' => 'Amoxicillin 500mg',  'category' => 'Antibiotic',    'currentStock' => 18,  'reorderLevel' => 50,  'expiryDate' => 'Jun 15, 2026'],
    ['id' => 'MED-000098', 'name' => 'Cetirizine 10mg',     'category' => 'Antihistamine', 'currentStock' => 12,  'reorderLevel' => 40,  'expiryDate' => 'Jun 20, 2026'],
    ['id' => 'MED-000145', 'name' => 'Metformin 500mg',     'category' => 'Antidiabetic',  'currentStock' => 22,  'reorderLevel' => 60,  'expiryDate' => 'Jul 2, 2026'],
    ['id' => 'MED-000076', 'name' => 'Salbutamol Inhaler',  'category' => 'Bronchodilator','currentStock' => 5,   'reorderLevel' => 20,  'expiryDate' => 'Jul 8, 2026'],
    ['id' => 'MED-000203', 'name' => 'Insulin Vials',       'category' => 'Antidiabetic',  'currentStock' => 8,   'reorderLevel' => 25,  'expiryDate' => 'May 30, 2026'],
];
