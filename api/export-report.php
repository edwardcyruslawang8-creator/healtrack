<?php
/**
 * =====================================================================
 * Export Report — JSON payload for PDF / Excel
 * ---------------------------------------------------------------------
 * GET ?report=<key>&format=pdf|excel&<the report page's own filters>.
 * Returns the report payload (includes/shared/report_export.php) that
 * assets/js/report-export.js turns into a PDF (jsPDF + AutoTable) or an
 * .xlsx workbook (SheetJS) in the browser. Role checks and the audit
 * log entry live in includes/shared/report_export_request.php.
 * =====================================================================
 */
session_start();

require_once __DIR__ . '/../config/constants.php';
require __DIR__ . '/../includes/shared/report_export_request.php'; // $rxPayload

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
echo json_encode(['success' => true, 'report' => $rxPayload]);
