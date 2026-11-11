<?php
/**
 * Alert History modal (includes/shared/risk_alert_history_modal.php)
 * ---------------------------------------------------------------------
 * Rendered once per page, inside <main> (so assets/js/ajax-tab-nav.js's
 * <main> swap brings it along). Opened by any card's "View History"
 * button (includes/shared/_risk_alert_card.php). Rows are fetched from
 * api/get-risk-alert-history.php by assets/js/risk-alert-history.js —
 * paginated server-side, filtered by Barangay / Risk Type / Severity /
 * Status / Date Range. Shows ACTIVE and automatically RESOLVED
 * indicators; nothing is ever deleted.
 *
 * Expects: $riskHistoryFacilityId (admin: the facility being viewed;
 * subadmin: ignored server-side — always their own facility).
 */
require_once __DIR__ . '/risk_alerts.php'; // hc_risk_types(), hc_risk_barangay_id(), HC_COVERED_BARANGAYS
?>
<div class="modal fade rah-modal" id="riskHistoryModal" tabindex="-1" aria-labelledby="riskHistoryModalTitle" aria-hidden="true"
     data-facility-id="<?= htmlspecialchars((string) ($riskHistoryFacilityId ?? '')) ?>"
     data-endpoint="<?= htmlspecialchars(BASE_URL . '/api/get-risk-alert-history.php') ?>">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl modal-fullscreen-md-down">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="riskHistoryModalTitle"><i class="bi bi-clock-history"></i> Alert History<span class="rah-tab-name"></span></h5>
                    <p class="rah-sub">Risk &amp; Alert Indicators detected on this tab, including automatic resolutions. Times are Philippine Standard Time.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form class="rah-filters" autocomplete="off">
                    <label class="rah-f-barangay">
                        <span>Barangay</span>
                        <select name="barangay" class="form-select form-select-sm">
                            <option value="">All barangays</option>
                            <?php foreach (HC_COVERED_BARANGAYS as $b): ?>
                            <option value="<?= htmlspecialchars(hc_risk_barangay_id($b)) ?>"><?= htmlspecialchars($b) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        <span>Risk Type</span>
                        <select name="riskType" class="form-select form-select-sm">
                            <option value="">All risk types</option>
                            <?php foreach (hc_risk_types() as $key => $t): ?>
                            <option value="<?= htmlspecialchars($key) ?>" data-group="<?= htmlspecialchars($t['group']) ?>"><?= htmlspecialchars($t['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        <span>Severity</span>
                        <select name="severity" class="form-select form-select-sm">
                            <option value="">All severities</option>
                            <?php foreach (['CRITICAL', 'ALERT', 'ELEVATED', 'WATCH'] as $s): ?>
                            <option value="<?= $s ?>"><?= ucfirst(strtolower($s)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        <span>Status</span>
                        <select name="status" class="form-select form-select-sm">
                            <option value="">All statuses</option>
                            <option value="ACTIVE">Active</option>
                            <option value="RESOLVED">Resolved</option>
                        </select>
                    </label>
                    <label>
                        <span>Detected from</span>
                        <input aria-label="From date" type="date" data-hc-datepicker data-hc-alt-class="form-control form-control-sm" data-hc-range-start="range-risk_alert_history_modal" name="from" class="form-control form-control-sm">
                    </label>
                    <label>
                        <span>Detected to</span>
                        <input aria-label="To date" type="date" data-hc-datepicker data-hc-alt-class="form-control form-control-sm" data-hc-range-end="range-risk_alert_history_modal" name="to" class="form-control form-control-sm">
                    </label>
                    <button type="reset" class="rah-reset">Clear filters</button>
                </form>

                <div class="rah-table-wrap">
                    <table class="rah-table">
                        <thead>
                            <tr>
                                <th class="rah-col-barangay rah-c-bgy" scope="col">Barangay</th>
                                <th class="rah-c-ind" scope="col">Risk Indicator</th>
                                <th class="rah-c-sev" scope="col">Severity</th>
                                <th class="rah-c-date" scope="col">Date Detected</th>
                                <th class="rah-c-date" scope="col">Date Resolved</th>
                                <th class="rah-c-st" scope="col">Status</th>
                            </tr>
                        </thead>
                        <tbody class="rah-body">
                            <tr><td colspan="6" class="rah-state">Loading…</td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="rah-pager">
                    <span class="rah-count"></span>
                    <div class="rah-pager-btns">
                        <button type="button" class="rah-prev" disabled><i class="bi bi-chevron-left"></i> Prev</button>
                        <span class="rah-page"></span>
                        <button type="button" class="rah-next" disabled>Next <i class="bi bi-chevron-right"></i></button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
