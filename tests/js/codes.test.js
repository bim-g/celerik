/**
 * @jest-environment node
 */
import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import {
  BinaryBitmap,
  Code128Reader,
  DecodeHintType,
  EAN13Reader,
  HybridBinarizer,
  QRCodeReader,
  RGBLuminanceSource,
} from '@zxing/library';

// The codes drawn by PHP (QrCode, Barcode), read by ZXing: an independent reader, so a mistake
// in the encoders (a table, the error correction, a mask) cannot hide behind tests written from
// the same understanding of the standards.
const fixtures = join(__dirname, 'fixtures');
const codes = JSON.parse(readFileSync(join(fixtures, 'codes.json'), 'utf8'));

// Rows of "1" (dark) and "0" (light), drawn black on white with a quiet zone, `scale` pixels a module.
function bitmap(rows, { scale = 3, quiet = 4 } = {}) {
  const width = (rows[0].length + quiet * 2) * scale;
  const height = (rows.length + quiet * 2) * scale;
  const pixels = new Uint8ClampedArray(width * height).fill(255);
  rows.forEach((row, y) => {
    [...row].forEach((module, x) => {
      if (module !== '1') return;
      for (let dy = 0; dy < scale; dy += 1) {
        const start = ((y + quiet) * scale + dy) * width + (x + quiet) * scale;
        pixels.fill(0, start, start + scale);
      }
    });
  });
  return new BinaryBitmap(new HybridBinarizer(new RGBLuminanceSource(pixels, width, height)));
}

const hints = new Map([[DecodeHintType.PURE_BARCODE, true], [DecodeHintType.CHARACTER_SET, 'UTF-8']]);
const readQr = (rows) => new QRCodeReader().decode(bitmap(rows), hints).getText();

// The rows of a QR code drawn by QrCode: its path is a run of dark modules per subpath.
function qrRows(html) {
  const side = Number(/viewBox="0 0 (\d+) \1"/.exec(html)[1]);
  const rows = Array.from({ length: side }, () => Array(side).fill('0'));
  for (const [, x, y, run] of html.matchAll(/M(\d+) (\d+)h(\d+)v1h-\3z/g)) {
    rows[Number(y)].fill('1', Number(x), Number(x) + Number(run));
  }
  return rows.map((row) => row.join(''));
}

// The modules of a barcode drawn by Barcode: one subpath per bar.
function barModules(html) {
  const width = Number(/viewBox="0 0 (\d+) /.exec(html)[1]);
  const modules = Array(width).fill('0');
  for (const [, x, bar] of html.matchAll(/M(\d+) 0h(\d+)v/g)) {
    modules.fill('1', Number(x), Number(x) + Number(bar));
  }
  return modules.join('');
}

describe('QR codes and barcodes, read by ZXing', () => {
  test.each(codes.qr.map((code) => [code.version, code.level, code]))(
    'QR code version %i, level %s',
    (version, level, code) => {
      expect(code.rows).toHaveLength(version * 4 + 17);
      expect(readQr(code.rows)).toBe(code.text);
    },
  );

  test.each(codes.barcodes.map((code) => [code.type, code.text, code]))('%s %j', (type, text, code) => {
    const reader = type === 'ean13' ? new EAN13Reader() : new Code128Reader();
    const rows = Array(20).fill(code.modules);
    expect(reader.decode(bitmap(rows, { scale: 2, quiet: 12 })).getText()).toBe(text);
  });

  test('the SVG of a QrCode reads as its value', () => {
    const link = readFileSync(join(fixtures, 'qrcode.html'), 'utf8');
    expect(readQr(qrRows(link))).toBe('https://example.com/?a=1&b=2');
    const hidden = readFileSync(join(fixtures, 'qrcode-hidden.html'), 'utf8');
    expect(readQr(qrRows(hidden))).toBe('WIFI:T:WPA;S:Office;P:Grüße;;');
  });

  test('the SVG of a Barcode reads as its value', () => {
    const read = (name, reader) => {
      const modules = barModules(readFileSync(join(fixtures, `${name}.html`), 'utf8'));
      return reader.decode(bitmap(Array(20).fill(modules), { scale: 2, quiet: 0 })).getText();
    };
    expect(read('barcode', new EAN13Reader())).toBe('9780201379624');
    expect(read('barcode-code128', new Code128Reader())).toBe('Order ABC-123456789xyz');
  });
});
