import { expect, test } from '@playwright/test';
import { axeViolations, MODES, openComponents } from './helpers.mjs';

const open = (page, options = {}) => openComponents(page, { path: '/map', ...options });

test.describe('map', () => {
  test('a named map region, its markers named buttons, and the list of its places', async ({ page }) => {
    const errors = await open(page);
    expect(errors).toEqual([]);
    const map = page.getByRole('region', { name: 'Our offices' });
    await expect(map).toHaveAttribute('aria-roledescription', 'map');
    await expect(map).toHaveAccessibleDescription('Arrow keys move the map, plus and minus zoom in and out.');
    await expect(map.getByRole('button', { name: 'Goma <HQ>' })).toBeVisible();
    await expect(map.getByRole('button', { name: 'Zoom in' })).toBeVisible();
    const places = page.getByRole('list', { name: 'Our offices: Places on the map' });
    await expect(places.getByRole('listitem')).toHaveCount(3);
    await expect(places.getByRole('link', { name: 'More about Goma <HQ>' })).toHaveAttribute('href', '/offices/goma');
    // Every marker fits in the first view.
    const box = await map.boundingBox();
    for (const marker of await map.locator('.leaflet-marker-icon').all()) {
      const pin = await marker.boundingBox();
      expect(pin.x).toBeGreaterThan(box.x);
      expect(pin.x + pin.width).toBeLessThan(box.x + box.width);
    }
  });

  test('"Show on map" opens the popup and moves focus to the marker; Escape closes it', async ({ page }) => {
    await open(page);
    await page.getByRole('button', { name: 'Show on map: Bukavu' }).click();
    const marker = page.getByRole('region', { name: 'Our offices' }).getByRole('button', { name: 'Bukavu' });
    await expect(marker).toBeFocused();
    const popup = page.locator('#offices .leaflet-popup');
    await expect(popup).toContainText('Place du 24 Novembre');
    await expect(popup.getByRole('button', { name: 'Close' })).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(popup).toHaveCount(0);
  });

  test('the keyboard moves and zooms the map; a marker opens its popup with Enter', async ({ page }) => {
    await open(page);
    const map = page.getByRole('region', { name: 'Our offices' });
    const pane = page.locator('#offices .leaflet-map-pane');
    await map.focus();
    const before = await pane.getAttribute('style');
    await page.keyboard.press('ArrowRight');
    await expect.poll(() => pane.getAttribute('style')).not.toBe(before);

    const zoomIn = map.getByRole('button', { name: 'Zoom in' });
    const zoomBefore = await page.evaluate(() => document.querySelector('#offices .leaflet-tile').src);
    await zoomIn.focus();
    await page.keyboard.press(' ');
    await expect.poll(() => page.evaluate(() => document.querySelector('#offices .leaflet-tile-container:last-child .leaflet-tile')?.src))
      .not.toBe(zoomBefore);

    await map.getByRole('button', { name: 'Kigali' }).focus();
    await page.keyboard.press('Enter');
    await expect(page.locator('#offices .leaflet-popup')).toContainText('Kigali');
  });

  test('the wheel scrolls the page, not the map', async ({ page }) => {
    await page.setViewportSize({ width: 1000, height: 500 });
    await open(page);
    const map = page.getByRole('region', { name: 'Our offices' });
    const before = await page.evaluate(() => document.querySelector('#offices .leaflet-tile').src);
    await map.hover();
    await page.mouse.wheel(0, 300);
    await expect.poll(() => page.evaluate(() => window.scrollY)).toBeGreaterThan(0);
    expect(await page.evaluate(() => document.querySelector('#offices .leaflet-tile').src)).toBe(before);
  });

  for (const mode of MODES) {
    test(`no axe-core violations (${mode}), with a popup open`, async ({ page }) => {
      await open(page, { mode });
      await page.getByRole('button', { name: 'Show on map: Goma <HQ>' }).click();
      await expect(page.locator('#offices .leaflet-popup')).toBeVisible();
      expect(await axeViolations(page)).toEqual([]);
    });
  }
});
