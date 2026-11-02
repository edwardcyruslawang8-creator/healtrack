<?php
/**
 * =====================================================================
 * Dispensing-unit profiles for the demo inventory
 * (scripts/inventory-unit-profiles.php)
 * ---------------------------------------------------------------------
 * Shared by scripts/migrate-dispensing-units.php (converts the live
 * medicines/medicine_batches docs) and scripts/seed-inventory-demo-data.php
 * (so a re-seed produces the same unit model). Not loaded by the app.
 *
 * Medicines.xlsx records almost everything in "boxes". The app counts
 * stock in each item's DISPENSING unit (includes/firestore/medicines.php's
 * hc_box_breakdown() comment), decided here from the dosage form in the
 * item's name:
 *   - tablets/capsules in boxes  -> individual Tablet/Capsule, with
 *     piecesPerBox (stock multiplied by it)
 *   - syrups/suspensions/drops    -> Bottle; a spreadsheet "box" of a
 *     liquid is one boxed bottle, so quantities are unchanged and
 *     nothing is converted into pieces
 *   - nebules / ORS sachets       -> Nebule / Sachet, with piecesPerBox
 *   - inhalers, vials, doses      -> unchanged
 * Pieces-per-box figures are SAMPLE values (common pack sizes), editable
 * per item afterwards via Edit Medicine's "Pieces per Box" field.
 * =====================================================================
 */

if (!function_exists('hc_demo_unit_profile')) {
    /**
     * @return array{unit: string, piecesPerBox: int, factor: int} factor =
     *         what an existing quantity in $currentUnit is multiplied by.
     */
    function hc_demo_unit_profile(string $name, string $currentUnit): array
    {
        $n = strtolower($name);

        if (in_array($currentUnit, ['Inhaler', 'Vial', 'Dose', 'Bottle', 'Sachet', 'Nebule'], true)) {
            return ['unit' => $currentUnit, 'piecesPerBox' => 0, 'factor' => 1];
        }
        if (preg_match('/syrup|suspension|drop/', $n)) {
            return ['unit' => 'Bottle', 'piecesPerBox' => 0, 'factor' => 1];
        }
        if (str_contains($n, 'nebule')) {
            return ['unit' => 'Nebule', 'piecesPerBox' => 20, 'factor' => $currentUnit === 'Box' ? 20 : 1];
        }
        if (str_contains($n, 'oral rehydration')) {
            return ['unit' => 'Sachet', 'piecesPerBox' => 25, 'factor' => $currentUnit === 'Box' ? 25 : 1];
        }

        $unit = in_array($currentUnit, ['Tablet', 'Capsule'], true)
            ? $currentUnit
            : (preg_match('/capsule|clindamycin/', $n) ? 'Capsule' : 'Tablet');
        // Maintenance medicines commonly packed in 30s; everything else 100s.
        $piecesPerBox = preg_match('/statin|sartan|montelukast|clopidogrel|fenofibrate/', $n) ? 30 : 100;

        return ['unit' => $unit, 'piecesPerBox' => $piecesPerBox, 'factor' => $currentUnit === 'Box' ? $piecesPerBox : 1];
    }
}
