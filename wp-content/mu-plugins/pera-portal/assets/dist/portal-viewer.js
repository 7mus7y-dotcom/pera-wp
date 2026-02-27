(function () {
    const config = window.PeraPortalConfig || {};
    const root = document.getElementById('pera-portal-root');

    if (!root) {
        return;
    }

    const svgPanel = root.querySelector('.pera-portal-panel--svg');
    const svgContainer = root.querySelector('.pera-portal-svg-placeholder');
    const detailsContainer = root.querySelector('.pera-portal-details-placeholder');
    const filtersContainer = root.querySelector('.pera-portal-filters');
    const countsContainer = root.querySelector('.pera-portal-counts');
    const floorSelect = root.querySelector('.pera-portal-floor-select');
    const restBase = String(config.rest_url || '');
    const headers = {
        'X-WP-Nonce': String(config.nonce || ''),
    };

    const urlParams = new URLSearchParams(window.location.search || '');
    const statusKeys = ['available', 'reserved', 'sold'];

    const state = {
        buildingId: Number(urlParams.get('building_id') || config.building_id || 0),
        floorId: Number(urlParams.get('floor_id') || config.floor_id || 0),
        selectedUnitCode: String(urlParams.get('unit') || ''),
    };

    let selectedElement = null;
    let selectedUnit = null;
    let unitsData = [];
    let svgUnits = [];
    let floorsData = [];

    function normalizeStatus(status) {
        const normalized = String(status || '').toLowerCase();
        return statusKeys.indexOf(normalized) >= 0 ? normalized : 'available';
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

            const svg = svgPanel ? svgPanel.querySelector('svg') : null;
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

            applyFilters();
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
