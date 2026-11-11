/* =============================================================
   PRINT PRESCRIPTION (doctor/active_consultation.php)
   ---------------------------------------------------------------
   "Print Prescription" under the Prescription / Treatment editor.
   Builds a standalone, A4, black-on-white prescription document in a
   hidden iframe and prints ONLY that — never the consultation page.
   It contains exactly four things:
     1. Facility name (top, prominent)
     2. Patient name
     3. Attending doctor's name
     4. The Prescription / Treatment editor's content, formatting kept
     + Follow-up Visit date — only when the doctor ticked "Also needs a
       follow-up visit" AND picked a date (see followUpDateLabel()).
   Names/facility come from the button's data-* attributes (set
   server-side from this consultation's own records). Nothing on the
   page is read except #acvPrescription, and nothing is modified —
   cancelling the print dialog leaves the consultation untouched.
   ============================================================= */
(function () {
    const btn = document.getElementById('acvPrintPrescriptionBtn');
    const editor = document.getElementById('acvPrescription');
    if (!btn || !editor) return;

    // Only the formatting the editor's own toolbar can produce survives
    // (bold/italic/underline, bullet + numbered lists, links, line
    // breaks); every other element is unwrapped to its text and every
    // attribute except a safe http(s)/mailto href is dropped — so pasted
    // styles, scripts or event handlers can never reach the printout.
    const ALLOWED = { B: 'strong', STRONG: 'strong', I: 'em', EM: 'em', U: 'u', UL: 'ul', OL: 'ol', LI: 'li', BR: 'br', P: 'p', DIV: 'div', A: 'a' };

    function cleanNode(src, doc) {
        const out = doc.createDocumentFragment();
        src.childNodes.forEach(function (node) {
            if (node.nodeType === Node.TEXT_NODE) {
                out.appendChild(doc.createTextNode(node.textContent));
                return;
            }
            if (node.nodeType !== Node.ELEMENT_NODE) return;
            if (['SCRIPT', 'STYLE', 'IFRAME', 'OBJECT', 'EMBED', 'TEMPLATE'].indexOf(node.tagName) !== -1) return;

            const tag = ALLOWED[node.tagName];
            if (!tag) {
                out.appendChild(cleanNode(node, doc)); // unwrap: keep text, drop the element
                return;
            }
            const el = doc.createElement(tag);
            if (tag === 'a') {
                const href = node.getAttribute('href') || '';
                if (/^(https?:|mailto:)/i.test(href.trim())) el.setAttribute('href', href.trim());
            }
            el.appendChild(cleanNode(node, doc));
            out.appendChild(el);
        });
        return out;
    }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    const PRINT_CSS = [
        '@page { size: A4; margin: 20mm 18mm; }',
        '* { box-sizing: border-box; }',
        'html, body { background: #fff; color: #000; margin: 0; }',
        'body { font-family: "Segoe UI", Arial, Helvetica, sans-serif; font-size: 12pt; line-height: 1.5; }',
        '.rx-head { text-align: center; padding-bottom: 12pt; border-bottom: 2px solid #000; margin-bottom: 18pt; }',
        '.rx-facility { font-size: 20pt; font-weight: 700; letter-spacing: .3pt; margin: 0; }',
        '.rx-doc-title { font-size: 10.5pt; text-transform: uppercase; letter-spacing: 2pt; margin: 4pt 0 0; }',
        '.rx-info { display: grid; grid-template-columns: 1fr 1fr; gap: 10pt 24pt; margin-bottom: 18pt; }',
        '.rx-label { display: block; font-size: 9pt; text-transform: uppercase; letter-spacing: 1pt; margin-bottom: 2pt; }',
        '.rx-value { display: block; font-size: 13pt; font-weight: 700; border-bottom: 1px solid #000; padding-bottom: 3pt; }',
        '.rx-symbol { font-family: Georgia, "Times New Roman", serif; font-size: 30pt; font-weight: 700; line-height: 1; margin: 0 0 8pt; }',
        '.rx-body { font-size: 12pt; }',
        '.rx-body p, .rx-body div { margin: 0 0 6pt; }',
        '.rx-body ul, .rx-body ol { margin: 0 0 8pt; padding-left: 22pt; }',
        '.rx-body li { margin-bottom: 3pt; }',
        '.rx-body a { color: #000; }',
        '.rx-body, .rx-body * { color: #000 !important; background: transparent !important; }',
        '.rx-followup { margin-top: 22pt; padding-top: 10pt; border-top: 1px solid #000; font-size: 12pt; }',
        '.rx-followup .rx-label { display: inline; margin-right: 6pt; }',
        '.rx-followup strong { font-size: 13pt; }',
        // Signature line, bottom-right. The body is at least one page
        // tall (A4 297mm minus the 2 x 20mm page margins, with a little
        // slack so it never spills a blank page), and the signature's
        // margin-top:auto pushes it to the bottom of that; a longer
        // prescription just flows it in after the content instead.
        'body { display: flex; flex-direction: column; min-height: 250mm; }',
        '.rx-sign { margin-top: auto; padding-top: 36pt; align-self: flex-end; width: 62mm; text-align: center; break-inside: avoid; }',
        '.rx-sign-line { border-top: 1px solid #000; padding-top: 4pt; font-size: 12pt; font-weight: 700; }',
        '.rx-sign .rx-label { margin: 2pt 0 0; }',
    ].join('\n');

    // The follow-up date the doctor set on this consultation ("Also needs
    // a follow-up visit" + Follow-up Date, right column) — read live at
    // print time, so it matches what's on screen even before saving.
    // '' when the box is unticked or no date is chosen: then the printout
    // has no follow-up line at all.
    function followUpDateLabel() {
        const needs = document.getElementById('acvNeedsFollowUp');
        const dateInput = document.getElementById('acvFollowUpDate');
        if (!needs || !needs.checked || !dateInput || !dateInput.value) return '';
        const parts = dateInput.value.split('-').map(Number); // YYYY-MM-DD, as a LOCAL date (no timezone shift)
        const d = new Date(parts[0], parts[1] - 1, parts[2]);
        if (isNaN(d.getTime())) return '';
        return d.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
    }

    btn.addEventListener('click', function () {
        if (editor.textContent.trim() === '') {
            const msg = 'There is no prescription or treatment to print yet. Type it in the Prescription / Treatment box first.';
            if (window.HCDialog) HCDialog.alert(msg); else alert(msg);
            return;
        }

        const facility = btn.dataset.facility || '';
        const patientName = btn.dataset.patientName || '';
        const doctorName = btn.dataset.doctorName || '';
        const followUp = followUpDateLabel();

        // Hidden, off-screen iframe holding only the prescription document.
        const frame = document.createElement('iframe');
        frame.setAttribute('aria-hidden', 'true');
        frame.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;visibility:hidden;';
        document.body.appendChild(frame);

        const doc = frame.contentDocument;
        doc.open();
        doc.write('<!DOCTYPE html><html><head><meta charset="utf-8"><title>Prescription — ' + escapeHtml(patientName) + '</title>'
            + '<style>' + PRINT_CSS + '</style></head><body>'
            + '<header class="rx-head"><p class="rx-facility">' + escapeHtml(facility) + '</p><p class="rx-doc-title">Prescription</p></header>'
            + '<section class="rx-info">'
            + '<div><span class="rx-label">Patient Name</span><span class="rx-value">' + escapeHtml(patientName) + '</span></div>'
            + '<div><span class="rx-label">Attending Physician</span><span class="rx-value">' + escapeHtml(doctorName) + '</span></div>'
            + '</section>'
            + '<p class="rx-symbol">&#8478;</p>'
            + '<main class="rx-body" id="rxBody"></main>'
            + (followUp !== ''
                ? '<section class="rx-followup"><span class="rx-label">Follow-up Visit:</span> <strong>' + escapeHtml(followUp) + '</strong></section>'
                : '')
            + '<section class="rx-sign"><div class="rx-sign-line">' + escapeHtml(doctorName) + '</div>'
            + '<span class="rx-label">Signature over Printed Name</span></section>'
            + '</body></html>');
        doc.close();

        doc.getElementById('rxBody').appendChild(cleanNode(editor, doc));

        function cleanup() {
            setTimeout(function () { if (frame.parentNode) frame.parentNode.removeChild(frame); }, 500);
        }
        frame.contentWindow.addEventListener('afterprint', cleanup);

        // Let the iframe lay out before opening the print dialog.
        setTimeout(function () {
            frame.contentWindow.focus();
            frame.contentWindow.print();
            // Fallback for browsers that don't fire afterprint on cancel.
            setTimeout(cleanup, 60000);
        }, 150);
    });
})();
