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
    const mapConfig = window.peraPropertyMap || {};
    const defaultCenter = { lat: 41.0082, lng: 28.9784 };
    const map = new window.google.maps.Map(mapEl, {
      center: defaultCenter,
      zoom: 12,
      mapTypeControl: false,
      streetViewControl: false,
    });

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
        this.div.style.transform = 'translate(-50%, -110%)';
        this.div.style.pointerEvents = 'auto';
        this.div.style.maxWidth = '280px';
        this.div.style.padding = '20px 22px';
        this.div.style.minWidth = '240px';
        this.div.style.boxShadow = '0 14px 40px rgba(0,0,0,0.18)';
        this.div.style.animation = 'peraMapFadeIn .18s ease-out';
      }

      onAdd() {
        this.div.innerHTML = `
          <div class="content-panel-box">
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
            background:#fff;
            transform:translateX(-50%) rotate(45deg);
            box-shadow: 4px 4px 10px rgba(0,0,0,0.08);
            pointer-events:none;
          "></div>
        `;

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

        const position = projection.fromLatLngToDivPixel(this.latLng);
        if (!position) {
          return;
        }

        this.div.style.left = `${position.x}px`;
        this.div.style.top = `${position.y}px`;
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

    markersData.forEach((markerData) => {
      const position = {
        lat: parseFloat(markerData.lat),
        lng: parseFloat(markerData.lng),
      };

      if (Number.isNaN(position.lat) || Number.isNaN(position.lng)) {
        return;
      }

      const marker = new window.google.maps.Marker({
        position,
        map,
        title: markerData.title || '',
      });

      bounds.extend(position);
      markerCount += 1;
      lastPosition = position;

      marker.addListener('click', () => {
        closeActiveOverlay();

        if (markerData.title && markerData.url) {
          activeOverlay = new CardOverlay(map, marker.getPosition(), markerData, closeActiveOverlay);
          activeOverlay.setMap(map);
        }

        loadMarkerCard(markerData.id);

        if (selectedPanel && window.innerWidth < 768) {
          selectedPanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
      });
    });

    if (markerCount === 0) {
      showMessage('No properties are available on the map right now.');
      return;
    }

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
