import { expect, test } from '@playwright/test';
import { axeViolations, MODES, openComponents } from './helpers.mjs';

const open = (page, options = {}) => openComponents(page, { path: '/diagram', ...options });

test.describe('diagram', () => {
  test('an image named by its caption, and its steps as linked text', async ({ page }) => {
    const errors = await open(page);
    expect(errors).toEqual([]);
    await expect(page.getByRole('img', { name: 'Sign-up. The steps are written as text below.' })).toBeVisible();
    await expect(page.getByRole('figure', { name: 'Sign-up' })).toBeVisible();

    const summary = page.locator('#signup > details > summary');
    await expect(summary).toHaveText('Steps as text');
    await summary.focus();
    await page.keyboard.press('Enter');
    const steps = page.locator('#signup .celerik-diagram-steps > li');
    await expect(steps).toHaveCount(5);
    await expect(steps.nth(2)).toContainText('Yes: Send a confirmation e-mail · No: Fill in the sign-up form');

    // A link leads to the step it names.
    await steps.nth(2).getByRole('link', { name: 'Fill in the sign-up form' }).click();
    await expect(page).toHaveURL(/#signup-step-form$/);
  });

  test('every step fits its text, and steps do not overlap', async ({ page }) => {
    await open(page);
    for (const id of ['signup', 'order']) {
      const boxes = await page.locator(`#${id} .celerik-diagram-node`).evaluateAll((nodes) => nodes.map((node) => {
        const shape = node.firstElementChild.getBoundingClientRect();
        const text = node.querySelector('text').getBoundingClientRect();
        return {
          shape: {
            left: shape.left, right: shape.right, top: shape.top, bottom: shape.bottom,
          },
          text: {
            left: text.left, right: text.right, top: text.top, bottom: text.bottom,
          },
        };
      }));
      for (const [index, { shape, text }] of boxes.entries()) {
        expect(text.left).toBeGreaterThanOrEqual(shape.left);
        expect(text.right).toBeLessThanOrEqual(shape.right);
        expect(text.top).toBeGreaterThanOrEqual(shape.top);
        expect(text.bottom).toBeLessThanOrEqual(shape.bottom);
        for (const other of boxes.slice(index + 1)) {
          const apart = shape.right <= other.shape.left || other.shape.right <= shape.left
            || shape.bottom <= other.shape.top || other.shape.bottom <= shape.top;
          expect(apart).toBe(true);
        }
      }
    }
  });

  test('on a phone the drawing shrinks to the screen, and the text stays', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 800 });
    await open(page);
    const width = await page.evaluate(() => document.documentElement.scrollWidth);
    expect(width).toBeLessThanOrEqual(390);
    await expect(page.locator('#order > details > summary')).toBeVisible();
  });

  for (const mode of MODES) {
    test(`no axe-core violations (${mode}), with the steps open`, async ({ page }) => {
      await open(page, { mode });
      await page.locator('#signup > details > summary').click();
      expect(await axeViolations(page)).toEqual([]);
    });
  }
});
