/* =============================================================
   CONSENT MANAGEMENT JS (subadmin/consent_management.php)
   ---------------------------------------------------------------
   Each row's "View" (eye) button populates and opens a real Consent
   Details modal from that row's data-* attributes (see this page's
   header comment). The modal's "Print Consent" button prints a clean
   consent document built from that same record (in a hidden iframe,
   so the page itself isn't what gets printed), and "Download PDF"
   saves the same document as a real PDF file via jsPDF (loaded from
   cdnjs by the page). The Accept/Reject buttons and the Add Consent
   modal/form were removed from the page — they were decorative with
   no write endpoint behind them — so this file no longer handles
   either.
   ============================================================= */
document.addEventListener('DOMContentLoaded', function () {
    /* ---------- Search field: clearing an active search with the
       field's own "×" reloads the full list. Enter already submits the
       form on its own (implicit submission), so only the cleared case is
       handled here. ---- */
    const filterForm = document.getElementById('csmFilterForm');
    const searchInput = document.getElementById('csmSearchInput');
    if (filterForm && searchInput) {
        const initialQuery = searchInput.value;
        searchInput.addEventListener('search', function () {
            if (searchInput.value === '' && initialQuery !== '') filterForm.submit();
        });
    }

    const tableBody = document.getElementById('csmTableBody');
    const detailsModalEl = document.getElementById('csmDetailsModal');
    if (!tableBody || !detailsModalEl) return;

    const statusMeta = {
        pending:  { label: 'Pending',  bg: '#FDF3DC', color: '#B7791F', icon: 'bi-clock-fill' },
        accepted: { label: 'Accepted', bg: '#E4F7EA', color: '#1E8E3E', icon: 'bi-check-circle-fill' },
        rejected: { label: 'Rejected', bg: '#FCE4E4', color: '#D93025', icon: 'bi-x-circle-fill' },
        expired:  { label: 'Expired',  bg: '#EEF0F3', color: '#6B7280', icon: 'bi-hourglass-split' },
    };

    const facility = detailsModalEl.dataset.facility || '';
    const generatedBy = detailsModalEl.dataset.generatedBy || '';

    // The record currently shown in the modal — what Print / Download PDF use.
    let currentRecord = null;

    const orDash = function (value) {
        return value && value.trim() !== '' ? value : '—';
    };

    /* ---------- View -> populate Consent Details modal ---------- */
    tableBody.addEventListener('click', function (e) {
        const viewBtn = e.target.closest('.csm-view-btn');
        if (!viewBtn) return;
        const row = viewBtn.closest('.csm-row');
        if (!row) return;

        const d = row.dataset;
        const meta = statusMeta[d.status] || statusMeta.pending;
        currentRecord = {
            patientName: orDash(d.patientName),
            type: orDash(d.typeLabel),
            status: meta.label,
            requestedBy: d.requestedByName
                ? d.requestedByName + (d.requestedByRole ? ' (' + d.requestedByRole + ')' : '')
                : '',
            purpose: orDash(d.purpose),
            givenOn: orDash(d.givenOn),
            approvedBy: orDash(d.approvedBy),
            notes: orDash(d.notes),
        };

        const set = function (id, value) {
            const el = document.getElementById(id);
            if (el) el.textContent = value;
        };
        set('csmDetailPatientName', currentRecord.patientName);
        set('csmDetailType', currentRecord.type);
        set('csmDetailPurpose', currentRecord.purpose);
        set('csmDetailGivenOn', currentRecord.givenOn);
        set('csmDetailApprovedBy', currentRecord.approvedBy);
        set('csmDetailNotes', currentRecord.notes);

        const requestedByRow = document.getElementById('csmDetailRequestedByRow');
        if (requestedByRow) {
            set('csmDetailRequestedBy', currentRecord.requestedBy);
            requestedByRow.style.display = currentRecord.requestedBy ? '' : 'none';
        }

        // Same pill as the table's Status column: icon + label.
        const statusEl = document.getElementById('csmDetailStatus');
        if (statusEl) {
            statusEl.innerHTML = '';
            const icon = document.createElement('i');
            icon.className = 'bi ' + meta.icon;
            statusEl.appendChild(icon);
            statusEl.appendChild(document.createTextNode(' ' + meta.label));
            statusEl.style.background = meta.bg;
            statusEl.style.color = meta.color;
        }
    });

    /* ---------- Shared document content (print + PDF) ---------- */
    function documentFields(r) {
        const fields = [
            ['Patient', r.patientName],
            ['Consent Type', r.type],
            ['Status', r.status],
        ];
        if (r.requestedBy) fields.push(['Requested By', r.requestedBy]);
        fields.push(
            ['Purpose', r.purpose],
            ['Date Granted', r.givenOn],
            ['Authorized By', r.approvedBy],
            ['Notes', r.notes]
        );
        return fields;
    }

    function generatedOnText() {
        return new Date().toLocaleString('en-US', {
            month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit',
        });
    }

    function fileSafe(text) {
        return text.replace(/[^A-Za-z0-9]+/g, '-').replace(/^-+|-+$/g, '') || 'patient';
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /* ---------- Print Consent ---------- */
    const printBtn = document.getElementById('csmPrintBtn');
    if (printBtn) {
        printBtn.addEventListener('click', function () {
            if (!currentRecord) return;

            const rows = documentFields(currentRecord).map(function (f) {
                return '<tr><th>' + escapeHtml(f[0]) + '</th><td>' + escapeHtml(f[1]) + '</td></tr>';
            }).join('');

            const html = '<!doctype html><html><head><meta charset="utf-8">'
                + '<title>Consent Record - ' + escapeHtml(currentRecord.patientName) + '</title>'
                + '<style>'
                + '@page{ margin: 18mm; }'
                + 'body{ font-family: "Segoe UI", Arial, sans-serif; color: #1F2937; margin: 0; }'
                + '.hd{ border-bottom: 3px solid #5B0A0A; padding-bottom: 12px; margin-bottom: 22px; }'
                + '.brand{ font-size: 20px; font-weight: 800; color: #5B0A0A; }'
                + '.fac{ font-size: 13px; color: #6B7280; margin-top: 2px; }'
                + 'h1{ font-size: 17px; margin: 0 0 16px; }'
                + 'table{ width: 100%; border-collapse: collapse; font-size: 13px; }'
                + 'th{ text-align: left; width: 30%; color: #6B7280; font-weight: 600; padding: 9px 12px 9px 0; vertical-align: top; }'
                + 'td{ padding: 9px 0; border-bottom: 1px solid #E5E7EB; }'
                + 'th{ border-bottom: 1px solid #E5E7EB; }'
                + '.ft{ margin-top: 28px; font-size: 11px; color: #9CA3AF; }'
                + '</style></head><body>'
                + '<div class="hd"><div class="brand">HEALTrack</div><div class="fac">' + escapeHtml(facility) + '</div></div>'
                + '<h1>Patient Consent Record</h1>'
                + '<table>' + rows + '</table>'
                + '<div class="ft">Generated ' + escapeHtml(generatedOnText())
                + (generatedBy ? ' by ' + escapeHtml(generatedBy) : '') + ' from HEALTrack Consent Management.</div>'
                + '</body></html>';

            // Hidden iframe, so only this document is printed — not the page.
            const frame = document.createElement('iframe');
            frame.style.position = 'fixed';
            frame.style.width = '0';
            frame.style.height = '0';
            frame.style.border = '0';
            frame.setAttribute('aria-hidden', 'true');
            document.body.appendChild(frame);

            const doc = frame.contentWindow.document;
            doc.open();
            doc.write(html);
            doc.close();

            frame.contentWindow.focus();
            setTimeout(function () {
                frame.contentWindow.print();
                setTimeout(function () { frame.remove(); }, 1000);
            }, 250);
        });
    }

    /* ---------- Download PDF ---------- */
    const pdfBtn = document.getElementById('csmDownloadPdfBtn');
    if (pdfBtn) {
        pdfBtn.addEventListener('click', function () {
            if (!currentRecord) return;
            if (!window.jspdf || !window.jspdf.jsPDF) {
                const message = 'The PDF generator could not be loaded. Please check your connection and try again.';
                if (window.HCDialog) window.HCDialog.alert(message); else window.alert(message);
                return;
            }

            const pdf = new window.jspdf.jsPDF({ unit: 'mm', format: 'a4' });
            const left = 20;
            const labelWidth = 45;
            const valueX = left + labelWidth;
            const valueWidth = 190 - valueX;
            let y = 24;

            // Header
            pdf.setFont('helvetica', 'bold');
            pdf.setFontSize(18);
            pdf.setTextColor(91, 10, 10);
            pdf.text('HEALTrack', left, y);
            y += 7;
            pdf.setFont('helvetica', 'normal');
            pdf.setFontSize(10.5);
            pdf.setTextColor(107, 114, 128);
            if (facility) pdf.text(facility, left, y);
            y += 5;
            pdf.setDrawColor(91, 10, 10);
            pdf.setLineWidth(0.8);
            pdf.line(left, y, 190, y);
            y += 12;

            pdf.setFont('helvetica', 'bold');
            pdf.setFontSize(14);
            pdf.setTextColor(31, 41, 55);
            pdf.text('Patient Consent Record', left, y);
            y += 10;

            // Fields
            pdf.setFontSize(10.5);
            pdf.setDrawColor(229, 231, 235);
            pdf.setLineWidth(0.2);
            documentFields(currentRecord).forEach(function (f) {
                const lines = pdf.splitTextToSize(f[1], valueWidth);
                pdf.setFont('helvetica', 'bold');
                pdf.setTextColor(107, 114, 128);
                pdf.text(f[0], left, y);
                pdf.setFont('helvetica', 'normal');
                pdf.setTextColor(31, 41, 55);
                pdf.text(lines, valueX, y);
                y += lines.length * 5 + 3;
                pdf.line(left, y - 1, 190, y - 1);
                y += 4;
            });

            // Footer
            y += 6;
            pdf.setFontSize(8.5);
            pdf.setTextColor(156, 163, 175);
            pdf.text('Generated ' + generatedOnText() + (generatedBy ? ' by ' + generatedBy : '') + ' from HEALTrack Consent Management.', left, y);

            pdf.save('Consent-' + fileSafe(currentRecord.patientName) + '.pdf');
        });
    }
});
