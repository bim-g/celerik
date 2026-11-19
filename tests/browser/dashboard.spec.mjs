import { expect, test } from '@playwright/test';
import { axeViolations, MODES, openComponents } from './helpers.mjs';

const open = (page, options = {}) => openComponents(page, { path: '/dashboard', ...options });

test.describe('dashboard', () => {
  test('stat tiles say their trend in words and name their sparkline', async ({ page }) => {
    const errors = await open(page);
    expect(errors).toEqual([]);
    const kpis = page.getByRole('list', { name: 'This month' });
    await expect(kpis.getByRole('listitem')).toHaveCount(4);
    await expect(page.locator('#kpi-churn .celerik-stat-change')).toHaveText('Down 0.4 pts');
    await expect(page.locator('#kpi-churn .celerik-stat-trend')).toHaveClass(/celerik-stat-trend-good/);
    await expect(page.getByRole('img', { name: /^Revenue, last 7 values: from 8 to 16/ })).toBeVisible();
    // The label's link covers the tile.
    const link = page.getByRole('link', { name: 'Revenue' });
    const box = await page.locator('#kpi-revenue').boundingBox();
    const hit = await page.evaluate(({ x, y }) => document.elementFromPoint(x, y)?.closest('a')?.textContent, {
      x: box.x + box.width - 10,
      y: box.y + box.height - 10,
    });
    expect(hit).toBe('Revenue');
    await expect(link).toHaveAttribute('href', '/reports/revenue');
  });

  test('a chart in a tile grows with it', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    await open(page);
    const canvas = page.locator('#sales-chart canvas');
    const before = (await canvas.boundingBox()).width;
    await page.getByRole('button', { name: 'Arrange Sales by month' }).click();
    await page.getByRole('group', { name: 'Sales by month' }).getByRole('button', { name: 'Wider' }).click();
    await expect(page.locator('#widgets-tile-sales')).toHaveAttribute('data-cols', '3');
    await expect.poll(async () => (await canvas.boundingBox()).width).toBeGreaterThan(before * 1.3);
    await expect(page.getByRole('table', { name: 'Latest orders' })).toBeVisible();
  });

  for (const mode of MODES) {
    test(`no axe-core violations (${mode})`, async ({ page }) => {
      await open(page, { mode });
      expect(await axeViolations(page)).toEqual([]);
    });
  }
});
