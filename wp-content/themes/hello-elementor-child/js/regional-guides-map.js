/* global L */
(() => {
  'use strict';
  const pickerBounds = [[40.90, 28.60], [41.27, 29.35]];
  function baseMap(canvas, options = {}) {
    const map = L.map(canvas, { scrollWheelZoom: false, ...options });
    if (!options.center) map.fitBounds(pickerBounds);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);
    return map;
  }
  function icon(kind) {
    return L.divIcon({ className: `regional-map__marker regional-map__marker--${kind}`, iconSize: kind === 'guide' ? [24, 24] : [12, 12] });
  }
  // Group nearby guides at each zoom. Identical coordinates always share a marker.
  function groups(guides, map) {
    const result = [];
    guides.forEach(guide => {
      const point = map.latLngToLayerPoint([guide.lat, guide.lng]);
      let group = result.find(item => point.distanceTo(item.point) < 32);
      if (!group) { group = { point, guides: [] }; result.push(group); }
      group.guides.push(guide);
    });
    return result;
  }
  function archive(root) {
    const data = JSON.parse(root.dataset.mapConfig);
    const canvas = root.querySelector('[data-map-canvas]');
    const status = root.querySelector('[data-map-status]');
    const preview = root.querySelector('[data-map-preview]');
    const choices = root.querySelector('[data-map-choices]');
    const card = root.querySelector('[data-map-card]');
    const templates = new Map(Array.from(root.querySelectorAll('[data-map-guide]'), template => [
      Number(template.dataset.mapGuide), template
    ]));
    let selectedGuideId;
    function showGuides(guides) {
      const showCard = guide => {
        const template = templates.get(guide.id);
        if (!template) return;
        selectedGuideId = guide.id;
        card.replaceChildren(template.content.cloneNode(true));
        for (const button of choices.children) {
          button.setAttribute('aria-pressed', String(Number(button.dataset.guideId) === guide.id));
        }
      };

      choices.replaceChildren();
      if (guides.length > 1) {
        for (const guide of guides) {
          const button = document.createElement('button');
          button.type = 'button';
          button.className = 'btn btn--ghost btn--blue';
          button.dataset.guideId = guide.id;
          button.textContent = guide.title;
          button.addEventListener('click', () => showCard(guide));
          choices.append(button);
        }
      }
      preview.hidden = false;
      showCard(guides.find(guide => guide.id === selectedGuideId) || guides[0]);
    }
    const map = baseMap(canvas, {
      center: [41.025, 28.985],
      zoom: 12,
      touchZoom: true,
      tapHold: false
    });
    // Leaflet's TouchZoom handles both pinch and two-finger midpoint panning.
    // Keep its touch-action pan-x pan-y, allowing the browser to scroll with one
    // finger. Disable Drag before Leaflet sees touch input; do not cancel taps.
    // Detect each input rather than the primary pointer (hybrid laptops/tablets).
    const disableTouchDrag = () => map.dragging.disable();
    canvas.addEventListener('touchstart', disableTouchDrag, { capture: true, passive: true });
    canvas.addEventListener('pointerdown', event => {
      if (event.pointerType === 'touch') disableTouchDrag();
      else if (event.pointerType === 'mouse') map.dragging.enable();
    }, true);
    // Mouse fallback for browsers without Pointer Events. Touch-generated mouse
    // events must not re-enable Drag during a touch sequence.
    canvas.addEventListener('mousedown', event => {
      if (!event.sourceCapabilities || !event.sourceCapabilities.firesTouchEvents) map.dragging.enable();
    }, true);
    const layers = L.layerGroup().addTo(map);
    function render() {
      layers.clearLayers();
      groups(data.guides, map).forEach(group => {
        const guide = group.guides[0];
        const label = group.guides.map(item => item.label || item.title).join(', ');
        const marker = L.marker([guide.lat, guide.lng], { icon: icon('guide'), title: label, alt: label, keyboard: true })
          .on('click', () => showGuides(group.guides)).addTo(layers);
        marker.getElement().addEventListener('keydown', event => {
          if (event.key === ' ' || event.key === 'Enter') {
            event.preventDefault();
            showGuides(group.guides);
          }
        });
      });
    }
    data.landmarks.forEach(landmark => {
      L.marker([landmark.lat, landmark.lng], { icon: icon('landmark'), title: landmark.title, alt: landmark.title, keyboard: true, zIndexOffset: -1000 })
        .bindTooltip(landmark.title).bindPopup(document.createTextNode(landmark.title)).addTo(map);
    });
    render();
    map.on('zoomend', render);
    map.on('tileerror', () => { status.hidden = false; });
    // Tile events originate on the tile layer rather than the map.
    map.eachLayer(layer => { if (layer instanceof L.TileLayer) layer.on('tileerror', () => { status.hidden = false; }); });
    status.hidden = true;
    if (window.ResizeObserver) new ResizeObserver(() => map.invalidateSize()).observe(canvas);
  }
  function picker(root) {
    let map, marker;
    const lat = document.getElementById('pera-guide-map-lat');
    const lng = document.getElementById('pera-guide-map-lng');
    function sync() {
      if (lat.value === '' || lng.value === '') { if (marker) { marker.remove(); marker = null; } return; }
      const a = Number(lat.value), b = Number(lng.value);
      if (!Number.isFinite(a) || !Number.isFinite(b) || Math.abs(a) > 90 || Math.abs(b) > 180) return;
      if (marker) marker.setLatLng([a, b]);
      else marker = L.marker([a, b], { icon: icon('guide') }).addTo(map);
    }
    root.addEventListener('toggle', () => {
      if (!root.open) return;
      if (!map) {
        map = baseMap(root.querySelector('[data-map-canvas]'));
        map.on('click', event => {
          lat.value = event.latlng.lat.toFixed(6);
          lng.value = event.latlng.wrap().lng.toFixed(6);
          [lat, lng].forEach(field => field.dispatchEvent(new Event('change', { bubbles: true })));
          sync();
        });
        [lat, lng].forEach(field => field.addEventListener('change', sync));
        sync();
        if (marker) map.setView(marker.getLatLng(), 12);
      }
      map.invalidateSize();
    });
  }
  if (!window.L) return; // Reserved canvas, fallback text and HTML links remain available.
  document.querySelectorAll('[data-regional-map]').forEach(root => {
    try { archive(root); } catch (_) { root.querySelector('[data-map-status]').hidden = false; }
  });
  document.querySelectorAll('[data-regional-map-picker]').forEach(picker);
})();
