(function () {
    const config = window.PeraPortalConfig || {};
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
    const planContainer = root.querySelector('.pera-portal-plan-placeholder');
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
    const quoteToolsContainer = root.querySelector('[data-quote-tools]');
    const colorModeButtons = root.querySelectorAll('[data-color-mode]');
    const restBase = typeof config.rest_url === 'string' ? config.rest_url : '';
    const headers = {
        'X-WP-Nonce': String(config.nonce || ''),
    };

    const urlParams = new URLSearchParams(window.location.search || '');
    const initialUnitsParam = String(urlParams.get('units') || '');
    const statusKeys = ['available', 'reserved', 'sold'];

    function preparePrintPlanFallback() {
        const printScope = document.getElementById('portal-print-scope');
        const scope = printScope || root;
        if (!scope || !scope.querySelector) {
            return;
        }

        const canvas = scope.querySelector('.portal-print-section--plan canvas');
        if (!canvas || scope.querySelector('img[data-print-temp="1"]')) {
            return;
        }

        try {
            const dataUrl = canvas.toDataURL('image/png');
            if (!dataUrl) {
                return;
            }

            const img = document.createElement('img');
            img.src = dataUrl;
            img.alt = 'Apartment plan print preview';
            img.className = 'portal-print-canvas-fallback';
            img.dataset.printTemp = '1';
            canvas.insertAdjacentElement('afterend', img);
        } catch (error) {
            return;
        }
    }

    window.addEventListener('afterprint', function () {
        document.querySelectorAll('img[data-print-temp="1"]').forEach(function (node) {
            if (node && node.parentNode) {
                node.parentNode.removeChild(node);
            }
        });
    });

    function safeText(v) {
        return (v == null) ? '' : String(v);
    }

    function isSafeHttpUrl(u) {
        const value = safeText(u).trim();
        if (!value) {
            return false;
        }

        try {
            const parsed = new URL(value, window.location.origin);
            return parsed.protocol === 'http:' || parsed.protocol === 'https:';
        } catch (e) {
            return false;
        }
    }

    function safeUrl(u) {
        return isSafeHttpUrl(u) ? new URL(safeText(u).trim(), window.location.origin).toString() : '';
    }

    function stripSvgDangerous(svgEl) {
        if (!svgEl || !svgEl.querySelectorAll) {
            return;
        }

        svgEl.querySelectorAll('script, foreignObject').forEach(function (node) {
            if (node.parentNode) {
                node.parentNode.removeChild(node);
            }
        });

        const allNodes = [svgEl].concat(Array.from(svgEl.querySelectorAll('*')));

        allNodes.forEach(function (node) {
            Array.from(node.attributes || []).forEach(function (attr) {
                const attrName = safeText(attr.name).toLowerCase();
                const attrValue = safeText(attr.value).trim();

                if (attrName.indexOf('on') === 0) {
                    node.removeAttribute(attr.name);
                    return;
                }

                if (attrName === 'href' || attrName === 'xlink:href' || attrName === 'src') {
                    if (/^\s*javascript:/i.test(attrValue)) {
                        node.removeAttribute(attr.name);
                    }
                }

                if (attrName === 'style') {
                    node.removeAttribute(attr.name);
                }
            });
        });
    }

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

        compareBody.textContent = '';

        shortlist.forEach(function (unit, key) {
            const tr = document.createElement('tr');

            [
                safeText(unit.unit_code || '—'),
                safeText(unit.unit_type || '—'),
                safeText(formatValue(unit.net_size)),
                safeText(formatValue(unit.gross_size)),
                safeText(shouldShowPrice() ? formatPrice(unit) : '—'),
                safeText(unit.status || '—'),
            ].forEach(function (value) {
                const td = document.createElement('td');
                td.textContent = value;
                tr.appendChild(td);
            });

            const planTd = document.createElement('td');
            const planUrl = safeUrl(unit.detail_plan_url);
            if (planUrl) {
                const link = document.createElement('a');
                link.href = planUrl;
                link.target = '_blank';
                link.rel = 'noopener noreferrer';
                link.textContent = 'View plan';
                planTd.appendChild(link);
            } else {
                planTd.textContent = '—';
            }
            tr.appendChild(planTd);

            const actionTd = document.createElement('td');
            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'pera-portal-compare__remove';
            removeBtn.setAttribute('data-remove-unit', safeText(key));
            removeBtn.setAttribute('aria-label', 'Remove ' + safeText(key));
            removeBtn.textContent = '×';
            actionTd.appendChild(removeBtn);
            tr.appendChild(actionTd);

            compareBody.appendChild(tr);
        });
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

        const firstLine = safeText(unit.unit_code || '—');
        const lines = [
            'Type: ' + safeText(unit.unit_type || '—'),
            'Status: ' + safeText(unit.status || '—'),
        ];

        if (shouldShowPrice()) {
            lines.push('Price: ' + safeText(formatPrice(unit)));

            if (state.colorMode === 'price' && typeof unit.price_per_sqm === 'number') {
                lines.push('PPSQM: ' + safeText(unit.price_per_sqm) + ' ' + safeText(unit.currency || '') + ' / m²');
            }
        }

        tooltip.textContent = '';
        const firstLineEl = document.createElement('strong');
        firstLineEl.textContent = firstLine;
        tooltip.appendChild(firstLineEl);

        lines.forEach(function (line) {
            const lineEl = document.createElement('div');
            lineEl.textContent = line;
            tooltip.appendChild(lineEl);
        });
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

    async function createQuote(payload) {
        const response = await fetch(restBase + 'quotes', {
            method: 'POST',
            headers: Object.assign({'Content-Type': 'application/json'}, headers),
            credentials: 'same-origin',
            body: JSON.stringify(payload),
        });

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data && data.message ? data.message : 'Unable to create quote.');
        }

        return data;
    }

    function renderQuoteTools(unit) {
        if (!quoteToolsContainer) {
            return;
        }

        if (!unit || !unit.id) {
            quoteToolsContainer.hidden = true;
            quoteToolsContainer.textContent = '';
            return;
        }

        quoteToolsContainer.hidden = false;
        quoteToolsContainer.innerHTML = '';

        const wrap = document.createElement('div');
        wrap.className = 'pera-portal-quote-box';

        const heading = document.createElement('h4');
        heading.textContent = 'Create Client Quote';
        wrap.appendChild(heading);

        const form = document.createElement('form');
        form.className = 'pera-portal-quote-form';
        form.innerHTML = ''
            + '<label>Quoted Price <input name="quoted_price" type="number" step="0.01" required></label>'
            + '<label>Currency <input name="currency" type="text" required></label>'
            + '<label>Expiry <input name="expires_at" type="datetime-local" required></label>'
            + '<label>Consultant Note <textarea name="consultant_note" rows="2"></textarea></label>'
            + '<label>Client Name <input name="client_name" type="text"></label>'
            + '<label>Client Email <input name="client_email" type="email"></label>'
            + '<label>Client Phone <input name="client_phone" type="text"></label>'
            + '<button type="submit" class="button-like">Create Client Quote</button>';

        const priceInput = form.querySelector('input[name="quoted_price"]');
        const currencyInput = form.querySelector('input[name="currency"]');
        if (priceInput && unit.price != null) {
            priceInput.value = String(unit.price);
        }
        if (currencyInput && unit.currency) {
            currencyInput.value = String(unit.currency);
        }

        const result = document.createElement('div');
        result.className = 'pera-portal-quote-result';

        form.addEventListener('submit', async function (event) {
            event.preventDefault();
            result.textContent = 'Creating quote…';

            const fd = new FormData(form);
            const payload = {
                unit_id: unit.id,
                quoted_price: Number(fd.get('quoted_price') || 0),
                currency: String(fd.get('currency') || ''),
                expires_at: String(fd.get('expires_at') || ''),
                consultant_note: String(fd.get('consultant_note') || ''),
                client_name: String(fd.get('client_name') || ''),
                client_email: String(fd.get('client_email') || ''),
                client_phone: String(fd.get('client_phone') || ''),
                source_context: 'portal',
            };

            try {
                const created = await createQuote(payload);
                result.innerHTML = ''
                    + '<p><strong>Reference:</strong> ' + safeText(created.quote_reference || '') + '</p>'
                    + '<p><a href="' + safeText(created.public_url || '#') + '" target="_blank" rel="noopener noreferrer">Open Quote</a></p>'
                    + '<p><button type="button" data-copy-quote="1">Copy Link</button></p>'
                    + (created.warning ? '<p>' + safeText(created.warning) + '</p>' : '');

                const copyBtn = result.querySelector('[data-copy-quote="1"]');
                if (copyBtn) {
                    copyBtn.addEventListener('click', async function () {
                        try {
                            await navigator.clipboard.writeText(String(created.public_url || ''));
                            copyBtn.textContent = 'Copied';
                        } catch (error) {
                            copyBtn.textContent = 'Copy failed';
                        }
                    });
                }
            } catch (error) {
                result.textContent = String(error && error.message ? error.message : 'Unable to create quote.');
            }
        });

        wrap.appendChild(form);
        wrap.appendChild(result);
        quoteToolsContainer.appendChild(wrap);
    }

    function renderPlanPlaceholder(message) {
        if (!planContainer) {
            return;
        }

        planContainer.textContent = '';

        const emptyState = document.createElement('div');
        emptyState.className = 'pera-portal-plan-state';
        emptyState.textContent = message;
        planContainer.appendChild(emptyState);
    }

    function renderDetails(unit) {
        if (!detailsContainer) {
            return;
        }

        detailsContainer.textContent = '';
        if (planContainer) {
            planContainer.textContent = '';
        }

        const card = document.createElement('section');
        card.className = 'pera-portal-unit-card';

        const codeValue = unit && unit.unit_code ? unit.unit_code : '-';
        const typeValue = unit && unit.unit_type ? unit.unit_type : '-';
        const netValue = unit && unit.net_size != null ? unit.net_size : '-';
        const grossValue = unit && unit.gross_size != null ? unit.gross_size : '-';
        const statusValue = unit && unit.status ? unit.status : '-';
        const priceValue = (unit && unit.price != null ? unit.price : '-') + ' ' + safeText(unit && unit.currency ? unit.currency : '');

        const summary = document.createElement('div');
        summary.className = 'pera-portal-unit-card__summary';

        const identity = document.createElement('div');
        identity.className = 'pera-portal-unit-card__identity';
        const codeLabel = document.createElement('p');
        codeLabel.className = 'pera-portal-unit-card__eyebrow';
        codeLabel.textContent = 'Selected Unit';
        const codeText = document.createElement('h4');
        codeText.className = 'pera-portal-unit-card__code';
        codeText.textContent = safeText(codeValue);
        const typeText = document.createElement('p');
        typeText.className = 'pera-portal-unit-card__type';
        typeText.textContent = 'Type: ' + safeText(typeValue);
        identity.appendChild(codeLabel);
        identity.appendChild(codeText);
        identity.appendChild(typeText);

        const statusBadge = document.createElement('span');
        const normalizedStatus = normalizeStatus(statusValue);
        statusBadge.className = 'pera-portal-unit-card__status pera-portal-unit-card__status--' + normalizedStatus;
        statusBadge.textContent = safeText(statusValue);

        summary.appendChild(identity);
        summary.appendChild(statusBadge);
        card.appendChild(summary);

        const pricing = document.createElement('div');
        pricing.className = 'pera-portal-unit-card__price';
        const priceLabel = document.createElement('p');
        priceLabel.textContent = 'Price';
        const priceText = document.createElement('p');
        priceText.className = 'pera-portal-unit-card__price-value';
        priceText.textContent = safeText(priceValue);
        pricing.appendChild(priceLabel);
        pricing.appendChild(priceText);
        card.appendChild(pricing);

        const facts = document.createElement('dl');
        facts.className = 'pera-portal-unit-card__facts';

        function appendFact(label, value) {
            const term = document.createElement('dt');
            term.textContent = label;
            const desc = document.createElement('dd');
            desc.textContent = safeText(value == null || value === '' ? '-' : value);
            facts.appendChild(term);
            facts.appendChild(desc);
        }

        appendFact('Net m²', netValue);
        appendFact('Gross m²', grossValue);
        appendFact('Code', codeValue);
        appendFact('Type', typeValue);

        card.appendChild(facts);

        const detailPlanUrl = unit && typeof unit.detail_plan_url === 'string' ? unit.detail_plan_url : '';
        const detailPlanMime = unit && typeof unit.detail_plan_mime === 'string' ? unit.detail_plan_mime.toLowerCase() : '';
        const imageExtensionPattern = /\.(jpg|jpeg|png)(?:$|[?#])/i;
        const isImagePlan = detailPlanMime.indexOf('image/') === 0 || imageExtensionPattern.test(detailPlanUrl);
        const safePlanUrl = safeUrl(detailPlanUrl);

        const planWrap = document.createElement('div');
        planWrap.className = 'pera-portal-unit-plan';

        const planHeader = document.createElement('div');
        planHeader.className = 'pera-portal-unit-plan__header';

        const planHeading = document.createElement('p');
        planHeading.className = 'pera-portal-unit-plan__title';
        planHeading.textContent = 'Plan preview';

        const planContext = document.createElement('p');
        planContext.className = 'pera-portal-unit-plan__context';
        planContext.textContent = 'Unit ' + safeText(codeValue);

        planHeader.appendChild(planHeading);
        planHeader.appendChild(planContext);

        if (safePlanUrl) {
            const planLink = document.createElement('a');
            planLink.href = safePlanUrl;
            planLink.target = '_blank';
            planLink.rel = 'noopener noreferrer';
            planLink.className = 'button-like pera-portal-unit-plan__action';
            planLink.textContent = 'Open full plan';
            planHeader.appendChild(planLink);

            if (isImagePlan) {
                const previewWrap = document.createElement('div');
                previewWrap.className = 'pera-portal-unit-plan__preview';
                const preview = document.createElement('img');
                preview.src = safePlanUrl;
                preview.alt = 'Unit detail plan preview';
                preview.loading = 'lazy';
                previewWrap.appendChild(preview);
                planWrap.appendChild(previewWrap);
            }
        } else {
            const empty = document.createElement('p');
            empty.className = 'pera-portal-unit-plan__empty';
            empty.textContent = 'No plan file is currently attached for this selected unit.';
            planWrap.appendChild(empty);
        }

        planWrap.insertAdjacentElement('afterbegin', planHeader);

        detailsContainer.appendChild(card);

        if (planContainer) {
            planContainer.appendChild(planWrap);
        } else {
            detailsContainer.appendChild(planWrap);
        }

        renderQuoteTools(unit);
    }

    function setMessage(target, message) {
        if (target) {
            target.textContent = message;
        }
    }

    function renderSvgWarning(message) {
        if (!svgContainer) {
            return;
        }

        const existing = svgContainer.querySelector('.pera-portal-svg-warning');
        if (existing && existing.parentNode) {
            existing.parentNode.removeChild(existing);
        }

        if (!message) {
            return;
        }

        const warning = document.createElement('p');
        warning.className = 'pera-portal-svg-warning';
        warning.textContent = message;
        svgContainer.insertAdjacentElement('afterbegin', warning);
    }

    function clearSelection(message) {
        if (selectedElement) {
            selectedElement.classList.remove('is-selected');
        }

        selectedElement = null;
        selectedUnit = null;

        if (planContainer) {
            renderPlanPlaceholder('Select a unit to review its plan and open the full layout.');
        }

        if (message) {
            setMessage(detailsContainer, message);
        }

        renderQuoteTools(null);
    }

    function applyFilters() {
        const enabledStatuses = getEnabledStatuses();

        svgUnits.forEach(function (entry) {
            const isVisible = enabledStatuses.has(entry.status);
            entry.element.classList.toggle('is-hidden', !isVisible);
        });

        if (selectedElement && selectedUnit && !enabledStatuses.has(selectedUnit.status)) {
            clearSelection('Select a visible unit to update details.');
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

        floorSelect.textContent = '';

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
        if (!restBase) {
            setMessage(svgContainer, 'Portal configuration is missing.');
            setMessage(detailsContainer, 'Unable to load portal data.');
            return;
        }

        if (!state.floorId) {
            setMessage(svgContainer, 'Select a floor to load the plan.');
            setMessage(detailsContainer, state.buildingId ? 'No floors are currently available for this building.' : 'Select a building to begin.');
            return;
        }

        clearSelection('Loading floor and unit availability...');
        clearShortlist();
        renderSvgWarning('');

        try {
            const units = await fetchJson('units?floor_id=' + encodeURIComponent(String(state.floorId)) + (state.buildingId > 0 ? '&building_id=' + encodeURIComponent(String(state.buildingId)) : '') + '&mode=' + encodeURIComponent(mode));
            const selectedFloor = floorsData.find(function (floor) {
                return Number(floor && floor.id) === state.floorId;
            }) || null;
            const svgVersion = selectedFloor && selectedFloor.svg_version ? String(selectedFloor.svg_version) : '';
            const svgRequestPath = 'floor?floor_id=' + encodeURIComponent(String(state.floorId)) + (svgVersion ? '&ver=' + encodeURIComponent(svgVersion) : '');
            const svgResponse = await fetch(restBase + svgRequestPath, {
                credentials: 'same-origin',
            });

            if (!svgResponse.ok) {
                const svgError = new Error('SVG request failed: HTTP ' + svgResponse.status);
                svgError.status = svgResponse.status;
                svgError.isSvgRequest = true;
                throw svgError;
            }

            const svgSource = safeText(svgResponse.headers.get('X-Pera-Portal-SVG-Source')).toLowerCase();
            const svgWarningCode = safeText(svgResponse.headers.get('X-Pera-Portal-Warning')).toLowerCase();
            const svgText = await svgResponse.text();
            const parser = new DOMParser();
            const svgDoc = parser.parseFromString(svgText, 'image/svg+xml');
            const svgEl = svgDoc.documentElement;

            if (!svgEl || safeText(svgEl.nodeName).toLowerCase() !== 'svg') {
                const pe = svgDoc.querySelector && svgDoc.querySelector('parsererror');
                const detail = pe ? safeText(pe.textContent).trim().slice(0, 300) : svgText.slice(0, 300);
                throw new Error('SVG parse failed (root=' + safeText(svgEl && svgEl.nodeName) + '): ' + detail);
            }

            stripSvgDangerous(svgEl);

            if (svgContainer) {
                svgContainer.textContent = '';
                svgContainer.appendChild(document.importNode(svgEl, true));
                if (svgSource === 'fixture' || svgWarningCode === 'floor_svg_missing_using_fixture') {
                    renderSvgWarning('Showing fallback floor plan while the main plan is being prepared.');
                }
            }

            const svg = svgContainer ? svgContainer.querySelector('svg') : null;
            if (!svg) {
                setMessage(detailsContainer, 'The floor plan could not be displayed at this time.');
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
                setMessage(detailsContainer, 'Unit details are currently unavailable for this floor. Please try another floor.');
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
                setMessage(detailsContainer, 'Step 2: Select any highlighted unit to view details and plan options.');
            }
        } catch (error) {
            const message = error && error.message ? error.message : 'Unknown error';

            if (error && (error.status === 401 || error.status === 403)) {
                setMessage(detailsContainer, 'Not authorized. Ensure you are logged in and have portal access.');
                setMessage(svgContainer, 'Unable to load floor plan. ' + message);
                return;
            }

            if (error && error.isSvgRequest === true && error.status === 404) {
                setMessage(svgContainer, 'Floor plan data is missing for this floor.');
                setMessage(detailsContainer, 'Unable to load floor plan for the selected floor.');
                return;
            }

            if (error && error.isSvgRequest === true && safeText(error.message).indexOf('SVG request failed') === 0) {
                setMessage(svgContainer, 'Unable to load floor plan. ' + message);
                setMessage(detailsContainer, 'Units loaded, but floor plan failed to load.');
                return;
            }

            setMessage(svgContainer, 'Unable to load floor plan. ' + message);
            setMessage(detailsContainer, 'Unable to load units. ' + message);
        }
    }

    renderPlanPlaceholder('Select a unit to review its plan and open the full layout.');

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
                    preparePrintPlanFallback();
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
                floorsData = await fetchJson('floors?building_id=' + encodeURIComponent(String(state.buildingId)) + '&mode=' + encodeURIComponent(mode));
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
                setMessage(svgContainer, 'Select a floor to load the plan.');
                setMessage(detailsContainer, 'Select a building to begin.');
                return;
            }
        }

        updateUrl(false);
        loadFloor();
    }

    init();
})();
