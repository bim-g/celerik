import { expect, test } from '@playwright/test';
import { axeViolations, MODES, openComponents } from './helpers.mjs';

const open = (page, options = {}) => openComponents(page, { path: '/gauges', ...options });

test.describe('gauges and sparklines', () => {
  test('a gauge is a meter named by its label, with its band and target in its value text', async ({ page }) => {
    const errors = await open(page);
    expect(errors).toEqual([]);
    const cpu = page.getByRole('meter', { name: 'CPU' });
    await expect(cpu).toHaveAttribute('aria-valuenow', '72');
    await expect(cpu).toHaveAttribute('aria-valuetext', '72%, High, target 90%');
    // The band is written, not only colored.
    await expect(page.locator('#cpu .caelrik-gauge-band')).toHaveText('High');
    await expect(page.getByRole('meter', { name: 'Storage used' })).toHaveAttribute('aria-valuetext', '1,250 GB, OK, target 1,500 GB');
  });

  test('the semicircle keeps its shape and its value sits inside it', async ({ page }) => {
    await open(page);
    for (const id of ['cpu', 'memory', 'disk']) {
      const svg = await page.locator(`#${id} .caelrik-gauge-svg`).boundingBox();
      expect(svg.width / svg.height).toBeCloseTo(100 / 52, 1);
      const value = await page.locator(`#${id} .caelrik-gauge-value`).boundingBox();
      expect(value.x).toBeGreaterThan(svg.x);
      expect(value.x + value.width).toBeLessThan(svg.x + svg.width);
    }
  });

  test('a linear gauge fills its share of the bar', async ({ page }) => {
    await open(page);
    const track = await page.locator('#quota .caelrik-gauge-track').boundingBox();
    const fill = await page.locator('#quota .caelrik-gauge-fill').boundingBox();
    expect(fill.width / track.width).toBeCloseTo(0.625, 2);
  });

  test('sparklines are images named by their range, or hidden next to their values', async ({ page }) => {
    await open(page);
    await expect(page.getByRole('img', { name: 'Revenue, last 7 values: from 8 to 16, lowest 8, highest 16.' })).toBeVisible();
    await expect(page.getByRole('img', { name: /^Profit, last 7 values/ })).toBeVisible();
    await expect(page.getByRole('img')).toHaveCount(3);
    const box = await page.locator('#spark-revenue').boundingBox();
    expect(box.width / box.height).toBeCloseTo(4, 1);
  });

  for (const mode of MODES) {
    test(`no axe-core violations (${mode})`, async ({ page }) => {
      await open(page, { mode });
      expect(await axeViolations(page)).toEqual([]);
    });
  }
});
