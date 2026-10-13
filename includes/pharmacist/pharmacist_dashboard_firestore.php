<?php
/**
 * =====================================================================
 * Pharmacist Dashboard — Data
 * ---------------------------------------------------------------------
 * Static stand-ins for pharmacist/dashboard.php, following the same
 * per-page "-data.php" convention as admin/, subadmin/, and
 * medical-staff/. Modeled directly on
 * includes/medical_staff_dashboard_firestore.php's shape (same stat-card /
 * trend-chart / doughnut / schedule-table / notification-list
 * structure), with every section reframed around pharmacy operations
 * — dispensing, medicine stock, and stock requests — instead of
 * consultations and patients.
 *
 * FRONT-END ONLY for the pharmacy numbers — there's no
 * medicines/dispensing/stock_requests collection scoped to a single
 * pharmacist anywhere in this app yet (subadmin/medicine_vaccine_inventory.php
 * and subadmin/stock_requests.php are facility-wide Sub-Admin views,
 * not a per-pharmacist model). $staffProfile IS real though — read
 * from the signed-in pharmacist's own Firestore users/{uid} document,
 * same as includes/medical_staff_dashboard_firestore.php.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore

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
// Dispensing Trend — line chart (default range: This Week)
// ---------------------------------------------------------------------
$dispensingTrendRange = 'This Week';
$dispensingTrend = [
    'labels' => ['May 14', 'May 15', 'May 16', 'May 17', 'May 18', 'May 19', 'May 20'],
    'values' => [28, 35, 22, 40, 31, 26, 42],
];

// ---------------------------------------------------------------------
// Medicines by Category — bar chart (default range: This Month)
// ---------------------------------------------------------------------
$medicinesByCategoryRange = 'This Month';
$medicinesByCategory = [
    'labels' => ['Antibiotics', 'Analgesics', 'Antihistamines', 'Vitamins', 'Antihypertensives', 'Others'],
    'values' => [72, 95, 48, 60, 54, 57],
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
