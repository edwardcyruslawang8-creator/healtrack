/* =============================================================
   REPORT EXPORT JS — Print / PDF / Excel
   ---------------------------------------------------------------
   Handles the buttons from includes/shared/report_export_actions.php
   on every Analytics & Reports page (subadmin report pages, Medical
   History, admin Facility Analytics' Medical History tab).

   Every action sends the page's CURRENT query string, so the server
   builds the export from exactly the filters on screen
   (includes/shared/report_export.php):
     - Print — opens api/print-report.php in a new tab, which renders a
       print-only document and opens the print dialog.
     - PDF   — fetches api/export-report.php's JSON and draws an A4 PDF
       with jsPDF + AutoTable: maroon header, filters, summary, every
       table (header row repeated on each page), "Page X of Y" footer.
     - Excel — same JSON into an .xlsx workbook with SheetJS: a Summary
       sheet plus one sheet per table.
   The PDF/Excel libraries load on first use only.

   Delegated on document, so the buttons keep working after
   assets/js/ajax-report-nav.js / ajax-tab-nav.js swap <main>; the
   guard stops ajax-tab-nav.js's re-injection from binding twice.
   ============================================================= */
(function () {
    if (window.__hcReportExportBound) return;
    window.__hcReportExportBound = true;

    var scriptSrc = (document.currentScript && document.currentScript.src) || '';
    var appBase = scriptSrc.replace(/\/assets\/js\/report-export\.js.*$/, '');

    var LIBS = {
        jspdf: 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js',
        autotable: 'https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js',
        xlsx: 'https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js',
    };
    var MAROON = [105, 11, 11];
    var loaded = {};

    function loadScript(url) {
        if (!loaded[url]) {
            loaded[url] = new Promise(function (resolve, reject) {
                var s = document.createElement('script');
                s.src = url;
                s.onload = resolve;
                s.onerror = function () { delete loaded[url]; reject(new Error('Could not load the export library. Check your internet connection and try again.')); };
                document.head.appendChild(s);
            });
        }
        return loaded[url];
    }

    function notify(message) {
        if (window.HCDialog) window.HCDialog.alert(message); // assets/js/hc-dialogs.js
        else window.alert(message);
    }

    function exportParams(report, format) {
        var params = new URLSearchParams(window.location.search);
        params.set('report', report);
        params.set('format', format);

        // Inventory's Stock Movement card keeps its own range client-side
        // (assets/js/inventory_movement_cards.js) — pass it along.
        var range = document.getElementById('irMovementRange');
        if (range) {
            var start = document.getElementById('irMovementStartDate');
            var end = document.getElementById('irMovementEndDate');
            if (range.value === 'custom' && start && end && start.value && end.value && start.value <= end.value) {
                params.set('movementStart', start.value);
                params.set('movementEnd', end.value);
            } else if (range.value !== 'custom') {
                params.set('movementDays', range.value);
            }
        }
        return params.toString();
    }

    function fetchReport(report, format) {
        return fetch(appBase + '/api/export-report.php?' + exportParams(report, format), { credentials: 'same-origin' })
            .then(function (res) {
                if (!res.ok) throw new Error(res.status === 403 ? 'You are not authorized to export this report.' : 'The report could not be generated. Please try again.');
                return res.json();
            })
            .then(function (json) {
                if (!json || !json.success) throw new Error('The report could not be generated. Please try again.');
                return json.report;
            });
    }

    // jsPDF's built-in fonts only cover WinAnsi; map the few symbols
    // this app's labels use to plain equivalents.
    function pdfText(value) {
        return String(value == null ? '' : value)
            .replace(/≤/g, '<=').replace(/≥/g, '>=')
            .replace(/[—–]/g, '-').replace(/[‘’]/g, "'").replace(/[“”]/g, '"')
            .replace(/•/g, '-').replace(/₱/g, 'PHP ')
            .replace(/[^\x00-\xFF]/g, '');
    }

    function buildPdf(report) {
        var jsPDF = window.jspdf.jsPDF;
        var wide = report.tables.some(function (t) { return t.columns.length >= 7; });
        var doc = new jsPDF({ orientation: wide ? 'landscape' : 'portrait', unit: 'pt', format: 'a4' });
        var pageW = doc.internal.pageSize.getWidth();
        var pageH = doc.internal.pageSize.getHeight();
        var margin = 36;
        var y;

        // Header band
        doc.setFillColor(MAROON[0], MAROON[1], MAROON[2]);
        doc.roundedRect(margin, margin, pageW - margin * 2, 56, 6, 6, 'F');
        doc.setTextColor(255, 255, 255);
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(17);
        doc.text('HEALTrack', margin + 16, margin + 25);
        doc.setFont('helvetica', 'normal');
        doc.setFontSize(9);
        doc.text('Health Information System', margin + 16, margin + 41);
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(12);
        doc.text(pdfText(report.facility), pageW - margin - 16, margin + 25, { align: 'right' });
        doc.setFont('helvetica', 'normal');
        doc.setFontSize(9);
        doc.text('Analytics & Reports', pageW - margin - 16, margin + 41, { align: 'right' });

        // Title + meta
        y = margin + 84;
        doc.setTextColor(MAROON[0], MAROON[1], MAROON[2]);
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(18);
        doc.text(pdfText(report.title), margin, y);
        y += 16;
        doc.setTextColor(107, 114, 128);
        doc.setFont('helvetica', 'normal');
        doc.setFontSize(9);
        doc.text(pdfText('Date generated: ' + report.generatedAt + '     Generated by: ' + report.generatedBy), margin, y);

        var tableBase = {
            margin: { left: margin, right: margin, top: 48, bottom: 44 },
            styles: { font: 'helvetica', fontSize: 8.5, cellPadding: 4, textColor: [31, 41, 55], lineColor: [229, 231, 235], overflow: 'linebreak' },
            headStyles: { fillColor: MAROON, textColor: 255, fontStyle: 'bold' },
            footStyles: { fillColor: [248, 245, 245], textColor: [31, 41, 55], fontStyle: 'bold' },
            alternateRowStyles: { fillColor: [250, 250, 251] },
            showHead: 'everyPage',
            showFoot: 'lastPage',
            rowPageBreak: 'avoid',
        };

        function section(title) {
            var lastY = doc.lastAutoTable ? doc.lastAutoTable.finalY : y;
            var top = Math.max(lastY, y) + 22;
            if (top > pageH - 110) { doc.addPage(); top = 64; }
            doc.setTextColor(MAROON[0], MAROON[1], MAROON[2]);
            doc.setFont('helvetica', 'bold');
            doc.setFontSize(11);
            doc.text(pdfText(title), margin, top);
            doc.setDrawColor(MAROON[0], MAROON[1], MAROON[2]);
            doc.setLineWidth(1.2);
            doc.line(margin, top + 4, pageW - margin, top + 4);
            y = top + 10;
            return y;
        }

        // Filters
        doc.autoTable(Object.assign({}, tableBase, {
            startY: y + 12,
            theme: 'plain',
            body: report.filters.map(function (f) { return [pdfText(f[0]), pdfText(f[1])]; }),
            styles: Object.assign({}, tableBase.styles, { fillColor: [248, 245, 245] }),
            columnStyles: { 0: { fontStyle: 'bold', cellWidth: 150 } },
        }));

        // Summary
        doc.autoTable(Object.assign({}, tableBase, {
            startY: section('Summary'),
            head: [['Metric', 'Value', 'Details']],
            body: report.summary.map(function (s) { return s.map(pdfText); }),
            columnStyles: { 1: { fontStyle: 'bold' } },
        }));

        report.tables.forEach(function (t) {
            var top = section(t.title + '  (' + t.rows.length + (t.rows.length === 1 ? ' row)' : ' rows)'));
            if (!t.rows.length) {
                doc.setTextColor(107, 114, 128);
                doc.setFont('helvetica', 'italic');
                doc.setFontSize(9);
                doc.text(pdfText(t.note || 'No records match the selected filters.'), margin, top + 12);
                doc.lastAutoTable.finalY = top + 16;
                return;
            }
            doc.autoTable(Object.assign({}, tableBase, {
                startY: top,
                head: [t.columns.map(pdfText)],
                body: t.rows.map(function (r) { return r.map(pdfText); }),
                foot: t.footer ? [t.footer.map(pdfText)] : undefined,
            }));
            if (t.note) {
                var noteY = doc.lastAutoTable.finalY + 12;
                if (noteY > pageH - 50) { doc.addPage(); noteY = 64; }
                doc.setTextColor(107, 114, 128);
                doc.setFont('helvetica', 'italic');
                doc.setFontSize(8.5);
                doc.text(doc.splitTextToSize(pdfText(t.note), pageW - margin * 2), margin, noteY);
                doc.lastAutoTable.finalY = noteY + 4;
            }
        });

        if (report.notes.length) {
            var notesY = doc.lastAutoTable.finalY + 22;
            if (notesY > pageH - 70) { doc.addPage(); notesY = 64; }
            doc.setTextColor(107, 114, 128);
            doc.setFont('helvetica', 'normal');
            doc.setFontSize(8.5);
            report.notes.forEach(function (n) {
                var lines = doc.splitTextToSize(pdfText(n), pageW - margin * 2);
                doc.text(lines, margin, notesY);
                notesY += lines.length * 11 + 3;
            });
        }

        // Running header (continuation pages) + footer with page numbers
        var pages = doc.internal.getNumberOfPages();
        for (var i = 1; i <= pages; i++) {
            doc.setPage(i);
            if (i > 1) {
                doc.setFillColor(MAROON[0], MAROON[1], MAROON[2]);
                doc.rect(margin, 24, pageW - margin * 2, 2, 'F');
                doc.setTextColor(MAROON[0], MAROON[1], MAROON[2]);
                doc.setFont('helvetica', 'bold');
                doc.setFontSize(8.5);
                doc.text(pdfText(report.title), margin, 38);
                doc.setFont('helvetica', 'normal');
                doc.text(pdfText(report.facility), pageW - margin, 38, { align: 'right' });
            }
            doc.setDrawColor(229, 231, 235);
            doc.setLineWidth(0.6);
            doc.line(margin, pageH - 30, pageW - margin, pageH - 30);
            doc.setTextColor(107, 114, 128);
            doc.setFont('helvetica', 'normal');
            doc.setFontSize(8);
            doc.text(pdfText('HEALTrack - ' + report.facility + ' - ' + report.title + ' - Generated ' + report.generatedAt), margin, pageH - 18);
            doc.text('Page ' + i + ' of ' + pages, pageW - margin, pageH - 18, { align: 'right' });
        }

        return doc;
    }

    // "1,501" -> 1501, "35.9%" -> 0.359 (shown as a percentage), else text.
    function excelCell(value) {
        var s = String(value == null ? '' : value).trim();
        if (/^-?[\d,]+(\.\d+)?$/.test(s)) {
            return { v: parseFloat(s.replace(/,/g, '')), t: 'n', z: s.indexOf('.') >= 0 ? '#,##0.0' : '#,##0' };
        }
        if (/^-?[\d,]+(\.\d+)?%$/.test(s)) {
            return { v: parseFloat(s.replace(/[,%]/g, '')) / 100, t: 'n', z: '0.0%' };
        }
        return { v: s, t: 's' };
    }

    function sheetFrom(rows) {
        var ws = window.XLSX.utils.aoa_to_sheet(rows);
        var widths = [];
        rows.forEach(function (r) {
            r.forEach(function (c, i) {
                var len = String(c && typeof c === 'object' ? c.v : (c == null ? '' : c)).length;
                widths[i] = Math.min(60, Math.max(widths[i] || 10, len + 2));
            });
        });
        ws['!cols'] = widths.map(function (w) { return { wch: w }; });
        return ws;
    }

    function buildWorkbook(report) {
        var XLSX = window.XLSX;
        var wb = XLSX.utils.book_new();
        var header = [
            [report.facility],
            [report.title],
            ['Date generated', report.generatedAt],
            ['Generated by', report.generatedBy],
            [],
        ];

        var summary = header.concat([['Filters']], report.filters, [[]], [['Summary'], ['Metric', 'Value', 'Details']],
            report.summary.map(function (s) { return [s[0], excelCell(s[1]), s[2]]; }));
        if (report.notes.length) summary = summary.concat([[]], report.notes.map(function (n) { return ['Note', n]; }));
        XLSX.utils.book_append_sheet(wb, sheetFrom(summary), 'Summary');

        var used = { Summary: true };
        report.tables.forEach(function (t) {
            var name = t.title.replace(/[\\\/\?\*\[\]:]/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 31);
            var base = name, n = 2;
            while (used[name]) { name = base.slice(0, 28) + ' ' + n++; }
            used[name] = true;

            var rows = [[t.title], [report.facility + ' - ' + report.generatedAt], [], t.columns]
                .concat(t.rows.map(function (r) { return r.map(excelCell); }));
            if (t.footer) rows.push(t.footer.map(excelCell));
            if (!t.rows.length) rows.push([t.note || 'No records match the selected filters.']);
            else if (t.note) rows.push([], [t.note]);
            XLSX.utils.book_append_sheet(wb, sheetFrom(rows), name);
        });

        return wb;
    }

    window.hcBuildReportPdf = buildPdf;       // exposed for testing
    window.hcBuildReportWorkbook = buildWorkbook;

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-report-export]');
        if (!btn || btn.disabled) return;
        e.preventDefault();

        var format = btn.getAttribute('data-report-export');
        var report = btn.getAttribute('data-report');

        if (format === 'print') {
            var win = window.open(appBase + '/api/print-report.php?' + exportParams(report, 'print') + '&autoprint=1', '_blank');
            if (!win) notify('Please allow pop-ups for this site to print the report.');
            return;
        }

        var originalHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Preparing…';

        var libs = format === 'pdf'
            ? loadScript(LIBS.jspdf).then(function () { return loadScript(LIBS.autotable); })
            : loadScript(LIBS.xlsx);

        Promise.all([libs, fetchReport(report, format)])
            .then(function (results) {
                var data = results[1];
                if (format === 'pdf') buildPdf(data).save(data.filename + '.pdf');
                else window.XLSX.writeFile(buildWorkbook(data), data.filename + '.xlsx');
            })
            .catch(function (err) { notify(err.message || 'The report could not be exported.'); })
            .finally(function () {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            });
    });
})();
