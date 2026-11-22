// Markdown fields (Field::markdown()): the native textarea stays the field (screen readers,
// spelling, autofill, phones), with a WAI-ARIA toolbar of formatting buttons (arrow keys move
// between them; Ctrl+B, Ctrl+I and Ctrl+K in the text) and Write and Preview tabs. The preview
// is Markdown made HTML by marked and sanitized by DOMPurify. Formatting goes through the
// browser's own editing where it can, so Undo undoes it.
import { marked } from 'marked';
import DOMPurify from 'dompurify';

const SVG = 'http://www.w3.org/2000/svg';
// 16 × 16 drawings, stroked with the text color.
const ICONS = {
  bold: 'M4.5 2.5h4a2.75 2.75 0 0 1 0 5.5h-4zM4.5 8h4.75a2.75 2.75 0 0 1 0 5.5H4.5z',
  italic: 'M7 2.5h5M4 13.5h5M10 2.5l-4 11',
  heading: 'M3 2.5v11M11 2.5v11M3 8h8',
  link: 'M6.5 9.5l3-3M7.25 4.75l1-1a2.5 2.5 0 0 1 3.5 3.5l-1 1M8.75 11.25l-1 1a2.5 2.5 0 0 1-3.5-3.5l1-1',
  bulletList: 'M6 4h8M6 8h8M6 12h8M2.5 4h.01M2.5 8h.01M2.5 12h.01',
  numberedList: 'M6 4h8M6 8h8M6 12h8M2 2.5h1v3M2 10h2l-2 3h2',
  quote: 'M3 3.5v9M6 5h7M6 8h7M6 11h5',
  code: 'M5.5 4.5L2 8l3.5 3.5M10.5 4.5L14 8l-3.5 3.5',
};
const ACTIONS = ['bold', 'italic', 'heading', 'link', 'bulletList', 'numberedList', 'quote', 'code'];
const SHORTCUTS = { b: 'bold', i: 'italic', k: 'link' };
const LINE_PREFIXES = {
  heading: { add: () => '## ', match: /^#{1,6} / },
  quote: { add: () => '> ', match: /^> ?/ },
  bulletList: { add: () => '- ', match: /^[-*+] /, replace: /^([-*+]|\d+\.) / },
  numberedList: { add: (index) => `${index + 1}. `, match: /^\d+\. /, replace: /^([-*+]|\d+\.) / },
};
const INLINE = { bold: '**', italic: '_', code: '`' };

/**
 * The change an action makes to `value` with the selection [start, end): replace [from, to)
 * by `insert`, then select [selectStart, selectEnd). Applying an inline style or a line prefix
 * again removes it.
 */
export function format(value, start, end, action) {
  const selected = value.slice(start, end);

  if (action === 'code' && selected.includes('\n')) {
    const insert = `\`\`\`\n${selected.replace(/\n$/, '')}\n\`\`\``;
    return { from: start, to: end, insert, selectStart: start + 4, selectEnd: start + 4 + selected.replace(/\n$/, '').length };
  }

  if (INLINE[action]) {
    const marker = INLINE[action];
    const size = marker.length;
    // Already styled: the markers around the selection, or inside it.
    if (value.slice(start - size, start) === marker && value.slice(end, end + size) === marker) {
      return { from: start - size, to: end + size, insert: selected, selectStart: start - size, selectEnd: end - size };
    }
    if (selected.length >= size * 2 && selected.startsWith(marker) && selected.endsWith(marker)) {
      const inner = selected.slice(size, -size);
      return { from: start, to: end, insert: inner, selectStart: start, selectEnd: start + inner.length };
    }
    return {
      from: start, to: end, insert: `${marker}${selected}${marker}`, selectStart: start + size, selectEnd: end + size,
    };
  }

  if (action === 'link') {
    if (/^https?:\/\/\S+$/.test(selected)) {
      // A selected address: the text to type goes in the brackets.
      return { from: start, to: end, insert: `[](${selected})`, selectStart: start + 1, selectEnd: start + 1 };
    }
    if (selected === '') {
      return { from: start, to: end, insert: '[]()', selectStart: start + 1, selectEnd: start + 1 };
    }
    const at = start + selected.length + 3;
    return { from: start, to: end, insert: `[${selected}]()`, selectStart: at, selectEnd: at };
  }

  const prefix = LINE_PREFIXES[action];
  if (prefix) {
    const from = value.lastIndexOf('\n', start - 1) + 1;
    // A selection that ends at the start of a line leaves that line out.
    let to = value.indexOf('\n', end > start && value[end - 1] === '\n' ? end - 1 : end);
    if (to === -1) to = value.length;
    const lines = value.slice(from, to).split('\n');
    const all = lines.every((line) => prefix.match.test(line));
    const insert = lines
      // A list of the other kind changes kind.
      .map((line, index) => (all
        ? line.replace(prefix.match, '')
        : prefix.add(index) + line.replace(prefix.replace ?? prefix.match, '')))
      .join('\n');
    const caret = from + insert.length;
    return {
      from, to, insert, selectStart: start === end ? caret : from, selectEnd: caret,
    };
  }

  throw new Error(`Unknown Markdown action "${action}".`);
}

// Markdown as safe HTML.
export function renderMarkdown(text) {
  const html = DOMPurify.sanitize(marked.parse(text, { async: false, gfm: true }));
  const template = document.createElement('template');
  template.innerHTML = html;
  for (const link of template.content.querySelectorAll('a[href]')) {
    link.setAttribute('rel', 'noopener noreferrer');
  }
  return template.innerHTML;
}

function icon(path) {
  const svg = document.createElementNS(SVG, 'svg');
  svg.setAttribute('viewBox', '0 0 16 16');
  svg.setAttribute('width', '16');
  svg.setAttribute('height', '16');
  svg.setAttribute('aria-hidden', 'true');
  svg.setAttribute('focusable', 'false');
  const drawing = document.createElementNS(SVG, 'path');
  drawing.setAttribute('d', path);
  svg.append(drawing);
  return svg;
}

function apply(textarea, change) {
  textarea.focus();
  textarea.setSelectionRange(change.from, change.to);
  // The browser's own editing keeps Undo working; jsdom and old browsers set the text.
  const edited = change.insert === ''
    ? document.execCommand?.('delete', false)
    : document.execCommand?.('insertText', false, change.insert);
  if (!edited) {
    textarea.setRangeText(change.insert, change.from, change.to, 'end');
    textarea.dispatchEvent(new Event('input', { bubbles: true }));
  }
  textarea.setSelectionRange(change.selectStart, change.selectEnd);
}

// Arrow keys, Home and End between the items of a toolbar or tab list.
function rove(items, event, activate) {
  const index = items.indexOf(document.activeElement);
  const next = {
    ArrowRight: index + 1, ArrowLeft: index - 1, Home: 0, End: items.length - 1,
  }[event.key];
  if (next === undefined || index === -1) return;
  event.preventDefault();
  const target = items[(next + items.length) % items.length];
  items.forEach((item) => item.setAttribute('tabindex', item === target ? '0' : '-1'));
  target.focus();
  activate?.(target);
}

export function initMarkdown(textarea) {
  if (textarea.closest('.celerik-markdown')) return;
  const labels = JSON.parse(textarea.getAttribute('data-celerik-markdown') || '{}');
  const id = textarea.id;
  const label = textarea.labels?.[0];

  const wrapper = document.createElement('div');
  wrapper.className = 'celerik-markdown';
  const bar = document.createElement('div');
  bar.className = 'celerik-markdown-bar';

  const tablist = document.createElement('div');
  tablist.className = 'celerik-markdown-tabs';
  tablist.setAttribute('role', 'tablist');
  if (label) tablist.setAttribute('aria-label', label.textContent.replace(/\s*\*$/, '').trim());
  const writePanel = document.createElement('div');
  const previewPanel = document.createElement('div');
  const tabs = [['write', writePanel], ['preview', previewPanel]].map(([name, panel], index) => {
    const tab = document.createElement('button');
    tab.type = 'button';
    tab.id = `${id}-${name}`;
    tab.className = 'celerik-markdown-tab';
    tab.setAttribute('role', 'tab');
    tab.setAttribute('aria-selected', index === 0 ? 'true' : 'false');
    tab.setAttribute('aria-controls', `${id}-${name}-panel`);
    tab.setAttribute('tabindex', index === 0 ? '0' : '-1');
    tab.textContent = labels[name];
    panel.id = `${id}-${name}-panel`;
    panel.setAttribute('role', 'tabpanel');
    panel.setAttribute('aria-labelledby', tab.id);
    tablist.append(tab);
    return tab;
  });

  const toolbar = document.createElement('div');
  toolbar.className = 'celerik-markdown-toolbar';
  toolbar.setAttribute('role', 'toolbar');
  toolbar.setAttribute('aria-label', labels.toolbar);
  toolbar.setAttribute('aria-controls', id);
  const buttons = ACTIONS.map((action, index) => {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'btn btn-sm celerik-markdown-action';
    button.dataset.action = action;
    button.setAttribute('aria-label', labels[action]);
    const shortcut = Object.keys(SHORTCUTS).find((key) => SHORTCUTS[key] === action);
    if (shortcut) button.setAttribute('aria-keyshortcuts', `Control+${shortcut.toUpperCase()}`);
    button.title = shortcut ? `${labels[action]} (Ctrl+${shortcut.toUpperCase()})` : labels[action];
    button.setAttribute('tabindex', index === 0 ? '0' : '-1');
    button.append(icon(ICONS[action]));
    button.addEventListener('click', () => {
      apply(textarea, format(textarea.value, textarea.selectionStart, textarea.selectionEnd, action));
    });
    toolbar.append(button);
    return button;
  });
  toolbar.addEventListener('keydown', (event) => rove(buttons, event));

  writePanel.className = 'celerik-markdown-write';
  previewPanel.className = 'celerik-markdown-preview';
  previewPanel.setAttribute('tabindex', '0');
  previewPanel.hidden = true;

  textarea.before(wrapper);
  bar.append(tablist, toolbar);
  writePanel.append(textarea);
  wrapper.append(bar, writePanel, previewPanel);

  const select = (tab) => {
    const preview = tab === tabs[1];
    tabs.forEach((each) => {
      each.setAttribute('aria-selected', each === tab ? 'true' : 'false');
      each.setAttribute('tabindex', each === tab ? '0' : '-1');
    });
    if (preview) {
      // The preview keeps the height of the text, so the page does not jump.
      previewPanel.style.minHeight = `${textarea.offsetHeight}px`;
      const html = renderMarkdown(textarea.value);
      if (html.trim()) {
        previewPanel.innerHTML = html;
      } else {
        const empty = document.createElement('p');
        empty.className = 'celerik-markdown-empty';
        empty.textContent = labels.empty;
        previewPanel.replaceChildren(empty);
      }
    }
    writePanel.hidden = preview;
    previewPanel.hidden = !preview;
    toolbar.hidden = preview;
  };
  tabs.forEach((tab) => tab.addEventListener('click', () => select(tab)));
  tablist.addEventListener('keydown', (event) => rove(tabs, event, select));

  textarea.addEventListener('keydown', (event) => {
    const action = SHORTCUTS[event.key.toLowerCase()];
    if (action && (event.ctrlKey || event.metaKey) && !event.altKey && !event.shiftKey) {
      event.preventDefault();
      apply(textarea, format(textarea.value, textarea.selectionStart, textarea.selectionEnd, action));
    }
  });

  // A reset shows the text again (form.js does so for a validation error).
  textarea.form?.addEventListener('reset', () => select(tabs[0]));
}

export function initMarkdowns(container) {
  for (const textarea of container.querySelectorAll('textarea[data-celerik-markdown]')) {
    initMarkdown(textarea);
  }
}
