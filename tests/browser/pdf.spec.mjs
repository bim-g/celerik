import { expect, test } from '@playwright/test';
import { axeViolations, MODES, openComponents } from './helpers.mjs';

// The missing document's 404 is expected.
const open = async (page, options = {}) => (await openComponents(page, { path: '/pdf', ...options }))
  .filter((error) => !error.includes('missing.pdf') && !error.includes('404'));

const pageWidth = (page, number) => page.locator(`#report .celerik-pdf-page[data-page="${number}"]`)
  .evaluate((element) => element.getBoundingClientRect().width);

test.describe('PDF viewer', () => {
  test('a named region with its pages, their text and a download link, under a strict CSP', async ({ page }) => {
    const errors = await open(page);
    expect(errors).toEqual([]);
    const viewer = page.getByRole('region', { name: 'Annual report 2026', exact: true });
    await expect(viewer).toBeVisible();
    await expect(viewer.getByRole('link', { name: 'Download the PDF' })).toHaveAttribute('href', '/files/report.pdf');
    await expect(viewer.getByRole('group', { name: 'Page 1 of 3' })).toBeVisible();
    await expect(viewer.locator('.celerik-pdf-total')).toHaveText('of 3');

    // The text layer: screen readers read it, and the browser's search finds it on every page.
    await expect(viewer.getByRole('group', { name: 'Page 3 of 3' })).toContainText('Page 3. Outlook');
    await expect(viewer.getByRole('group', { name: 'Page 1 of 3' }).locator('canvas')).toHaveAttribute('aria-hidden', 'true');
    // The first page is drawn: not a blank canvas.
    const inked = await viewer.locator('.celerik-pdf-page[data-page="1"] canvas').evaluate((canvas) => {
      const { data } = canvas.getContext('2d').getImageData(0, 0, canvas.width, canvas.height);
      let dark = 0;
      for (let i = 0; i < data.length; i += 4) dark += data[i] < 128 ? 1 : 0;
      return dark;
    });
    expect(inked).toBeGreaterThan(1000);
  });

  test('previous, next and a page number, with the keyboard', async ({ page }) => {
    await open(page);
    const viewer = page.locator('#report');
    const number = viewer.getByLabel('Page', { exact: true });
    await expect(viewer.getByRole('button', { name: 'Previous page' })).toBeDisabled();

    await viewer.getByRole('button', { name: 'Next page' }).focus();
    await page.keyboard.press('Enter');
    await expect(number).toHaveValue('2');
    await expect(viewer.getByRole('status')).toHaveText('Page 2 of 3');
    await expect(viewer.getByRole('group', { name: 'Page 2 of 3' })).toBeInViewport({ ratio: 0.3 });

    await number.fill('3');
    await number.press('Enter');
    await expect(viewer.getByRole('button', { name: 'Next page' })).toBeDisabled();
    await expect(viewer.getByRole('status')).toHaveText('Page 3 of 3');

    // The pages scroll with the keyboard too, and the number follows.
    await viewer.getByRole('region', { name: 'Annual report 2026, pages' }).focus();
    await page.keyboard.press('Home');
    await expect(number).toHaveValue('1');
  });

  test('zoom: in, out, a percentage and back to the width', async ({ page }) => {
    await open(page);
    const viewer = page.locator('#report');
    const fit = await pageWidth(page, 1);

    await viewer.getByLabel('Zoom', { exact: true }).selectOption('100');
    await expect.poll(() => pageWidth(page, 1)).toBeCloseTo(595, -1);
    await viewer.getByRole('button', { name: 'Zoom in' }).click();
    await expect.poll(() => pageWidth(page, 1)).toBeCloseTo(595 * 1.25, -1);
    await expect(viewer.getByLabel('Zoom', { exact: true })).toHaveValue('125');
    await viewer.getByRole('button', { name: 'Zoom out' }).click();
    await viewer.getByRole('button', { name: 'Zoom out' }).click();
    await expect.poll(() => pageWidth(page, 1)).toBeCloseTo(595 * 0.75, -1);

    await viewer.getByLabel('Zoom', { exact: true }).selectOption('fit');
    await expect.poll(() => pageWidth(page, 1)).toBeCloseTo(fit, 0);
    // Text stays on top of the drawing after a zoom.
    await expect(viewer.getByRole('group', { name: 'Page 1 of 3' })).toContainText('Page 1. Summary');
  });

  test('a document that cannot be opened says so, with a download link', async ({ page }) => {
    await open(page);
    const viewer = page.locator('#missing');
    await expect(viewer.locator('.celerik-pdf-viewport')).toContainText('This document could not be opened. Download it instead.');
    await expect(viewer.locator('.celerik-pdf-viewport').getByRole('link', { name: 'Download the PDF' })).toBeVisible();
    await expect(viewer.locator('.celerik-pdf-toolbar')).toBeHidden();
  });

  test('without JavaScript, a message and the download link', async ({ browser }) => {
    const context = await browser.newContext({ javaScriptEnabled: false });
    const page = await context.newPage();
    await page.goto('/pdf');
    await expect(page.locator('#report .celerik-pdf-viewport'))
      .toContainText('The document is shown here with JavaScript. Download it instead.');
    await expect(page.locator('#report .celerik-pdf-toolbar')).toBeHidden();
    await context.close();
  });

  test('on a phone the pages fit the width', async ({ page }) => {
    await page.setViewportSize({ width: 360, height: 800 });
    await open(page);
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(360);
    const viewport = await page.locator('#report .celerik-pdf-viewport').boundingBox();
    expect(await pageWidth(page, 1)).toBeLessThanOrEqual(viewport.width);
  });

  for (const mode of MODES) {
    test(`no axe-core violations (${mode})`, async ({ page }) => {
      await open(page, { mode });
      await expect(page.locator('#report').getByRole('group', { name: 'Page 1 of 3' })).toContainText('Summary');
      expect(await axeViolations(page)).toEqual([]);
    });
  }
});
