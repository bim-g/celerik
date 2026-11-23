import axe from 'axe-core';
import { initForm } from '../../resources/js/form.js';
import { readOptions } from '../../resources/js/core.js';
import { initCodes, LANGUAGES } from '../../resources/js/form-code.js';
import { loadFixture } from './helpers.js';

// CodeMirror runs in jsdom for its DOM and state; typing, Tab and colors are checked in the
// browser (tests/browser/code.spec.mjs).
beforeAll(() => {
  document.createRange = () => {
    const range = new Range();
    range.getClientRects = () => [];
    range.getBoundingClientRect = () => ({ left: 0, top: 0, right: 0, bottom: 0, width: 0, height: 0 });
    return range;
  };
});

describe('code fields (Field::code())', () => {
  let form;
  let parsley;

  beforeEach(async () => {
    document.head.innerHTML = '<meta name="csp-nonce" content="abc">';
    form = loadFixture('form-code');
    parsley = await initForm(form, readOptions(form));
    await initCodes(form);
  });

  afterEach(() => {
    parsley.destroy();
    document.head.innerHTML = '';
  });

  const parts = (name) => {
    const textarea = form.querySelector(`#snippet-${name}`);
    return { textarea, editor: textarea.caelrikEditor, content: textarea.caelrikEditor.root };
  };

  test('the editor takes the name, the description and the text; the textarea steps aside', () => {
    const { textarea, editor, content } = parts('source');
    expect(content.getAttribute('aria-labelledby')).toBe('snippet-source-label');
    expect(content.getAttribute('aria-multiline')).toBe('true');
    expect(content.getAttribute('aria-describedby')).toBe('snippet-source-help snippet-source-keys');
    expect(form.querySelector('#snippet-source-keys').textContent)
      .toBe('Tab moves to the next field; Ctrl+] indents and Ctrl+[ outdents.');
    expect(editor.value).toBe('// Hi\nconst a = 1;');
    expect(textarea.classList.contains('visually-hidden')).toBe(true);
    expect(textarea.getAttribute('tabindex')).toBe('-1');
    expect(textarea.getAttribute('aria-hidden')).toBe('true');
    // The injected stylesheet carries the page's nonce.
    expect(document.querySelector('style[nonce="abc"]')).not.toBeNull();
  });

  test('changes reach the textarea, and a reset comes back', async () => {
    const { textarea, editor } = parts('source');
    const inputs = [];
    textarea.addEventListener('input', () => inputs.push(textarea.value));
    editor.value = 'let b = 2;';
    expect(textarea.value).toBe('let b = 2;');
    expect(inputs).toEqual(['let b = 2;']);
    expect(new FormData(form).get('source')).toBe('let b = 2;');

    form.reset();
    await new Promise((resolve) => setTimeout(resolve, 0));
    expect(editor.value).toBe('// Hi\nconst a = 1;');
  });

  test('a required empty editor: invalid, required, and focused through the textarea', () => {
    const { textarea, content } = parts('config');
    expect(content.getAttribute('aria-describedby')).toBe('snippet-config-keys');
    expect(form.querySelector('#snippet-config-keys').textContent).toBe('Tab indents; press Escape, then Tab, to leave the editor.');
    expect(parsley.validate()).toBe(false);
    return Promise.resolve().then(() => {
      expect(content.getAttribute('aria-invalid')).toBe('true');
      expect(content.getAttribute('aria-required')).toBe('true');
      expect(content.closest('.caelrik-code').classList.contains('is-invalid')).toBe(true);
      textarea.focus();
      expect(document.activeElement).toBe(content);
    });
  });

  test('readOnly() locks the editor', () => {
    const { editor, content } = parts('source');
    editor.readOnly();
    expect(content.getAttribute('contenteditable')).toBe('false');
    editor.readOnly(false);
    expect(content.getAttribute('contenteditable')).toBe('true');
  });

  test('every language loads', async () => {
    for (const load of Object.values(LANGUAGES)) {
      expect(await load()).toBeTruthy();
    }
  });

  test('no axe-core violations', async () => {
    const results = await axe.run(form, {
      runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'] },
      rules: { 'color-contrast': { enabled: false } },
    });
    expect(results.violations.map((violation) => violation.id)).toEqual([]);
  });
});
