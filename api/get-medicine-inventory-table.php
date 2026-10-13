<?php
/**
 * =====================================================================
 * Get Medicine Inventory Table (api/get-medicine-inventory-table.php)
 * ---------------------------------------------------------------------
 * Called by assets/js/mvi-ajax-pagination.js whenever a Previous/
 * page-number/Next link is clicked on subadmin/medicine_vaccine_inventory.php —
 * returns just that tab's table+pagination HTML fragment (same markup
 * includes/subadmin/_mvi_medicines_table.php / _mvi_vaccines_table.php
 * render on a normal full page load) so only the table swaps, instead
 * of a full page reload.
 *
 * ?tab=medicines|vaccines selects which partial to render; every other
 * querystring param (q/category/status/page or vq/vcategory/vstatus/vpage)
 * is read exactly the same way the full page reads it — this file just
 * runs the SAME data file subadmin/medicine_vaccine_inventory.php does,
 * then echoes one fragment instead of the whole page.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/constants.php'; // BASE_URL, asset_url()

if (($_SESSION['role'] ?? null) !== 'subadmin') {
    http_response_code(403);
    exit('Not authorized.');
}

require_once __DIR__ . '/../includes/subadmin/medicine_vaccine_inventory_firestore.php';
// $medicines, $medicinesCurrentPage, $medicinesTotalPages, $medicinePaginationWindow, ...
// $vaccines, $vaccinesCurrentPage, $vaccinesTotalPages, $vaccinePaginationWindow, ...

$tab = $_GET['tab'] ?? 'medicines';

if ($tab === 'vaccines') {
    require __DIR__ . '/../includes/subadmin/_mvi_vaccines_table.php';
} else {
    require __DIR__ . '/../includes/subadmin/_mvi_medicines_table.php';
}
