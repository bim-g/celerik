import { expect, test } from '@playwright/test';
import { axeViolations, MODES, openComponents } from './helpers.mjs';

const open = async (page, options = {}) => {
  const errors = await openComponents(page, { path: '/markdown', ...options });
  await page.locator('.caelrik-markdown').first().waitFor();
  return errors;
};
const body = (page) => page.getByRole('textbox', { name: 'Body' });

test.describe('Markdown field', () => {
  test('tabs and a named toolbar around the native textarea, under a strict CSP', async ({ page }) => {
    const errors = await open(page);
    expect(errors).toEqual([]);
    await expect(page.getByRole('tablist', { name: 'Body' })).toBeVisible();
    await expect(page.getByRole('tab', { name: 'Write' }).first()).toHaveAttribute('aria-selected', 'true');
    const toolbar = page.getByRole('toolbar', { name: 'Formatting' }).first();
    await expect(toolbar.getByRole('button')).toHaveCount(8);
    await expect(toolbar.getByRole('button', { name: 'Bold' })).toHaveAttribute('aria-keyshortcuts', 'Control+B');
    await expect(body(page)).toHaveAttribute('required', '');
  });

  test('the toolbar: one tab stop, arrow keys between buttons', async ({ page }) => {
    await open(page);
    const toolbar = page.getByRole('toolbar', { name: 'Formatting' }).first();
    await toolbar.getByRole('button', { name: 'Bold' }).focus();
    await page.keyboard.press('ArrowRight');
    await expect(toolbar.getByRole('button', { name: 'Italic' })).toBeFocused();
    await page.keyboard.press('End');
    await expect(toolbar.getByRole('button', { name: 'Code' })).toBeFocused();
    await page.keyboard.press('ArrowRight');
    await expect(toolbar.getByRole('button', { name: 'Bold' })).toBeFocused();
    await page.keyboard.press('Tab');
    await expect(body(page)).toBeFocused();
  });

  test('shortcuts and buttons format the selection, and Undo undoes them', async ({ page }) => {
    await open(page);
    const textarea = body(page);
    await textarea.fill('Hello world');
    await textarea.evaluate((element) => element.setSelectionRange(6, 11));
    await page.keyboard.press('Control+b');
    await expect(textarea).toHaveValue('Hello **world**');
    await page.keyboard.press('Control+z');
    await expect(textarea).toHaveValue('Hello world');

    await textarea.evaluate((element) => element.setSelectionRange(0, 5));
    await page.keyboard.press('Control+k');
    await expect(textarea).toHaveValue('[Hello]() world');
    // The caret waits for the address.
    await page.keyboard.type('https://example.com');
    await expect(textarea).toHaveValue('[Hello](https://example.com) world');

    await textarea.fill('one\ntwo');
    await textarea.evaluate((element) => element.setSelectionRange(0, 7));
    await page.getByRole('toolbar', { name: 'Formatting' }).first().getByRole('button', { name: 'Numbered list' }).click();
    await expect(textarea).toHaveValue('1. one\n2. two');
  });

  test('the preview is sanitized HTML, and the arrow keys switch tabs', async ({ page }) => {
    await open(page);
    const notes = page.locator('.caelrik-markdown').nth(1);
    await notes.getByRole('tab', { name: 'Write' }).focus();
    await page.keyboard.press('ArrowRight');
    const preview = notes.getByRole('tabpanel', { name: 'Preview' });
    await expect(preview).toBeVisible();
    await expect(notes.getByRole('tab', { name: 'Preview' })).toBeFocused();
    await expect(notes.getByRole('toolbar')).toBeHidden();
    await expect(preview.getByRole('heading', { name: 'Release notes' })).toBeVisible();
    await expect(preview.locator('strong')).toHaveText('0.6');
    await expect(preview.getByRole('link', { name: 'Markdown field' })).toHaveAttribute('rel', 'noopener noreferrer');
    await expect(preview.getByRole('listitem')).toHaveCount(2);
    // DOMPurify: no script, no event handler.
    await expect(preview.locator('script')).toHaveCount(0);
    expect(await preview.locator('img').evaluateAll((images) => images.map((image) => image.getAttribute('onerror'))))
      .toEqual([null]);
    expect(await page.title()).not.toBe('hacked');

    await page.keyboard.press('ArrowLeft');
    await expect(notes.getByRole('textbox')).toBeVisible();
  });

  test('a required field that is empty goes back to its text', async ({ page }) => {
    await open(page);
    await page.getByRole('tab', { name: 'Preview' }).first().click();
    await expect(page.locator('#post-body-preview-panel')).toContainText('Nothing to preview.');
    await page.getByRole('button', { name: 'Publish' }).click();
    await expect(body(page)).toBeFocused();
    await expect(body(page)).toHaveAttribute('aria-invalid', 'true');
    await expect(page.getByRole('tab', { name: 'Write' }).first()).toHaveAttribute('aria-selected', 'true');
  });

  test('without JavaScript, a plain textarea', async ({ browser }) => {
    const context = await browser.newContext({ javaScriptEnabled: false });
    const page = await context.newPage();
    await page.goto('/markdown');
    await expect(page.getByRole('textbox', { name: 'Body' })).toBeVisible();
    await expect(page.getByRole('toolbar')).toHaveCount(0);
    await context.close();
  });

  test('on a phone the toolbar wraps', async ({ page }) => {
    await page.setViewportSize({ width: 320, height: 800 });
    await open(page);
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(320);
  });

  for (const mode of MODES) {
    test(`no axe-core violations (${mode}), writing and previewing`, async ({ page }) => {
      await open(page, { mode });
      expect(await axeViolations(page)).toEqual([]);
      await page.locator('.caelrik-markdown').nth(1).getByRole('tab', { name: 'Preview' }).click();
      expect(await axeViolations(page)).toEqual([]);
    });
  }
});
