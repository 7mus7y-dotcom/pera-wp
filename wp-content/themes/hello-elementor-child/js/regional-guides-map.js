/* global L */
(() => {
  'use strict';
  const initialBounds = [[40.90, 28.60], [41.27, 29.35]];
  function baseMap(canvas, options = {}) {
    const map = L.map(canvas, { scrollWheelZoom: false, ...options });
    map.fitBounds(initialBounds);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);
    return map;
  }
  function icon(kind) {
    return L.divIcon({ className: `regional-map__marker regional-map__marker--${kind}`, iconSize: kind === 'guide' ? [24, 24] : [12, 12] });
  }
  // Group by screen distance at each zoom. Identical coordinates always share a popup.
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
  function popup(guides, read) {
    const content = document.createElement('div');
    content.className = 'regional-map__popup';
    guides.forEach(guide => {
      const item = document.createElement('p');
      const title = document.createElement('strong');
      title.textContent = guide.title;
      const link = document.createElement('a');
      link.textContent = read;
      // PHP esc_url protects crawlable links; constrain the separate JSON consumer too.
      const url = new URL(guide.url, window.location.href);
      if (!['https:', 'http:'].includes(url.protocol)) return;
      link.href = url.href;
      item.append(title, document.createElement('br'), link);
      content.append(item);
    });
    return content;
  }
  function archive(root) {
    const data = JSON.parse(root.dataset.mapConfig);
    const canvas = root.querySelector('[data-map-canvas]');
    const status = root.querySelector('[data-map-status]');
    const map = baseMap(canvas, { touchZoom: true, tapHold: false });
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
        L.marker([guide.lat, guide.lng], { icon: icon('guide'), title: label, alt: label, keyboard: true })
          .bindPopup(popup(group.guides, data.read), { maxHeight: 220 }).addTo(layers);
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
