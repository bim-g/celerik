import { expect, test } from '@playwright/test';
import { axeViolations, MODES, openComponents } from './helpers.mjs';

const open = (page, options = {}) => openComponents(page, { path: '/timeline-carousel', ...options });

test.describe('timeline and carousel', () => {
  test('a timeline is a named list; its current step is marked', async ({ page }) => {
    const errors = await open(page);
    expect(errors).toEqual([]);
    const timeline = page.getByRole('list', { name: 'Order history' });
    await expect(timeline.getByRole('listitem')).toHaveCount(4);
    await expect(timeline.locator('[aria-current="step"]')).toContainText('Shipped');
    await expect(timeline.locator('time').first()).toHaveAttribute('datetime', /^2026-11-01T09:30:00/);
  });

  test('an alternate timeline uses both sides on wide screens and one on phones', async ({ page }) => {
    await open(page);
    const bodies = page.locator('#history .caelrik-timeline-body');
    const first = await bodies.nth(0).boundingBox();
    const second = await bodies.nth(1).boundingBox();
    expect(second.x + second.width).toBeLessThan(first.x);

    await page.setViewportSize({ width: 390, height: 800 });
    const narrowFirst = await bodies.nth(0).boundingBox();
    const narrowSecond = await bodies.nth(1).boundingBox();
    expect(Math.round(narrowSecond.x)).toBe(Math.round(narrowFirst.x));
  });

  test('the carousel moves from the keyboard and names its slides', async ({ page }) => {
    await open(page);
    const carousel = page.getByRole('region', { name: 'News' });
    await expect(carousel).toHaveAttribute('aria-roledescription', 'carousel');
    const first = carousel.getByRole('group', { name: '1 of 3' });
    await expect(first).toBeVisible();
    await expect(first.getByRole('img', { name: 'Lake Kivu at dawn, hills behind the water' })).toBeVisible();

    const next = carousel.getByRole('button', { name: 'Next slide' });
    await next.focus();
    await page.keyboard.press('Enter');
    await expect(carousel.getByRole('group', { name: 'Retreat' })).toBeVisible();
    await expect(first).toBeHidden();
    await expect(carousel.getByRole('button', { name: 'Retreat' })).toHaveAttribute('aria-current', 'true');
    await expect(next).toBeFocused();

    await carousel.getByRole('button', { name: 'Slide 3' }).click();
    await expect(carousel.getByRole('group', { name: '3 of 3' })).toBeVisible();
    // The carousel keeps its height from slide to slide.
    const height = (await carousel.boundingBox()).height;
    await carousel.getByRole('button', { name: 'Previous slide' }).click();
    expect((await carousel.boundingBox()).height).toBe(height);
  });

  test('automatic rotation pauses for the pointer and focus, and Stop stops it', async ({ page }) => {
    await open(page);
    const carousel = page.locator('#tips');
    const active = () => carousel.locator('.caelrik-carousel-slide.is-active');
    await expect(active()).toHaveAttribute('aria-label', '1 of 3');
    await expect(active()).toHaveAttribute('aria-label', '2 of 3', { timeout: 5000 });

    // Focus inside: it pauses.
    const stop = carousel.getByRole('button', { name: 'Stop automatic slide show' });
    await stop.focus();
    await page.waitForTimeout(3500);
    await expect(active()).toHaveAttribute('aria-label', '2 of 3');

    await page.keyboard.press('Enter');
    const start = carousel.getByRole('button', { name: 'Start automatic slide show' });
    await expect(start).toBeFocused();
    await expect(carousel.locator('.caelrik-carousel-slides')).toHaveAttribute('aria-live', 'polite');
  });

  test('carousel buttons are at least 24 x 24 px', async ({ page }) => {
    await open(page);
    for (const button of await page.locator('.caelrik-carousel button').all()) {
      const box = await button.boundingBox();
      expect(box.width).toBeGreaterThanOrEqual(24);
      expect(box.height).toBeGreaterThanOrEqual(24);
    }
  });

  test('reduced motion: the carousel does not rotate', async ({ page }) => {
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await open(page);
    await expect(page.getByRole('button', { name: 'Start automatic slide show' })).toBeVisible();
    await page.waitForTimeout(3500);
    await expect(page.locator('#tips .caelrik-carousel-slide.is-active')).toHaveAttribute('aria-label', '1 of 3');
  });

  for (const mode of MODES) {
    test(`no axe-core violations (${mode})`, async ({ page }) => {
      await open(page, { mode });
      await page.locator('#tips').getByRole('button', { name: 'Stop automatic slide show' }).click();
      expect(await axeViolations(page)).toEqual([]);
    });
  }
});
