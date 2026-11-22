import { expect, test } from '@playwright/test';
import zxing from '@zxing/library';
import { axeViolations, MODES, openComponents } from './helpers.mjs';

const {
  BinaryBitmap, Code128Reader, DecodeHintType, EAN13Reader, HybridBinarizer, QRCodeReader, RGBLuminanceSource,
} = zxing;

const open = (page, options = {}) => openComponents(page, { path: '/codes', ...options });

// Reads what the screen shows: a screenshot of the code, drawn on a canvas for its pixels, then
// decoded by ZXing.
async function scan(locator, reader) {
  const png = (await locator.screenshot()).toString('base64');
  const { width, height, pixels } = await locator.page().evaluate(async (data) => {
    const image = new Image();
    image.src = `data:image/png;base64,${data}`;
    await image.decode();
    const canvas = document.createElement('canvas');
    canvas.width = image.width;
    canvas.height = image.height;
    const context = canvas.getContext('2d');
    context.drawImage(image, 0, 0);
    const rgba = context.getImageData(0, 0, image.width, image.height).data;
    const gray = [];
    for (let i = 0; i < rgba.length; i += 4) gray.push(Math.round((rgba[i] + rgba[i + 1] + rgba[i + 2]) / 3));
    return { width: image.width, height: image.height, pixels: gray };
  }, png);
  const bitmap = new BinaryBitmap(new HybridBinarizer(new RGBLuminanceSource(Uint8ClampedArray.from(pixels), width, height)));
  return reader.decode(bitmap, new Map([[DecodeHintType.CHARACTER_SET, 'UTF-8']])).getText();
}

test.describe('QR codes and barcodes', () => {
  test('images named by their labels, with their values as text', async ({ page }) => {
    const errors = await open(page);
    expect(errors).toEqual([]);
    await expect(page.getByRole('img', { name: 'QR code: Our website' })).toBeVisible();
    await expect(page.getByRole('img', { name: 'QR code: Office Wi-Fi' })).toBeVisible();
    await expect(page.getByRole('img', { name: 'QR code: tel:+243999999999' })).toBeVisible();
    await expect(page.getByRole('img', { name: 'Barcode: ISBN' })).toBeVisible();
    await expect(page.getByRole('figure', { name: 'ISBN 9780201379624' })).toBeVisible();

    const link = page.getByRole('link', { name: 'https://example.com/a-rather-long-address/that-wraps?under=the-code' });
    await expect(link).toHaveAttribute('href', 'https://example.com/a-rather-long-address/that-wraps?under=the-code');
    await expect(page.locator('#wifi')).not.toContainText('battery');
  });

  for (const mode of MODES) {
    test(`the codes on screen scan (${mode})`, async ({ page }) => {
      await open(page, { mode });
      expect(await scan(page.locator('#site svg'), new QRCodeReader()))
        .toBe('https://example.com/a-rather-long-address/that-wraps?under=the-code');
      expect(await scan(page.locator('#wifi svg'), new QRCodeReader()))
        .toBe('WIFI:T:WPA;S:Office;P:correct horse battery staple;;');
      expect(await scan(page.locator('#isbn svg'), new EAN13Reader())).toBe('9780201379624');
      expect(await scan(page.locator('#order svg'), new Code128Reader())).toBe('ORDER-2026-000042');
    });
  }

  test('in forced colors the codes stay dark on light, and scan', async ({ page }) => {
    await page.emulateMedia({ forcedColors: 'active', colorScheme: 'dark' });
    await open(page, { mode: 'dark' });
    expect(await scan(page.locator('#site svg'), new QRCodeReader()))
      .toBe('https://example.com/a-rather-long-address/that-wraps?under=the-code');
    expect(await scan(page.locator('#order svg'), new Code128Reader())).toBe('ORDER-2026-000042');
  });

  test('a long value wraps under the code instead of widening it', async ({ page }) => {
    await open(page);
    const [code, caption] = await Promise.all([
      page.locator('#site svg').boundingBox(),
      page.locator('#site figcaption').boundingBox(),
    ]);
    expect(caption.width).toBeLessThanOrEqual(code.width + 0.5);
    expect(caption.height).toBeGreaterThan(30);
  });

  test('on a phone nothing scrolls sideways', async ({ page }) => {
    await page.setViewportSize({ width: 320, height: 800 });
    await open(page);
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(320);
  });

  for (const mode of MODES) {
    test(`no axe-core violations (${mode})`, async ({ page }) => {
      await open(page, { mode });
      expect(await axeViolations(page)).toEqual([]);
    });
  }
});
