/**
 * @jest-environment node
 */
import Ajv2020 from 'ajv/dist/2020';
import { readdirSync, readFileSync } from 'node:fs';
import { join } from 'node:path';
import { contrast, normalizeHex, properties } from './css-helpers.js';

// The bootstrap preset (theme/presets/bootstrap.json) describes Bootstrap 5.3 as shipped.
// These tests fail when the preset and Bootstrap drift apart.
const root = join(__dirname, '../..');
const read = (path) => JSON.parse(readFileSync(join(root, path), 'utf8'));
const schema = read('theme/theme.schema.json');
const preset = (name) => read(`theme/presets/${name}.json`);

describe('theme presets', () => {
  const validate = new Ajv2020({ allErrors: true }).compile(schema);

  test('only the bootstrap preset is shipped', () => {
    expect(readdirSync(join(root, 'theme/presets'))).toEqual(['bootstrap.json']);
  });

  test.each(['bootstrap'])('%s matches the schema', (name) => {
    expect(validate(preset(name)) ? [] : validate.errors).toEqual([]);
  });

  test.each(['bootstrap'])('%s meets every pair of theme/contrast.json', (name) => {
    const { pairs } = read('theme/contrast.json');
    const failures = ['light', 'dark'].flatMap((mode) => pairs
      .map((pair) => ({ ...pair, ratio: contrast(preset(name)[mode][pair.foreground], preset(name)[mode][pair.background]) }))
      .filter((pair) => pair.ratio < pair.minimum)
      .map((pair) => `${mode} ${pair.label}: ${pair.ratio.toFixed(2)}`));
    expect(failures).toEqual([]);
  });

  test('the schema rejects unsafe or malformed values', () => {
    const invalid = (change) => !validate({ ...preset('bootstrap'), ...change(preset('bootstrap')) });
    expect(invalid((theme) => ({ light: { ...theme.light, primary: 'red' } }))).toBe(true);
    expect(invalid((theme) => ({ dark: { ...theme.dark, primary: '#fff' } }))).toBe(true);
    expect(invalid(() => ({ fontFamily: 'Inter; } body { display: none' }))).toBe(true);
    expect(invalid(() => ({ fontFamily: 'url(https://evil.example/font)' }))).toBe(true);
    expect(invalid(() => ({ radius: 5 }))).toBe(true);
    expect(invalid(() => ({ base: 'bulma' }))).toBe(true);
    // Themes made before 0.2.0: "bootstrap" is the only base left.
    expect(invalid(() => ({ base: 'material' }))).toBe(true);
    expect(invalid(() => ({ base: 'bootstrap' }))).toBe(false);
    expect(invalid(() => ({ extra: true }))).toBe(true);
    expect(invalid(() => ({ name: '<script>' }))).toBe(true);
    expect(invalid(() => ({ name: 'Acme Café 2' }))).toBe(false);
  });

  test('the stylesheet generated from the bootstrap preset keeps Bootstrap 5.3 light colors', () => {
    const bootstrap = properties(readFileSync(join(root, 'node_modules/bootstrap/dist/css/bootstrap.css'), 'utf8'), ':root');
    const generated = properties(readFileSync(join(__dirname, 'fixtures', 'theme-bootstrap.css'), 'utf8'), '[data-bs-theme=light]');
    const same = [
      '--bs-primary', '--bs-primary-rgb', '--bs-link-color', '--bs-link-color-rgb', '--bs-link-hover-color',
      '--bs-link-hover-color-rgb', '--bs-body-bg', '--bs-body-bg-rgb', '--bs-body-color', '--bs-body-color-rgb',
      '--bs-tertiary-bg', '--bs-tertiary-bg-rgb', '--bs-border-color', '--bs-focus-ring-color',
      '--bs-form-invalid-color', '--bs-form-invalid-border-color',
    ];
    for (const property of same) {
      expect([property, generated[property]]).toEqual([property, normalizeHex(bootstrap[property])]);
    }
  });

  test('the bootstrap preset is Bootstrap 5.3, with field borders that meet 3:1', () => {
    const css = readFileSync(join(root, 'node_modules/bootstrap/dist/css/bootstrap.css'), 'utf8');
    const light = Object.fromEntries(Object.entries(properties(css, ':root'))
      .map(([name, value]) => [name, normalizeHex(value)]));
    const theme = preset('bootstrap');

    expect(theme.light.primary).toBe(light['--bs-primary']);
    expect(theme.light.primaryHover).toBe(light['--bs-link-hover-color']);
    expect(theme.light.bodyBg).toBe(light['--bs-body-bg']);
    expect(theme.light.bodyColor).toBe(light['--bs-body-color']);
    expect(theme.light.surface).toBe(light['--bs-tertiary-bg']);
    expect(theme.light.border).toBe(light['--bs-border-color']);
    expect(theme.light.danger).toBe(light['--bs-form-invalid-color']);
    // Bootstrap's field border is its decorative border (#dee2e6, 1.3:1): the preset uses gray-600.
    expect(theme.light.inputBorder).toBe(light['--bs-gray-600']);
    expect(`${theme.radius}rem`).toBe(light['--bs-border-radius']);
  });
});
