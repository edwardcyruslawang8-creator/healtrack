<?php
/**
 * =====================================================================
 * Report Export — request bootstrap
 * ---------------------------------------------------------------------
 * Shared by api/export-report.php (JSON for PDF/Excel) and
 * api/print-report.php (printable page). Must be required at FILE
 * scope: it requires the report's own data file(s), whose variables
 * have to land in the global scope for get_defined_vars() — see
 * includes/shared/report_export.php.
 *
 * Checks the role may export ?report= (403 otherwise), builds
 * $rxPayload from the request's own filter params, and writes one audit
 * log entry.
 * =====================================================================
 */

require_once __DIR__ . '/report_export.php';
require_once __DIR__ . '/audit_log_writer.php'; // write_audit_log()

$rxReport = (string) ($_GET['report'] ?? '');
$rxFormat = in_array($_GET['format'] ?? '', ['print', 'pdf', 'excel'], true) ? $_GET['format'] : 'print';
$rxEntry = hc_report_export_entry($rxReport, $_SESSION['role'] ?? null);
if ($rxEntry === null) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    exit('You are not authorized to export this report.');
}

foreach ($rxEntry['files'] as $rxFile) {
    require_once $rxFile;
}
$rxPayload = hc_report_export_build($rxReport, get_defined_vars());

$rxRowCount = array_sum(array_map(fn ($t) => count($t['rows']), $rxPayload['tables']));
write_audit_log(
    'viewed',
    'Analytics & Reports',
    ['print' => 'Printed', 'pdf' => 'Exported to PDF', 'excel' => 'Exported to Excel'][$rxFormat] . ' the ' . $rxPayload['title']
        . ' (' . implode('; ', array_map(fn ($f) => $f[0] . ': ' . $f[1], $rxPayload['filters'])) . '; ' . number_format($rxRowCount) . ' table rows).'
);
