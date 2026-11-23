import { expect, test } from '@playwright/test';
import { axeViolations, MODES, openComponents } from './helpers.mjs';

const open = (page, options = {}) => openComponents(page, { path: '/signature', ...options });

// Draws a zigzag across the pad with the mouse.
async function sign(page, selector = '#contract-signature-group canvas') {
  const box = await page.locator(selector).boundingBox();
  await page.mouse.move(box.x + box.width * 0.2, box.y + box.height * 0.6);
  await page.mouse.down();
  for (const [x, y] of [[0.3, 0.3], [0.4, 0.7], [0.5, 0.3], [0.6, 0.7], [0.7, 0.4]]) {
    await page.mouse.move(box.x + box.width * x, box.y + box.height * y, { steps: 4 });
  }
  await page.mouse.up();
}

// Submits the form and returns what Field::signatureImage() made of each value.
async function submit(page) {
  const [response] = await Promise.all([
    page.waitForResponse((each) => each.url().endsWith('/signature') && each.request().method() === 'POST'),
    page.getByRole('button', { name: 'Sign', exact: true }).click(),
  ]);
  return response.json();
}

test.describe('signature', () => {
  test('a required signature asks for a drawing or a name', async ({ page }) => {
    const errors = await open(page);
    expect(errors).toEqual([]);
    const name = page.getByLabel('Type your name').first();
    await expect(page.getByRole('group', { name: 'Your signature' })).toBeVisible();
    await expect(page.getByRole('img', { name: 'Signature pad, empty' }).first()).toBeVisible();

    await page.getByRole('button', { name: 'Sign', exact: true }).click();
    await expect(name).toBeFocused();
    await expect(name).toHaveAttribute('aria-invalid', 'true');
    await expect(page.locator('#contract-signature-group').locator('..')).toContainText('Draw or type your signature.');

    await sign(page);
    await expect(page.locator('#contract-signature-group canvas')).toHaveAccessibleName('Signature pad, signed');
    await expect(name).not.toHaveAttribute('aria-invalid', 'true');
  });

  test('a drawing is submitted as a PNG that PHP decodes', async ({ page }) => {
    await open(page);
    await sign(page);
    const result = await submit(page);
    expect(result.signature).toEqual({ width: 600, height: 200, bytes: expect.any(Number) });
    expect(result.signature.bytes).toBeGreaterThan(500);
    // An empty pad that is not required submits nothing.
    expect(result.witness).toEqual({ text: '' });
  });

  test('with the keyboard: a typed name is drawn and submitted as an image', async ({ page }) => {
    await open(page);
    await page.keyboard.press('Tab');
    // The pad is not in the tab order: Clear, then the name input.
    await expect(page.getByRole('button', { name: 'Clear the signature' }).first()).toBeFocused();
    await page.keyboard.press('Tab');
    await expect(page.getByLabel('Type your name').first()).toBeFocused();
    await page.keyboard.type('Ada Lovelace');

    const inked = await page.locator('#contract-signature-group canvas').evaluate((canvas) => {
      const { data } = canvas.getContext('2d').getImageData(0, 0, canvas.width, canvas.height);
      let count = 0;
      for (let i = 3; i < data.length; i += 4) count += data[i] > 0 ? 1 : 0;
      return count;
    });
    expect(inked).toBeGreaterThan(200);
    const result = await submit(page);
    expect(result.signature.width).toBe(600);
  });

  test('Clear empties the pad', async ({ page }) => {
    await open(page);
    await sign(page);
    await page.getByRole('button', { name: 'Clear the signature' }).first().click();
    await expect(page.locator('#contract-signature-group canvas')).toHaveAccessibleName('Signature pad, empty');
    expect(await page.locator('#contract-signature-image').inputValue()).toBe('');
  });

  test('without JavaScript the typed name is the signature', async ({ browser }) => {
    const context = await browser.newContext({ javaScriptEnabled: false });
    const page = await context.newPage();
    await page.goto('/signature');
    await expect(page.locator('.caelrik-signature-pad').first()).toBeHidden();
    await page.getByLabel('Type your name').first().fill('Ada Lovelace');
    const [response] = await Promise.all([
      page.waitForResponse((each) => each.url().endsWith('/signature') && each.request().method() === 'POST'),
      page.getByRole('button', { name: 'Sign', exact: true }).click(),
    ]);
    expect((await response.json()).signature).toEqual({ text: 'Ada Lovelace' });
    await context.close();
  });

  test('on a phone the pad fits the screen', async ({ page }) => {
    await page.setViewportSize({ width: 320, height: 800 });
    await open(page);
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(320);
    const box = await page.locator('#contract-signature-group canvas').boundingBox();
    expect(box.width).toBeGreaterThan(250);
    expect(box.height).toBeCloseTo(box.width / 3, 0);
  });

  for (const mode of MODES) {
    test(`no axe-core violations (${mode}), signed`, async ({ page }) => {
      await open(page, { mode });
      await sign(page);
      expect(await axeViolations(page)).toEqual([]);
    });
  }
});
