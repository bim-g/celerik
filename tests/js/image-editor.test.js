import axe from 'axe-core';
import { readOptions } from '../../resources/js/core.js';
import {
  clampCrop, fitCrop, initImageEditor, outputSize, parseRatio,
} from '../../resources/js/form-image.js';
import { initForm } from '../../resources/js/form.js';
import { loadFixture } from './helpers.js';

// The pictures themselves (decoding, drawing, the file sent) are checked in the browser
// (tests/browser/image.spec.mjs); here, the crop's arithmetic and the editor's markup.
describe('crop arithmetic', () => {
  test('parseRatio', () => {
    expect(parseRatio('16:9')).toBeCloseTo(16 / 9);
    expect(parseRatio('free')).toBeNull();
  });

  test('fitCrop: the largest centered crop of a shape', () => {
    expect(fitCrop(400, 200, null)).toEqual({ x: 0, y: 0, width: 400, height: 200 });
    expect(fitCrop(400, 200, 1)).toEqual({ x: 100, y: 0, width: 200, height: 200 });
    expect(fitCrop(400, 300, 16 / 9)).toEqual({ x: 0, y: 38, width: 400, height: 225 });
    expect(fitCrop(200, 400, 4 / 3)).toEqual({ x: 0, y: 125, width: 200, height: 150 });
  });

  test('clampCrop: inside the picture, at least 8 pixels, of its shape', () => {
    expect(clampCrop({ x: -5, y: 190, width: 50, height: 50 }, 400, 200, null))
      .toEqual({ x: 0, y: 150, width: 50, height: 50 });
    expect(clampCrop({ x: 0, y: 0, width: 2, height: 1 }, 400, 200, null)).toEqual({ x: 0, y: 0, width: 8, height: 8 });
    expect(clampCrop({ x: 300, y: 0, width: 300, height: 10 }, 400, 200, 1))
      .toEqual({ x: 200, y: 0, width: 200, height: 200 });
    // The height was set: the width follows.
    expect(clampCrop({ x: 0, y: 0, width: 10, height: 90 }, 400, 200, 2, 'height'))
      .toEqual({ x: 0, y: 0, width: 180, height: 90 });
  });

  test('outputSize: at most maxSize on the longest side', () => {
    expect(outputSize({ width: 400, height: 200 }, 200)).toEqual({ width: 200, height: 100 });
    expect(outputSize({ width: 100, height: 50 }, 200)).toEqual({ width: 100, height: 50 });
    expect(outputSize({ width: 400, height: 200 }, null)).toEqual({ width: 400, height: 200 });
  });
});

describe('the editor', () => {
  let form;
  let input;
  let parsley;

  beforeEach(async () => {
    form = loadFixture('form-image');
    parsley = await initForm(form, readOptions(form));
    input = form.querySelector('#profile-photo');
  });

  afterEach(() => parsley.destroy());

  test('is hidden until a picture is chosen, with named tools and numbers', () => {
    const editor = form.querySelector('.caelrik-image-editor');
    expect(input.caelrikImageEditor).toBeDefined();
    expect(editor.hidden).toBe(true);
    expect(editor.getAttribute('role')).toBe('group');
    expect(editor.getAttribute('aria-label')).toBe('Edit the picture');
    expect([...editor.querySelectorAll('.caelrik-image-tools button')].map((button) => button.getAttribute('aria-label')))
      .toEqual(['Rotate left', 'Rotate right', 'Flip horizontally', 'Flip vertically', 'Undo the changes']);
    expect([...editor.querySelectorAll('.caelrik-image-ratio option')].map((option) => option.textContent)).toEqual(['Free', '1:1']);
    expect(editor.querySelector('label[for="profile-photo-ratio"]').textContent).toBe('Shape');
    const crop = editor.querySelector('.caelrik-image-crop');
    expect(crop.tabIndex).toBe(0);
    expect(crop.getAttribute('aria-label')).toBe('Crop area');
    expect(document.getElementById(crop.getAttribute('aria-describedby')).textContent).toMatch(/arrow keys move it/);
    expect([...editor.querySelectorAll('.caelrik-image-numbers label')].map((label) => label.textContent))
      .toEqual(['Left', 'Top', 'Width', 'Height']);
    expect(editor.querySelector('[role="status"]')).not.toBeNull();
    // Set up once.
    expect(initImageEditor(input)).toBe(input.caelrikImageEditor);
  });

  test('a file that is not a picture keeps the editor hidden', () => {
    Object.defineProperty(input, 'files', { value: [new File(['%PDF'], 'a.pdf', { type: 'application/pdf' })], configurable: true });
    input.dispatchEvent(new Event('change'));
    expect(form.querySelector('.caelrik-image-editor').hidden).toBe(true);
  });

  test('no axe-core violations, the editor shown', async () => {
    form.querySelector('.caelrik-image-editor').hidden = false;
    const results = await axe.run(form, {
      runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'] },
      rules: { 'color-contrast': { enabled: false } },
    });
    expect(results.violations.map((violation) => violation.id)).toEqual([]);
  });
});
