import { expect, test } from '@playwright/test';
import { axeViolations, MODES, openComponents } from './helpers.mjs';

const open = (page, options = {}) => openComponents(page, { path: '/display', ...options });

test.describe('display elements', () => {
  test('a tooltip stays while the pointer moves onto it, and Escape hides it without moving focus', async ({ page }) => {
    const errors = await open(page);
    expect(errors).toEqual([]);
    const save = page.getByRole('button', { name: 'Save' });
    await expect(save).toHaveAccessibleDescription('Saves a draft you can finish later');
    const tip = page.locator('#save-tip-tip');

    await save.hover();
    await expect(tip).toBeVisible();
    // From the button onto the tooltip (WCAG 1.4.13: hoverable).
    const box = await tip.boundingBox();
    await page.mouse.move(box.x + box.width / 2, box.y + box.height / 2, { steps: 4 });
    await page.waitForTimeout(300);
    await expect(tip).toBeVisible();
    await page.mouse.move(5, 700);
    await expect(tip).toBeHidden();

    await save.focus();
    await expect(tip).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(tip).toBeHidden();
    await expect(save).toBeFocused();
  });

  test('a tooltip at the top of the page shows below its control', async ({ page }) => {
    await open(page);
    const control = page.getByRole('button', { name: 'At the edge' });
    await control.focus();
    const tip = page.locator('#edge-tip-tip');
    await expect(tip).toBeVisible();
    await expect(tip).toHaveClass(/is-below/);
    expect((await tip.boundingBox()).y).toBeGreaterThan((await control.boundingBox()).y);
  });

  test('plain text with a tooltip is reachable from the keyboard', async ({ page }) => {
    await open(page);
    const term = page.locator('.celerik-tooltip-text');
    await term.focus();
    await expect(term).toHaveAccessibleDescription('A secret that identifies your application');
    await expect(page.locator('#api-tip-tip')).toBeVisible();
  });

  test('the popover opens from the keyboard; Escape closes it and returns focus', async ({ page }) => {
    await open(page);
    // A <summary>: browsers expose it as a disclosure button; Playwright has no role for it.
    const button = page.locator('#pricing-help > summary');
    await expect(button).toHaveAccessibleName('About pricing');
    await button.focus();
    await page.keyboard.press('Enter');
    const panel = page.getByRole('group', { name: 'Pricing' });
    await expect(panel).toBeVisible();
    await page.keyboard.press('Tab');
    await expect(panel.getByRole('link', { name: 'See all prices' })).toBeFocused();
    await page.keyboard.press('Escape');
    await expect(panel).toBeHidden();
    await expect(button).toBeFocused();
  });

  test('a chip is removed from the keyboard, its field with it; focus goes to the next chip', async ({ page }) => {
    await open(page);
    await page.getByRole('button', { name: 'Remove JavaScript' }).focus();
    await page.keyboard.press('Enter');
    await expect(page.locator('#chip-js')).toHaveCount(0);
    await expect(page.getByRole('button', { name: 'Remove Go' })).toBeFocused();
    await expect(page.locator('#celerik-chip-status')).toHaveText('JavaScript removed.');
    const values = await page.locator('#skills').evaluate((form) => new FormData(form).getAll('skills[]'));
    expect(values).toEqual(['php', 'go']);

    // A filter chip is a checkbox.
    const onsite = page.getByRole('checkbox', { name: 'On site' });
    await onsite.focus();
    await page.keyboard.press('Space');
    await expect(onsite).toBeChecked();
  });

  test('chip buttons are 24 x 24 px targets', async ({ page }) => {
    await open(page);
    for (const button of await page.locator('.celerik-chip-remove').all()) {
      const box = await button.boundingBox();
      expect(box.width).toBeGreaterThanOrEqual(24);
      expect(box.height).toBeGreaterThanOrEqual(24);
    }
  });

  for (const mode of MODES) {
    test(`no axe-core violations (${mode}), with a tooltip and the popover open`, async ({ page }) => {
      await open(page, { mode });
      await page.getByRole('button', { name: 'Save' }).focus();
      await page.locator('#pricing-help > summary').click();
      expect(await axeViolations(page)).toEqual([]);
    });
  }
});
