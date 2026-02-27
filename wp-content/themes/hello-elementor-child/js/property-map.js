(() => {
  const initPropertyMap = () => {
    const mapEl = document.getElementById('property-map');
    if (!mapEl || !window.google || !window.google.maps) {
      return;
    }

    let markersData = [];
    const markersRawEl = document.getElementById('property-map-data');
    try {
      markersData = markersRawEl ? JSON.parse(markersRawEl.textContent) : [];
    } catch (error) {
      console.error('[PropertyMap] Failed to parse markers JSON.', error);
      markersData = [];
    }

    console.log('[PropertyMap] markers:', markersData.length);

    const selectedPanel = document.querySelector('.property-map__selected');
    const defaultCenter = { lat: 41.0082, lng: 28.9784 };
    const map = new window.google.maps.Map(mapEl, {
      center: defaultCenter,
      zoom: 12,
      mapTypeControl: false,
      streetViewControl: false,
    });

    if (!markersData.length) {
      if (selectedPanel) {
        selectedPanel.innerHTML = `
          <div class="content-panel-box">
            <p class="text-sm muted">No properties are available on the map right now.</p>
          </div>
        `;
      }
      return;
    }

    const bounds = new window.google.maps.LatLngBounds();
    const infoWindow = new window.google.maps.InfoWindow();
    let markerCount = 0;
    let lastPosition = null;

    const selectResultCard = (markerData) => {
      if (!selectedPanel || !markerData || !markerData.id) {
        return;
      }

      const cardWrappers = selectedPanel.querySelectorAll('[data-property-id]');
      cardWrappers.forEach((cardWrapper) => cardWrapper.classList.remove('is-active'));

      const target = selectedPanel.querySelector(`[data-property-id="${String(markerData.id)}"]`);
      if (!target) {
        return;
      }

      target.classList.add('is-active');
      target.scrollIntoView({ behavior: 'smooth', block: 'center' });
    };

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
        selectResultCard(markerData);
        if (markerData.title && markerData.url) {
          infoWindow.setContent(
            `<div class="property-map__info"><strong>${markerData.title}</strong><br><a href="${markerData.url}">View</a></div>`
          );
          infoWindow.open(map, marker);
        }

        if (selectedPanel && window.innerWidth < 768) {
          selectedPanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
      });
    });

    if (markerCount === 0) {
      if (selectedPanel) {
        selectedPanel.innerHTML = `
          <div class="content-panel-box">
            <p class="text-sm muted">No properties are available on the map right now.</p>
          </div>
        `;
      }
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
