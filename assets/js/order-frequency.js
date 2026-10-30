/* =============================================================
   ORDER/PRESCRIPTION FREQUENCY CARD
   (admin/facility_analytics.php, subadmin/predictive_analytics.php)
   ---------------------------------------------------------------
   window.HCOrderFrequency.init(idPrefix, initialItems, apiUrl)
   drives the "Order/Prescription Frequency" card: a top-5 list +
   category/date-range filters + summary stats, plus a "View All
   Items" modal with search/category/sort/pagination over the full
   real inventory (including zero-order items).

   $idPrefix matches the same "faPred" prefix predictive-analytics.js
   uses for this same tab's canvas ids (both pages hardcode "faPred"
   in their own markup — see that file's own header comment).

   $initialItems is the real, already-computed breakdown PHP handed
   over for the page's default view (days=30, no category filter) —
   includes/shared/predictive_analytics_core.php's own
   hc_order_frequency_breakdown(), one row per real inventory item:
   {name, itemType, orders, dispensed, prescriptions, total}. Category
   switches and the "View All" modal's search/sort/pagination all stay
   client-side against this same array — only a DATE RANGE change
   re-fetches from $apiUrl (api/get-order-frequency.php), since that's
   the one filter this card can't honestly answer from data already on
   the page.
   ============================================================= */
window.HCOrderFrequency = (function () {
    function escapeHtml(s) {
        const div = document.createElement('div');
        div.textContent = s == null ? '' : String(s);
        return div.innerHTML;
    }

    function typeColor(itemType) {
        return itemType === 'vaccine' ? '#12A6C6' : '#5B0A0A'; // teal for vaccines, brand maroon for medicines
    }

    function byCategory(items, category) {
        if (category === 'all' || !category) return items;
        return items.filter(function (i) { return i.itemType === category; });
    }

    function init(idPrefix, initialItems, apiUrl) {
        const top5List = document.getElementById(idPrefix + 'OPFTop5List');
        if (!top5List) return; // card isn't on this page, or the real inventory is empty (no markup to wire)

        const categorySeg = document.getElementById(idPrefix + 'OPFCategorySeg');
        const rangeSelect = document.getElementById(idPrefix + 'OPFRange');
        const totalRequestsEl = document.getElementById(idPrefix + 'OPFTotalRequests');
        const itemsRequestedEl = document.getElementById(idPrefix + 'OPFItemsRequested');
        const top5Empty = document.getElementById(idPrefix + 'OPFTop5Empty');

        const modalSearch = document.getElementById(idPrefix + 'OPFModalSearch');
        const modalCategorySeg = document.getElementById(idPrefix + 'OPFModalCategorySeg');
        const modalSort = document.getElementById(idPrefix + 'OPFModalSort');
        const modalTableBody = document.getElementById(idPrefix + 'OPFModalTableBody');
        const modalEmpty = document.getElementById(idPrefix + 'OPFModalEmpty');
        const modalPagination = document.getElementById(idPrefix + 'OPFModalPagination');

        const PAGE_SIZE = 10;
        let allItems = initialItems || [];
        let cardCategory = 'all';
        let modalCategory = 'all';
        let modalPage = 1;

        function renderCard() {
            const filtered = byCategory(allItems, cardCategory);
            const totalRequests = filtered.reduce(function (sum, i) { return sum + i.total; }, 0);
            const itemsRequested = filtered.filter(function (i) { return i.total > 0; }).length;
            if (totalRequestsEl) totalRequestsEl.textContent = totalRequests.toLocaleString();
            if (itemsRequestedEl) itemsRequestedEl.textContent = itemsRequested + ' / ' + filtered.length;

            const top5 = filtered.slice()
                .sort(function (a, b) { return b.total - a.total || a.name.localeCompare(b.name); })
                .slice(0, 5);
            const maxTotal = top5.reduce(function (m, i) { return Math.max(m, i.total); }, 0);

            top5List.innerHTML = '';
            if (top5.length === 0 || maxTotal === 0) {
                top5List.style.display = 'none';
                if (top5Empty) top5Empty.style.display = '';
                return;
            }
            top5List.style.display = '';
            if (top5Empty) top5Empty.style.display = 'none';

            top5.forEach(function (item) {
                const pct = Math.max(4, Math.round((item.total / maxTotal) * 100));
                const row = document.createElement('div');
                row.className = 'opf-top5-row';
                row.innerHTML =
                    '<div class="opf-top5-row-head">' +
                        '<span class="opf-top5-row-name">' + escapeHtml(item.name) + '</span>' +
                        '<span class="opf-top5-row-value">' + item.total + '</span>' +
                    '</div>' +
                    '<div class="opf-top5-row-track"><div class="opf-top5-row-fill" style="width:' + pct + '%;background:' + typeColor(item.itemType) + ';"></div></div>';
                top5List.appendChild(row);
            });
        }

        function renderModal() {
            if (!modalTableBody) return;

            const term = modalSearch ? modalSearch.value.trim().toLowerCase() : '';
            let rows = byCategory(allItems, modalCategory);
            if (term) {
                rows = rows.filter(function (i) { return i.name.toLowerCase().indexOf(term) !== -1; });
            }
            rows = rows.slice();
            const sortValue = modalSort ? modalSort.value : 'total-desc';
            if (sortValue === 'total-asc') {
                rows.sort(function (a, b) { return a.total - b.total || a.name.localeCompare(b.name); });
            } else if (sortValue === 'name-asc') {
                rows.sort(function (a, b) { return a.name.localeCompare(b.name); });
            } else {
                rows.sort(function (a, b) { return b.total - a.total || a.name.localeCompare(b.name); });
            }

            const totalPages = Math.max(1, Math.ceil(rows.length / PAGE_SIZE));
            if (modalPage > totalPages) modalPage = totalPages;
            if (modalPage < 1) modalPage = 1;
            const pageRows = rows.slice((modalPage - 1) * PAGE_SIZE, modalPage * PAGE_SIZE);

            modalTableBody.innerHTML = '';
            if (pageRows.length === 0) {
                if (modalEmpty) modalEmpty.style.display = '';
            } else {
                if (modalEmpty) modalEmpty.style.display = 'none';
                pageRows.forEach(function (item) {
                    const tr = document.createElement('tr');
                    tr.innerHTML =
                        '<td>' + escapeHtml(item.name) + '</td>' +
                        '<td><span class="opf-type-badge opf-type-' + item.itemType + '">' + (item.itemType === 'vaccine' ? 'Vaccine' : 'Medicine') + '</span></td>' +
                        '<td>' + item.orders + '</td>' +
                        '<td>' + item.dispensed + '</td>' +
                        '<td>' + item.prescriptions + '</td>' +
                        '<td class="opf-total-cell">' + item.total + '</td>';
                    modalTableBody.appendChild(tr);
                });
            }

            if (modalPagination) {
                modalPagination.innerHTML = '';
                const info = document.createElement('span');
                info.className = 'opf-page-info';
                info.textContent = rows.length + ' item' + (rows.length === 1 ? '' : 's');
                if (totalPages > 1) {
                    info.textContent += ' — page ' + modalPage + ' of ' + totalPages;
                    const prevBtn = document.createElement('button');
                    prevBtn.type = 'button';
                    prevBtn.className = 'opf-page-btn';
                    prevBtn.textContent = 'Prev';
                    prevBtn.disabled = modalPage <= 1;
                    prevBtn.addEventListener('click', function () { modalPage--; renderModal(); });

                    const nextBtn = document.createElement('button');
                    nextBtn.type = 'button';
                    nextBtn.className = 'opf-page-btn';
                    nextBtn.textContent = 'Next';
                    nextBtn.disabled = modalPage >= totalPages;
                    nextBtn.addEventListener('click', function () { modalPage++; renderModal(); });

                    modalPagination.appendChild(prevBtn);
                    modalPagination.appendChild(info);
                    modalPagination.appendChild(nextBtn);
                } else {
                    modalPagination.appendChild(info);
                }
            }
        }

        function bindSeg(seg, onPick) {
            if (!seg) return;
            seg.addEventListener('click', function (e) {
                const btn = e.target.closest('.opf-seg-btn');
                if (!btn || !seg.contains(btn)) return;
                Array.prototype.forEach.call(seg.querySelectorAll('.opf-seg-btn'), function (b) { b.classList.remove('active'); });
                btn.classList.add('active');
                onPick(btn.getAttribute('data-category') || 'all');
            });
        }

        bindSeg(categorySeg, function (category) {
            cardCategory = category;
            renderCard();
        });
        bindSeg(modalCategorySeg, function (category) {
            modalCategory = category;
            modalPage = 1;
            renderModal();
        });

        if (rangeSelect) {
            rangeSelect.addEventListener('change', function () {
                const days = rangeSelect.value;
                fetch(apiUrl + '?days=' + encodeURIComponent(days), { credentials: 'same-origin' })
                    .then(function (res) { return res.json(); })
                    .then(function (json) {
                        if (!json || !json.success) return;
                        allItems = json.items || [];
                        modalPage = 1;
                        renderCard();
                        renderModal();
                    })
                    .catch(function () {
                        // A real network hiccup on a secondary filter — leave
                        // whatever was already on screen rather than blanking it.
                    });
            });
        }

        if (modalSearch) {
            modalSearch.addEventListener('input', function () { modalPage = 1; renderModal(); });
        }
        if (modalSort) {
            modalSort.addEventListener('change', function () { modalPage = 1; renderModal(); });
        }

        renderCard();
        renderModal();
    }

    return { init: init };
})();
