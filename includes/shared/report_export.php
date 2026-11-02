<?php
/**
 * =====================================================================
 * Report Export — shared payload builder (requirement 6.5.3)
 * ---------------------------------------------------------------------
 * One payload per Analytics & Reports page, built from the SAME data
 * file the page itself renders from (includes/subadmin/*_reports_firestore.php,
 * includes/{subadmin,admin}/medical_history_*_firestore.php). Those
 * files read the page's own filters straight from $_GET, so as long as
 * the caller passes the page's current query string through, the
 * export holds exactly the filtered data on screen, never a re-derived
 * copy that could drift.
 *
 * Payload shape (consumed by subadmin/report_print.php for Print and
 * by assets/js/report-export.js for PDF/Excel via
 * api/export-report.php):
 *   facility, title, generatedAt, generatedBy, filename,
 *   filters  — [[label, value], ...]
 *   summary  — [[label, value, sub], ...]
 *   tables   — [['title', 'columns', 'rows', 'footer' (optional row), 'note' (optional)], ...]
 *   notes    — [string, ...]
 *
 * Paginated on-screen tables are exported in full (every filtered row,
 * not just the visible page). Placeholder figures are never exported.
 *
 * Usage, at FILE scope (the data files define globals):
 *   $rxEntry = hc_report_export_entry($report, $role);   // null = not allowed
 *   foreach ($rxEntry['files'] as $rxFile) { require_once $rxFile; }
 *   $rxPayload = hc_report_export_build($report, get_defined_vars());
 * =====================================================================
 */

if (!function_exists('hc_report_export_registry')) {
    function hc_report_export_registry(): array
    {
        $sub = __DIR__ . '/../subadmin/';

        return [
            'patient'         => ['title' => 'Patient Report',                     'roles' => ['subadmin' => [$sub . 'patient_reports_firestore.php']]],
            'medical_history' => ['title' => 'Medical History Summary',            'roles' => [
                'subadmin' => [$sub . 'medical_history_reports_firestore.php'],
                'admin' => [__DIR__ . '/../admin/medical_history_summary_firestore.php'],
            ]],
            'consultation'    => ['title' => 'Consultation Report',                'roles' => ['subadmin' => [$sub . 'consultation_reports_firestore.php']]],
            'vaccine'         => ['title' => 'Vaccine Report',                     'roles' => ['subadmin' => [$sub . 'vaccine_reports_firestore.php']]],
            'inventory'       => ['title' => 'Inventory Report',                   'roles' => ['subadmin' => [$sub . 'inventory_reports_firestore.php']]],
            'lab_xray'        => ['title' => 'Laboratory & X-ray Report',          'roles' => ['subadmin' => [$sub . 'lab_xray_reports_firestore.php']]],
            'stock_request'   => ['title' => 'Stock Request Report',               'roles' => ['subadmin' => [$sub . 'stock_request_reports_firestore.php']]],
        ];
    }
}

if (!function_exists('hc_report_export_entry')) {
    /** ['title', 'files'] for $report as $role, or null when that role may not export it. */
    function hc_report_export_entry(string $report, ?string $role): ?array
    {
        $entry = hc_report_export_registry()[$report] ?? null;
        if ($entry === null || $role === null || !isset($entry['roles'][$role])) {
            return null;
        }

        return ['title' => $entry['title'], 'files' => $entry['roles'][$role]];
    }
}

if (!function_exists('hc_rx_n')) {
    function hc_rx_n($n): string
    {
        return number_format((float) $n, is_float($n) && floor($n) != $n ? 1 : 0);
    }

    function hc_rx_pct($p): string
    {
        return rtrim(rtrim(number_format((float) $p, 1), '0'), '.') . '%';
    }

    /** "All ..." when a filter is unset. */
    function hc_rx_filter(string $value, string $all): string
    {
        return $value !== '' ? $value : $all;
    }

    function hc_rx_cards(array $cards): array
    {
        return array_map(function ($c) {
            $value = $c['value'];
            if (is_numeric($value)) {
                $value = hc_rx_n($value) . (($c['valueSuffix'] ?? '') === '%' ? '%' : ($c['valueSuffix'] ?? ''));
            }

            return [$c['label'], (string) $value, (string) ($c['sub'] ?? '')];
        }, $cards);
    }

    /** A [label, count, %] breakdown table from rows with label/value(/pct). */
    function hc_rx_breakdown(string $title, string $labelHeader, array $rows, string $countHeader = 'Count'): array
    {
        $total = array_sum(array_column($rows, 'value'));

        return [
            'title' => $title,
            'columns' => [$labelHeader, $countHeader, '% of Total'],
            'rows' => array_map(fn ($r) => [$r['label'], hc_rx_n($r['value']), hc_rx_pct($r['pct'] ?? ($total > 0 ? $r['value'] / $total * 100 : 0))], $rows),
            'footer' => $rows !== [] ? ['Total', hc_rx_n($total), $total > 0 ? '100%' : '0%'] : null,
        ];
    }
}

if (!function_exists('hc_report_export_build')) {
    function hc_report_export_build(string $report, array $v): array
    {
        $title = hc_report_export_registry()[$report]['title'];
        // $mhFacilityName first: the admin data file also loads
        // facility_management_firestore.php, whose loop leaves an
        // unrelated global $facilityName behind.
        $facility = $v['mhFacilityName'] ?? $v['facilityName'] ?? ($v['adminProfile']['facility'] ?? '');

        $body = match ($report) {
            'patient' => hc_rx_patient($v),
            'medical_history' => hc_rx_medical_history($v),
            'consultation' => hc_rx_consultation($v),
            'vaccine' => hc_rx_vaccine($v),
            'inventory' => hc_rx_inventory($v),
            'lab_xray' => hc_rx_lab_xray($v),
            'stock_request' => hc_rx_stock_request($v),
        };

        return [
            'report' => $report,
            'facility' => $facility !== '' ? $facility : 'HEALTrack',
            'title' => $title,
            'generatedAt' => date('F j, Y g:i A'),
            'generatedBy' => trim(($_SESSION['name'] ?? '') . ' (' . match ($_SESSION['role'] ?? '') {
                'subadmin' => 'Sub-Admin',
                'admin' => 'Admin',
                default => 'Staff',
            } . ')'),
            'filename' => preg_replace('/[^a-z0-9]+/', '-', strtolower($title)) . '_' . date('Y-m-d'),
            'filters' => $body['filters'],
            'summary' => $body['summary'],
            'tables' => $body['tables'],
            'notes' => $body['notes'] ?? [],
        ];
    }
}

// ---------------------------------------------------------------------
// Per-report builders. Each reads only variables its data file defines.
// ---------------------------------------------------------------------

if (!function_exists('hc_rx_patient')) {
    function hc_rx_patient(array $v): array
    {
        $patients = $v['allReportPatients'];
        usort($patients, fn ($a, $b) => strcmp($b['dateRegistered'] ?? '', $a['dateRegistered'] ?? ''));

        $barangayRows = [];
        $total = count($patients);
        foreach ($v['barangayCounts'] as $label => $count) {
            $barangayRows[] = ['label' => $label, 'value' => $count, 'pct' => $total > 0 ? $count / $total * 100 : 0];
        }
        $diagnosisRows = [];
        $diagnosisTotal = array_sum($v['prDiagnosisTallies']);
        foreach ($v['prDiagnosisTallies'] as $label => $count) {
            $diagnosisRows[] = ['label' => $label, 'value' => $count, 'pct' => $diagnosisTotal > 0 ? $count / $diagnosisTotal * 100 : 0];
        }

        return [
            'filters' => [
                ['Date Registered', $v['prDateFilter']['label']],
                ['Age Group', hc_rx_filter($v['prAgeGroupFilter'], 'All Age Groups')],
                ['Gender', hc_rx_filter($v['prGenderFilter'], 'All Gender')],
                ['Barangay', hc_rx_filter($v['prBarangayFilter'], 'All Barangays')],
            ],
            'summary' => hc_rx_cards($v['patientStatCards']),
            'tables' => [
                hc_rx_breakdown('Patients by Age Group', 'Age Group', $v['patientsByAgeGroup'], 'Patients'),
                hc_rx_breakdown('Patients by Gender', 'Gender', $v['patientsByGenderReport'], 'Patients'),
                hc_rx_breakdown('Patients by Barangay', 'Barangay', $barangayRows, 'Patients'),
                hc_rx_breakdown('Diagnoses (All Patients)', 'Diagnosis', $diagnosisRows, 'Records'),
                [
                    'title' => 'Patient List',
                    'columns' => ['Patient Name', 'Age', 'Gender', 'Barangay', 'Date Registered'],
                    'rows' => array_map(fn ($p) => [
                        trim((string) ($p['name'] ?? trim(($p['firstName'] ?? '') . ' ' . ($p['lastName'] ?? '')))),
                        (string) (int) ($p['age'] ?? 0),
                        (string) ($p['gender'] ?? '—'),
                        (string) ($p['barangay'] ?? '—'),
                        !empty($p['dateRegistered']) ? date('M j, Y', strtotime($p['dateRegistered'])) : '—',
                    ], $patients),
                ],
            ],
        ];
    }
}

if (!function_exists('hc_rx_medical_history')) {
    function hc_rx_medical_history(array $v): array
    {
        $mh = $v['mh'];
        $tables = [];
        foreach ($mh['visible'] as $key) {
            $cat = $mh['categories'][$key];
            $columns = [
                ['conditions' => 'Chronic Condition', 'allergies' => 'Allergy', 'surgeries' => 'Surgery / Procedure', 'family' => 'Condition (Family)'][$key],
                'Patients',
                '% of Patients',
            ];
            if ($key === 'family') {
                $columns[] = 'Relations';
            }
            $tables[] = [
                'title' => $key === 'family' ? 'Family Medical History Patterns' : $cat['label'],
                'columns' => $columns,
                'rows' => array_map(function ($i) use ($key) {
                    $row = [$i['label'], hc_rx_n($i['patients']), hc_rx_pct($i['pct'])];
                    if ($key === 'family') {
                        $row[] = implode(', ', array_map(fn ($r, $n) => "$r ($n)", array_keys($i['relations']), $i['relations']));
                    }
                    return $row;
                }, $cat['items']),
                'note' => $cat['items'] === [] ? 'No ' . strtolower($cat['label']) . ' recorded for the patients in this report.' : null,
            ];
        }
        foreach (['conditions' => 'Medical Conditions by Barangay', 'allergies' => 'Allergies by Barangay'] as $key => $title) {
            if (!in_array($key, $mh['visible'], true)) {
                continue;
            }
            $tables[] = [
                'title' => $title,
                'columns' => ['Barangay', 'Patients', '% of Barangay', 'Most Common'],
                'rows' => array_map(fn ($r) => [$r['barangay'], hc_rx_n($r['patients']), hc_rx_pct($r['pct']), $r['top'] . ' (' . $r['topPatients'] . ')'], $mh['categories'][$key]['barangays']),
                'note' => $mh['categories'][$key]['barangays'] === [] ? 'No records for the patients in this report.' : null,
            ];
        }

        return [
            'filters' => hc_mh_filter_summary($v['mhFilters']),
            'summary' => array_map(fn ($c) => [$c['label'], (string) $c['value'], $c['sub']], $v['mhStatCards']),
            'tables' => $tables,
            'notes' => ['Counts are unique patients. A patient with several conditions or allergies is counted under each of them. Percentages are of the ' . number_format($mh['scopePatients']) . ' patients matching the filters.'],
        ];
    }
}

if (!function_exists('hc_rx_consultation')) {
    function hc_rx_consultation(array $v): array
    {
        $typeMeta = $v['consultTypeMeta'];
        $statusMeta = $v['consultStatusMeta'];

        return [
            'filters' => [
                ['Date Range', $v['crDateFilter']['label']],
                ['Consultation Type', hc_rx_filter($v['crTypeFilterLabel'], 'All Consultation Types')],
                ['Status', hc_rx_filter($v['crStatusFilterLabel'], 'All Status')],
                ['Physician (physician table)', hc_rx_filter($v['crPhysicianFilter'], 'All Physicians')],
                ['Barangay (barangay table)', hc_rx_filter($v['crBarangayFilter'], 'All Barangays')],
            ],
            'summary' => hc_rx_cards($v['consultationStatCards']),
            'tables' => [
                hc_rx_breakdown('Consultations by Type', 'Type', $v['consultationsByType'], 'Consultations'),
                hc_rx_breakdown('Consultations by Status', 'Status', $v['consultationsByStatus'], 'Consultations'),
                [
                    'title' => 'Consultations by Physician',
                    'columns' => ['Physician', 'Total', 'Completed', 'Pending', 'Cancelled / No Show', 'Completion Rate'],
                    'rows' => array_map(fn ($p) => [$p['physician'], hc_rx_n($p['total']), hc_rx_n($p['completed']), hc_rx_n($p['pending']), hc_rx_n($p['cancelled']), hc_rx_pct($p['rate'])], $v['filteredPhysicianConsultations']),
                ],
                [
                    'title' => 'Consultations by Barangay',
                    'columns' => ['Barangay', 'Consultations', '% of Total'],
                    'rows' => array_map(fn ($r) => [$r['barangay'], hc_rx_n($r['total']), hc_rx_pct($r['pct'])], $v['filteredConsultationsByBarangay']),
                ],
                [
                    'title' => 'Consultation Records',
                    'columns' => ['Code', 'Date & Time', 'Patient', 'Type', 'Physician', 'Status'],
                    'rows' => array_map(fn ($c) => [
                        $c['consultationCode'] ?: '—',
                        $c['dateTime'] !== '' ? date('M j, Y g:i A', strtotime($c['dateTime'])) : '—',
                        $c['patientName'] ?: '—',
                        $typeMeta[$c['consultationType']]['label'] ?? ucfirst(str_replace('_', ' ', $c['consultationType'])),
                        $c['assignedPersonnel'] ?: '—',
                        $statusMeta[$c['status']]['label'] ?? ucfirst($c['status']),
                    ], $v['facilityConsultations']),
                ],
            ],
            'notes' => ['The Physician and Barangay filters apply only to their own tables, as on the report page.'],
        ];
    }
}

if (!function_exists('hc_rx_vaccine')) {
    function hc_rx_vaccine(array $v): array
    {
        $t = $v['vaccineAdministrationTotal'];
        $ageTotal = array_sum(array_column($v['coverageByAgeGroup'], 'value'));

        return [
            'filters' => [
                ['Date Range', $v['vrDateFilter']['label']],
                ['Vaccine Type', hc_rx_filter($v['vrTypeFilterLabel'], 'All Vaccine Type')],
                ['Status', hc_rx_filter($v['vrStatusFilterLabel'], 'All Status')],
                ['Barangay (barangay table)', hc_rx_filter($v['vrBarangayFilter'], 'All Barangays')],
            ],
            'summary' => hc_rx_cards($v['vaccineStatCards']),
            'tables' => [
                hc_rx_breakdown('Doses Administered by Vaccine Type', 'Vaccine Type', $v['dosesByVaccineType'], 'Doses'),
                hc_rx_breakdown('Doses Administered by Age Group', 'Age Group', array_map(fn ($r) => $r + ['pct' => $ageTotal > 0 ? $r['value'] / $ageTotal * 100 : 0], $v['coverageByAgeGroup']), 'Doses'),
                [
                    'title' => 'Vaccine Administration Summary',
                    'columns' => ['Vaccine Type', 'Doses Recorded', 'Completed', 'Pending', 'Booster', 'Completion Rate'],
                    'rows' => array_map(fn ($r) => [$r['type'], hc_rx_n($r['doses']), hc_rx_n($r['fully']), hc_rx_n($r['partial']), hc_rx_n($r['booster']), hc_rx_pct($r['coverage'])], $v['filteredVaccineAdministrationSummary']),
                    'footer' => $v['filteredVaccineAdministrationSummary'] !== [] ? ['Total', hc_rx_n($t['doses']), hc_rx_n($t['fully']), hc_rx_n($t['partial']), hc_rx_n($t['booster']), hc_rx_pct($t['coverage'])] : null,
                ],
                [
                    'title' => 'Vaccination by Barangay',
                    'columns' => ['Barangay', 'Doses Administered', 'Unique Patients', 'Patients per Dose'],
                    'rows' => array_map(fn ($r) => [$r['barangay'], hc_rx_n($r['doses']), hc_rx_n($r['fully']), hc_rx_pct($r['coverage'])], $v['filteredVaccinationByBarangay']),
                ],
            ],
            'notes' => ['The Barangay filter applies only to the Vaccination by Barangay table, as on the report page.'],
        ];
    }
}

if (!function_exists('hc_rx_inventory')) {
    function hc_rx_inventory(array $v): array
    {
        // The Stock Movement card keeps its own range on the page
        // (assets/js/inventory_movement_cards.js); the export receives
        // it as movementDays / movementStart+movementEnd.
        $mStart = (string) ($_GET['movementStart'] ?? '');
        $mEnd = (string) ($_GET['movementEnd'] ?? '');
        if (!(preg_match('/^\d{4}-\d{2}-\d{2}$/', $mStart) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $mEnd) && $mStart <= $mEnd)) {
            $days = in_array((string) ($_GET['movementDays'] ?? ''), ['7', '30', '90'], true) ? (int) $_GET['movementDays'] : 30;
            $mStart = date('Y-m-d', strtotime("-$days days"));
            $mEnd = date('Y-m-d');
        }
        $movement = hc_compute_stock_movement_summary($mStart, $mEnd);

        $statusLabels = ['available' => 'In Stock', 'low' => 'Low Stock', 'unavailable' => 'Out of Stock', 'overstocked' => 'Overstocked'];
        $expiryLabels = ['expired' => 'Expired', 'today' => 'Expires Today', 'expiring_soon' => 'Expiring Soon', 'ok' => 'OK', 'no_expiry' => 'No Expiry'];
        $items = $v['allInventoryItems'];
        usort($items, fn ($a, $b) => strcasecmp($a['name'], $b['name']));
        $expiring = array_values(array_filter($v['irExpiryAllItems'], fn ($r) => in_array($r['status'], ['expired', 'today', 'expiring_soon'], true)));

        return [
            'filters' => [
                ['Last Updated', $v['irDateFilter']['label']],
                ['Category', hc_rx_filter($v['irCategoryFilter'], 'All Categories')],
                ['Form', hc_rx_filter($v['irFormFilter'], 'All Forms')],
                ['Status', hc_rx_filter($v['irStatusFilter'], 'All Status')],
                ['Stock Movement Period', date('M j, Y', strtotime($mStart)) . ' - ' . date('M j, Y', strtotime($mEnd))],
            ],
            'summary' => hc_rx_cards($v['inventoryStatCards']),
            'tables' => [
                [
                    'title' => 'Inventory Status Overview',
                    'columns' => ['Status', 'Items', '% of Items'],
                    'rows' => array_map(fn ($r) => [$r['label'], hc_rx_n($r['value']), hc_rx_pct($r['pct'])], $v['inventoryStatusOverview']),
                    'note' => 'An item can be both low/out of stock and expiring soon, so these rows can overlap.',
                ],
                hc_rx_breakdown('Inventory by Category', 'Category', $v['inventoryByCategory'], 'Items'),
                [
                    'title' => 'Stock Movement Summary',
                    'columns' => ['Category', 'Opening Stock', 'Received', 'Issued', 'Closing Stock'],
                    'rows' => array_map(fn ($r) => [$r['category'], hc_rx_n($r['opening']), hc_rx_n($r['received']), hc_rx_n($r['issued']), hc_rx_n($r['closing'])], $movement['byCategory']),
                    'footer' => ['Total', hc_rx_n($movement['total']['opening']), hc_rx_n($movement['total']['received']), hc_rx_n($movement['total']['issued']), hc_rx_n($movement['total']['closing'])],
                ],
                [
                    'title' => 'Inventory Items',
                    'columns' => ['Item Name', 'Category', 'Type', 'Current Stock', 'Status', 'Nearest Expiry'],
                    'rows' => array_map(fn ($m) => [
                        $m['name'],
                        $m['category'],
                        $m['type'] === 'vaccine' ? 'Vaccine' : 'Medicine',
                        hc_rx_n($m['currentStock']),
                        $statusLabels[$m['status']] ?? ucfirst($m['status']),
                        $m['expiryDateRaw'] ? date('M j, Y', strtotime($m['expiryDateRaw'])) : '—',
                    ], $items),
                ],
                [
                    'title' => 'Inventory Expiry Monitor (Expired & Expiring within 30 Days)',
                    'columns' => ['Item Name', 'Category', 'Batch No.', 'Expiry Date', 'Quantity', 'Status'],
                    'rows' => array_map(fn ($r) => [$r['name'], $r['category'], $r['batchNo'], $r['expiryDate'], hc_rx_n($r['quantity']) . ' ' . $r['unit'], $expiryLabels[$r['status']] ?? $r['status']], $expiring),
                    'note' => $expiring === [] ? 'No expired or soon-to-expire batches.' : null,
                ],
            ],
            'notes' => ['The Stock Movement and Expiry Monitor sections cover all inventory; the item filters apply to the other sections, as on the report page.'],
        ];
    }
}

if (!function_exists('hc_rx_lab_xray')) {
    function hc_rx_lab_xray(array $v): array
    {
        $statusLabels = ['completed' => 'Completed', 'pending' => 'Pending'];
        $ranked = fn (string $title, string $header, array $rows) => [
            'title' => $title,
            'columns' => [$header, 'Requests'],
            'rows' => array_map(fn ($r) => [$r['label'], hc_rx_n($r['value'])], $rows),
        ];

        return [
            'filters' => [
                ['Date Ordered', $v['lxrDateFilter']['label']],
                ['Type', hc_rx_filter($v['lxrTypeFilterLabel'], 'All Types')],
                ['Status', hc_rx_filter($v['lxrStatusFilterLabel'], 'All Status')],
            ],
            'summary' => hc_rx_cards($v['labXrayStatCards']),
            'tables' => [
                hc_rx_breakdown('Requests by Type', 'Type', $v['requestsByType'], 'Requests'),
                [
                    'title' => 'Completion Status by Type',
                    'columns' => ['Type', 'Completed', 'Pending'],
                    'rows' => array_map(fn ($i) => [$v['statusByType']['labels'][$i], hc_rx_n($v['statusByType']['completed'][$i]), hc_rx_n($v['statusByType']['pending'][$i])], array_keys($v['statusByType']['labels'])),
                ],
                $ranked('Top 5 Laboratory Tests', 'Laboratory Test', $v['topLabTests']),
                $ranked('Top 5 X-ray Exams', 'X-ray Exam', $v['topXrayExams']),
                $ranked('Top 5 Ordering Physicians', 'Physician', $v['topOrderingPhysicians']),
                [
                    'title' => 'Lab & X-ray Requests',
                    'columns' => ['Patient', 'Type', 'Test / Exam', 'Ordered By', 'Date Ordered', 'Date Completed', 'Status'],
                    'rows' => array_map(fn ($r) => [$r['patientName'], $r['typeLabel'], $r['name'], $r['orderedBy'], $r['orderedDate'], $r['completedDate'], $statusLabels[$r['status']] ?? ucfirst($r['status'])], $v['lxrRows']),
                ],
            ],
        ];
    }
}

if (!function_exists('hc_rx_stock_request')) {
    function hc_rx_stock_request(array $v): array
    {
        $statusLabels = ['approved' => 'Approved', 'pending' => 'Pending', 'rejected' => 'Rejected', 'released' => 'Fulfilled'];
        $rows = array_map(function ($r) use ($statusLabels) {
            $items = $r['items'] ?? [];
            $byUnit = [];
            foreach ($items as $line) {
                $unit = trim((string) ($line['unit'] ?? ''));
                $byUnit[$unit] = ($byUnit[$unit] ?? 0) + (int) ($line['qty'] ?? 0);
            }
            $qty = [];
            foreach ($byUnit as $unit => $total) {
                $qty[] = number_format($total) . ($unit !== '' ? ' ' . $unit : '');
            }

            return [
                'REQ-' . strtoupper(substr($r['docId'], 0, 8)),
                $r['pharmacistName'] ?? 'Unknown',
                ($r['type'] ?? 'medicine') === 'vaccine' ? 'Vaccines' : 'Medicines',
                implode('; ', array_map(fn ($l) => ($l['name'] ?? '—') . ' (' . (int) ($l['qty'] ?? 0) . (trim((string) ($l['unit'] ?? '')) !== '' ? ' ' . $l['unit'] : '') . ')', $items)) ?: '(no items)',
                $qty !== [] ? implode(' + ', $qty) : '0',
                !empty($r['requestedAt']) ? date('M j, Y', strtotime($r['requestedAt'])) : '—',
                $statusLabels[$r['status'] ?? 'pending'] ?? ucfirst($r['status'] ?? 'pending'),
            ];
        }, $v['allSrReports']);

        return [
            'filters' => [
                ['Date Requested', $v['srDateFilter']['label']],
                ['Status', hc_rx_filter($v['srStatusFilter'], 'All Status')],
                ['Category', hc_rx_filter($v['srCategoryFilter'], 'All Categories')],
                ['Requested By', hc_rx_filter($v['srRequestedByFilter'], 'All Requested By')],
            ],
            'summary' => hc_rx_cards($v['stockRequestStatCards']),
            'tables' => [
                hc_rx_breakdown('Requests by Status', 'Status', $v['requestsByStatus'], 'Requests'),
                hc_rx_breakdown('Requests by Category', 'Category', $v['requestsByCategory'], 'Requests'),
                [
                    'title' => 'Request Fulfillment Summary',
                    'columns' => ['Category', 'Total', 'Fulfilled', 'Approved (Not Yet Released)', 'Pending', 'Rejected', 'Fulfillment Rate'],
                    'rows' => array_map(fn ($r) => [$r['category'], hc_rx_n($r['total']), hc_rx_n($r['fulfilled']), hc_rx_n($r['awaitingRelease']), hc_rx_n($r['pending']), hc_rx_n($r['rejected']), hc_rx_pct($r['rate'])], $v['fulfillmentSummary']),
                    'footer' => (fn ($t) => ['Total', hc_rx_n($t['total']), hc_rx_n($t['fulfilled']), hc_rx_n($t['awaitingRelease']), hc_rx_n($t['pending']), hc_rx_n($t['rejected']), hc_rx_pct($t['rate'])])($v['fulfillmentTotal']),
                    'note' => 'Fulfillment Rate = released requests / all requests. Partial releases are not tracked, so a released request counts as fulfilled.',
                ],
                [
                    'title' => 'Stock Requests',
                    'columns' => ['Request ID', 'Requested By', 'Category', 'Items', 'Total Quantity', 'Date Requested', 'Status'],
                    'rows' => $rows,
                ],
            ],
        ];
    }
}
