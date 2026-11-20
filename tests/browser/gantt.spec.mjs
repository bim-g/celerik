import { expect, test } from '@playwright/test';
import { axeViolations, MODES, openComponents } from './helpers.mjs';

const open = (page, options = {}) => openComponents(page, { path: '/gantt', ...options });

test.describe('gantt', () => {
  test('a table named by its label, whose rows say what the bars show', async ({ page }) => {
    const errors = await open(page);
    expect(errors).toEqual([]);
    const table = page.getByRole('table', { name: 'Product launch' });
    await expect(page.getByRole('region', { name: 'Product launch' })).toBeVisible();
    const build = table.getByRole('row', { name: /^Build <api>/ });
    await expect(build.getByRole('cell')).toHaveText(['Nov 16, 2026', 'Dec 11, 2026', '40%', 'Design', '']);
    await expect(table.getByRole('row', { name: /^Release/ }).getByRole('cell').nth(2)).toHaveText('Milestone');
  });

  test('each bar lines up with its row and spans its days', async ({ page }) => {
    await open(page);
    for (const id of ['research', 'design', 'build', 'release']) {
      const row = await page.locator(`#launch tr[data-task="${id}"]`).boundingBox();
      const shape = await page.locator(`#launch .celerik-gantt-row [data-task="${id}"]`).boundingBox();
      expect(shape.y).toBeGreaterThanOrEqual(row.y);
      expect(shape.y + shape.height).toBeLessThanOrEqual(row.y + row.height);
    }
    // Design: 12 days at 10 px a day, less its 1 px margins (and give or take its border).
    const width = (await page.locator('#launch .celerik-gantt-bar[data-task="design"]').boundingBox()).width;
    expect(Math.abs(width - 118)).toBeLessThanOrEqual(2);
  });

  test('arrows go from the end of a task to the start of the tasks that wait for it', async ({ page }) => {
    await open(page);
    const links = page.locator('#launch .celerik-gantt-link');
    await expect(links).toHaveCount(6);
    // The DOM's boxes: Playwright's own add strokes and markers differently for SVG shapes.
    const box = (selector) => page.locator(selector).evaluate((element) => {
      const { left, right } = element.getBoundingClientRect();
      return { left, right };
    });
    const link = await box('#launch .celerik-gantt-link[data-from="design"][data-to="build"]');
    const design = await box('#launch .celerik-gantt-bar[data-task="design"]');
    const build = await box('#launch .celerik-gantt-bar[data-task="build"]');
    expect(link.left).toBeCloseTo(design.right, 0);
    expect(link.right).toBeCloseTo(build.left, 0);
  });

  test('on a phone the timeline scrolls sideways, the task names stay, and today is brought into view', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 800 });
    await open(page);
    const scroll = page.locator('#launch .celerik-gantt-scroll');
    expect(await scroll.evaluate((element) => element.scrollLeft)).toBeGreaterThan(0);
    const box = await scroll.boundingBox();
    const today = await page.locator('#launch .celerik-gantt-scale .celerik-gantt-today').boundingBox();
    expect(today.x).toBeGreaterThan(box.x);
    expect(today.x).toBeLessThan(box.x + box.width);
    const name = await page.locator('#launch tbody .celerik-gantt-name').first().boundingBox();
    expect(Math.round(name.x)).toBe(Math.round(box.x + 1));

    // The region scrolls from the keyboard.
    await scroll.focus();
    const before = await scroll.evaluate((element) => element.scrollLeft);
    await page.keyboard.press('ArrowLeft');
    await expect.poll(() => scroll.evaluate((element) => element.scrollLeft)).toBeLessThan(before);
  });

  for (const mode of MODES) {
    test(`no axe-core violations (${mode})`, async ({ page }) => {
      await open(page, { mode });
      expect(await axeViolations(page)).toEqual([]);
    });
  }
});
