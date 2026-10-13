<?php
/**
 * =====================================================================
 * Date Range Filter
 * ---------------------------------------------------------------------
 * Backs the "Today / This Week / This Month / Custom Range" dropdown
 * that Patient/Inventory/Stock Request/Consultation/Vaccine Reports all
 * share (identical markup, "?dateFilter=..." query params standardized
 * across all 5 so includes/shared/date_range_filter_dropdown.php can be
 * one shared partial rather than 5 near-identical copies). Each page
 * applies hc_date_in_range() against its OWN real date field
 * (dateRegistered for patients, requestedAt for stock requests, dateTime
 * for consultations, lastUpdated for inventory) — see each page's own
 * -firestore.php header comment for which field it filters.
 * =====================================================================
 */

if (!function_exists('hc_resolve_date_range_filter')) {
    function hc_resolve_date_range_filter(
        string $optionParam = 'dateFilter',
        string $fromParam = 'dateFrom',
        string $toParam = 'dateTo'
    ): array {
        $option = $_GET[$optionParam] ?? 'all';
        if (!in_array($option, ['all', 'today', 'week', 'month', 'custom'], true)) {
            $option = 'all';
        }

        $now = new DateTimeImmutable('now');
        $from = null;
        $to = null;
        $label = 'All Time';
        $fromRaw = '';
        $toRaw = '';

        switch ($option) {
            case 'today':
                $from = $now->setTime(0, 0);
                $to = $now->setTime(23, 59, 59);
                $label = 'Today';
                break;
            case 'week':
                $from = $now->modify('monday this week')->setTime(0, 0);
                $to = $now->setTime(23, 59, 59);
                $label = 'This Week';
                break;
            case 'month':
                $from = $now->modify('first day of this month')->setTime(0, 0);
                $to = $now->setTime(23, 59, 59);
                $label = 'This Month';
                break;
            case 'custom':
                $fromRaw = (string) ($_GET[$fromParam] ?? '');
                $toRaw = (string) ($_GET[$toParam] ?? '');
                $fromDt = DateTimeImmutable::createFromFormat('Y-m-d', $fromRaw);
                $toDt = DateTimeImmutable::createFromFormat('Y-m-d', $toRaw);
                if ($fromDt !== false && $toDt !== false && $fromDt <= $toDt) {
                    $from = $fromDt->setTime(0, 0);
                    $to = $toDt->setTime(23, 59, 59);
                    $label = $fromDt->format('M j, Y') . ' - ' . $toDt->format('M j, Y');
                } else {
                    // No (or invalid) custom dates chosen yet — falls back
                    // to "All Time" rather than silently filtering nothing
                    // in with a broken range.
                    $option = 'all';
                    $label = 'All Time';
                }
                break;
        }

        return [
            'option' => $option,
            'from' => $from,
            'to' => $to,
            'label' => $label,
            'fromRaw' => $fromRaw,
            'toRaw' => $toRaw,
            'optionParam' => $optionParam,
            'fromParam' => $fromParam,
            'toParam' => $toParam,
        ];
    }
}

if (!function_exists('hc_date_in_range')) {
    /** @param array $bounds Result of hc_resolve_date_range_filter(). */
    function hc_date_in_range(?string $rawDate, array $bounds): bool
    {
        if ($bounds['from'] === null || $bounds['to'] === null) {
            return true; // 'all'
        }
        if (!$rawDate) {
            return false;
        }
        $dt = DateTimeImmutable::createFromFormat('Y-m-d', substr($rawDate, 0, 10));
        if ($dt === false) {
            return false;
        }
        return $dt >= $bounds['from'] && $dt <= $bounds['to'];
    }
}
