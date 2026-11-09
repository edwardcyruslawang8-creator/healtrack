<?php
/**
 * =====================================================================
 * Complete consultation details (shared, read-only)
 * ---------------------------------------------------------------------
 * hc_render_consultation_details($c) turns one consultation (the array
 * from includes/firestore/consultations.php's hc_map_consultation_doc())
 * into the full "Consultation Details" view shown by the Consultation
 * History tab's View Details button (doctor / medical-staff / subadmin
 * history.php — see includes/shared/_history_record_table.php and
 * assets/js/consultation-details-modal.js).
 *
 * Sections, in visit order; a section with nothing recorded is left out:
 *   Visit (date, type, priority, attending, chief complaint) · Vital Signs ·
 *   History of Present Illness · Review of Symptoms · Clinical Impression /
 *   Diagnosis · Prescription / Treatment · Medications & Vaccines ·
 *   Laboratory Tests · X-ray Examinations · Next Step & Follow-up ·
 *   Additional Notes
 *
 * Everything is escaped; the doctor's Prescription / Treatment rich text
 * keeps only basic formatting (bold/italic/underline, lists, paragraphs,
 * line breaks, http(s)/mailto links) — see hc_safe_rich_text().
 * =====================================================================
 */

require_once __DIR__ . '/lab_test_names.php'; // hc_lab_test_display_name()

if (!function_exists('hc_safe_rich_text')) {
    /** Doctor-entered rich text reduced to a safe, formatting-only subset. */
    function hc_safe_rich_text(string $html): string
    {
        if (trim(strip_tags($html)) === '') {
            return '';
        }
        $allowed = ['b' => 'strong', 'strong' => 'strong', 'i' => 'em', 'em' => 'em', 'u' => 'u', 'p' => 'p', 'div' => 'p',
            'br' => 'br', 'ul' => 'ul', 'ol' => 'ol', 'li' => 'li', 'a' => 'a'];

        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="hcRoot">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        $root = $doc->getElementById('hcRoot');
        if ($root === null) {
            return nl2br(htmlspecialchars(strip_tags($html)));
        }

        $walk = function (DOMNode $node) use (&$walk, $allowed): string {
            $out = '';
            foreach ($node->childNodes as $child) {
                if ($child instanceof DOMText) {
                    $out .= htmlspecialchars($child->nodeValue);
                    continue;
                }
                if (!$child instanceof DOMElement) {
                    continue;
                }
                $tag = strtolower($child->tagName);
                if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'template'], true)) {
                    continue;
                }
                if (!isset($allowed[$tag])) {
                    $out .= $walk($child); // unwrap unknown tags, keep their text
                    continue;
                }
                $t = $allowed[$tag];
                if ($t === 'br') {
                    $out .= '<br>';
                    continue;
                }
                $attrs = '';
                if ($t === 'a') {
                    $href = trim($child->getAttribute('href'));
                    if (preg_match('#^(https?:|mailto:)#i', $href)) {
                        $attrs = ' href="' . htmlspecialchars($href) . '" target="_blank" rel="noopener"';
                    }
                }
                if ($t === 'ol' && ctype_digit($child->getAttribute('start')) && $child->getAttribute('start') !== '1') {
                    $attrs .= ' start="' . (int) $child->getAttribute('start') . '"';
                }
                $out .= '<' . $t . $attrs . '>' . $walk($child) . '</' . $t . '>';
            }
            return $out;
        };

        return $walk($root);
    }
}

if (!function_exists('hc_render_consultation_details')) {
    function hc_render_consultation_details(array $c): string
    {
        $e = fn ($v) => htmlspecialchars((string) $v);
        $has = fn ($v) => is_string($v) ? trim($v) !== '' && trim($v) !== '—' : !empty($v);

        $typeMeta = function_exists('hc_consultation_type_meta') ? hc_consultation_type_meta() : [];
        $priorityMeta = function_exists('hc_consultation_priority_meta') ? hc_consultation_priority_meta() : [];
        $ts = strtotime((string) ($c['dateTime'] ?? ''));
        $sections = [];

        // --- Visit ----------------------------------------------------
        $visit = [
            'Date & Time' => $ts !== false ? date('M j, Y · g:i A', $ts) : '',
            'Consultation Type' => $typeMeta[$c['consultationType'] ?? '']['label'] ?? ucwords(str_replace('_', ' ', (string) ($c['consultationType'] ?? ''))),
            'Priority' => ($c['priority'] ?? '') !== '' ? ($priorityMeta[$c['priority']]['label'] ?? ucwords(str_replace('_', ' ', (string) $c['priority']))) : '',
            'Attended By' => $c['assignedPersonnel'] ?? '',
            'Chief Complaint' => $c['chiefComplaint'] ?? '',
        ];
        $rows = '';
        foreach ($visit as $label => $value) {
            if ($has($value)) {
                $rows .= '<div class="cdv-field"><dt>' . $e($label) . '</dt><dd>' . $e($value) . '</dd></div>';
            }
        }
        if ($rows !== '') {
            $sections[] = ['Visit', 'bi-calendar2-check', '<dl class="cdv-grid">' . $rows . '</dl>'];
        }

        // --- Vital Signs ----------------------------------------------
        $v = is_array($c['vitals'] ?? null) ? $c['vitals'] : [];
        $vitals = [];
        if ($has($v['temperature'] ?? '')) $vitals['Temperature'] = $v['temperature'] . ' °C';
        if ($has($v['bloodPressureSystolic'] ?? '') && $has($v['bloodPressureDiastolic'] ?? '')) $vitals['Blood Pressure'] = $v['bloodPressureSystolic'] . '/' . $v['bloodPressureDiastolic'] . ' mmHg';
        if ($has($v['heartRate'] ?? '')) $vitals['Heart Rate'] = $v['heartRate'] . ' bpm';
        if ($has($v['weight'] ?? '')) $vitals['Weight'] = $v['weight'] . ' kg';
        if ($has($v['height'] ?? '')) $vitals['Height'] = $v['height'] . ' cm';
        if ($has($v['bmi'] ?? '')) $vitals['BMI'] = $v['bmi'] . ' kg/m²';
        if ($vitals) {
            $rows = '';
            foreach ($vitals as $label => $value) {
                $rows .= '<div class="cdv-vital"><span>' . $e($label) . '</span><strong>' . $e($value) . '</strong></div>';
            }
            $sections[] = ['Vital Signs', 'bi-heart-pulse', '<div class="cdv-vitals">' . $rows . '</div>'];
        }

        // --- Free-text clinical notes ---------------------------------
        foreach ([
            ['History of Present Illness', 'bi-journal-text', 'historyOfPresentIllness'],
            ['Review of Symptoms', 'bi-list-check', 'reviewOfSymptoms'],
            ['Clinical Impression / Diagnosis', 'bi-clipboard2-pulse', 'diagnosis'],
        ] as [$title, $icon, $key]) {
            if ($has($c[$key] ?? '')) {
                $sections[] = [$title, $icon, '<p class="cdv-text">' . nl2br($e(trim((string) $c[$key]))) . '</p>'];
            }
        }

        // --- Prescription / Treatment (rich text) ---------------------
        $rx = hc_safe_rich_text((string) ($c['prescriptionHtml'] ?? ''));
        if ($rx !== '') {
            $sections[] = ['Prescription / Treatment', 'bi-prescription2', '<div class="cdv-rich">' . $rx . '</div>'];
        }

        // --- Medications & Vaccines -----------------------------------
        $meds = array_values(array_filter(is_array($c['medications'] ?? null) ? $c['medications'] : [], fn ($m) => is_array($m) && $has($m['name'] ?? '')));
        if ($meds) {
            $body = '<div class="cdv-table-wrap"><table class="cdv-table"><thead><tr><th>Medicine / Vaccine</th><th>Dosage</th><th>Qty</th><th>Frequency</th><th>Duration</th><th>Route</th><th>Instructions</th></tr></thead><tbody>';
            foreach ($meds as $m) {
                $isVaccine = ($m['type'] ?? '') === 'vaccine';
                $body .= '<tr><td><strong>' . $e($m['name']) . '</strong>' . ($isVaccine ? ' <span class="cdv-tag">Vaccine</span>' : '') . '</td>';
                foreach (['dosage', 'quantity', 'frequency', 'duration', 'route', 'instructions'] as $k) {
                    $val = $m[$k] ?? '';
                    $body .= '<td>' . ($has((string) $val) ? $e($val) : '<span class="cdv-muted">—</span>') . '</td>';
                }
                $body .= '</tr>';
            }
            $body .= '</tbody></table></div>';
            $sections[] = ['Medications & Vaccines', 'bi-capsule', $body];
        }

        // --- Lab / X-ray orders ---------------------------------------
        $labs = array_values(array_filter(is_array($c['labTests'] ?? null) ? $c['labTests'] : [], fn ($t) => $has((string) $t)));
        if ($labs) {
            $chips = '';
            foreach ($labs as $t) {
                $chips .= '<span class="cdv-chip">' . $e(hc_lab_test_display_name((string) $t)) . '</span>';
            }
            $sections[] = ['Laboratory Tests Ordered', 'bi-droplet', '<div class="cdv-chips">' . $chips . '</div>'];
        }
        $xrays = array_values(array_filter(is_array($c['xrayExams'] ?? null) ? $c['xrayExams'] : [], fn ($t) => $has(is_array($t) ? ($t['name'] ?? '') : (string) $t)));
        if ($xrays) {
            $chips = '';
            foreach ($xrays as $t) {
                $chips .= '<span class="cdv-chip">' . $e(is_array($t) ? ($t['name'] ?? '') : $t) . '</span>';
            }
            $sections[] = ['X-ray Examinations Ordered', 'bi-lungs', '<div class="cdv-chips">' . $chips . '</div>'];
        }

        // --- Next step & follow-up ------------------------------------
        $routingLabels = ['laboratory' => 'Laboratory', 'xray' => 'X-ray', 'pharmacy' => 'Pharmacy', 'vaccination' => 'Vaccination', 'follow_up' => 'Follow-up visit'];
        $next = [];
        if ($has($c['nextRouting'] ?? '')) {
            $next['Patient Sent To'] = $routingLabels[$c['nextRouting']] ?? ucwords(str_replace('_', ' ', (string) $c['nextRouting']));
        }
        if (!empty($c['needsFollowUp']) || $has($c['nextFollowUp'] ?? '')) {
            $fu = strtotime((string) ($c['nextFollowUp'] ?? ''));
            $next['Follow-up Visit'] = $fu !== false && $has($c['nextFollowUp'] ?? '') ? date('l, F j, Y', $fu) : 'Needed (no date set)';
        }
        if ($next) {
            $rows = '';
            foreach ($next as $label => $value) {
                $rows .= '<div class="cdv-field"><dt>' . $e($label) . '</dt><dd>' . $e($value) . '</dd></div>';
            }
            $sections[] = ['Next Step & Follow-up', 'bi-signpost-split', '<dl class="cdv-grid">' . $rows . '</dl>'];
        }

        // --- Notes ----------------------------------------------------
        $notes = [];
        if ($has($c['doctorNotes'] ?? '')) $notes[] = (string) $c['doctorNotes'];
        if ($has($c['notes'] ?? '') && trim((string) $c['notes']) !== trim((string) ($c['doctorNotes'] ?? ''))) $notes[] = (string) $c['notes'];
        if ($notes) {
            $sections[] = ['Additional Notes', 'bi-sticky', '<p class="cdv-text">' . nl2br($e(trim(implode("\n\n", $notes)))) . '</p>'];
        }

        $html = '<div class="cdv">';
        foreach ($sections as [$title, $icon, $body]) {
            $html .= '<section class="cdv-section"><h6 class="cdv-section-title"><i class="bi ' . $e($icon) . '"></i> ' . $e($title) . '</h6>' . $body . '</section>';
        }
        return $html . '</div>';
    }
}
