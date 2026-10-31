/* =============================================================
   RANGE DROPDOWN FILTERS (shared by every dashboard AND Audit Logs,
   every role)
   ---------------------------------------------------------------
   Backs includes/shared/dashboard_range_dropdown.php's "Custom Date"
   row + From/To panel everywhere it's used. Two independent pieces:

   1) Custom Date toggle — runs on EVERY range dropdown on the page.
      Clicking "Custom Date" expands/collapses the From/To panel in
      place (smooth max-height transition, chevron rotates, dropdown
      re-positioned via Bootstrap/Popper so the panel growing taller
      doesn't overflow past where the menu was originally placed) and
      stops the click from bubbling to Bootstrap's own dropdown-auto-
      close handler, which would otherwise treat clicking this button
      like clicking any other dropdown-item and close the whole menu.
      Every preset option and the custom form's Apply button are still
      real, working links/a real GET form — on a page that doesn't do
      (2) below, picking one just navigates/submits normally (e.g.
      Audit Logs' full-page reload).

   2) AJAX single-card refresh — ONLY for cards that opt in by wrapping
      themselves in [data-dashboard-card="cardKey"][data-range-param]
      and the page setting window.HC_DASHBOARD_CARD_ENDPOINT before this
      script loads (the 4 role dashboards' Consultations/Tests Trend,
      Patients by Age Group, Tests by Type, and subadmin's Top 5 Common
      Health Conditions). There, picking a range fetches just that
      card's data and updates it in place instead of reloading the
      page. Requires each page's own chart-building script (medical_
      staff_dashboard.js, doctor_dashboard.js, subadmin_dashboard.js,
      medical_technician_dashboard.js) to have already run and stored
      its Chart.js instances on window.HC_DASHBOARD_CHARTS.{cardKey}. A
      card whose update is an HTML swap rather than a chart (subadmin's
      Top 5 Common Health Conditions) marks the swap target with
      [data-card-list-wrap] instead.
   ============================================================= */
document.addEventListener('DOMContentLoaded', function () {

    // ---------------------------------------------------------------
    // 1) Custom Date toggle — every range dropdown on the page.
    // ---------------------------------------------------------------
    document.querySelectorAll('[data-custom-toggle]').forEach(function (customToggle) {
        const dropdown = customToggle.closest('.dropdown');
        const customWrap = dropdown && dropdown.querySelector('[data-custom-range-wrap]');
        if (!dropdown || !customWrap) return;

        const dropdownToggleBtn = dropdown.querySelector('.dropdown-toggle[data-bs-toggle="dropdown"]');

        function repositionDropdown() {
            if (!dropdownToggleBtn || !window.bootstrap) return;
            window.bootstrap.Dropdown.getOrCreateInstance(dropdownToggleBtn).update();
        }

        customToggle.addEventListener('click', function (e) {
            e.preventDefault();
            // Clicking any element inside a Bootstrap dropdown-menu that
            // isn't an <input>/<select>/<textarea>/<form> closes the
            // whole menu by default (its own document-level auto-close
            // handler) — stopping propagation here keeps the dropdown
            // open so this click only toggles the panel.
            e.stopPropagation();

            const expanding = !customWrap.classList.contains('hc-expanded');
            customWrap.style.overflow = 'hidden'; // clipped while animating either direction
            customWrap.classList.toggle('hc-expanded', expanding);
            customToggle.classList.toggle('hc-expanded', expanding);
            repositionDropdown();

            customWrap.addEventListener('transitionend', function onEnd() {
                customWrap.style.overflow = customWrap.classList.contains('hc-expanded') ? 'visible' : 'hidden';
                repositionDropdown();
                customWrap.removeEventListener('transitionend', onEnd);
            });
        });

        // Clicks anywhere else inside the expanded panel (the "From"/
        // "To" labels, the gap between fields, ...) shouldn't close the
        // dropdown either — only Apply or an outside click should.
        customWrap.addEventListener('click', function (e) { e.stopPropagation(); });
    });

    // ---------------------------------------------------------------
    // 2) AJAX single-card refresh — only on pages that opt in.
    // ---------------------------------------------------------------
    const endpoint = window.HC_DASHBOARD_CARD_ENDPOINT;
    if (!endpoint) return;

    document.querySelectorAll('[data-dashboard-card]').forEach(function (card) {
        const cardKey = card.dataset.dashboardCard;
        const rangeParam = card.dataset.rangeParam;
        const labelEl = card.querySelector('[data-range-label]');
        const presetItems = card.querySelectorAll('.dropdown-item[data-range-value]');
        const customForm = card.querySelector('[data-custom-range-form]');
        const customToggle = card.querySelector('[data-custom-toggle]');
        const customToggleLabel = card.querySelector('[data-custom-toggle-label]');
        const dropdownToggleBtn = card.querySelector('.dropdown-toggle[data-bs-toggle="dropdown"]');

        function closeDropdown() {
            if (!dropdownToggleBtn || !window.bootstrap) return;
            window.bootstrap.Dropdown.getOrCreateInstance(dropdownToggleBtn).hide();
        }

        function applyResult(data) {
            if (labelEl) labelEl.textContent = data.rangeLabel;

            const listWrap = card.querySelector('[data-card-list-wrap]');
            if (listWrap) {
                listWrap.innerHTML = data.html;
                return;
            }

            const chart = window.HC_DASHBOARD_CHARTS && window.HC_DASHBOARD_CHARTS[cardKey];
            if (chart) {
                chart.data.labels = data.labels;
                // Multi-line cards (subadmin's Lab & X-ray Requests) send
                // one values array per dataset as "series".
                if (Array.isArray(data.series)) {
                    data.series.forEach(function (values, i) {
                        if (chart.data.datasets[i]) chart.data.datasets[i].data = values;
                    });
                } else {
                    chart.data.datasets[0].data = data.values;
                }
                chart.update();
            }
        }

        function markActive(value) {
            presetItems.forEach(function (i) { i.classList.toggle('active', i.dataset.rangeValue === value); });

            const isCustom = value === 'custom';
            if (customToggle) customToggle.classList.toggle('active', isCustom);
            if (customToggleLabel) customToggleLabel.textContent = 'Custom Date' + (isCustom ? ' (active)' : '');
        }

        function syncUrl(params) {
            if (!window.history || !window.history.replaceState) return;
            const url = new URL(window.location.href);
            Object.keys(params).forEach(function (k) { url.searchParams.set(k, params[k]); });
            window.history.replaceState(null, '', url);
        }

        function refresh(query, activeValue) {
            card.classList.add('hc-dashboard-card-loading');

            fetch(endpoint + '?card=' + encodeURIComponent(cardKey) + '&' + query)
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (!data.success) throw new Error(data.error || 'Could not refresh this card.');
                    applyResult(data);
                    if (activeValue) markActive(activeValue);
                })
                .catch(function (err) {
                    HCDialog.alert(err.message);
                })
                .finally(function () {
                    card.classList.remove('hc-dashboard-card-loading');
                });
        }

        presetItems.forEach(function (item) {
            item.addEventListener('click', function (e) {
                e.preventDefault();
                if (item.classList.contains('active')) return; // already showing this range

                const value = item.dataset.rangeValue;
                syncUrl((function () { const o = {}; o[rangeParam] = value; return o; })());
                refresh(encodeURIComponent(rangeParam) + '=' + encodeURIComponent(value), value);
            });
        });

        if (customForm) {
            customForm.addEventListener('submit', function (e) {
                e.preventDefault();
                const fromInput = customForm.querySelector('input[name$="From"]');
                const toInput = customForm.querySelector('input[name$="To"]');
                if (!fromInput.value || !toInput.value) return;
                if (fromInput.value > toInput.value) {
                    HCDialog.alert('The "from" date must be on or before the "to" date.');
                    return;
                }

                const params = {};
                params[rangeParam] = 'custom';
                params[fromInput.name] = fromInput.value;
                params[toInput.name] = toInput.value;
                syncUrl(params);

                const query = Object.keys(params).map(function (k) { return encodeURIComponent(k) + '=' + encodeURIComponent(params[k]); }).join('&');
                refresh(query, 'custom');
                closeDropdown(); // matches picking a preset option, which also closes the menu
            });
        }
    });
});
