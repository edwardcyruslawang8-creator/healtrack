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
 * other as a WHOLE word (first two words when the first is only 1–2
 * letters, e.g. "B complex"). Approximate by nature — documented here rather than presented
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

if (!function_exists('hc_parse_medicine_descriptor')) {
    /**
     * Breaks a medicine/vaccine name ("Amoxicillin 250 mg/5mL Oral
     * Suspension", "Salmeterol + Fluticasone (Seretide) 25mcg/50mcg") into
     * the parts hc_find_inventory_match() compares:
     *
     *  - tokens:    the generic-name words (form/strength/filler words
     *               removed), e.g. ['amoxicillin']
     *  - alias:     words inside parentheses — a brand or abbreviation,
     *               e.g. ['seretide'], ['mr'] — accepted as an alternative name
     *  - form:      canonical dosage form (tablet, capsule, syrup,
     *               suspension, drops, solution, inhaler, nebule, sachet,
     *               injection, cream, ointment, …), or 'liquid' when only a
     *               "Bottle" unit says it's some liquid, or '' when unknown
     *  - strengths: normalized strengths, sorted — '500mg', '75mcg',
     *               '250mg/5ml' (concentration); g→mg, µg/ug→mcg. A bare
     *               volume like "120 ml" is pack size, not strength.
     *
     * @param string $formHint the inventory item's own form/unit field ("Tablet", "Bottle", …), if any
     * @return array{tokens: string[], alias: string[], form: string, strengths: string[]}
     */
    function hc_parse_medicine_descriptor(string $text, string $formHint = ''): array
    {
        static $formWords = [
            'tablet' => 'tablet', 'tablets' => 'tablet', 'tab' => 'tablet', 'tabs' => 'tablet', 'caplet' => 'tablet', 'caplets' => 'tablet',
            'capsule' => 'capsule', 'capsules' => 'capsule', 'cap' => 'capsule', 'caps' => 'capsule',
            'syrup' => 'syrup', 'suspension' => 'suspension', 'susp' => 'suspension',
            'drop' => 'drops', 'drops' => 'drops', 'solution' => 'solution', 'elixir' => 'elixir',
            'inhaler' => 'inhaler', 'nebule' => 'nebule', 'nebules' => 'nebule',
            'sachet' => 'sachet', 'sachets' => 'sachet',
            'injection' => 'injection', 'inj' => 'injection', 'ampule' => 'injection', 'ampoule' => 'injection',
            'cream' => 'cream', 'ointment' => 'ointment', 'gel' => 'gel', 'lotion' => 'lotion',
            'suppository' => 'suppository', 'lozenge' => 'lozenge',
        ];
        // Unit/"form" field values on inventory docs that imply a form.
        static $hintForms = [
            'tablet' => 'tablet', 'capsule' => 'capsule', 'bottle' => 'liquid', 'inhaler' => 'inhaler',
            'nebule' => 'nebule', 'sachet' => 'sachet', 'ampule' => 'injection', 'tube' => '',
        ];
        // Filler words that never distinguish one medicine from another.
        static $noise = [
            'oral', 'po', 'vaccine', 'and', 'plus', 'with', 'of', 'per', 'mg', 'mcg', 'g', 'ml', 'iu',
            'acid', 'hcl', 'hydrochloride', 'sodium', 'potassium', 'sulfate', 'sulphate', 'maleate', 'besylate',
        ];

        $s = strtolower($text);

        // Strengths first (then removed from the text so their numbers
        // don't become name tokens).
        $strengths = [];
        $s = preg_replace_callback(
            '/(\d+(?:\.\d+)?)\s*(mcg|µg|ug|mg|g|iu|units?|%)(?:\s*\/\s*(\d+(?:\.\d+)?)?\s*(ml|l)\b)?/u',
            function ($m) use (&$strengths) {
                $num = (float) $m[1];
                $unit = $m[2];
                if ($unit === 'g') { $num *= 1000; $unit = 'mg'; }
                if ($unit === 'µg' || $unit === 'ug') { $unit = 'mcg'; }
                if ($unit === 'unit' || $unit === 'units') { $unit = 'iu'; }
                $strength = rtrim(rtrim(number_format($num, 3, '.', ''), '0'), '.') . $unit;
                if (!empty($m[4])) {
                    $per = ($m[3] ?? '') !== '' ? rtrim(rtrim(number_format((float) $m[3], 3, '.', ''), '0'), '.') : '1';
                    $strength .= '/' . $per . $m[4];
                }
                $strengths[] = $strength;
                return ' ';
            },
            $s
        );
        sort($strengths);

        // Parenthesized brand/abbreviation → alias tokens.
        $alias = [];
        $s = preg_replace_callback('/\(([^)]*)\)/', function ($m) use (&$alias) {
            $alias = array_merge($alias, preg_split('/[^a-z0-9]+/', $m[1], -1, PREG_SPLIT_NO_EMPTY));
            return ' ';
        }, $s);

        $form = '';
        $tokens = [];
        foreach (preg_split('/[^a-z0-9]+/', $s, -1, PREG_SPLIT_NO_EMPTY) as $w) {
            if (isset($formWords[$w])) {
                $form = $form === '' ? $formWords[$w] : $form;
                continue;
            }
            if (ctype_digit($w) || in_array($w, $noise, true)) {
                continue; // bare numbers (pack sizes like "120 ml") and filler
            }
            $tokens[] = $w;
        }
        if ($form === '') {
            $form = $hintForms[strtolower(trim($formHint))] ?? '';
        }

        $alias = array_values(array_filter($alias, fn ($w) => !in_array($w, $noise, true)));

        return ['tokens' => $tokens, 'alias' => $alias, 'form' => $form, 'strengths' => $strengths];
    }
}

if (!function_exists('hc_find_inventory_match')) {
    /**
     * The id of the ONE inventory item a prescribed/ordered item name
     * confidently refers to, or '' when there's no match or it's uncertain.
     * Used to auto-select the item in the Prescription Queue's Dispense
     * modal. Deliberately strict — a wrong auto-selection deducts the wrong
     * stock, while '' just means the pharmacist picks manually:
     *
     *  1. Name: the generic-name words must be the SAME set (or the same
     *     letters once spaces/hyphens are dropped: "Co-amoxiclav" =
     *     "Coamoxiclav"), or equal the item's parenthesized alias. A shared
     *     first letter or partial word is never enough.
     *  2. Form: when both sides state a form they must be the same (Tablet ≠
     *     Capsule, Syrup ≠ Suspension; a "Bottle" item counts as any liquid).
     *  3. Strength: when both sides state a strength it must be identical
     *     (500mg ≠ 250mg; 25mcg/50mcg ≠ 25mcg/125mcg).
     *  4. Exactly one item may survive. If several do (e.g. "Cetirizine"
     *     with no form vs. Cetirizine Tablet/Syrup/Drop), only one whose
     *     form AND strength are both confirmed on both sides is accepted;
     *     otherwise nothing is selected.
     *
     * @param array<int, array{id: string, name: string, form?: string, unit?: string}> $items
     */
    function hc_find_inventory_match(string $orderedName, array $items): string
    {
        $order = hc_parse_medicine_descriptor($orderedName);
        if ($order['tokens'] === [] && $order['alias'] === []) {
            return '';
        }
        $orderSet = $order['tokens'];
        sort($orderSet);
        $orderJoined = implode('', $order['tokens']);
        $orderAliasSet = $order['alias'];
        sort($orderAliasSet);
        $liquids = ['syrup', 'suspension', 'drops', 'solution', 'elixir'];

        $candidates = [];
        foreach ($items as $item) {
            $inv = hc_parse_medicine_descriptor((string) $item['name'], (string) ($item['form'] ?? ($item['unit'] ?? '')));
            $invSet = $inv['tokens'];
            sort($invSet);
            $invAliasSet = $inv['alias'];
            sort($invAliasSet);

            $nameMatch = ($orderSet !== [] && $orderSet === $invSet)
                || ($orderJoined !== '' && $orderJoined === implode('', $inv['tokens']))
                || ($orderSet !== [] && $invAliasSet !== [] && $orderSet === $invAliasSet)
                || ($orderAliasSet !== [] && $orderAliasSet === $invAliasSet);
            if (!$nameMatch) {
                continue;
            }

            $formKnown = $order['form'] !== '' && $inv['form'] !== '';
            if ($formKnown && $order['form'] !== $inv['form']) {
                $liquidPair = ($order['form'] === 'liquid' && in_array($inv['form'], $liquids, true))
                    || ($inv['form'] === 'liquid' && in_array($order['form'], $liquids, true));
                if (!$liquidPair) {
                    continue;
                }
            }

            $strengthKnown = $order['strengths'] !== [] && $inv['strengths'] !== [];
            if ($strengthKnown && $order['strengths'] !== $inv['strengths']) {
                continue;
            }

            $candidates[] = [
                'id' => (string) $item['id'],
                'exact' => $formKnown && $order['form'] === $inv['form'] && $strengthKnown,
            ];
        }

        if (count($candidates) === 1) {
            return $candidates[0]['id'];
        }
        $exact = array_values(array_filter($candidates, fn ($c) => $c['exact']));

        return count($exact) === 1 ? $exact[0]['id'] : '';
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
        // Each side's generic name — its first word — must appear in the
        // other as a WHOLE word/phrase. A plain substring check let "B
        // complex Tablet" (first word "b") match anything containing the
        // letter b — "ibuprofen", "budesonide" — which skewed the
        // pharmacist dashboard's dispensed-by-category chart and predictive
        // analytics' demand/shortage/pending-request checks. A first word of
        // 1–2 letters ("b", "co") is too short to identify a medicine on
        // its own, so the first TWO words are used as the phrase instead —
        // otherwise "b" would still whole-word-match "Hepatitis B".
        return hc_lead_phrase_in($a, $b) || hc_lead_phrase_in($b, $a);
    }
}

if (!function_exists('hc_lead_phrase_in')) {
    /**
     * @internal hc_item_names_match() helper — whether $source's leading
     * name phrase (first word, or first two when the first is 1–2
     * characters) occurs in $target as whole word(s). Both already
     * hc_normalize_text()-ed.
     */
    function hc_lead_phrase_in(string $source, string $target): bool
    {
        $words = explode(' ', $source);
        $phrase = $words[0];
        if (strlen($phrase) <= 2 && isset($words[1])) {
            $phrase .= ' ' . $words[1];
        }

        // Optional plural ending on the target word ("Multivitamins" ↔
        // "Multivitamin Drop"); still no match inside a longer word.
        return (bool) preg_match('/(?<![a-z0-9])' . preg_quote($phrase, '/') . '(?:e?s)?(?![a-z0-9])/', $target);
    }
}
