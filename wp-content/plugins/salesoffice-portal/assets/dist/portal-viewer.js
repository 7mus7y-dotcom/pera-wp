(function () {
    const config = window.SoPortalConfig || {};
    const root = document.getElementById('pera-portal-root');

    if (!root) {
        return;
    }

    const mode = ['internal', 'external', 'investor'].indexOf(String(config.mode || '').toLowerCase()) >= 0
        ? String(config.mode || '').toLowerCase()
        : 'external';

    root.classList.add('mode-' + mode);

    const svgPanel = root.querySelector('.pera-portal-panel--svg');
    const svgContainer = root.querySelector('.pera-portal-svg-placeholder');
    const detailsContainer = root.querySelector('.pera-portal-details-placeholder');
    const filtersContainer = root.querySelector('.pera-portal-filters');
    const countsContainer = root.querySelector('.pera-portal-counts');
    const floorSelect = root.querySelector('.pera-portal-floor-select');
    const shortlistCountEl = root.querySelector('[data-shortlist-count]');
    const shortlistClearBtn = root.querySelector('[data-shortlist-clear]');
    const compareContainer = root.querySelector('.pera-portal-compare');
    const compareBody = root.querySelector('[data-compare-body]');
    const shareContainer = root.querySelector('.pera-portal-share');
    const toastEl = root.querySelector('[data-share-toast]');
    const newWinLink = root.querySelector('[data-share="newwin"]');
    const summaryContainer = root.querySelector('.pera-portal-summary');
    const summaryTotalEl = root.querySelector('[data-summary-total]');
    const summaryPpsEl = root.querySelector('[data-summary-pps]');
    const summarySizeEl = root.querySelector('[data-summary-size]');
    const summaryCountEl = root.querySelector('[data-summary-count]');
    const colorModeButtons = root.querySelectorAll('[data-color-mode]');
    const restBase = String(config.rest_url || '');
    const headers = {
        'X-WP-Nonce': String(config.nonce || ''),
    };

    const urlParams = new URLSearchParams(window.location.search || '');
    const initialUnitsParam = String(urlParams.get('units') || '');
    const statusKeys = ['available', 'reserved', 'sold'];

    function parseUnitsParam(value) {
        if (!value) return [];

        return value
            .split(',')
            .map(function (v) {
                return v.trim();
            })
            .filter(function (v) {
                return v.length > 0;
            });
    }

    const initialShortlistCodes = parseUnitsParam(initialUnitsParam);

    const state = {
        buildingId: Number(urlParams.get('building_id') || config.building_id || 0),
        floorId: Number(urlParams.get('floor_id') || config.floor_id || 0),
        selectedUnitCode: String(urlParams.get('unit') || ''),
        colorMode: mode === 'investor' ? 'price' : 'availability',
    };

    let selectedElement = null;
    let selectedUnit = null;
    let unitsData = [];
    let svgUnits = [];
    let floorsData = [];
    const shortlist = new Map();
    const tooltip = document.createElement('div');
    tooltip.className = 'pera-portal-tooltip';
    tooltip.hidden = true;
    root.appendChild(tooltip);

    function normalizeStatus(status) {
        const normalized = String(status || '').toLowerCase();
        return statusKeys.indexOf(normalized) >= 0 ? normalized : 'available';
    }

    function shouldShowPrice() {
        return true;
    }

    function formatValue(value) {
        return value ?? '—';
    }

    function formatPrice(unit) {
        const price = unit && typeof unit.price === 'number' ? unit.price : null;
        if (price === null) {
            return '—';
        }
        return String(price) + ' ' + (unit.currency || '');
    }

    function getHeatMetric(unit) {
        if (unit && typeof unit.price_per_sqm === 'number') {
            return unit.price_per_sqm;
        }

        return unit && typeof unit.price === 'number' ? unit.price : null;
    }

    function setShortlistCount() {
        if (shortlistCountEl) {
            shortlistCountEl.textContent = String(shortlist.size);
        }
        if (shortlistClearBtn) {
            shortlistClearBtn.disabled = shortlist.size < 1;
        }
    }

    function renderCompareTable() {
        if (!compareContainer || !compareBody) {
            return;
        }

        const rows = [];
        shortlist.forEach(function (unit, key) {
            const viewPlan = unit.detail_plan_url
                ? '<a href="' + unit.detail_plan_url + '" target="_blank" rel="noopener">View plan</a>'
                : '—';

            rows.push([
                '<tr>',
                '<td>' + (unit.unit_code || '—') + '</td>',
                '<td>' + (unit.unit_type || '—') + '</td>',
                '<td>' + formatValue(unit.net_size) + '</td>',
                '<td>' + formatValue(unit.gross_size) + '</td>',
                '<td>' + (shouldShowPrice() ? formatPrice(unit) : '—') + '</td>',
                '<td>' + (unit.status || '—') + '</td>',
                '<td>' + viewPlan + '</td>',
                '<td><button type="button" class="pera-portal-compare__remove" data-remove-unit="' + key + '" aria-label="Remove ' + key + '">×</button></td>',
                '</tr>',
            ].join(''));
        });

        compareBody.innerHTML = rows.join('');
        compareContainer.hidden = shortlist.size < 1;
    }

    function updateShortlistUrl() {
        const params = new URLSearchParams(window.location.search || '');

        if (shortlist.size > 0) {
            const codes = Array.from(shortlist.keys());
            params.set('units', codes.join(','));
        } else {
            params.delete('units');
        }

        const query = params.toString();
        const newUrl = window.location.pathname + (query ? '?' + query : '') + window.location.hash;
        window.history.replaceState({}, '', newUrl);
        syncShareLinks();
    }

    function updateSummary() {
        if (!summaryContainer) {
            return;
        }

        if (shortlist.size === 0) {
            summaryContainer.hidden = true;
            return;
        }

        let totalValue = 0;
        let totalSize = 0;
        let ppsqmSum = 0;
        let ppsqmCount = 0;

        shortlist.forEach(function (unit) {
            if (typeof unit.price === 'number') {
                totalValue += unit.price;
            }

            if (typeof unit.gross_size === 'number') {
                totalSize += unit.gross_size;
            }

            if (typeof unit.price_per_sqm === 'number') {
                ppsqmSum += unit.price_per_sqm;
                ppsqmCount += 1;
            }
        });

        const avgPps = ppsqmCount > 0 ? (ppsqmSum / ppsqmCount) : null;

        if (summaryTotalEl) {
            summaryTotalEl.textContent = totalValue ? totalValue.toLocaleString() : '—';
        }
        if (summaryPpsEl) {
            summaryPpsEl.textContent = avgPps ? avgPps.toFixed(0) : '—';
        }
        if (summarySizeEl) {
            summarySizeEl.textContent = totalSize ? totalSize.toFixed(1) : '—';
        }
        if (summaryCountEl) {
            summaryCountEl.textContent = String(shortlist.size);
        }

        summaryContainer.hidden = false;
    }

    function getCurrentShareUrl() {
        return window.location.href;
    }

    function syncShareLinks() {
        if (newWinLink) {
            newWinLink.href = getCurrentShareUrl();
        }
    }

    function showShareToast(message) {
        if (!toastEl) {
            return;
        }

        toastEl.textContent = message;
        toastEl.hidden = false;

        window.clearTimeout(showShareToast.timeoutId);
        showShareToast.timeoutId = window.setTimeout(function () {
            toastEl.hidden = true;
        }, 1500);
    }

    function copyCurrentLinkToClipboard() {
        const url = getCurrentShareUrl();

        if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
            return navigator.clipboard.writeText(url);
        }

        try {
            const temp = document.createElement('textarea');
            temp.value = url;
            temp.setAttribute('readonly', 'readonly');
            temp.style.position = 'absolute';
            temp.style.left = '-9999px';
            document.body.appendChild(temp);
            temp.select();
            document.execCommand('copy');
            document.body.removeChild(temp);
        } catch (e) {}

        return Promise.resolve();
    }

    function toggleShortlist(unit, element) {
        if (!unit || !unit.unit_code || !element) {
            return;
        }

        const key = String(unit.unit_code);
        if (shortlist.has(key)) {
            shortlist.delete(key);
            element.classList.remove('is-shortlisted');
        } else {
            shortlist.set(key, unit);
            element.classList.add('is-shortlisted');
        }

        setShortlistCount();
        renderCompareTable();
        updateSummary();
        updateShortlistUrl();
    }

    function clearShortlist() {
        shortlist.clear();
        svgUnits.forEach(function (entry) {
            entry.element.classList.remove('is-shortlisted');
        });
        setShortlistCount();
        renderCompareTable();
        updateSummary();
        updateShortlistUrl();
    }

    function showTooltip(unit, event) {
        if (!unit || !event) {
            tooltip.hidden = true;
            return;
        }

        const lines = [
            '<strong>' + (unit.unit_code || '—') + '</strong>',
            '<div>Type: ' + (unit.unit_type || '—') + '</div>',
            '<div>Status: ' + (unit.status || '—') + '</div>',
        ];

        if (shouldShowPrice()) {
            lines.push('<div>Price: ' + formatPrice(unit) + '</div>');

            if (state.colorMode === 'price' && typeof unit.price_per_sqm === 'number') {
                lines.push('<div>PPSQM: ' + String(unit.price_per_sqm) + ' ' + (unit.currency || '') + ' / m²</div>');
            }
        }

        tooltip.innerHTML = lines.join('');
        tooltip.hidden = false;
        tooltip.style.visibility = 'hidden';

        const padding = 12;
        const rect = tooltip.getBoundingClientRect();
        const maxLeft = Math.max(padding, window.innerWidth - rect.width - padding);
        const maxTop = Math.max(padding, window.innerHeight - rect.height - padding);
        const left = Math.min(Math.max(event.clientX + 16, padding), maxLeft);
        const top = Math.min(Math.max(event.clientY + 16, padding), maxTop);

        tooltip.style.left = left + 'px';
        tooltip.style.top = top + 'px';
        tooltip.style.visibility = '';
    }

    function updateColorModeButtons() {
        colorModeButtons.forEach(function (button) {
            const isActive = button.getAttribute('data-color-mode') === state.colorMode;
            button.classList.toggle('is-active', isActive);
        });
    }

    function heatColorFromRatio(ratio) {
        const hue = 120 - Math.round(ratio * 120);
        return 'hsl(' + hue + ' 75% 78%)';
    }

    function applyColorMode() {
        root.classList.toggle('is-color-price', state.colorMode === 'price');

        if (state.colorMode !== 'price') {
            svgUnits.forEach(function (entry) {
                entry.element.style.removeProperty('--unit-heat-fill');
                entry.element.style.removeProperty('--unit-heat-opacity');
            });
            updateColorModeButtons();
            return;
        }

        const priced = unitsData.map(function (unit) {
            return getHeatMetric(unit);
        }).filter(function (value) {
            return value !== null;
        });

        const min = priced.length ? Math.min.apply(Math, priced) : 0;
        const max = priced.length ? Math.max.apply(Math, priced) : 0;
        const spread = max - min;

        svgUnits.forEach(function (entry) {
            const metricValue = getHeatMetric(entry.unit);
            if (metricValue === null) {
                entry.element.style.setProperty('--unit-heat-fill', '#e5e7eb');
                entry.element.style.setProperty('--unit-heat-opacity', '0.35');
                return;
            }

            const ratio = spread > 0 ? (metricValue - min) / spread : 0.5;
            entry.element.style.setProperty('--unit-heat-fill', heatColorFromRatio(ratio));
            entry.element.style.setProperty('--unit-heat-opacity', '0.55');
        });

        updateColorModeButtons();
    }

    function getEnabledStatuses() {
        const enabled = new Set(statusKeys);

        if (!filtersContainer) {
            return enabled;
        }

        enabled.clear();

        filtersContainer.querySelectorAll('[data-status-filter]').forEach(function (input) {
            if (input.checked) {
                enabled.add(normalizeStatus(input.getAttribute('data-status-filter')));
            }
        });

        return enabled;
    }

    function renderCounts() {
        if (!countsContainer) {
            return;
        }

        const enabledStatuses = getEnabledStatuses();
        const totals = {
            available: 0,
            reserved: 0,
            sold: 0,
        };
        let visibleTotal = 0;

        unitsData.forEach(function (unit) {
            totals[unit.status] += 1;
            if (enabledStatuses.has(unit.status)) {
                visibleTotal += 1;
            }
        });

        countsContainer.textContent = 'Total: ' + unitsData.length + ' (Visible: ' + visibleTotal + ') | Available: ' + totals.available + ' | Reserved: ' + totals.reserved + ' | Sold: ' + totals.sold;
    }

    function renderDetails(unit) {
        if (!detailsContainer) {
            return;
        }

        const detailPlanUrl = unit && typeof unit.detail_plan_url === 'string' ? unit.detail_plan_url : '';
        const detailPlanMime = unit && typeof unit.detail_plan_mime === 'string' ? unit.detail_plan_mime.toLowerCase() : '';
        const imageExtensionPattern = /\.(jpg|jpeg|png)(?:$|[?#])/i;
        const isImagePlan = detailPlanMime.indexOf('image/') === 0 || imageExtensionPattern.test(detailPlanUrl);

        const planHtml = detailPlanUrl
            ? [
                '<div class="pera-portal-unit-plan">',
                '<p><strong>Plan:</strong></p>',
                '<a href="' + detailPlanUrl + '" target="_blank" rel="noopener noreferrer" class="button-like">View unit plan</a>',
                isImagePlan ? '<img src="' + detailPlanUrl + '" alt="Unit detail plan preview" loading="lazy" />' : '',
                '</div>',
            ].join('')
            : '<div class="pera-portal-unit-plan"><p><strong>Plan:</strong> -</p></div>';

        detailsContainer.innerHTML = [
            '<div class="pera-portal-unit-card">',
            '<p><strong>Code:</strong> ' + (unit.unit_code || '-') + '</p>',
            '<p><strong>Type:</strong> ' + (unit.unit_type || '-') + '</p>',
            '<p><strong>Net:</strong> ' + (unit.net_size ?? '-') + '</p>',
            '<p><strong>Gross:</strong> ' + (unit.gross_size ?? '-') + '</p>',
            '<p><strong>Price:</strong> ' + (unit.price ?? '-') + ' ' + (unit.currency || '') + '</p>',
            '<p><strong>Status:</strong> ' + (unit.status || '-') + '</p>',
            planHtml,
            '</div>',
        ].join('');
    }

    function setMessage(target, message) {
        if (target) {
            target.textContent = message;
        }
    }

    function clearSelection(message) {
        if (selectedElement) {
            selectedElement.classList.remove('is-selected');
        }

        selectedElement = null;
        selectedUnit = null;

        if (message) {
            setMessage(detailsContainer, message);
        }
    }

    function applyFilters() {
        const enabledStatuses = getEnabledStatuses();

        svgUnits.forEach(function (entry) {
            const isVisible = enabledStatuses.has(entry.status);
            entry.element.classList.toggle('is-hidden', !isVisible);
        });

        if (selectedElement && selectedUnit && !enabledStatuses.has(selectedUnit.status)) {
            clearSelection('Select a highlighted unit to see details.');
        }

        renderCounts();
    }

    function findUnitElement(startNode) {
        let current = startNode;
        let depth = 0;

        while (current && depth <= 5) {
            if (current.classList && current.classList.contains('unit')) {
                return current;
            }

            current = current.parentElement;
            depth += 1;
        }

        return null;
    }

    function updateUrl(clearUnit) {
        const params = new URLSearchParams(window.location.search || '');

        if (state.buildingId > 0) {
            params.set('building_id', String(state.buildingId));
        } else {
            params.delete('building_id');
        }

        if (state.floorId > 0) {
            params.set('floor_id', String(state.floorId));
        } else {
            params.delete('floor_id');
        }

        if (clearUnit) {
            params.delete('unit');
            state.selectedUnitCode = '';
        } else if (state.selectedUnitCode) {
            params.set('unit', state.selectedUnitCode);
        } else {
            params.delete('unit');
        }

        const next = window.location.pathname + (params.toString() ? '?' + params.toString() : '') + window.location.hash;
        window.history.replaceState({}, '', next);
        syncShareLinks();
    }

    async function fetchJson(path) {
        const response = await fetch(restBase + path, {
            headers: headers,
            credentials: 'same-origin',
        });

        if (!response.ok) {
            const error = new Error('REST request failed: HTTP ' + response.status);
            error.status = response.status;
            throw error;
        }

        return response.json();
    }

    function populateFloorSelect() {
        if (!floorSelect) {
            return;
        }

        floorSelect.innerHTML = '';

        floorsData.forEach(function (floor) {
            const option = document.createElement('option');
            const number = String(floor.floor_number || '').trim();
            option.value = String(floor.id || '');
            option.textContent = number ? ('Floor ' + number + ' — ' + (floor.title || '')) : (floor.title || '');
            option.selected = Number(floor.id) === state.floorId;
            floorSelect.appendChild(option);
        });

        floorSelect.disabled = floorsData.length <= 1;
    }

    function selectUnit(unitCode) {
        if (!unitCode) {
            return;
        }

        const match = svgUnits.find(function (entry) {
            return String(entry.unit.unit_code || '') === String(unitCode);
        });

        if (!match || !match.element || match.element.classList.contains('is-hidden')) {
            return;
        }

        if (selectedElement) {
            selectedElement.classList.remove('is-selected');
        }

        selectedElement = match.element;
        selectedUnit = match.unit;
        selectedElement.classList.add('is-selected');
        renderDetails(match.unit);
    }

    async function loadFloor() {
        if (!state.floorId || !restBase) {
            setMessage(svgContainer, 'Floor not selected.');
            setMessage(detailsContainer, state.buildingId ? 'No floor found for this building.' : 'Select a building in shortcode.');
            return;
        }

        clearSelection('Loading floor data...');
        clearShortlist();

        try {
            const floor = await fetchJson('floor?floor_id=' + encodeURIComponent(String(state.floorId)));
            const svgUrl = floor && typeof floor.svg_url === 'string' ? floor.svg_url.trim() : '';

            if (!svgUrl) {
                setMessage(svgContainer, 'No SVG found for this floor.');
                setMessage(detailsContainer, 'Please upload a floor SVG in floor settings.');
                return;
            }

            const units = await fetchJson('units?floor_id=' + encodeURIComponent(String(state.floorId)));
            const svgResponse = await fetch(svgUrl, {
                credentials: 'same-origin',
            });

            if (!svgResponse.ok) {
                throw new Error('SVG request failed: HTTP ' + svgResponse.status);
            }

            if (svgContainer) {
                svgContainer.innerHTML = await svgResponse.text();
            }

            const svg = svgContainer ? svgContainer.querySelector('svg') : null;
            if (!svg) {
                setMessage(detailsContainer, 'SVG loaded but no root <svg> found.');
                return;
            }

            const normalizedUnits = (units || []).filter(function (unit) {
                return unit && unit.unit_code;
            }).map(function (unit) {
                return Object.assign({}, unit, { status: normalizeStatus(unit.status) });
            });

            const matchedSvgUnits = [];

            normalizedUnits.forEach(function (unit) {
                const target = svg.querySelector('#' + CSS.escape(String(unit.unit_code)));
                if (!target || !target.classList) {
                    return;
                }

                const unitStatus = unit.status;

                target.classList.add('unit');
                target.classList.add('status-' + unitStatus);
                target.dataset.unitId = String(unit.id || '');
                target.dataset.unitCode = String(unit.unit_code || '');
                target.dataset.status = unitStatus;
                target.style.pointerEvents = 'all';

                matchedSvgUnits.push({
                    unit: unit,
                    element: target,
                    status: unitStatus,
                });
            });

            unitsData = normalizedUnits;
            svgUnits = matchedSvgUnits;

            if (initialShortlistCodes.length) {
                initialShortlistCodes.forEach(function (code) {
                    const match = svgUnits.find(function (entry) {
                        return String(entry.unit.unit_code || '') === code;
                    });

                    if (match) {
                        shortlist.set(code, match.unit);
                        match.element.classList.add('is-shortlisted');
                    }
                });

                setShortlistCount();
                renderCompareTable();
                updateSummary();
                updateShortlistUrl();

                if (shortlist.size > 0 && compareContainer) {
                    compareContainer.hidden = false;
                }
            }

            if (!svgUnits.length) {
                setMessage(detailsContainer, 'No SVG IDs matched unit codes. Check unit_code vs SVG element id. Your SVG unit shapes must be closed and filled (can be transparent). Stroke-only outlines won’t be easy to click.');
                renderCounts();
                return;
            }

            svg.addEventListener('click', function (event) {
                const target = findUnitElement(event.target);
                if (!target || target.classList.contains('is-hidden')) {
                    return;
                }

                const match = svgUnits.find(function (entry) {
                    return entry.element === target;
                });
                if (!match) {
                    return;
                }

                event.preventDefault();

                if (event.shiftKey === true) {
                    toggleShortlist(match.unit, target);
                    return;
                }

                if (selectedElement) {
                    selectedElement.classList.remove('is-selected');
                }

                target.classList.add('is-selected');
                selectedElement = target;
                selectedUnit = match.unit;
                state.selectedUnitCode = String(match.unit.unit_code || '');
                updateUrl(false);
                renderDetails(match.unit);
            });

            svg.addEventListener('mousemove', function (event) {
                const target = findUnitElement(event.target);
                if (!target || target.classList.contains('is-hidden')) {
                    tooltip.hidden = true;
                    return;
                }

                const match = svgUnits.find(function (entry) {
                    return entry.element === target;
                });

                if (!match) {
                    tooltip.hidden = true;
                    return;
                }

                showTooltip(match.unit, event);
            });

            if (svgPanel) {
                svgPanel.addEventListener('mouseleave', function () {
                    tooltip.hidden = true;
                });
            }

            applyFilters();
            applyColorMode();
            if (state.selectedUnitCode) {
                selectUnit(state.selectedUnitCode);
            }

            if (!selectedUnit) {
                setMessage(detailsContainer, 'Click a highlighted unit to see details. Your SVG unit shapes must be closed and filled (can be transparent). Stroke-only outlines won’t be easy to click.');
            }
        } catch (error) {
            const message = error && error.message ? error.message : 'Unknown error';

            if (error && (error.status === 401 || error.status === 403)) {
                setMessage(detailsContainer, 'Not authorized. Ensure you are logged in and have portal access.');
                setMessage(svgContainer, 'Unable to load floor plan. ' + message);
                return;
            }

            setMessage(svgContainer, 'Unable to load floor plan. ' + message);
            setMessage(detailsContainer, 'Unable to load units. ' + message);
        }
    }

    async function init() {
        if (filtersContainer) {
            filtersContainer.querySelectorAll('[data-status-filter]').forEach(function (input) {
                input.addEventListener('change', applyFilters);
            });
        }

        if (shortlistClearBtn) {
            shortlistClearBtn.addEventListener('click', clearShortlist);
        }

        if (shareContainer) {
            shareContainer.addEventListener('click', function (event) {
                const trigger = event.target && event.target.closest ? event.target.closest('[data-share]') : null;
                if (!trigger) {
                    return;
                }

                const action = String(trigger.getAttribute('data-share') || '');

                if (action === 'whatsapp') {
                    const shareUrl = 'https://wa.me/?text=' + encodeURIComponent(getCurrentShareUrl());
                    window.open(shareUrl, '_blank', 'noopener');
                    return;
                }

                if (action === 'copy') {
                    event.preventDefault();
                    copyCurrentLinkToClipboard().then(function () {
                        showShareToast('Copied');
                    }).catch(function () {
                        showShareToast('Copy failed');
                    });
                    return;
                }

                if (action === 'print') {
                    event.preventDefault();
                    window.print();
                    return;
                }

                if (action === 'newwin') {
                    syncShareLinks();
                }
            });
        }

        if (compareBody) {
            compareBody.addEventListener('click', function (event) {
                const button = event.target && event.target.closest ? event.target.closest('[data-remove-unit]') : null;
                if (!button) {
                    return;
                }

                const unitCode = String(button.getAttribute('data-remove-unit') || '');
                shortlist.delete(unitCode);
                const match = svgUnits.find(function (entry) {
                    return String(entry.unit.unit_code || '') === unitCode;
                });
                if (match) {
                    match.element.classList.remove('is-shortlisted');
                }

                setShortlistCount();
                renderCompareTable();
                updateSummary();
                updateShortlistUrl();
            });
        }

        colorModeButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                const value = button.getAttribute('data-color-mode');
                if (value !== 'availability' && value !== 'price') {
                    return;
                }

                state.colorMode = value;
                applyColorMode();
            });
        });

        setShortlistCount();
        renderCompareTable();
        updateSummary();
        root.classList.toggle('is-color-price', state.colorMode === 'price');
        updateColorModeButtons();
        syncShareLinks();

        if (!restBase) {
            setMessage(svgContainer, 'Portal configuration is missing.');
            setMessage(detailsContainer, 'Unable to load portal data.');
            return;
        }

        if (state.buildingId > 0) {
            try {
                floorsData = await fetchJson('floors?building_id=' + encodeURIComponent(String(state.buildingId)));
                floorsData = Array.isArray(floorsData) ? floorsData : [];
                if (!state.floorId && floorsData.length) {
                    state.floorId = Number(floorsData[0].id || 0);
                }
                populateFloorSelect();
            } catch (error) {
                const message = error && error.message ? error.message : 'Unknown error';
                if (error && (error.status === 401 || error.status === 403)) {
                    setMessage(detailsContainer, 'Not authorized. Ensure you are logged in and have portal access.');
                    setMessage(svgContainer, 'Unable to load floors. ' + message);
                    return;
                }
                setMessage(svgContainer, 'Unable to load floors. ' + message);
                setMessage(detailsContainer, 'Unable to load floor list. ' + message);
                return;
            }

            if (floorSelect) {
                floorSelect.addEventListener('change', function () {
                    state.floorId = Number(floorSelect.value || 0);
                    updateUrl(true);
                    loadFloor();
                });
            }
        } else {
            if (floorSelect) {
                floorSelect.disabled = true;
            }
            if (!state.floorId) {
                setMessage(svgContainer, 'Floor not selected.');
                setMessage(detailsContainer, 'Select a building in shortcode.');
                return;
            }
        }

        updateUrl(false);
        loadFloor();
    }

    init();
})();
