import { expect, test } from '@playwright/test';
import { axeViolations, MODES, openComponents } from './helpers.mjs';

const open = (page, options = {}) => openComponents(page, { path: '/media', ...options });

test.describe('media player', () => {
  test('named controls, a figure named by its label, under a strict CSP', async ({ page }) => {
    const errors = await open(page);
    expect(errors).toEqual([]);
    const lesson = page.getByRole('figure', { name: 'A lesson in tones' });
    await expect(lesson).toBeVisible();
    await expect(lesson.getByRole('button', { name: 'Play, A lesson in tones' }).first()).toBeVisible();
    await expect(lesson.getByRole('slider', { name: 'Seek' })).toBeVisible();
    await expect(lesson.getByRole('slider', { name: 'Volume' })).toBeVisible();
    await expect(lesson.getByRole('button', { name: /captions/i })).toBeVisible();
    // Plyr's icons come from the bundle, not from its CDN.
    const icon = await lesson.locator('.plyr__controls use').first().getAttribute('href');
    expect(new URL(icon).origin).toBe(new URL(page.url()).origin);
    expect(new URL(icon).pathname).toMatch(/^\/assets\/chunks\/plyr-[\w-]+\.svg$/);
  });

  test('plays and pauses with the keyboard', async ({ page }) => {
    await open(page);
    const lesson = page.locator('#lesson');
    const play = lesson.locator('.plyr__controls [data-plyr="play"]');
    await play.focus();
    await page.keyboard.press('Enter');
    await expect.poll(() => lesson.locator('video').evaluate((media) => media.paused)).toBe(false);
    await expect(play).toHaveAccessibleName('Pause');
    await expect(play).toHaveAttribute('aria-pressed', 'true');
    await page.keyboard.press('Enter');
    await expect.poll(() => lesson.locator('video').evaluate((media) => media.paused)).toBe(true);
  });

  test('the captions track is on, and its cue is shown', async ({ page }) => {
    await open(page);
    const lesson = page.locator('#lesson');
    await expect.poll(() => lesson.locator('video').evaluate((media) => media.textTracks[0]?.mode)).not.toBe('disabled');
    await lesson.locator('.plyr__controls [data-plyr="play"]').click();
    await expect(lesson.locator('.plyr__captions')).toContainText('A soft tone starts.');
  });

  test('the transcript plays from a line, and marks the line being spoken', async ({ page }) => {
    await open(page);
    const podcast = page.locator('#podcast');
    await podcast.getByText('Transcript').click();
    const seek = podcast.getByRole('button', { name: 'Play from 0:04' });
    await expect(seek).toBeVisible();
    await expect(podcast.locator('.celerik-media-cue').first()).toContainText('Narrator A soft tone starts.');
    await seek.click();
    await expect.poll(() => podcast.locator('audio').evaluate((media) => media.currentTime)).toBeGreaterThanOrEqual(4);
    await expect(podcast.locator('.celerik-media-cue[aria-current="true"]')).toContainText('The tone fades out.');
  });

  test('without JavaScript, the browser\'s own player and the transcript', async ({ browser }) => {
    const context = await browser.newContext({ javaScriptEnabled: false });
    const page = await context.newPage();
    await page.goto('/media');
    await expect(page.locator('#lesson video')).toHaveAttribute('controls', '');
    await page.locator('#podcast summary').click();
    await expect(page.locator('#podcast time').last()).toHaveText('0:04');
    await context.close();
  });

  test('on a phone nothing scrolls sideways', async ({ page }) => {
    await page.setViewportSize({ width: 320, height: 800 });
    await open(page);
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(320);
  });

  for (const mode of MODES) {
    test(`no axe-core violations (${mode}), with the transcripts open`, async ({ page }) => {
      await open(page, { mode });
      for (const summary of await page.locator('.celerik-media-transcript summary').all()) {
        await summary.click();
      }
      await page.locator('#podcast').getByRole('button', { name: 'Play from 0:02' }).click();
      expect(await axeViolations(page)).toEqual([]);
    });
  }
});
