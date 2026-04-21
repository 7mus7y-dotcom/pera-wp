/*! Leaflet-compatible local runtime shim for citizenship page (version tag: 1.9.4) */
(function (window, document) {
	if (window.L) return;

	function setStyles(el, styles) {
		Object.keys(styles).forEach(function (key) { el.style[key] = styles[key]; });
	}

	function createEl(tag, className, parent) {
		var el = document.createElement(tag);
		if (className) el.className = className;
		if (parent) parent.appendChild(el);
		return el;
	}

	function project(lat, lng, zoom) {
		var sin = Math.sin(lat * Math.PI / 180);
		var scale = 256 * Math.pow(2, zoom);
		var x = scale * (lng + 180) / 360;
		var y = scale * (0.5 - Math.log((1 + sin) / (1 - sin)) / (4 * Math.PI));
		return { x: x, y: y };
	}

	function unproject(x, y, zoom) {
		var scale = 256 * Math.pow(2, zoom);
		var lng = x / scale * 360 - 180;
		var n = Math.PI - 2 * Math.PI * y / scale;
		var lat = 180 / Math.PI * Math.atan(0.5 * (Math.exp(n) - Math.exp(-n)));
		return { lat: lat, lng: lng };
	}

	function Map(container, options) {
		this._container = container;
		this._options = options || {};
		this._zoom = 13;
		this._center = { lat: 41.015137, lng: 28.97953 };
		this._layers = [];
		this._markers = [];
		this._popup = null;

		container.classList.add('leaflet-container');
		container.innerHTML = '';
		setStyles(container, { position: 'relative' });

		this._tilePane = createEl('div', 'leaflet-pane leaflet-tile-pane', container);
		this._markerPane = createEl('div', 'leaflet-pane leaflet-marker-pane', container);
		this._popupPane = createEl('div', 'leaflet-pane leaflet-popup-pane', container);
	}

	Map.prototype._size = function () {
		return { w: this._container.clientWidth || 1, h: this._container.clientHeight || 1 };
	};

	Map.prototype._pixelOrigin = function () {
		var size = this._size();
		var centerPx = project(this._center.lat, this._center.lng, this._zoom);
		return { x: centerPx.x - size.w / 2, y: centerPx.y - size.h / 2 };
	};

	Map.prototype._latLngToContainerPoint = function (lat, lng) {
		var px = project(lat, lng, this._zoom);
		var origin = this._pixelOrigin();
		return { x: px.x - origin.x, y: px.y - origin.y };
	};

	Map.prototype._containerPointToLatLng = function (x, y) {
		var origin = this._pixelOrigin();
		return unproject(origin.x + x, origin.y + y, this._zoom);
	};

	Map.prototype._renderTiles = function () {
		var tileLayer = this._layers.find(function (layer) { return layer && layer._isTileLayer; });
		if (!tileLayer) return;

		this._tilePane.innerHTML = '';
		var size = this._size();
		var origin = this._pixelOrigin();
		var minX = Math.floor(origin.x / 256);
		var minY = Math.floor(origin.y / 256);
		var maxX = Math.floor((origin.x + size.w) / 256);
		var maxY = Math.floor((origin.y + size.h) / 256);
		var maxIdx = Math.pow(2, this._zoom);

		for (var x = minX; x <= maxX; x++) {
			for (var y = minY; y <= maxY; y++) {
				if (y < 0 || y >= maxIdx) continue;
				var wrappedX = ((x % maxIdx) + maxIdx) % maxIdx;
				var url = tileLayer._url
					.replace('{s}', 'a')
					.replace('{z}', String(this._zoom))
					.replace('{x}', String(wrappedX))
					.replace('{y}', String(y));
				var img = createEl('img', 'leaflet-tile', this._tilePane);
				img.src = url;
				img.alt = '';
				img.loading = 'lazy';
				img.classList.add('leaflet-tile-loaded');
				setStyles(img, {
					width: '256px',
					height: '256px',
					left: (x * 256 - origin.x) + 'px',
					top: (y * 256 - origin.y) + 'px'
				});
			}
		}
	};

	Map.prototype._renderMarkers = function () {
		var self = this;
		this._markerPane.innerHTML = '';
		this._markers.forEach(function (marker) {
			var point = self._latLngToContainerPoint(marker._latlng[0], marker._latlng[1]);
			var el = createEl('button', 'leaflet-marker-icon', self._markerPane);
			el.type = 'button';
			el.setAttribute('aria-label', 'Map marker');
			setStyles(el, {
				width: '22px',
				height: '22px',
				borderRadius: '50%',
				border: '2px solid #fff',
				background: '#2f9e44',
				boxShadow: '0 1px 6px rgba(0,0,0,.35)',
				transform: 'translate(-50%, -100%)',
				left: point.x + 'px',
				top: point.y + 'px',
				cursor: 'pointer'
			});
			el.addEventListener('click', function () { self._showPopup(marker); });
			marker._el = el;
		});
	};

	Map.prototype._showPopup = function (marker) {
		this._popupPane.innerHTML = '';
		if (!marker._popupHtml) return;
		var point = this._latLngToContainerPoint(marker._latlng[0], marker._latlng[1]);
		var popup = createEl('div', 'leaflet-popup', this._popupPane);
		setStyles(popup, {
			left: point.x + 'px',
			top: (point.y - 30) + 'px',
			transform: 'translate(-50%, -100%)'
		});
		var wrapper = createEl('div', 'leaflet-popup-content-wrapper', popup);
		var content = createEl('div', 'leaflet-popup-content', wrapper);
		content.innerHTML = marker._popupHtml;
		var tipContainer = createEl('div', 'leaflet-popup-tip-container', popup);
		createEl('div', 'leaflet-popup-tip', tipContainer);
		this._popup = popup;
	};

	Map.prototype.invalidateSize = function () {
		this._renderTiles();
		this._renderMarkers();
	};

	Map.prototype.fitBounds = function (bounds, opts) {
		if (!Array.isArray(bounds) || !bounds.length) return;
		var padding = (opts && opts.padding && opts.padding[0]) ? Number(opts.padding[0]) : 0;
		var minLat = Infinity, maxLat = -Infinity, minLng = Infinity, maxLng = -Infinity;
		bounds.forEach(function (p) {
			if (!Array.isArray(p) || p.length < 2) return;
			var lat = Number(p[0]);
			var lng = Number(p[1]);
			if (!isFinite(lat) || !isFinite(lng)) return;
			if (lat < minLat) minLat = lat;
			if (lat > maxLat) maxLat = lat;
			if (lng < minLng) minLng = lng;
			if (lng > maxLng) maxLng = lng;
		});
		if (!isFinite(minLat)) return;
		this._center = { lat: (minLat + maxLat) / 2, lng: (minLng + maxLng) / 2 };

		var size = this._size();
		for (var z = 19; z >= 2; z--) {
			var p1 = project(minLat, minLng, z);
			var p2 = project(maxLat, maxLng, z);
			var width = Math.abs(p2.x - p1.x);
			var height = Math.abs(p2.y - p1.y);
			if (width <= size.w - 2 * padding && height <= size.h - 2 * padding) {
				this._zoom = z;
				break;
			}
		}
		this._renderTiles();
		this._renderMarkers();
	};

	function TileLayer(url, options) {
		this._url = url;
		this._options = options || {};
		this._isTileLayer = true;
	}
	TileLayer.prototype.addTo = function (map) {
		map._layers.push(this);
		map._renderTiles();
		return this;
	};

	function Marker(latlng) {
		this._latlng = latlng;
		this._popupHtml = '';
	}
	Marker.prototype.addTo = function (map) {
		this._map = map;
		map._markers.push(this);
		map._renderMarkers();
		return this;
	};
	Marker.prototype.bindPopup = function (html) {
		this._popupHtml = html || '';
		return this;
	};

	window.L = {
		version: '1.9.4',
		map: function (el, options) { return new Map(el, options); },
		tileLayer: function (url, options) { return new TileLayer(url, options); },
		marker: function (latlng) { return new Marker(latlng); }
	};
})(window, document);
