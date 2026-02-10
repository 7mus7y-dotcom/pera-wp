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

    const renderSelectedCard = (markerData) => {
      if (!selectedPanel) {
        return;
      }

      selectedPanel.innerHTML = '';

      const wrapper = document.createElement('div');
      wrapper.className = 'content-panel-box';

      if (markerData.thumb) {
        const image = document.createElement('img');
        image.src = markerData.thumb;
        image.alt = markerData.title ? `${markerData.title} thumbnail` : 'Property thumbnail';
        image.loading = 'lazy';
        image.style.width = '100%';
        image.style.height = 'auto';
        image.style.borderRadius = '12px';
        image.style.display = 'block';
        image.style.marginBottom = '12px';
        wrapper.appendChild(image);
      }

      const title = document.createElement('h3');
      title.className = 'text-lg';
      const titleLink = document.createElement('a');
      titleLink.href = markerData.url || '#';
      titleLink.textContent = markerData.title || 'View listing';
      title.appendChild(titleLink);
      wrapper.appendChild(title);

      if (markerData.price_text) {
        const price = document.createElement('p');
        price.className = 'text-sm muted';
        price.textContent = markerData.price_text;
        wrapper.appendChild(price);
      }

      const button = document.createElement('a');
      button.href = markerData.url || '#';
      button.className = 'btn btn--solid btn--green';
      button.textContent = 'View listing';
      wrapper.appendChild(button);

      selectedPanel.appendChild(wrapper);
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
        renderSelectedCard(markerData);
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
