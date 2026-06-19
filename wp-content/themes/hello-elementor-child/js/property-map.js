(() => {
  const initPropertyMap = () => {
    const mapEl = document.getElementById('property-map');
    if (!mapEl || !window.google || !window.google.maps) {
      return;
    }

    if (!document.getElementById('pera-map-overlay-style')) {
      const overlayStyle = document.createElement('style');
      overlayStyle.id = 'pera-map-overlay-style';
      overlayStyle.textContent = `
        @keyframes peraMapFadeIn {
          from { opacity: 0; transform: translate(-50%, -100%) scale(0.96); }
          to { opacity: 1; transform: translate(-50%, -110%) scale(1); }
        }
      `;
      document.head.appendChild(overlayStyle);
    }

    let markersData = [];
    const markersRawEl = document.getElementById('property-map-data');
    try {
      markersData = markersRawEl ? JSON.parse(markersRawEl.textContent) : [];
    } catch (error) {
      console.error('[PropertyMap] Failed to parse markers JSON.', error);
      markersData = [];
    }

    const selectedPanel = document.querySelector('.property-map__selected');
    const resultsEl = document.getElementById('property-map-results');
    const filtersForm = document.getElementById('property-map-filters');
    const countEl = document.getElementById('property-map-count');
    const emptyEl = document.getElementById('property-map-empty');
    const layoutEl = document.querySelector('.property-map-layout');
    const mapConfig = window.peraPropertyMap || {};
    const markerIconUrl = mapConfig.marker_icon || null;
    const defaultCenter = { lat: 41.0082, lng: 28.9784 };
    const map = new window.google.maps.Map(mapEl, {
      center: defaultCenter,
      zoom: 12,
      mapTypeControl: false,
      streetViewControl: false,
    });

    const trackEvent = (eventName, params = {}) => {
      document.dispatchEvent(new CustomEvent('pera:track', {
        detail: Object.assign({
          event_name: eventName,
          page_location: window.location.href,
          page_title: document.title || ''
        }, params)
      }));
    };

    const setResultsHtml = (html) => {
      if (resultsEl) {
        resultsEl.innerHTML = html;
      }
    };

    const showMessage = (message) => {
      setResultsHtml(`<p class="no-results">${message}</p>`);
    };

    const escapeHtml = (value) => {
      const div = document.createElement('div');
      div.textContent = value == null ? '' : String(value);
      return div.innerHTML;
    };

    let activeController = null;

    const loadMarkerCard = (propertyId) => {
      if (!propertyId || !mapConfig.ajax_url || !mapConfig.action || !mapConfig.nonce) {
        showMessage('Unable to load listing details right now.');
        return;
      }

      if (activeController) {
        activeController.abort();
      }

      const controller = new AbortController();
      activeController = controller;

      const body = new URLSearchParams({
        action: mapConfig.action,
        nonce: mapConfig.nonce,
        property_id: String(propertyId),
      });

      showMessage('Loading listing…');

      fetch(mapConfig.ajax_url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        },
        body: body.toString(),
        credentials: 'same-origin',
        signal: controller.signal,
      })
        .then((response) => {
          if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
          }
          return response.json();
        })
        .then((payload) => {
          if (!payload || !payload.success || !payload.data || !payload.data.card_html) {
            showMessage('No listing is available for this marker.');
            return;
          }
          setResultsHtml(payload.data.card_html);
          if (layoutEl && window.innerWidth < 768) {
            layoutEl.setAttribute('data-active-view', 'list');
            document.querySelectorAll('[data-map-view]').forEach((btn) => btn.classList.toggle('is-active', btn.getAttribute('data-map-view') === 'list'));
          }
        })
        .catch((error) => {
          if (error && error.name === 'AbortError') {
            return;
          }
          console.error('[PropertyMap] Failed to load marker card.', error);
          showMessage('Unable to load listing details right now.');
        })
        .finally(() => {
          if (activeController === controller) {
            activeController = null;
          }
        });
    };

    if (!markersData.length) {
      showMessage('No properties are available on the map right now.');
      return;
    }

    class CardOverlay extends window.google.maps.OverlayView {
      constructor(mapInstance, latLng, data, onClose) {
        super();
        this.map = mapInstance;
        this.latLng = latLng;
        this.data = data || {};
        this.onClose = onClose;
        this.div = document.createElement('div');
        this.div.style.position = 'absolute';
        this.div.style.zIndex = '9999';
        this.div.style.display = 'block';
        this.div.style.transform = 'translate(-50%, -110%)';
        this.div.style.pointerEvents = 'auto';
        this.div.style.animation = 'peraMapFadeIn .18s ease-out';
      }

      onAdd() {
        this.div.innerHTML = `
          <div class="content-panel-box" data-map-bubble="1">
            <div style="display:flex; justify-content:space-between; gap:12px; align-items:flex-start;">
              <div>
                <p class="text-xs muted text-upper" style="margin:0 0 6px;">Property</p>
                <h3 class="text-base" style="margin:0; line-height:1.2;">${escapeHtml(this.data.title || '')}</h3>
              </div>
              <button type="button" class="btn btn--ghost" aria-label="Close" style="padding:6px 10px; line-height:1;">×</button>
            </div>
            <div style="margin-top:10px;">
              <a class="btn btn--ghost btn--green" href="${escapeHtml(this.data.url || '#')}">View listing</a>
            </div>
          </div>
          <div style="
            position:absolute;
            left:50%;
            bottom:-8px;
            width:16px;
            height:16px;
            background:inherit;
            transform:translateX(-50%) rotate(45deg);
            box-shadow: 4px 4px 10px rgba(0,0,0,0.08);
            pointer-events:none;
          "></div>
        `;

        const bubble = this.div.querySelector('[data-map-bubble="1"]');
        if (bubble) {
          bubble.style.backgroundColor = 'var(--panel-bg, #fff)';
          bubble.style.padding = '18px 20px';
          bubble.style.width = 'min(320px, calc(100vw - 48px))';
          bubble.style.boxShadow = '0 14px 40px rgba(0,0,0,0.18)';
          bubble.style.animation = 'peraMapFadeIn .18s ease-out';
        }

        const closeButton = this.div.querySelector('button');
        if (closeButton) {
          closeButton.addEventListener('click', () => {
            if (typeof this.onClose === 'function') {
              this.onClose();
            }
          });
        }

        const panes = this.getPanes();
        if (panes && panes.floatPane) {
          panes.floatPane.appendChild(this.div);
        }
      }

      draw() {
        const projection = this.getProjection();
        if (!projection) {
          return;
        }

        const pixel = projection.fromLatLngToDivPixel(this.latLng);
        if (!pixel) {
          return;
        }

        const mapDiv = this.getMap().getDiv();
        const bubbleEl = this.div.querySelector('[data-map-bubble="1"]');
        const bubbleW = bubbleEl ? bubbleEl.getBoundingClientRect().width : 0;
        const wrapW = this.div.getBoundingClientRect().width;
        const finalW = (bubbleW && bubbleW > 50) ? bubbleW : (wrapW || 280);
        const mapW = mapDiv.clientWidth || mapDiv.getBoundingClientRect().width;
        const margin = 16;
        const half = finalW / 2;
        const x = pixel.x;

        const clampedX = Math.min(Math.max(x, margin + half), mapW - margin - half);

        this.div.style.left = `${clampedX}px`;
        this.div.style.top = `${pixel.y}px`;
      }

      onRemove() {
        if (this.div && this.div.parentNode) {
          this.div.parentNode.removeChild(this.div);
        }
      }
    }

    const bounds = new window.google.maps.LatLngBounds();
    let activeOverlay = null;

    const closeActiveOverlay = () => {
      if (activeOverlay) {
        activeOverlay.setMap(null);
        activeOverlay = null;
      }
    };

    window.google.maps.event.addListener(map, 'click', () => {
      closeActiveOverlay();
    });

    let markerCount = 0;
    let lastPosition = null;
    const markerEntries = [];

    const getFilters = () => {
      const formData = filtersForm ? new FormData(filtersForm) : new FormData();
      return {
        district: String(formData.get('district') || ''),
        minPrice: parseInt(formData.get('min_price') || '0', 10) || 0,
        maxPrice: parseInt(formData.get('max_price') || '0', 10) || 0,
        bedrooms: parseInt(formData.get('bedrooms') || '0', 10) || 0,
        type: String(formData.get('type') || '')
      };
    };

    const propertyMatches = (data, filters) => {
      const priceMin = parseInt(data.price_min || '0', 10) || 0;
      const priceMax = parseInt(data.price_max || priceMin || '0', 10) || priceMin;
      const beds = Array.isArray(data.bedrooms) ? data.bedrooms.map((bed) => parseInt(bed || '0', 10)).filter(Boolean) : [];
      if (filters.district && data.district !== filters.district) return false;
      if (filters.type && data.type !== filters.type) return false;
      if (filters.minPrice && priceMax && priceMax < filters.minPrice) return false;
      if (filters.maxPrice && priceMin && priceMin > filters.maxPrice) return false;
      if (filters.bedrooms && !beds.some((bed) => bed >= filters.bedrooms)) return false;
      return true;
    };

    const updateCount = (visibleCount) => {
      if (countEl) {
        countEl.textContent = `${visibleCount.toLocaleString()} ${visibleCount === 1 ? 'property' : 'properties'} shown`;
      }
      if (emptyEl) {
        emptyEl.hidden = visibleCount !== 0;
      }
    };

    const applyFilters = (shouldTrack = false, adjustViewport = false) => {
      const filters = getFilters();
      let visible = 0;
      const visibleBounds = new window.google.maps.LatLngBounds();
      markerEntries.forEach((entry) => {
        const match = propertyMatches(entry.data, filters);
        entry.marker.setMap(match ? map : null);
        if (match) {
          visible += 1;
          visibleBounds.extend(entry.marker.getPosition());
        }
      });
      closeActiveOverlay();
      updateCount(visible);
      if (adjustViewport && visible > 1) {
        map.fitBounds(visibleBounds);
      } else if (adjustViewport && visible === 1) {
        map.setCenter(visibleBounds.getCenter());
        map.setZoom(14);
      }
      if (shouldTrack) {
        trackEvent('property_map_filter', {
          filter_district: filters.district,
          filter_type: filters.type,
          filter_bedrooms: filters.bedrooms,
          filter_min_price: filters.minPrice,
          filter_max_price: filters.maxPrice,
          visible_count: visible
        });
      }
    };

    markersData.forEach((markerData) => {
      const position = {
        lat: parseFloat(markerData.lat),
        lng: parseFloat(markerData.lng),
      };

      if (Number.isNaN(position.lat) || Number.isNaN(position.lng)) {
        return;
      }

      const markerOptions = {
        position,
        map,
        title: markerData.title || '',
      };

      if (markerIconUrl) {
        markerOptions.icon = {
          url: markerIconUrl,
          scaledSize: new window.google.maps.Size(40, 40),
          anchor: new window.google.maps.Point(20, 40),
        };
      }

      const marker = new window.google.maps.Marker(markerOptions);

      bounds.extend(position);
      markerCount += 1;
      lastPosition = position;

      markerEntries.push({ marker, data: markerData });

      marker.addListener('click', () => {
        closeActiveOverlay();

        if (markerData.title && markerData.url) {
          activeOverlay = new CardOverlay(map, marker.getPosition(), markerData, closeActiveOverlay);
          activeOverlay.setMap(map);
        }

        loadMarkerCard(markerData.id);
        trackEvent('property_map_marker_select', { property_id: markerData.id || '', property_title: markerData.title || '' });

        if (selectedPanel && window.innerWidth < 768) {
          selectedPanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
      });
    });

    if (markerCount === 0) {
      showMessage('No properties are available on the map right now.');
      return;
    }

    if (filtersForm) {
      let filterTimer = null;
      filtersForm.addEventListener('input', (event) => {
        const target = event.target;
        if (!target || !target.matches || !target.matches('input')) return;
        window.clearTimeout(filterTimer);
        filterTimer = window.setTimeout(() => applyFilters(true, false), 250);
      });
      filtersForm.addEventListener('change', (event) => {
        const target = event.target;
        if (!target || !target.matches || target.matches('input')) return;
        applyFilters(true, true);
      });
      filtersForm.addEventListener('reset', () => {
        window.setTimeout(() => applyFilters(true, true), 0);
      });
    }

    document.addEventListener('click', (event) => {
      const trackEl = event.target && event.target.closest ? event.target.closest('[data-map-track]') : null;
      if (trackEl) {
        trackEvent('property_map_interaction', { interaction: trackEl.getAttribute('data-map-track') || '' });
      }

      const detailLink = event.target && event.target.closest ? event.target.closest('#property-map-results a[href]') : null;
      if (detailLink && !detailLink.matches('[data-whatsapp="1"]')) {
        trackEvent('property_map_detail_click', { link_url: detailLink.href });
      }

      const viewButton = event.target && event.target.closest ? event.target.closest('[data-map-view]') : null;
      if (viewButton && layoutEl) {
        const view = viewButton.getAttribute('data-map-view');
        layoutEl.setAttribute('data-active-view', view);
        document.querySelectorAll('[data-map-view]').forEach((btn) => btn.classList.toggle('is-active', btn === viewButton));
        if (view === 'map') {
          window.google.maps.event.trigger(map, 'resize');
        }
      }
    });

    document.querySelectorAll('a[href^="#"]').forEach((anchor) => {
      anchor.addEventListener('click', (event) => {
        const target = document.querySelector(anchor.getAttribute('href'));
        if (!target) return;
        event.preventDefault();
        target.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });
      });
    });

    const assistForm = document.getElementById('property-map-assist-form');
    if (assistForm) {
      assistForm.addEventListener('submit', (event) => {
        event.preventDefault();
        const formData = new FormData(assistForm);
        const message = `Hi, I'd like personalised Istanbul property recommendations. Name: ${formData.get('name') || ''}. WhatsApp: ${formData.get('phone') || ''}. Budget: ${formData.get('budget') || ''}. Purpose: ${formData.get('purpose') || ''}. Preferred area: ${formData.get('area') || 'Not sure'}.`;
        const fallback = assistForm.getAttribute('data-whatsapp-url') || '';
        if (!fallback) return;
        const base = fallback.split('?')[0];
        trackEvent('property_map_assisted_search_submit', { buying_purpose: String(formData.get('purpose') || '') });
        window.location.href = `${base}?text=${encodeURIComponent(message)}`;
      });
    }

    updateCount(markerEntries.length);

    if (markerCount === 1 && lastPosition) {
      map.setCenter(lastPosition);
      map.setZoom(14);
    } else if (markerCount > 1) {
      map.fitBounds(bounds);
    }
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initPropertyMap);
  } else {
    initPropertyMap();
  }
})();
