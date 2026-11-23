import {
  attributionHtml, initMap, popupContent,
} from '../../resources/js/map.js';
import { readOptions } from '../../resources/js/core.js';
import { loadFixture } from './helpers.js';

describe('map popups and attribution', () => {
  test('a popup is built from text: name, description and a safe link', () => {
    const popup = popupContent(
      { name: 'Goma <b>HQ</b>', description: '<img src=x onerror=alert(1)>', url: '/offices/goma' },
      { more: 'Plus sur {name}' },
    );
    expect(popup.querySelector('img')).toBeNull();
    expect(popup.querySelector('.caelrik-map-popup-name').textContent).toBe('Goma <b>HQ</b>');
    expect(popup.querySelector('.caelrik-map-popup-description').textContent).toBe('<img src=x onerror=alert(1)>');
    const link = popup.querySelector('a');
    expect(link.getAttribute('href')).toBe('/offices/goma');
    expect(link.textContent).toBe('Plus sur Goma <b>HQ</b>');
  });

  test('an unsafe link is left out', () => {
    expect(popupContent({ name: 'A', url: 'javascript:alert(1)' }).querySelector('a')).toBeNull();
    expect(popupContent({ name: 'A', url: 'data:text/html,x' }).querySelector('a')).toBeNull();
    expect(popupContent({ name: 'A' }).querySelector('a')).toBeNull();
  });

  test('the attribution is escaped text, linked when its URL is safe', () => {
    expect(attributionHtml('OpenStreetMap <contributors>', 'https://www.openstreetmap.org/copyright'))
      .toBe('© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap &lt;contributors&gt;</a>');
    expect(attributionHtml('Tiles', 'javascript:alert(1)')).toBe('© Tiles');
  });
});

describe('map', () => {
  let root;
  let api;

  beforeEach(() => {
    root = loadFixture('map');
    api = initMap(root, readOptions(root));
  });

  afterEach(() => api?.destroy());

  test('the canvas becomes a named region with its markers as named buttons', () => {
    const canvas = root.querySelector('.caelrik-map-canvas');
    expect(canvas.querySelector('.caelrik-map-noscript')).toBeNull();
    expect(canvas.getAttribute('role')).toBe('region');
    expect(canvas.getAttribute('aria-roledescription')).toBe('map');
    expect(canvas.getAttribute('aria-label')).toBe('Our offices');
    const markers = [...canvas.querySelectorAll('.leaflet-marker-icon')];
    expect(markers.map((marker) => marker.getAttribute('aria-label'))).toEqual(['Goma <HQ>', 'Bukavu']);
    expect(markers.every((marker) => marker.getAttribute('role') === 'button' && marker.tabIndex === 0)).toBe(true);
    expect(markers[1].classList.contains('caelrik-map-marker-success')).toBe(true);
    expect(canvas.querySelector('.leaflet-control-zoom-in').getAttribute('aria-label')).toBe('Zoom in (test)');
  });

  test('"Show on map" opens the place\'s popup and moves focus to its marker', () => {
    root.querySelector('.caelrik-map-show[data-marker="1"]').click();
    const popup = root.querySelector('.leaflet-popup');
    expect(popup.textContent).toContain('Bukavu');
    expect(document.activeElement).toBe(api.markers[1].getElement());
    expect(popup.querySelector('.leaflet-popup-close-button').getAttribute('aria-label')).toBe('Close');
  });

  test('Escape closes the open popup, focus staying on its marker', () => {
    root.querySelector('.caelrik-map-show[data-marker="0"]').click();
    const marker = api.markers[0].getElement();
    marker.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
    expect(root.querySelector('.leaflet-popup')).toBeNull();
    expect(document.activeElement).toBe(marker);
  });

  test('Space presses the zoom buttons, as Enter does', () => {
    const zoomIn = root.querySelector('.leaflet-control-zoom-in');
    let clicks = 0;
    zoomIn.addEventListener('click', () => { clicks += 1; });
    zoomIn.dispatchEvent(new KeyboardEvent('keydown', { key: ' ', bubbles: true }));
    expect(clicks).toBe(1);
  });
});
