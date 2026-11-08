<?php
/**
 * =====================================================================
 * Risk & Alert Indicator thresholds (config/risk_alerts.php)
 * ---------------------------------------------------------------------
 * Every threshold the automatic risk monitor
 * (includes/shared/risk_alerts.php) uses, in one place. Values below
 * are the initial capstone-testing thresholds — change them here, not
 * in the evaluator.
 * =====================================================================
 */

return [
    // All scheduled evaluations and every displayed timestamp use this.
    'timezone' => 'Asia/Manila',

    // -----------------------------------------------------------------
    // Service demand — rolling 7-day comparison of ACTUAL recorded
    // events per barangay: the most recent 7 complete days (yesterday
    // and the 6 days before it) vs. the 7 days before that.
    // Applied to consultations, laboratory requests and X-ray requests.
    // -----------------------------------------------------------------
    'demand' => [
        'window_days' => 7,
        // WATCH: increase >= 50% AND at least 3 more than the prior window.
        'watch_min_pct' => 50,
        'watch_min_increase' => 3,
        // ALERT: increase >= 100% AND at least 10 in the current window.
        'alert_min_pct' => 100,
        'alert_min_current' => 10,
        // Prior window below this = no real baseline to compute a % from.
        // A barangay with no baseline is only flagged when the current
        // window alone reaches alert_min_current (a genuine surge), never
        // on a tiny count like 0 -> 3.
        'min_baseline' => 1,
        // The facility needs real records older than both windows
        // (14 days) before any demand alert can be raised at all —
        // prevents false alerts while a facility is still new to the system.
        'require_full_history' => true,
        // Consecutive normal DAILY evaluations before auto-resolution.
        'resolve_after_normal_evaluations' => 2,
    ],

    // Forecast-based (projected, NOT actual) consultation demand —
    // the existing linear-regression projection on the Consultation
    // Trends tab. Only ever raised as WATCH, labeled "Forecast".
    'forecast' => [
        'watch_min_growth_pct' => 20,
        'resolve_after_normal_evaluations' => 2,
    ],

    // -----------------------------------------------------------------
    // Notifiable disease clusters (barangay + disease category). Uses
    // the existing 14-day case tiers in hc_disease_demand_forecast()
    // (Watch >= 2, Elevated >= 4, Critical >= 6 and still rising) for
    // DETECTION. Resolution is stricter than demand alerts:
    //   - "normal" only when the pair is below every tier AND has had
    //     no new case for quiet_days days, and
    //   - that must hold for N consecutive daily evaluations, with a
    //     longer N if the alert ever reached CRITICAL.
    // -----------------------------------------------------------------
    'disease' => [
        'quiet_days' => 7,
        'resolve_after_normal_evaluations' => 3,
        'resolve_after_normal_evaluations_critical' => 7,
    ],

    // -----------------------------------------------------------------
    // Inventory (medicine/vaccine shortage + replenishment gaps).
    // Resolves as soon as the inventory condition is corrected.
    // -----------------------------------------------------------------
    'inventory' => [
        'resolve_after_normal_evaluations' => 1,
    ],

    // Alert History modal page size.
    'history_page_size' => 10,
];
