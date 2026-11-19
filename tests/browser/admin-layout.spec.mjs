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

  test('a toolbar is one tab stop; its menu button opens with the keyboard and an action asks first', async ({ page }) => {
    await open(page);
    const toolbar = page.getByRole('toolbar', { name: 'Member' });
    const edit = toolbar.getByRole('link', { name: 'Edit' });
    const exportButton = toolbar.getByRole('button', { name: 'Export' });
    await edit.focus();
    await page.keyboard.press('ArrowRight');
    await expect(exportButton).toBeFocused();
    await expect(exportButton).toHaveAttribute('aria-haspopup', 'menu');

    await page.keyboard.press('Enter');
    const menu = page.getByRole('menu', { name: 'Export' });
    await expect(menu).toBeVisible();
    await expect(menu.getByRole('menuitem', { name: 'CSV' })).toBeFocused();
    await page.keyboard.press('End');
    await expect(menu.getByRole('menuitem', { name: 'Deactivate' })).toBeFocused();
    expect(await axeViolations(page, { include: '#member-tools' })).toEqual([]);
    await page.keyboard.press('Enter');
    await expect(menu).toBeHidden();
    const dialog = page.getByRole('alertdialog', { name: 'Deactivate Ada Lovelace?' });
    await expect(dialog).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(exportButton).toBeFocused();

    // Tab leaves the toolbar from any of its controls.
    await page.keyboard.press('Tab');
    await expect(toolbar.locator(':focus')).toHaveCount(0);
  });

  test('a context menu opens with Shift+F10 on a file and with a right click, for that file', async ({ page }) => {
    await open(page);
    const file = page.locator('#files [data-id="8"]');
    await file.focus();
    await page.keyboard.press('Shift+F10');
    const menu = page.getByRole('menu', { name: 'File actions' });
    await expect(menu).toBeVisible();
    await expect(menu.getByRole('menuitem', { name: 'Open Photo.jpg' })).toBeFocused();
    await expect(menu.getByRole('menuitem', { name: 'Download' })).toHaveAttribute('href', '/admin/files/8/download');
    expect(await axeViolations(page, { include: '.celerik-contextmenu-list' })).toEqual([]);
    await page.keyboard.press('Escape');
    await expect(menu).toBeHidden();
    await expect(file).toBeFocused();

    await page.locator('#files [data-id="7"]').click({ button: 'right' });
    await expect(menu.getByRole('menuitem', { name: 'Open Contract.pdf' })).toBeVisible();
  });

  test('a horizontal navigation group drops down and closes with Escape', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    await open(page);
    const site = page.getByRole('navigation', { name: 'Site' });
    await site.getByText('Help', { exact: true }).click();
    await expect(site.getByRole('link', { name: 'Guide' })).toBeVisible();
    await site.getByRole('link', { name: 'Guide' }).focus();
    await page.keyboard.press('Escape');
    await expect(site.getByRole('link', { name: 'Guide' })).toBeHidden();
  });

  test('the accordion: one panel at a time, arrows between headings', async ({ page }) => {
    await open(page);
    const access = page.getByRole('button', { name: 'Access' });
    const billing = page.getByRole('button', { name: 'Billing' });
    await expect(access).toHaveAttribute('aria-expanded', 'true');
    await expect(page.getByRole('region', { name: 'Billing' })).toBeHidden();
    await access.focus();
    await page.keyboard.press('ArrowDown');
    await expect(billing).toBeFocused();
    await page.keyboard.press('Enter');
    await expect(page.getByRole('region', { name: 'Billing' })).toBeVisible();
    await expect(access).toHaveAttribute('aria-expanded', 'false');
  });

  test('the splitter: keys and a drag resize the panes', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    await open(page);
    const divider = page.getByRole('separator', { name: 'Resize Files' });
    await expect(divider).toHaveAttribute('aria-valuenow', '30');
    await divider.focus();
    await page.keyboard.press('ArrowRight');
    await expect(divider).toHaveAttribute('aria-valuenow', '35');
    await page.keyboard.press('Home');
    await expect(divider).toHaveAttribute('aria-valuenow', '20');

    const box = await page.locator('#member-split').boundingBox();
    const handle = await divider.boundingBox();
    await page.mouse.move(handle.x + handle.width / 2, handle.y + handle.height / 2);
    await page.mouse.down();
    await page.mouse.move(box.x + box.width * 0.5, handle.y + handle.height / 2, { steps: 5 });
    await page.mouse.up();
    const value = Number(await divider.getAttribute('aria-valuenow'));
    expect(value).toBeGreaterThan(45);
    expect(value).toBeLessThan(55);
    const files = await page.locator('#member-split-pane-1').boundingBox();
    expect(files.width / box.width).toBeGreaterThan(0.4);
  });

  test('the card: its title link covers it, its action is its own', async ({ page }) => {
    await open(page);
    const card = page.locator('#member-card');
    await expect(card.getByRole('link', { name: 'Ada Lovelace' })).toHaveAttribute('href', '/admin/members/42/profile');
    const action = card.getByRole('link', { name: 'Send a message' });
    // The action sits above the stretched link: it is what a click on it reaches.
    const hit = await action.evaluate((link) => {
      const rect = link.getBoundingClientRect();
      return document.elementFromPoint(rect.x + rect.width / 2, rect.y + rect.height / 2) === link;
    });
    expect(hit).toBe(true);
  });
});
