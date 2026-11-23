import { expect, test } from '@playwright/test';
import { MODES, openComponents } from './helpers.mjs';

// Caelrik's flat design (resources/scss/_flat.scss): borders instead of shadows, and field
// borders that can be seen (3:1 on the page, WCAG 1.4.11), in light and dark mode.

// WCAG contrast of two computed "rgb(r, g, b)" colors.
function contrast(a, b) {
  const luminance = (color) => {
    const [r, g, bl] = color.match(/\d+(\.\d+)?/g).slice(0, 3).map(Number)
      .map((c) => c / 255).map((c) => (c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4));
    return 0.2126 * r + 0.7152 * g + 0.0722 * bl;
  };
  const [light, dark] = [luminance(a), luminance(b)].sort((x, y) => y - x);
  return (light + 0.05) / (dark + 0.05);
}

for (const mode of MODES) {
  test.describe(mode, () => {
    test('every kind of field has a border with 3:1 contrast on the page', async ({ page }) => {
      await openComponents(page, { mode });
      const background = await page.evaluate(() => getComputedStyle(document.body).backgroundColor);
      const fields = {
        'text input': '#signup-name',
        select: '#signup-country',
        checkbox: '#signup-terms',
        'searchable select': '#preferences-languages-ts-control',
        'rich text editor': '.ql-container.ql-snow',
        'one-time code box': '.caelrik-otp-slot',
      };
      for (const [name, selector] of Object.entries(fields)) {
        // Tom Select draws the border on its wrapper, around the search box.
        const border = await page.locator(selector).first().evaluate((element) => {
          const target = element.closest('.ts-wrapper') ?? element;
          return getComputedStyle(target).borderTopColor;
        });
        expect([name, contrast(border, background) >= 3]).toEqual([name, true]);
      }
    });

    test('surfaces are flat: no drop shadows, and the current calendar view is filled', async ({ page }) => {
      await openComponents(page, { mode });
      const shadow = (selector) => page.locator(selector).first().evaluate((element) => getComputedStyle(element).boxShadow);

      expect(await shadow('.notyf__toast')).toBe('none');
      expect(await shadow('.caelrik-scheduler-dialog, #team-calendar')).toBe('none');

      const active = page.locator('#team-calendar .fc-button-active');
      const [color, fill] = await active.evaluate((element) => [getComputedStyle(element).color, getComputedStyle(element).backgroundColor]);
      expect(contrast(color, fill)).toBeGreaterThanOrEqual(4.5);
      // The other toolbar buttons are outlined, not filled.
      expect(await page.locator('#team-calendar .fc-next-button').evaluate((element) => getComputedStyle(element).backgroundColor))
        .toBe('rgba(0, 0, 0, 0)');

      // The searchable select's dropdown has a border, not a shadow.
      await page.getByRole('combobox', { name: 'Reviewer' }).focus();
      await page.keyboard.type('an');
      // The endpoint answers through the test server: allow for a slow machine.
      await expect(page.locator('#preferences-reviewer-ts-dropdown .option').first()).toBeVisible({ timeout: 15_000 });
      expect(await shadow('#preferences-reviewer-ts-dropdown')).toBe('none');
    });
  });
}
