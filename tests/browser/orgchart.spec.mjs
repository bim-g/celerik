import { expect, test } from '@playwright/test';
import { axeViolations, MODES, openComponents } from './helpers.mjs';

const open = (page, options = {}) => openComponents(page, { path: '/orgchart', ...options });

test.describe('org chart', () => {
  test('people in nested lists, named by the chart', async ({ page }) => {
    const errors = await open(page);
    expect(errors).toEqual([]);
    await expect(page.getByRole('region', { name: 'Company' })).toBeVisible();
    const tree = page.getByRole('list', { name: 'Company' });
    await expect(tree.getByRole('listitem')).toHaveCount(8);
    // Grace's team: a list of 3 inside her item.
    const grace = tree.getByRole('listitem').filter({ hasText: 'Grace Hopper' }).first();
    await expect(grace.getByRole('list').first().locator(':scope > li')).toHaveCount(3);
  });

  test('a team hangs under its manager, its members side by side', async ({ page }) => {
    await open(page);
    const box = (name) => page.locator('#company .caelrik-orgchart-node', { hasText: name }).boundingBox();
    const grace = await box('Grace Hopper');
    const ada = await box('Ada Lovelace');
    const katherine = await box('Katherine Johnson');
    expect(ada.y).toBeGreaterThan(grace.y + grace.height);
    expect(Math.round(ada.y)).toBe(Math.round(katherine.y));
    expect(ada.x + ada.width).toBeLessThan(katherine.x);
  });

  test('a manager\'s button hides and shows their team from the keyboard', async ({ page }) => {
    await open(page);
    const button = page.getByRole('button', { name: "Ada Lovelace's team, 3 people" }).first();
    await expect(button).toHaveAttribute('aria-expanded', 'true');
    await button.focus();
    await page.keyboard.press('Enter');
    await expect(button).toHaveAttribute('aria-expanded', 'false');
    await expect(page.locator('#company').getByText('Alan Turing')).toBeHidden();
    await page.keyboard.press('Space');
    await expect(page.locator('#company').getByText('Alan Turing')).toBeVisible();
  });

  test('a narrow chart is an indented list; expandLevels opens two levels', async ({ page }) => {
    await open(page);
    const ada = await page.locator('#narrow .caelrik-orgchart-node', { hasText: 'Ada Lovelace' }).boundingBox();
    const alan = await page.locator('#narrow .caelrik-orgchart-node', { hasText: 'Alan Turing' }).boundingBox();
    expect(alan.x).toBeGreaterThan(ada.x);
    expect(alan.y).toBeGreaterThan(ada.y + ada.height);
    const alanTeam = page.locator('#narrow').getByRole('button', { name: "Alan Turing's team, 1 person" });
    await expect(alanTeam).toHaveAttribute('aria-expanded', 'false');
    await expect(page.locator('#narrow').getByText('Barbara Liskov')).toBeHidden();
  });

  test('on a phone the wide chart becomes a list too', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 800 });
    await open(page);
    const scroll = page.locator('#company .caelrik-orgchart-scroll');
    expect(await scroll.evaluate((element) => element.scrollWidth <= element.clientWidth)).toBe(true);
  });

  for (const mode of MODES) {
    test(`no axe-core violations (${mode}), with a team hidden`, async ({ page }) => {
      await open(page, { mode });
      await page.getByRole('button', { name: "Katherine Johnson's team, 1 person" }).click();
      expect(await axeViolations(page)).toEqual([]);
    });
  }
});
