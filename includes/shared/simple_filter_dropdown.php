<?php
/**
 * =====================================================================
 * Simple Filter Dropdown (partial)
 * ---------------------------------------------------------------------
 * Renders one exact-match filter dropdown (category/status/gender/age
 * group/barangay/form/etc.) shared by all 5 subadmin report pages
 * (Patient/Inventory/Stock Request/Consultation/Vaccine Reports) —
 * these all already share the same options-array shape (a plain list of
 * labels where index 0 is the "All ..." / no-filter choice and the
 * filter's real value IS the label text itself, e.g. "Female",
 * "Tablet", "Balintawak"), so one partial replaces 5 near-identical
 * foreach blocks.
 *
 * Include with these set beforehand:
 *   $sfdOptions  — array of labels, index 0 = "All ..." (no filter)
 *   $sfdParam    — query param name this dropdown reads/writes
 *   $sfdCurrent  — currently selected value ('' = "All ...")
 *   $sfdBtnClass — the page's own filter-button CSS class
 *                  ("prp-filter-select" on Patient Reports,
 *                  "arp-filter-select" on the other 4)
 * Requires includes/shared/query_helpers.php (hc_url_with_params()) to
 * already be loaded.
 * =====================================================================
 */
?>
<div class="dropdown">
    <button class="btn btn-light border <?= htmlspecialchars($sfdBtnClass) ?> dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
        <?= htmlspecialchars($sfdCurrent !== '' ? $sfdCurrent : $sfdOptions[0]) ?>
    </button>
    <ul class="dropdown-menu">
        <?php foreach ($sfdOptions as $i => $opt): $val = $i === 0 ? '' : $opt; ?>
        <li><a class="dropdown-item <?= $sfdCurrent === $val ? 'active' : '' ?>" href="<?= htmlspecialchars(hc_url_with_params([$sfdParam => $val])) ?>"><?= htmlspecialchars($opt) ?></a></li>
        <?php endforeach; ?>
    </ul>
</div>
