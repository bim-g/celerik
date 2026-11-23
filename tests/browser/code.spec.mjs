import { expect, test } from '@playwright/test';
import { axeViolations, MODES, openComponents } from './helpers.mjs';

const open = async (page, options = {}) => {
  const errors = await openComponents(page, { path: '/code', ...options });
  await page.locator('.celerik-code .cm-content').nth(1).waitFor();
  return errors;
};
const editor = (page, name) => page.getByRole('textbox', { name, exact: true });

test.describe('code field', () => {
  test('an editor named by the label, with syntax colors, under a strict CSP', async ({ page }) => {
    const errors = await open(page);
    expect(errors).toEqual([]);
    const source = editor(page, 'PHP source');
    await expect(source).toHaveAttribute('aria-multiline', 'true');
    await expect(source).toHaveAccessibleDescription(
      'Any PHP 8 code. Tab moves to the next field; Ctrl+] indents and Ctrl+[ outdents.',
    );
    await expect(source.locator('.celerik-code-keyword', { hasText: 'function' })).toBeVisible();
    await expect(source.locator('.celerik-code-comment')).toHaveText('// Greets someone.');
    // The textarea is out of sight and of the tab order.
    await expect(page.locator('#snippet-source')).toHaveAttribute('tabindex', '-1');
  });

  test('typing changes the submitted text; Tab leaves the editor', async ({ page }) => {
    await open(page);
    const source = editor(page, 'PHP source');
    await source.click();
    await page.keyboard.press('Control+End');
    await page.keyboard.type('// The end.');
    await expect(page.locator('#snippet-source')).toHaveValue(/\/\/ The end\.$/);
    expect(await page.evaluate(() => new FormData(document.getElementById('snippet')).get('source')))
      .toMatch(/echo greet\('Ada'\) \. 42;\n\/\/ The end\.$/);

    await page.keyboard.press('Tab');
    await expect(editor(page, 'Configuration')).toBeFocused();
  });

  test('with tabIndents(), Tab indents, and Escape then Tab leaves', async ({ page }) => {
    await open(page);
    const config = editor(page, 'Configuration');
    await expect(config).toHaveAccessibleDescription('Tab indents; press Escape, then Tab, to leave the editor.');
    await config.click();
    await page.keyboard.press('Tab');
    await page.keyboard.type('"a": 1');
    await expect(page.locator('#snippet-config')).toHaveValue('    "a": 1');
    await page.keyboard.press('Escape');
    await page.keyboard.press('Tab');
    await expect(page.getByRole('textbox', { name: 'Title' })).toBeFocused();
  });

  test('a required empty editor: focus, an error state, and the message', async ({ page }) => {
    await open(page);
    await page.getByRole('button', { name: 'Save' }).click();
    const config = editor(page, 'Configuration');
    await expect(config).toBeFocused();
    await expect(config).toHaveAttribute('aria-invalid', 'true');
    await expect(config).toHaveAttribute('aria-required', 'true');
    await expect(page.locator('.celerik-code').nth(1)).toHaveClass(/is-invalid/);
    await page.keyboard.type('{}');
    await expect(config).not.toHaveAttribute('aria-invalid', 'true');
  });

  test('search with Ctrl+F, and a reset puts the text back', async ({ page }) => {
    await open(page);
    const source = editor(page, 'PHP source');
    await source.click();
    await page.keyboard.press('Control+f');
    await expect(page.getByRole('textbox', { name: 'Find' })).toBeFocused();
    await page.keyboard.press('Escape');

    await page.keyboard.press('Control+a');
    await page.keyboard.type('changed');
    await page.evaluate(() => document.getElementById('snippet').reset());
    await expect(source).toContainText('function greet');
  });

  test('without JavaScript, a plain textarea', async ({ browser }) => {
    const context = await browser.newContext({ javaScriptEnabled: false });
    const page = await context.newPage();
    await page.goto('/code');
    await expect(page.getByRole('textbox', { name: 'PHP source' })).toHaveValue(/function greet/);
    await context.close();
  });

  test('on a phone the editor scrolls inside its box', async ({ page }) => {
    await page.setViewportSize({ width: 320, height: 800 });
    await open(page);
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(320);
  });

  for (const mode of MODES) {
    test(`no axe-core violations (${mode}), with the search open`, async ({ page }) => {
      await open(page, { mode });
      expect(await axeViolations(page)).toEqual([]);
      await editor(page, 'PHP source').click();
      await page.keyboard.press('Control+f');
      expect(await axeViolations(page)).toEqual([]);
    });
  }
});
