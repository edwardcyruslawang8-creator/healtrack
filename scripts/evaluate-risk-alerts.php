<?php
/**
 * =====================================================================
 * Daily Risk & Alert Evaluation (scripts/evaluate-risk-alerts.php)
 * ---------------------------------------------------------------------
 * CLI-only. Runs the automatic risk monitor
 * (includes/shared/risk_alerts.php) for EVERY facility, plus the
 * system-wide inventory indicators, so alerts are created, updated and
 * auto-resolved even when nobody has the dashboard open.
 *
 * Schedule it DAILY at 12:00 AM Philippine Standard Time — same
 * Windows Task Scheduler mechanism as scripts/send-followup-reminders.php
 * (the server's clock is Asia/Manila):
 *
 *   schtasks /Create /TN "HEALTrack Risk Alerts" /SC DAILY /ST 00:00 ^
 *     /TR "C:\xampp\php\php.exe C:\xampp\htdocs\Healtrack\scripts\evaluate-risk-alerts.php" /F
 *
 * Linux cron equivalent:
 *   CRON_TZ=Asia/Manila
 *   0 0 * * * php /path/to/Healtrack/scripts/evaluate-risk-alerts.php
 *
 * The evaluation date is always computed in Asia/Manila regardless of
 * the server's own timezone. Safe to rerun: a rerun for the same date
 * recomputes from that date's starting state, never double-counts a
 * normal day, and never creates a duplicate alert. If the job missed
 * midnight, the first dashboard load of the day runs the same
 * evaluation for that facility.
 *
 * Usage:
 *   C:\xampp\php\php.exe scripts\evaluate-risk-alerts.php
 *   C:\xampp\php\php.exe scripts\evaluate-risk-alerts.php --date=2026-11-07   (testing: evaluate as of a given day)
 * =====================================================================
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script is CLI-only.');
}

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/firebase.php'; // $firestore
require_once __DIR__ . '/../includes/firestore/medicines.php';
require_once __DIR__ . '/../includes/firestore/pharmacy_orders.php';
require_once __DIR__ . '/../includes/firestore/stock_requests.php';
require_once __DIR__ . '/../includes/shared/forecast_helpers.php';
require_once __DIR__ . '/../includes/shared/risk_alerts.php';

date_default_timezone_set(hc_risk_config()['timezone']);

$evalDate = hc_risk_today();
foreach ($argv as $arg) {
    if (preg_match('/^--date=(\d{4}-\d{2}-\d{2})$/', $arg, $m)) {
        $evalDate = $m[1];
    }
}

$log = fn (string $msg) => print('[' . hc_risk_now() . '] ' . $msg . PHP_EOL);
$log("Risk evaluation for {$evalDate} (Asia/Manila) started.");

$facilityDocs = $firestore->listDocuments('facilities');
if ($firestore->lastRequestFailed) {
    $log('Could not read facilities — aborting so no partial evaluation is written.');
    exit(1);
}

// System-wide inventory (medicines/vaccines have no facility field).
$inventory = array_merge(
    array_map(fn ($m) => $m + ['itemType' => 'medicine'], hc_get_inventory_items('medicine')),
    array_map(fn ($v) => $v + ['itemType' => 'vaccine'], hc_get_inventory_items('vaccine'))
);
$pt2 = hc_medicine_vaccine_shortage($inventory, hc_get_all_pharmacy_order_docs(), $firestore->listDocuments('medications'));
$s = hc_risk_evaluate(HC_RISK_SYSTEM_SCOPE, hc_risk_observe_inventory($pt2), ['stock_shortage'], $evalDate);
$log("Inventory (system-wide): created {$s['created']}, updated {$s['updated']}, resolved {$s['resolved']}, failed writes {$s['failed']}.");

$allStockRequests = hc_get_all_stock_requests();
$exit = 0;
foreach ($facilityDocs as $doc) {
    $name = trim((string) ($doc['fields']['name'] ?? ''));
    if ($name === '') {
        continue;
    }

    $d = hc_risk_run_facility_daily($name, $evalDate);

    $pt3 = hc_inventory_replenishment($pt2['items'], array_values(array_filter($allStockRequests, fn ($r) => $r['facility'] === $name)));
    $r = hc_risk_evaluate($name, hc_risk_observe_replenishment($pt3), ['replenishment_gap'], $evalDate);

    $log("{$name}: daily — created {$d['created']}, updated {$d['updated']}, resolved {$d['resolved']}; replenishment — created {$r['created']}, updated {$r['updated']}, resolved {$r['resolved']}.");
    if ($d['failed'] + $r['failed'] > 0) {
        $log("{$name}: " . ($d['failed'] + $r['failed']) . ' write(s)/read(s) failed — rerun this script; it is safe to rerun.');
        $exit = 1;
    }
}

$log('Done.');
exit($exit);
