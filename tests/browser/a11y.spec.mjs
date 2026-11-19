import { expect, test } from '@playwright/test';
import { axeViolations, MODES, openComponents, settled, THEMES } from './helpers.mjs';

// Every component, in light and dark mode, with color contrast checked in a real browser.
for (const theme of THEMES) {
  for (const mode of MODES) {
    test.describe(`${theme} ${mode}`, () => {
      test('all components are ready, with no console errors', async ({ page }) => {
        const errors = await openComponents(page, { theme, mode });
        expect(errors).toEqual([]);
      });

      test('no axe-core violations', async ({ page }) => {
        await openComponents(page, { theme, mode });
        expect(await axeViolations(page)).toEqual([]);
      });

      test('no axe-core violations after failed validation, with the error summary', async ({ page }) => {
        await openComponents(page, { theme, mode });
        await page.locator('#article').getByRole('textbox', { name: /Title/ }).fill('');
        await page.locator('#article').getByRole('button', { name: 'Publish' }).click();
        await expect(page.locator('#article .celerik-error-summary')).toBeVisible();
        expect(await axeViolations(page, { include: '#article' })).toEqual([]);
      });

      test('no axe-core violations with the modal open', async ({ page }) => {
        await openComponents(page, { theme, mode });
        await page.getByRole('button', { name: 'Contact us' }).click();
        await expect(page.getByRole('dialog', { name: 'Contact us' })).toBeVisible();
        await settled(page);
        expect(await axeViolations(page, { include: '.modal.show' })).toEqual([]);
      });

      test('no axe-core violations with an action form open, with an error from the server', async ({ page }) => {
        await openComponents(page, { theme, mode });
        await page.locator('#staff').getByRole('button', { name: 'Edit Ada Lovelace' }).click({ timeout: 15_000 });
        const dialog = page.getByRole('dialog', { name: 'Edit Ada Lovelace' });
        await dialog.getByRole('textbox', { name: /Name/ }).fill('Grace Hopper');
        await dialog.getByRole('button', { name: 'Save' }).click();
        await expect(dialog.getByText('This name is taken.')).toBeVisible({ timeout: 15_000 });
        await settled(page);
        expect(await axeViolations(page, { include: '#staff-form-modal' })).toEqual([]);
      });

      test('no axe-core violations in a tree, with a node open and one loaded', async ({ page }) => {
        await openComponents(page, { theme, mode });
        // The chevron opens a node; a click on its name would check it.
        await page.getByRole('treeitem', { name: 'Pictures', exact: true }).locator('.celerik-tree-toggle').click();
        await expect(page.getByRole('treeitem', { name: 'Holidays' })).toBeVisible({ timeout: 15_000 });
        await page.getByRole('treeitem', { name: 'Holidays' }).focus();
        expect(await axeViolations(page, { include: '#folders' })).toEqual([]);
      });

      test('no axe-core violations in a tree list, all rows open', async ({ page }) => {
        await openComponents(page, { theme, mode });
        await page.getByRole('button', { name: 'Expand all' }).click();
        await page.getByRole('treegrid', { name: 'Plan' }).getByRole('row').nth(3).focus();
        expect(await axeViolations(page, { include: '#plan' })).toEqual([]);
      });

      test('no axe-core violations in a task board, a card picked up', async ({ page }) => {
        await openComponents(page, { theme, mode });
        await page.locator('#sprint .celerik-board-card').first().focus();
        await page.keyboard.press('Space');
        expect(await axeViolations(page, { include: '#sprint' })).toEqual([]);
      });

      test('no axe-core violations in a list view with its pager', async ({ page }) => {
        await openComponents(page, { theme, mode });
        await expect(page.locator('#team [role=status]')).toHaveText('1–4 of 6', { timeout: 15_000 });
        expect(await axeViolations(page, { include: '#team' })).toEqual([]);
      });

      test('no axe-core violations in a grouped grid with totals', async ({ page }) => {
        await openComponents(page, { theme, mode });
        // The endpoint answers through the test server: allow for a slow machine.
        await expect(page.locator('#budget tr.celerik-grid-group')).toHaveCount(2, { timeout: 15_000 });
        expect(await axeViolations(page, { include: '#budget' })).toEqual([]);
      });

      test('no axe-core violations with a row action\'s confirmation open', async ({ page }) => {
        await openComponents(page, { theme, mode });
        await page.locator('#users').getByRole('button', { name: 'Delete Ada Lovelace' }).click();
        await expect(page.getByRole('alertdialog', { name: 'Delete Ada Lovelace?' })).toBeVisible();
        expect(await axeViolations(page, { include: '.celerik-action-dialog' })).toEqual([]);
      });

      test('no axe-core violations with a searchable select open, matches highlighted', async ({ page }) => {
        await openComponents(page, { theme, mode });
        await page.getByRole('combobox', { name: 'Reviewer' }).focus();
        await page.keyboard.type('an');
        // The endpoint answers through the test server: allow for a slow machine.
        await expect(page.locator('#preferences-reviewer-ts-dropdown .highlight').first()).toBeVisible({ timeout: 15_000 });
        expect(await axeViolations(page, { include: '#preferences' })).toEqual([]);
      });

      test('no axe-core violations with a tags field offering a new tag', async ({ page }) => {
        await openComponents(page, { theme, mode });
        await page.getByRole('combobox', { name: 'Skills' }).focus();
        await page.keyboard.type('Ru');
        await expect(page.locator('#preferences-skills-ts-dropdown .create')).toBeVisible();
        expect(await axeViolations(page, { include: '#preferences' })).toEqual([]);
      });
    });
  }
}
