<?php
/**
 * =====================================================================
 * Shared — Inventory Name Matching
 * ---------------------------------------------------------------------
 * Whether a real "medicines" inventory item and a free-text prescribed/
 * ordered/diagnosis string plausibly refer to the same thing.
 * Prescribing is free text (a doctor types "Paracetamol" during a
 * consultation — see api/complete-consultation.php's Medications
 * table — never picked from the inventory list), so an exact-string
 * match would miss almost everything; hc_item_names_match() instead
 * checks whether either string's first word (the generic name, before
 * any dosage/form suffix like "500 mg Tablet") appears inside the
 * other. Approximate by nature — documented here rather than presented
 * as exact.
 *
 * hc_normalize_text() is the plain lowercase/whitespace-collapse half
 * of that, kept generic (not "item name" specific) since it's also
 * used on its own for diagnosis/chief-complaint text — see
 * includes/shared/predictive_analytics_core.php's hc_disease_demand_forecast().
 *
 * First written for Predictive Analytics' Medicine & Vaccine Shortage
 * tab (6.3.2), extracted here so api/dispense-pharmacy-order.php's
 * stock-decrement-on-dispense feature can reuse the exact same
 * matching logic instead of a second, drifting copy.
 * =====================================================================
 */

if (!function_exists('hc_normalize_text')) {
    /** Lowercased, whitespace-collapsed — for fuzzy free-text matching. */
    function hc_normalize_text(string $s): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', $s)));
    }
}

if (!function_exists('hc_item_names_match')) {
    function hc_item_names_match(string $inventoryName, string $orderedName): bool
    {
        $a = hc_normalize_text($inventoryName);
        $b = hc_normalize_text($orderedName);
        if ($a === '' || $b === '') {
            return false;
        }
        if ($a === $b) {
            return true;
        }
        $aFirst = explode(' ', $a)[0];
        $bFirst = explode(' ', $b)[0];

        return str_contains($b, $aFirst) || str_contains($a, $bFirst);
    }
}
