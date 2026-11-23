// Code fields (Field::code()), with CodeMirror 6: line numbers, syntax colors, brackets,
// search and Undo. The textarea stays in the form and keeps the text (validation, submit,
// reset); the editor takes its name (the label), its description and its error state.
// Tab moves to the next field unless the field asks Tab to indent; Escape then Tab leaves
// the editor then. Colors are classes, styled by the theme in light and dark mode.
import { basicSetup } from 'codemirror';
import { EditorView, keymap } from '@codemirror/view';
import { Compartment, EditorState } from '@codemirror/state';
import { HighlightStyle, indentUnit, syntaxHighlighting } from '@codemirror/language';
import { indentWithTab } from '@codemirror/commands';
import { tags } from '@lezer/highlight';

// Each language's grammar is loaded when a field needs it.
export const LANGUAGES = {
  javascript: () => import('@codemirror/lang-javascript').then((module) => module.javascript()),
  typescript: () => import('@codemirror/lang-javascript').then((module) => module.javascript({ typescript: true })),
  jsx: () => import('@codemirror/lang-javascript').then((module) => module.javascript({ jsx: true })),
  php: () => import('@codemirror/lang-php').then((module) => module.php()),
  html: () => import('@codemirror/lang-html').then((module) => module.html()),
  css: () => import('@codemirror/lang-css').then((module) => module.css()),
  json: () => import('@codemirror/lang-json').then((module) => module.json()),
  sql: () => import('@codemirror/lang-sql').then((module) => module.sql()),
  python: () => import('@codemirror/lang-python').then((module) => module.python()),
  markdown: () => import('@codemirror/lang-markdown').then((module) => module.markdown()),
  xml: () => import('@codemirror/lang-xml').then((module) => module.xml()),
};

// Syntax colors as classes (no inline styles), colored by the stylesheet.
const highlight = HighlightStyle.define([
  { tag: [tags.keyword, tags.operatorKeyword, tags.modifier, tags.controlKeyword], class: 'caelrik-code-keyword' },
  { tag: [tags.string, tags.special(tags.string), tags.regexp], class: 'caelrik-code-string' },
  { tag: [tags.comment, tags.lineComment, tags.blockComment, tags.docComment], class: 'caelrik-code-comment' },
  { tag: [tags.number, tags.bool, tags.null, tags.atom], class: 'caelrik-code-number' },
  { tag: [tags.function(tags.variableName), tags.function(tags.propertyName)], class: 'caelrik-code-function' },
  { tag: [tags.typeName, tags.className, tags.namespace], class: 'caelrik-code-type' },
  { tag: [tags.tagName, tags.angleBracket], class: 'caelrik-code-tag' },
  { tag: [tags.attributeName, tags.propertyName], class: 'caelrik-code-property' },
  { tag: [tags.heading, tags.strong], class: 'caelrik-code-strong' },
  { tag: tags.emphasis, class: 'caelrik-code-emphasis' },
  { tag: tags.link, class: 'caelrik-code-link' },
  { tag: tags.invalid, class: 'caelrik-code-invalid' },
]);

// The editor in the page's colors (Bootstrap's variables): CodeMirror's own theme is made for
// a light page, with a black cursor and a light gray selection.
const theme = EditorView.theme({
  '&': { color: 'var(--bs-body-color)', backgroundColor: 'var(--bs-body-bg)', fontSize: '0.875rem' },
  '&.cm-focused': { outline: 'none' },
  '.cm-scroller': { fontFamily: 'var(--bs-font-monospace)', lineHeight: '1.5' },
  '.cm-content': { caretColor: 'var(--bs-body-color)' },
  '.cm-cursor, .cm-dropCursor': { borderLeftColor: 'var(--bs-body-color)' },
  '&.cm-focused > .cm-scroller > .cm-selectionLayer .cm-selectionBackground, .cm-selectionBackground, .cm-content ::selection': {
    backgroundColor: 'var(--caelrik-code-selection)',
  },
  '.cm-gutters': {
    backgroundColor: 'var(--bs-tertiary-bg)',
    color: 'var(--bs-secondary-color)',
    borderRight: '1px solid var(--bs-border-color)',
  },
  '.cm-activeLine': { backgroundColor: 'var(--caelrik-code-active-line)' },
  '.cm-activeLineGutter': { backgroundColor: 'var(--caelrik-code-active-line)', color: 'var(--bs-body-color)' },
  '.cm-matchingBracket, &.cm-focused .cm-matchingBracket': {
    backgroundColor: 'transparent', outline: '1px solid var(--bs-secondary-color)',
  },
  '.cm-searchMatch': { backgroundColor: 'var(--caelrik-code-match)', outline: '1px solid var(--bs-warning-border-subtle)' },
  '.cm-panels': { backgroundColor: 'var(--bs-tertiary-bg)', color: 'var(--bs-body-color)' },
  '.cm-panels.cm-panels-bottom': { borderTop: '1px solid var(--bs-border-color)' },
  '.cm-textfield, .cm-button': {
    minHeight: '24px',
    backgroundColor: 'var(--bs-body-bg)',
    backgroundImage: 'none',
    color: 'var(--bs-body-color)',
    border: '1px solid var(--caelrik-input-border, var(--bs-border-color))',
    borderRadius: 'var(--bs-border-radius-sm)',
  },
  '.cm-panel.cm-search [name=close]': { color: 'var(--bs-body-color)', minWidth: '24px', minHeight: '24px' },
  '.cm-tooltip': {
    backgroundColor: 'var(--bs-body-bg)', color: 'var(--bs-body-color)', border: '1px solid var(--bs-border-color)',
  },
  '.cm-tooltip-autocomplete ul li[aria-selected]': { backgroundColor: 'var(--bs-primary)', color: '#fff' },
  '.cm-foldPlaceholder': {
    backgroundColor: 'var(--bs-secondary-bg)', color: 'var(--bs-body-color)', border: '1px solid var(--bs-border-color)',
  },
});

// The attributes the editor's text area copies from the textarea.
const MIRRORED = ['aria-invalid', 'aria-describedby', 'aria-required'];

export async function initCode(textarea) {
  if (textarea.caelrikEditor) return textarea.caelrikEditor;
  const options = JSON.parse(textarea.getAttribute('data-caelrik-code') || '{}');
  const language = LANGUAGES[options.language] ? await LANGUAGES[options.language]() : [];
  const nonce = document.querySelector('meta[name="csp-nonce"]')?.content;

  const keys = document.createElement('div');
  keys.id = `${textarea.id}-keys`;
  keys.className = 'form-text caelrik-code-keys';
  keys.textContent = options.keys ?? '';

  const host = document.createElement('div');
  host.className = 'caelrik-code';
  textarea.before(host);
  host.after(keys);

  const editable = new Compartment();
  const view = new EditorView({
    parent: host,
    state: EditorState.create({
      doc: textarea.value,
      extensions: [
        basicSetup,
        language,
        indentUnit.of('    '),
        syntaxHighlighting(highlight),
        theme,
        // CodeMirror injects its stylesheet as a <style> element: under a Content Security
        // Policy it needs the page's nonce (Assets::tags($url, $nonce) prints it).
        nonce ? EditorView.cspNonce.of(nonce) : [],
        options.tabIndents ? keymap.of([indentWithTab]) : [],
        editable.of([EditorView.editable.of(!textarea.readOnly), EditorState.readOnly.of(textarea.readOnly)]),
        EditorView.contentAttributes.of({
          'aria-labelledby': `${textarea.id}-label`,
          'aria-multiline': 'true',
          spellcheck: 'false',
          autocapitalize: 'off',
          autocorrect: 'off',
        }),
        EditorView.updateListener.of((update) => {
          if (!update.docChanged) return;
          textarea.value = update.state.doc.toString();
          textarea.dispatchEvent(new Event('input', { bubbles: true }));
          // A message already shown follows the text.
          if (textarea.classList.contains('is-invalid') || textarea.classList.contains('is-valid')) {
            textarea.dispatchEvent(new Event('change', { bubbles: true }));
          }
        }),
      ],
    }),
  });

  // The textarea stays in the form for validation and submit, out of sight and of the tab order.
  textarea.classList.add('visually-hidden');
  textarea.setAttribute('tabindex', '-1');
  textarea.setAttribute('aria-hidden', 'true');

  // Its name, description, error state and required state go to the editor's text.
  const content = view.contentDOM;
  const mirror = () => {
    const described = [textarea.getAttribute('aria-describedby'), keys.id].filter(Boolean).join(' ');
    content.setAttribute('aria-describedby', [...new Set(described.split(' '))].join(' '));
    for (const name of MIRRORED.filter((each) => each !== 'aria-describedby')) {
      if (textarea.hasAttribute(name)) content.setAttribute(name, textarea.getAttribute(name));
      else content.removeAttribute(name);
    }
    if (textarea.required) content.setAttribute('aria-required', 'true');
    host.classList.toggle('is-invalid', textarea.classList.contains('is-invalid'));
  };
  new MutationObserver(mirror).observe(textarea, { attributes: true, attributeFilter: [...MIRRORED, 'class'] });
  mirror();

  // Focus on the textarea (a validation error, the error summary) goes to the editor.
  textarea.focus = () => view.focus();
  textarea.addEventListener('focus', () => view.focus());

  // A reset puts the form's text back, after the reset event.
  textarea.form?.addEventListener('reset', () => setTimeout(() => {
    view.dispatch({ changes: { from: 0, to: view.state.doc.length, insert: textarea.value } });
  }));

  const api = {
    view,
    root: content,
    get value() {
      return view.state.doc.toString();
    },
    set value(text) {
      view.dispatch({ changes: { from: 0, to: view.state.doc.length, insert: text } });
    },
    readOnly(readOnly = true) {
      view.dispatch({
        effects: editable.reconfigure([EditorView.editable.of(!readOnly), EditorState.readOnly.of(readOnly)]),
      });
    },
    destroy() {
      view.destroy();
    },
  };
  textarea.caelrikEditor = api;
  return api;
}

export function initCodes(container) {
  return Promise.all([...container.querySelectorAll('textarea[data-caelrik-code]')].map((textarea) => initCode(textarea)));
}
