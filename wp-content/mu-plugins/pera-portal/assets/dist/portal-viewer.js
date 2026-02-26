(function () {
    const config = window.PeraPortalConfig || {};
    const root = document.getElementById('pera-portal-root');

    if (!root) {
        return;
    }

    const svgPanel = root.querySelector('.pera-portal-panel--svg');
    const svgContainer = root.querySelector('.pera-portal-svg-placeholder');
    const detailsContainer = root.querySelector('.pera-portal-details-placeholder');
    const floorId = Number(config.floor_id || 0);
    const restBase = String(config.rest_url || '');
    const headers = {
        'X-WP-Nonce': String(config.nonce || ''),
    };

    let selectedElement = null;

    function renderDetails(unit) {
        if (!detailsContainer) {
            return;
        }

        detailsContainer.innerHTML = [
            '<div class="pera-portal-unit-card">',
            '<p><strong>Code:</strong> ' + (unit.unit_code || '-') + '</p>',
            '<p><strong>Type:</strong> ' + (unit.unit_type || '-') + '</p>',
            '<p><strong>Net:</strong> ' + (unit.net_size ?? '-') + '</p>',
            '<p><strong>Gross:</strong> ' + (unit.gross_size ?? '-') + '</p>',
            '<p><strong>Price:</strong> ' + (unit.price ?? '-') + ' ' + (unit.currency || '') + '</p>',
            '<p><strong>Status:</strong> ' + (unit.status || '-') + '</p>',
            '</div>',
        ].join('');
    }

    function setMessage(target, message) {
        if (target) {
            target.textContent = message;
        }
    }

    async function fetchJson(path) {
        const response = await fetch(restBase + path, {
            headers: headers,
            credentials: 'same-origin',
        });

        if (!response.ok) {
            throw new Error('REST request failed: HTTP ' + response.status);
        }

        return response.json();
    }

    async function init() {
        if (!floorId || !restBase) {
            setMessage(svgContainer, 'Floor not selected.');
            setMessage(detailsContainer, 'Select a floor in shortcode, e.g. [pera_portal floor="123"].');
            return;
        }

        try {
            const floor = await fetchJson('floor?floor_id=' + encodeURIComponent(String(floorId)));
            const svgUrl = floor && typeof floor.svg_url === 'string' ? floor.svg_url.trim() : '';

            if (!svgUrl) {
                setMessage(svgContainer, 'No SVG found for this floor.');
                setMessage(detailsContainer, 'Please upload a floor SVG in floor settings.');
                return;
            }

            const units = await fetchJson('units?floor_id=' + encodeURIComponent(String(floorId)));
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

            units.forEach(function (unit) {
                if (!unit || !unit.unit_code) {
                    return;
                }

                const target = svg.querySelector('#' + CSS.escape(String(unit.unit_code)));
                if (!target) {
                    return;
                }

                const statusClass = 'status-' + (unit.status || 'available');
                target.classList.add('unit');
                target.classList.add(statusClass);

                target.addEventListener('click', function (event) {
                    event.preventDefault();

                    if (selectedElement) {
                        selectedElement.classList.remove('is-selected');
                    }

                    target.classList.add('is-selected');
                    selectedElement = target;
                    renderDetails(unit);
                });
            });

            setMessage(detailsContainer, 'Click a highlighted unit to see details.');
        } catch (error) {
            const message = error && error.message ? error.message : 'Unknown error';
            setMessage(svgContainer, 'Unable to load floor plan.');
            setMessage(detailsContainer, 'Unable to load units. ' + message);
        }
    }

    init();
})();
