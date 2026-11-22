import axe from 'axe-core';
import { initForm } from '../../resources/js/form.js';
import { readOptions } from '../../resources/js/core.js';
import { drawStrokes } from '../../resources/js/form-signature.js';
import { loadFixture } from './helpers.js';

// jsdom has no canvas: a context that records what is drawn, and a PNG that says it.
function recordingContext(calls) {
  return new Proxy({}, {
    get: (target, key) => (key in target ? target[key] : (...args) => calls.push([key, ...args])),
    set: (target, key, value) => {
      target[key] = value;
      return true;
    },
  });
}

describe('signature fields (Field::signature())', () => {
  const { getContext, toDataURL } = HTMLCanvasElement.prototype;
  let calls;
  let form;
  let parsley;

  beforeEach(async () => {
    calls = [];
    HTMLCanvasElement.prototype.getContext = () => recordingContext(calls);
    HTMLCanvasElement.prototype.toDataURL = () => 'data:image/png;base64,c2lnbmVk';
    HTMLCanvasElement.prototype.getBoundingClientRect = () => ({
      left: 0, top: 0, width: 300, height: 100, right: 300, bottom: 100,
    });
    form = loadFixture('form-signature');
    parsley = await initForm(form, readOptions(form));
  });

  afterEach(() => {
    parsley.destroy();
    HTMLCanvasElement.prototype.getContext = getContext;
    HTMLCanvasElement.prototype.toDataURL = toDataURL;
    delete HTMLCanvasElement.prototype.getBoundingClientRect;
  });

  const parts = (name) => {
    const fieldset = form.querySelector(`#contract-${name}-group`);
    return {
      fieldset,
      pad: fieldset.querySelector('.celerik-signature-pad'),
      canvas: fieldset.querySelector('canvas'),
      typed: fieldset.querySelector('.celerik-signature-typed'),
      value: fieldset.querySelector('.celerik-signature-value'),
      clear: fieldset.querySelector('.celerik-signature-clear'),
    };
  };

  const pointer = (canvas, type, x, y) => {
    const event = new MouseEvent(type, { bubbles: true, cancelable: true, clientX: x, clientY: y, button: 0 });
    canvas.dispatchEvent(event);
  };

  test('the pad shows, and the image is submitted under the field name', () => {
    const { pad, typed, value } = parts('signature');
    expect(pad.hidden).toBe(false);
    expect(value.name).toBe('signature');
    expect(typed.hasAttribute('name')).toBe(false);
    expect(new FormData(form).get('signature')).toBe('');
  });

  test('a required signature: a drawing or a name, with its message', () => {
    const { canvas, typed, value } = parts('signature');
    expect(parsley.validate()).toBe(false);
    expect(typed.classList.contains('is-invalid')).toBe(true);
    expect(typed.closest('.mb-3').querySelector('.invalid-feedback').textContent).toBe('Draw or type your signature.');

    pointer(canvas, 'pointerdown', 30, 50);
    pointer(canvas, 'pointermove', 60, 40);
    pointer(canvas, 'pointermove', 90, 60);
    pointer(canvas, 'pointerup', 90, 60);

    expect(value.value).toBe('data:image/png;base64,c2lnbmVk');
    expect(canvas.getAttribute('aria-label')).toBe('Signature pad, signed');
    // Shown messages follow the pad.
    expect(typed.classList.contains('is-valid')).toBe(true);
    expect(calls.some(([name]) => name === 'quadraticCurveTo')).toBe(true);
  });

  test('typing a name draws it, and replaces a drawing', () => {
    const { canvas, typed, value } = parts('signature');
    pointer(canvas, 'pointerdown', 30, 50);
    pointer(canvas, 'pointerup', 30, 50);
    calls.length = 0;

    typed.value = 'Ada Lovelace';
    typed.dispatchEvent(new Event('input', { bubbles: true }));

    expect(calls.find(([name]) => name === 'fillText')).toEqual(['fillText', 'Ada Lovelace', expect.any(Number), expect.any(Number)]);
    expect(calls.some(([name]) => name === 'arc')).toBe(false);
    expect(value.value).toBe('data:image/png;base64,c2lnbmVk');
    expect(parsley.validate()).toBe(true);

    // Drawing replaces the typed name.
    pointer(canvas, 'pointerdown', 30, 50);
    expect(typed.value).toBe('');
  });

  test('Clear empties the pad and the name', () => {
    const { canvas, clear, typed, value } = parts('signature');
    typed.value = 'Ada';
    typed.dispatchEvent(new Event('input', { bubbles: true }));
    clear.click();
    expect(typed.value).toBe('');
    expect(value.value).toBe('');
    expect(canvas.getAttribute('aria-label')).toBe('Signature pad, empty');
  });

  test('a name typed before (old input) is submitted as an image', () => {
    const { canvas, typed, value } = parts('witness');
    expect(typed.value).toBe('Grace Hopper');
    expect(value.value).toBe('data:image/png;base64,c2lnbmVk');
    expect(canvas.getAttribute('aria-label')).toBe('Signature pad, signed');
    expect(new FormData(form).get('witness')).toBe('data:image/png;base64,c2lnbmVk');
  });

  test('no axe-core violations', async () => {
    const results = await axe.run(form, {
      runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'] },
      rules: { 'color-contrast': { enabled: false } },
    });
    expect(results.violations.map((violation) => violation.id)).toEqual([]);
  });
});

describe('without a canvas', () => {
  test('the name input stays the field, required', async () => {
    const form = loadFixture('form-signature');
    const parsley = await initForm(form, readOptions(form));
    const typed = form.querySelector('#contract-signature');
    expect(typed.name).toBe('signature');
    expect(typed.required).toBe(true);
    expect(form.querySelector('#contract-signature-group .celerik-signature-pad').hidden).toBe(true);
    parsley.destroy();
  });
});

test('drawStrokes draws a dot for a single point and a smooth line through several', () => {
  const calls = [];
  const context = recordingContext(calls);
  drawStrokes(context, [[[0.1, 0.1]], [[0.1, 0.2], [0.2, 0.25], [0.3, 0.2]]], 600);
  expect(calls.filter(([name]) => name === 'arc')).toHaveLength(1);
  expect(calls.find(([name]) => name === 'quadraticCurveTo')).toEqual(['quadraticCurveTo', 120, 150, 150, 135]);
  expect(context.lineWidth).toBeCloseTo(3.6);
});
