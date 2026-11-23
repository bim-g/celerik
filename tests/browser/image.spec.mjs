import { deflateSync } from 'node:zlib';
import { expect, test } from '@playwright/test';
import { axeViolations, MODES, openComponents } from './helpers.mjs';

// A 400 × 200 PNG in four colored quarters: red, green / blue, yellow.
const QUARTERS = [[220, 0, 0], [0, 160, 0], [0, 0, 220], [230, 200, 0]];
function png(width = 400, height = 200) {
  const crcTable = Array.from({ length: 256 }, (_, n) => {
    let c = n;
    for (let k = 0; k < 8; k += 1) c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1;
    return c >>> 0;
  });
  const crc = (buffer) => {
    let c = 0xffffffff;
    for (const byte of buffer) c = crcTable[(c ^ byte) & 0xff] ^ (c >>> 8);
    return (c ^ 0xffffffff) >>> 0;
  };
  const chunk = (type, data) => {
    const length = Buffer.alloc(4);
    length.writeUInt32BE(data.length);
    const body = Buffer.concat([Buffer.from(type), data]);
    const sum = Buffer.alloc(4);
    sum.writeUInt32BE(crc(body));
    return Buffer.concat([length, body, sum]);
  };
  const header = Buffer.alloc(13);
  header.writeUInt32BE(width, 0);
  header.writeUInt32BE(height, 4);
  header.set([8, 2, 0, 0, 0], 8);
  const rows = [];
  for (let y = 0; y < height; y += 1) {
    const row = [0];
    for (let x = 0; x < width; x += 1) row.push(...QUARTERS[(y < height / 2 ? 0 : 2) + (x < width / 2 ? 0 : 1)]);
    rows.push(Buffer.from(row));
  }
  return Buffer.concat([
    Buffer.from([137, 80, 78, 71, 13, 10, 26, 10]),
    chunk('IHDR', header), chunk('IDAT', deflateSync(Buffer.concat(rows))), chunk('IEND', Buffer.alloc(0)),
  ]);
}

const number = (page, name) => page.getByRole('spinbutton', { name, exact: true });
const open = (page, options = {}) => openComponents(page, { path: '/image', ...options });

async function choose(page) {
  const edited = page.locator('#profile-photo').evaluate((input) => new Promise((resolve) => {
    input.addEventListener('caelrik:image-edited', (event) => resolve(event.detail), { once: true });
  }));
  await page.locator('#profile-photo').setInputFiles({ name: 'quarters.png', mimeType: 'image/png', buffer: png() });
  return edited;
}

// The file in the input: its size and the colors at the middle of its four quarters.
const result = (page) => page.locator('#profile-photo').evaluate(async (input) => {
  const file = input.files[0];
  const bitmap = await createImageBitmap(file);
  const canvas = document.createElement('canvas');
  canvas.width = bitmap.width;
  canvas.height = bitmap.height;
  const context = canvas.getContext('2d');
  context.drawImage(bitmap, 0, 0);
  const at = (x, y) => [...context.getImageData(Math.floor(x * bitmap.width), Math.floor(y * bitmap.height), 1, 1).data.slice(0, 3)];
  return {
    name: file.name,
    type: file.type,
    width: bitmap.width,
    height: bitmap.height,
    colors: [at(0.25, 0.25), at(0.75, 0.25), at(0.25, 0.75), at(0.75, 0.75)],
  };
});
const near = (actual, expected) => actual.every((value, index) => Math.abs(value - expected[index]) < 30);

test.describe('image editor', () => {
  test('a chosen picture opens the editor; the file sent is edited, at most 200 pixels', async ({ page }) => {
    const errors = await open(page);
    expect(errors).toEqual([]);
    await expect(page.getByRole('group', { name: 'Edit the picture' })).toBeHidden();
    await choose(page);
    const editor = page.getByRole('group', { name: 'Edit the picture' });
    await expect(editor).toBeVisible();
    await expect(editor.getByRole('status')).toHaveText('The picture sent: 200 × 100 pixels.');

    const sent = await result(page);
    expect(sent).toMatchObject({ name: 'quarters.png', type: 'image/png', width: 200, height: 100 });
    expect(near(sent.colors[0], QUARTERS[0]) && near(sent.colors[3], QUARTERS[3])).toBe(true);

    // The server receives the edited picture.
    const [response] = await Promise.all([
      page.waitForResponse((each) => each.url().endsWith('/image') && each.request().method() === 'POST'),
      page.getByRole('button', { name: 'Save' }).click(),
    ]);
    expect(await response.json()).toEqual({ name: 'quarters.png', type: 'image/png', width: 200, height: 100 });
  });

  test('a square crop by its shape, then moved by its numbers', async ({ page }) => {
    await open(page);
    await choose(page);
    await page.getByLabel('Shape').selectOption('1:1');
    await expect(number(page, 'Width')).toHaveValue('200');
    await expect(number(page, 'Left')).toHaveValue('100');
    await number(page, 'Left').fill('200');
    // Enter applies the number; it does not send the form.
    await number(page, 'Left').press('Enter');
    await expect(page).toHaveURL(/\/image/);
    await expect(page.getByRole('status')).toHaveText('The picture sent: 200 × 200 pixels.');
    // The right half: green above, yellow below (once the moved crop is sent).
    await expect.poll(async () => {
      const sent = await result(page);
      return near(sent.colors[0], QUARTERS[1]) && near(sent.colors[2], QUARTERS[3]);
    }).toBe(true);
  });

  test('the keyboard moves and resizes the crop area', async ({ page }) => {
    await open(page);
    await choose(page);
    const area = page.getByRole('group', { name: 'Crop area' }).first();
    await area.focus();
    await page.keyboard.press('Shift+ArrowLeft');
    await expect(number(page, 'Width')).toHaveValue('396');
    await page.keyboard.press('ArrowRight');
    await expect(number(page, 'Left')).toHaveValue('4');
    await expect(area).toHaveAccessibleDescription(/the arrow keys move it/);
  });

  test('the pointer drags a corner', async ({ page }) => {
    await open(page);
    await choose(page);
    const handle = page.locator('.caelrik-image-handle-se');
    const box = await handle.boundingBox();
    await page.mouse.move(box.x + 12, box.y + 12);
    await page.mouse.down();
    await page.mouse.move(box.x - 100, box.y - 40, { steps: 5 });
    await page.mouse.up();
    expect(Number(await number(page, 'Width').inputValue())).toBeLessThan(400);
    expect(Number(await number(page, 'Height').inputValue())).toBeLessThan(200);
    await expect(number(page, 'Left')).toHaveValue('0');
  });

  test('rotate and flip', async ({ page }) => {
    await open(page);
    await choose(page);
    await page.getByRole('button', { name: 'Rotate right' }).click();
    await expect(page.getByRole('status')).toHaveText('The picture sent: 100 × 200 pixels.');
    let sent = await result(page);
    // Turned clockwise: blue top-left, red top-right.
    expect(near(sent.colors[0], QUARTERS[2]) && near(sent.colors[1], QUARTERS[0])).toBe(true);

    await page.getByRole('button', { name: 'Undo the changes' }).click();
    await page.getByRole('button', { name: 'Flip horizontally' }).click();
    await expect(page.getByRole('status')).toHaveText('The picture sent: 200 × 100 pixels.');
    await expect.poll(async () => near((await result(page)).colors[0], QUARTERS[1])).toBe(true);
    sent = await result(page);
    expect(near(sent.colors[1], QUARTERS[0])).toBe(true);
  });

  test('without JavaScript, the file is sent as chosen', async ({ browser }) => {
    const context = await browser.newContext({ javaScriptEnabled: false });
    const page = await context.newPage();
    await page.goto('/image');
    await page.locator('#profile-photo').setInputFiles({ name: 'quarters.png', mimeType: 'image/png', buffer: png() });
    const [response] = await Promise.all([
      page.waitForResponse((each) => each.url().endsWith('/image') && each.request().method() === 'POST'),
      page.getByRole('button', { name: 'Save' }).click(),
    ]);
    expect(await response.json()).toMatchObject({ width: 400, height: 200 });
    await context.close();
  });

  test('on a phone the editor fits', async ({ page }) => {
    await page.setViewportSize({ width: 320, height: 800 });
    await open(page);
    await choose(page);
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(320);
  });

  for (const mode of MODES) {
    test(`no axe-core violations (${mode}), editing`, async ({ page }) => {
      await open(page, { mode });
      await choose(page);
      expect(await axeViolations(page)).toEqual([]);
    });
  }
});
