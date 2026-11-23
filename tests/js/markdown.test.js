import axe from 'axe-core';
import { initForm } from '../../resources/js/form.js';
import { readOptions } from '../../resources/js/core.js';
import { format, renderMarkdown } from '../../resources/js/form-markdown.js';
import { loadFixture } from './helpers.js';

// Applies a change to a text whose selection is marked by [ and ].
function run(marked, action) {
  const start = marked.indexOf('[');
  const end = marked.indexOf(']') - 1;
  const value = marked.replace('[', '').replace(']', '');
  const change = format(value, start, end, action);
  const text = value.slice(0, change.from) + change.insert + value.slice(change.to);
  return `${text.slice(0, change.selectStart)}[${text.slice(change.selectStart, change.selectEnd)}]${text.slice(change.selectEnd)}`;
}

describe('format()', () => {
  test.each([
    ['Hello [world]', 'bold', 'Hello **[world]**'],
    ['Hello **[world]**', 'bold', 'Hello [world]'],
    ['Hello [**world**]', 'bold', 'Hello [world]'],
    ['Hello []', 'bold', 'Hello **[]**'],
    ['An [idea]', 'italic', 'An _[idea]_'],
    ['Run [npm test]', 'code', 'Run `[npm test]`'],
    ['[a\nb]', 'code', '```\n[a\nb]\n```'],
    ['[Caelrik]', 'link', '[Caelrik]([])'],
    ['[https://example.com]', 'link', '[[]](https://example.com)'],
    ['See []', 'link', 'See [[]]()'],
    ['Ti[]tle', 'heading', '## Title[]'],
    ['## Ti[]tle', 'heading', 'Title[]'],
    ['[one\ntwo]', 'bulletList', '[- one\n- two]'],
    ['[- one\n- two]', 'bulletList', '[one\ntwo]'],
    ['[one\ntwo\n]three', 'numberedList', '[1. one\n2. two]\nthree'],
    ['[- one\n- two]', 'numberedList', '[1. one\n2. two]'],
    ['Intro\n[said]', 'quote', 'Intro\n[> said]'],
  ])('%j with %s gives %j', (marked, action, expected) => {
    expect(run(marked, action)).toBe(expected);
  });

  test('an unknown action', () => {
    expect(() => format('a', 0, 1, 'underline')).toThrow('Unknown Markdown action "underline".');
  });
});

test('renderMarkdown makes safe HTML', () => {
  const html = renderMarkdown('# Hi\n\n[x](https://example.com) <img src=x onerror=alert(1)> <script>alert(1)</script>');
  expect(html).toContain('<h1>Hi</h1>');
  expect(html).toContain('<a href="https://example.com" rel="noopener noreferrer">x</a>');
  expect(html).not.toMatch(/onerror|<script/);
  expect(renderMarkdown('[x](javascript:alert(1))')).not.toContain('javascript:');
});

describe('Markdown fields in a form', () => {
  let form;
  let parsley;

  beforeEach(async () => {
    form = loadFixture('form-markdown');
    parsley = await initForm(form, readOptions(form));
  });

  afterEach(() => parsley.destroy());

  const parts = (name) => {
    const box = form.querySelector(`#post-${name}`).closest('.caelrik-markdown');
    return {
      box,
      textarea: box.querySelector('textarea'),
      tabs: [...box.querySelectorAll('[role="tab"]')],
      toolbar: box.querySelector('[role="toolbar"]'),
      preview: box.querySelector('.caelrik-markdown-preview'),
    };
  };

  test('tabs, panels and a toolbar around the textarea', () => {
    const { textarea, tabs, toolbar } = parts('body');
    expect(textarea.name).toBe('body');
    expect(tabs.map((tab) => [tab.textContent, tab.getAttribute('aria-selected')])).toEqual([['Write', 'true'], ['Preview', 'false']]);
    expect(tabs[0].getAttribute('aria-controls')).toBe('post-body-write-panel');
    expect(textarea.closest('[role="tabpanel"]').getAttribute('aria-labelledby')).toBe('post-body-write');
    expect(toolbar.getAttribute('aria-label')).toBe('Formatting');
    expect(toolbar.getAttribute('aria-controls')).toBe('post-body');
    expect([...toolbar.querySelectorAll('button')].map((button) => [button.getAttribute('aria-label'), button.tabIndex])).toEqual([
      ['Bold', 0], ['Italic', -1], ['Heading', -1], ['Link', -1], ['Bulleted list', -1], ['Numbered list', -1], ['Quote', -1], ['Code', -1],
    ]);
    expect(textarea.closest('[role="tablist"]')).toBeNull();
    expect(form.querySelector('[role="tablist"]').getAttribute('aria-label')).toBe('Body');
  });

  test('a button formats the selection and fires input', () => {
    const { textarea, toolbar } = parts('body');
    const inputs = [];
    textarea.addEventListener('input', () => inputs.push(textarea.value));
    textarea.value = 'Hello world';
    textarea.setSelectionRange(6, 11);
    toolbar.querySelector('[data-action="bold"]').click();
    expect(textarea.value).toBe('Hello **world**');
    expect([textarea.selectionStart, textarea.selectionEnd]).toEqual([8, 13]);
    expect(inputs).toEqual(['Hello **world**']);
  });

  test('Ctrl+I in the text', () => {
    const { textarea } = parts('body');
    textarea.value = 'An idea';
    textarea.setSelectionRange(3, 7);
    textarea.dispatchEvent(new KeyboardEvent('keydown', { key: 'i', ctrlKey: true, bubbles: true }));
    expect(textarea.value).toBe('An _idea_');
  });

  test('the preview shows sanitized HTML, hides the toolbar, and keeps a message for an empty text', () => {
    const notes = parts('notes');
    notes.tabs[1].click();
    expect(notes.preview.hidden).toBe(false);
    expect(notes.textarea.closest('[role="tabpanel"]').hidden).toBe(true);
    expect(notes.toolbar.hidden).toBe(true);
    expect(notes.preview.querySelector('h2').textContent).toBe('Notes');
    expect(notes.preview.innerHTML).not.toContain('onerror');

    const body = parts('body');
    body.tabs[1].click();
    expect(body.preview.textContent).toBe('Nothing to preview.');
  });

  test('a required empty field shows its text again when validated', () => {
    const { tabs, textarea } = parts('body');
    tabs[1].click();
    expect(parsley.validate()).toBe(false);
    expect(tabs[0].getAttribute('aria-selected')).toBe('true');
    expect(textarea.closest('[role="tabpanel"]').hidden).toBe(false);
    expect(textarea.getAttribute('aria-invalid')).toBe('true');
  });

  test('no axe-core violations, writing and previewing', async () => {
    const check = async () => (await axe.run(form, {
      runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'] },
      rules: { 'color-contrast': { enabled: false }, 'image-alt': { enabled: false } },
    })).violations.map((violation) => violation.id);
    expect(await check()).toEqual([]);
    parts('notes').tabs[1].click();
    expect(await check()).toEqual([]);
  });
});
