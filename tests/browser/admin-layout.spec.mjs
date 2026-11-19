import { expect, test } from '@playwright/test';
import { axeViolations, MODES, openComponents } from './helpers.mjs';

const open = (page, options = {}) => openComponents(page, { path: '/admin/members/42', ...options });

test.describe('admin layout', () => {
  test('the skip link is the first tab stop and moves focus to the main content', async ({ page }) => {
    const errors = await open(page);
    expect(errors).toEqual([]);
    await page.keyboard.press('Tab');
    const skip = page.getByRole('link', { name: 'Skip to main content' });
    await expect(skip).toBeFocused();
    await expect(skip).toBeVisible();
    await page.keyboard.press('Enter');
    await expect(page.getByRole('main')).toBeFocused();
  });

  test('on a large screen, the sidebar is beside the content, with the current section marked', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    await open(page);
    const nav = page.getByRole('navigation', { name: 'Main' });
    await expect(nav).toBeVisible();
    await expect(page.getByRole('button', { name: 'Open navigation' })).toBeHidden();
    await expect(nav.getByRole('link', { name: 'All members' })).toHaveAttribute('aria-current', 'true');
    // A group is a disclosure: its summary opens and closes it.
    const group = nav.locator('details');
    await expect(group).toHaveAttribute('open', '');
    await nav.getByText('Members', { exact: true }).click();
    await expect(group).not.toHaveAttribute('open', '');
  });

  test('on a phone, the menu button opens the navigation as a dialog; Escape closes it and focus returns', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 800 });
    await open(page);
    const button = page.getByRole('button', { name: 'Open navigation' });
    const sidebar = page.getByRole('dialog', { name: 'Navigation' });
    await expect(page.getByRole('navigation', { name: 'Main' })).toBeHidden();

    await button.focus();
    await page.keyboard.press('Enter');
    await expect(sidebar).toBeVisible();
    await expect(sidebar.getByRole('link', { name: 'Dashboard' })).toBeVisible();
    expect(await axeViolations(page, { include: '#app-sidebar' })).toEqual([]);
    await page.keyboard.press('Escape');
    await expect(sidebar).toBeHidden();
    await expect(button).toBeFocused();
  });

  test('without JavaScript, the navigation is shown on a phone', async ({ browser }) => {
    const context = await browser.newContext({ javaScriptEnabled: false, viewport: { width: 390, height: 800 } });
    const page = await context.newPage();
    await page.goto('/admin/members/42');
    await expect(page.getByRole('navigation', { name: 'Main' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Invitations 3' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Open navigation' })).toBeHidden();
    await context.close();
  });

  test('the user menu opens from the keyboard; Escape closes it and returns focus', async ({ page }) => {
    await open(page);
    const toggle = page.locator('#account summary');
    await toggle.focus();
    await page.keyboard.press('Enter');
    const profile = page.getByRole('link', { name: 'Profile' });
    await expect(profile).toBeVisible();
    await page.keyboard.press('Tab');
    await expect(profile).toBeFocused();
    await page.keyboard.press('Escape');
    await expect(profile).toBeHidden();
    await expect(toggle).toBeFocused();
  });

  for (const mode of MODES) {
    test(`no axe-core violations (${mode}), with the user menu open`, async ({ page }) => {
      await open(page, { mode });
      expect(await axeViolations(page)).toEqual([]);
      await page.locator('#account summary').click();
      await expect(page.getByRole('link', { name: 'Profile' })).toBeVisible();
      expect(await axeViolations(page)).toEqual([]);
    });
  }
});
