// Map (PHP: Components\Map), with Leaflet.
// - The map is a named region ("Our offices, map"), focusable: Leaflet's arrow keys move it,
//   plus and minus zoom; a hidden text says so (aria-describedby, from PHP).
// - Markers are pins drawn with CSS (no images to resolve), keyboard buttons named after their
//   place; their popup (name, description, link) is built from text, never from HTML.
// - The list of places under the map is the text alternative; its "Show on map" buttons bring
//   a marker into view, open its popup and move focus to the marker.
// - The zoom and close controls (links with role=button in Leaflet) also answer Space, and
//   Escape closes an open popup wherever focus is in the map (Leaflet only listens on the
//   map itself), focus staying where it is.
// - Wheel zoom only with options.scrollZoom; no animations for reduced motion. Leaflet moves
//   things with the CSSOM, which a strict Content Security Policy allows.
import 'leaflet/dist/leaflet.css';
import L from 'leaflet';
import { prefersReducedMotion } from './core.js';

const text = (tag, className, value) => {
  const element = document.createElement(tag);
  element.className = className;
  element.textContent = value;
  return element;
};

// Only relative, http and https links (PHP checks them too).
const safeUrl = (url) => {
  if (typeof url !== 'string' || url === '') {
    return null;
  }
  try {
    const { protocol } = new URL(url, window.location.href);
    return protocol === 'http:' || protocol === 'https:' ? url : null;
  } catch {
    return null;
  }
};

export function popupContent(marker, labels = {}) {
  const content = document.createElement('div');
  content.className = 'celerik-map-popup';
  content.append(text('p', 'celerik-map-popup-name', marker.name));
  if (marker.description) {
    content.append(text('p', 'celerik-map-popup-description', marker.description));
  }
  const url = safeUrl(marker.url);
  if (url) {
    const link = text('a', 'celerik-map-popup-link', (labels.more ?? 'More about {name}').replaceAll('{name}', marker.name));
    link.href = url;
    content.append(link);
  }
  return content;
}

export function attributionHtml(attribution, url) {
  const span = document.createElement('span');
  const safe = safeUrl(url);
  if (safe) {
    const link = document.createElement('a');
    link.href = safe;
    link.textContent = attribution;
    span.append('© ', link);
  } else {
    span.append(`© ${attribution}`);
  }
  return span.innerHTML;
}

// Space on a link that plays a button (Leaflet's controls).
function spaceClicks(container) {
  container.addEventListener('keydown', (event) => {
    const target = event.target;
    if (event.key === ' ' && target instanceof HTMLElement && target.matches('a[role="button"]')) {
      event.preventDefault();
      target.click();
    }
  });
}

export function initMap(element, options = {}) {
  const canvas = element.querySelector('.celerik-map-canvas');
  if (!canvas) {
    return null;
  }
  const labels = options.labels ?? {};
  const markers = options.markers ?? [];
  const still = prefersReducedMotion();
  canvas.querySelector('.celerik-map-noscript')?.remove();

  const map = L.map(canvas, {
    scrollWheelZoom: Boolean(options.scrollZoom),
    zoomControl: false,
    zoomAnimation: !still,
    fadeAnimation: !still,
    markerZoomAnimation: !still,
    inertia: !still,
  });
  L.control.zoom({
    zoomInTitle: labels.zoomIn ?? 'Zoom in',
    zoomOutTitle: labels.zoomOut ?? 'Zoom out',
  }).addTo(map);
  map.attributionControl.setPrefix('<a href="https://leafletjs.com">Leaflet</a>');
  L.tileLayer(options.tiles ?? 'https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: options.maxZoom ?? 19,
    attribution: attributionHtml(options.attribution ?? 'OpenStreetMap contributors', options.attributionUrl),
  }).addTo(map);

  // The map's container: a named, focusable region.
  canvas.setAttribute('role', 'region');
  canvas.setAttribute('aria-roledescription', labels.map ?? 'map');
  canvas.setAttribute('aria-label', options.label ?? '');

  // The view first: Leaflet draws markers (and so their elements) once the map has one.
  const points = markers.map((marker) => [marker.lat, marker.lng]);
  if (options.center) {
    map.setView(options.center, options.zoom ?? 13, { animate: false });
  } else if (points.length === 1) {
    map.setView(points[0], options.zoom ?? 13, { animate: false });
  } else {
    map.fitBounds(L.latLngBounds(points), { padding: [32, 32], animate: false });
  }

  const layers = markers.map((marker) => {
    const layer = L.marker([marker.lat, marker.lng], {
      icon: L.divIcon({
        className: `celerik-map-marker celerik-map-marker-${marker.color ?? 'primary'}`,
        html: '<span class="celerik-map-pin" aria-hidden="true"></span>',
        iconSize: [28, 36],
        iconAnchor: [14, 34],
        popupAnchor: [0, -30],
      }),
      keyboard: true,
      title: marker.name,
      riseOnHover: true,
    }).addTo(map);
    layer.getElement()?.setAttribute('aria-label', marker.name);
    layer.bindPopup(() => popupContent(marker, labels), { autoPanPadding: [24, 24] });
    return layer;
  });

  // Leaflet names its close button "Close popup" in English: use the translation.
  map.on('popupopen', (event) => {
    const close = event.popup.getElement()?.querySelector('.leaflet-popup-close-button');
    if (close) {
      close.setAttribute('aria-label', labels.close ?? 'Close');
      close.removeAttribute('title');
    }
  });

  const show = (index) => {
    const layer = layers[index];
    if (!layer) {
      return;
    }
    map.setView(layer.getLatLng(), Math.max(map.getZoom(), 15), { animate: !still });
    layer.openPopup();
    layer.getElement()?.focus({ preventScroll: true });
    canvas.scrollIntoView?.({ block: 'nearest', behavior: still ? 'auto' : 'smooth' });
  };
  element.querySelectorAll('.celerik-map-show').forEach((button) => {
    button.addEventListener('click', () => show(Number(button.dataset.marker)));
  });

  spaceClicks(canvas);
  canvas.addEventListener('keydown', (event) => {
    const opener = event.key === 'Escape' ? layers.find((layer) => layer.isPopupOpen()) : null;
    if (opener) {
      event.preventDefault();
      event.stopPropagation();
      opener.closePopup();
      opener.getElement()?.focus({ preventScroll: true });
    }
  });

  // Leaflet measures its container once; redraw when it changes size (tabs, splitters, tiles).
  let observer = null;
  if (typeof ResizeObserver === 'function') {
    observer = new ResizeObserver(() => map.invalidateSize({ animate: false }));
    observer.observe(canvas);
  }

  return {
    map, markers: layers, show, destroy: () => { observer?.disconnect(); map.remove(); },
  };
}
