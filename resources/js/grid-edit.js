// Inline editing (Column::editable(), Grid::inlineEdit()). An editable cell shows its value
// and an edit button; the button (or a double click) turns the cell into a labelled field.
// Enter or leaving the field saves, Escape cancels, and focus goes back to the button. The
// value is checked as Editor::parse() checks it on the server, then sent as a form-encoded
// POST with `field`, `value` and `_method`. A refused value keeps the field open, with the
// message linked to it. Controls are built with DOM methods (CSP).
import { fill } from './action.js';
import { escapeHtml } from './notification-config.js';

// The same pencil as Grid::PENCIL (Bootstrap Icons, MIT).
export const PENCIL = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" '
  + 'viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5'
  + ' 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168zM11.207 2.5 13.5'
  + ' 4.793 14.793 3.5 12.5 1.207zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5'
  + 'v.5h.293zm-9.761 5.175-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1'
  + '-.5-.5V11h-.5a.5.5 0 0 1-.468-.325"/></svg>';

const NUMBER = /^[+-]?(\d+\.?\d*|\.\d+)([eE][+-]?\d+)?$/;
const DAY = /^\d{4}-\d{2}-\d{2}$/;

let editors = 0;

function read(row, path) {
  return path.split('.').reduce((value, key) => (value == null ? undefined : value[key]), row);
}

function write(row, path, value) {
  const keys = path.split('.');
  const last = keys.pop();
  const target = keys.reduce((object, key) => {
    if (object[key] == null || typeof object[key] !== 'object') {
      object[key] = {};
    }
    return object[key];
  }, row);
  target[last] = value;
}

// As Arr::toText().
function toText(value) {
  if (value === null || value === undefined) {
    return '';
  }
  if (typeof value === 'boolean') {
    return value ? 'Yes' : 'No';
  }
  return typeof value === 'object' ? JSON.stringify(value) : String(value);
}

// As Editor::editValue(): the value an editor starts from.
export function editValue(editor, value) {
  if (value === null || value === undefined) {
    return '';
  }
  if (typeof value === 'boolean') {
    return value ? '1' : '0';
  }
  if (editor.type === 'date') {
    return String(value).slice(0, 10);
  }
  return toText(value);
}

// As Grid::editText(): an option's label, Yes or No.
export function editText(editor, value, labels) {
  const key = editValue(editor, value);
  if (editor.type === 'select') {
    const option = (editor.options ?? []).find(([optionValue]) => optionValue === key);
    if (option) {
      return option[1];
    }
  }
  if (editor.type === 'boolean' && key !== '') {
    return ['1', 'true'].includes(key) ? labels.yes : labels.no;
  }
  return toText(value);
}

function name(template, column, rowTitle) {
  return template.replaceAll('{column}', column.title).replaceAll('{row}', rowTitle);
}

// The same markup as Grid::renderEditable(), for rows a grid draws in the browser.
export function editableCellHtml(column, row, edit, rowTitle) {
  const label = name(edit.labels.edit, column, toText(read(row, rowTitle ?? column.field)));
  return `<span class="celerik-grid-value">${escapeHtml(editText(column.editor, read(row, column.field), edit.labels))}</span>`
    + `<button type="button" class="btn btn-link btn-sm celerik-grid-edit" aria-label="${escapeHtml(label)}"`
    + ` data-celerik-edit="${escapeHtml(column.field)}">${PENCIL}</button>`;
}

// As Editor::parse(), without the conversion: a message, or null when the value is fine.
export function checkValue(editor, value, labels) {
  if (value === '') {
    return editor.required ? labels.required : null;
  }
  switch (editor.type) {
    case 'number': {
      if (!NUMBER.test(value)) {
        return labels.invalid;
      }
      const number = Number(value);
      if (editor.min !== undefined && number < editor.min) {
        return labels.tooLow.replaceAll('{min}', String(editor.min));
      }
      if (editor.max !== undefined && number > editor.max) {
        return labels.tooHigh.replaceAll('{max}', String(editor.max));
      }
      return null;
    }
    case 'select':
      return (editor.options ?? []).some(([option]) => option === value) ? null : labels.invalid;
    case 'boolean':
      return ['1', '0'].includes(value) ? null : labels.invalid;
    case 'date':
      return DAY.test(value) && !Number.isNaN(Date.parse(`${value}T00:00:00Z`)) ? null : labels.invalid;
    default:
      return editor.maxLength !== undefined && [...value].length > editor.maxLength
        ? labels.tooLong.replaceAll('{max}', String(editor.maxLength))
        : null;
  }
}

// The value as the server reads it, for an answer that does not say: a number, true or
// false, or null for an empty cell.
function typed(editor, value) {
  if (value === '') {
    return null;
  }
  if (editor.type === 'number') {
    return Number(value);
  }
  if (editor.type === 'boolean') {
    return value === '1';
  }
  return value;
}

function control(editor, value, labels) {
  if (editor.type === 'select' || editor.type === 'boolean') {
    const select = document.createElement('select');
    select.className = 'form-select form-select-sm';
    const options = editor.type === 'boolean' ? [['1', labels.yes], ['0', labels.no]] : (editor.options ?? []);
    if (!editor.required) {
      select.append(new Option('', ''));
    }
    options.forEach(([optionValue, label]) => select.append(new Option(label, optionValue)));
    select.value = value;
    select.required = Boolean(editor.required);
    return select;
  }
  const input = document.createElement('input');
  input.className = 'form-control form-control-sm';
  input.type = { number: 'number', date: 'date' }[editor.type] ?? 'text';
  ['min', 'max', 'step'].forEach((key) => {
    if (editor[key] !== undefined) {
      input[key] = String(editor[key]);
    }
  });
  if (editor.maxLength !== undefined) {
    input.maxLength = editor.maxLength;
  }
  input.required = Boolean(editor.required);
  input.autocomplete = 'off';
  input.value = value;
  return input;
}

async function readAnswer(response) {
  if (!(response.headers.get('Content-Type') ?? '').includes('json')) {
    return null;
  }
  try {
    return await response.json();
  } catch {
    return null;
  }
}

// A refused value's message: {errors: {field: ['...']}} (Laravel, InvalidCellEdit), or
// {message}.
function refusal(answer, field) {
  const errors = answer?.errors?.[field] ?? answer?.errors?.value;
  const first = Array.isArray(errors) ? errors[0] : errors;
  if (typeof first === 'string' && first !== '') {
    return first;
  }
  return typeof answer?.message === 'string' && answer.message !== '' ? answer.message : null;
}

// options.fetch and options.notify: for tests and custom UIs.
export function enableInlineEdit(grid, element, options, deps = {}) {
  const edit = options.edit;
  const columns = new Map((options.columns ?? []).filter((column) => column.editor).map((column) => [column.field, column]));
  if (!edit || columns.size === 0) {
    return;
  }
  const { labels } = edit;
  const serverSide = Boolean(options.url);
  const send = deps.fetch ?? ((...args) => fetch(...args));
  const notify = deps.notify ?? (async (type, message) => (await import('./notification.js')).notify(type, message));
  const titleField = options.rowTitle ?? [...columns.keys()][0];
  const index = (field) => grid.column(`${field}:name`).index();

  // Announces saves, and refusals the user did not see happen in the field.
  const status = document.createElement('div');
  status.className = 'visually-hidden';
  status.setAttribute('role', 'status');
  grid.table().container().append(status);
  const announce = (text) => {
    status.textContent = '';
    setTimeout(() => { status.textContent = text; }, 50);
  };

  // A client-side row's values, from its cells: what PHP wrote in data-value, or the text.
  const rowData = (tr) => {
    if (serverSide) {
      return grid.row(tr).data();
    }
    const row = {};
    for (const column of options.columns ?? []) {
      const node = grid.cell(tr, index(column.field)).node();
      if (node) {
        write(row, column.field, node.dataset.value ?? node.textContent.trim());
      }
    }
    return row;
  };
  // The row's name, as its title cell shows it.
  const rowTitle = (tr) => {
    const node = grid.cell(tr, index(titleField)).node();
    return (node?.querySelector('.celerik-grid-value') ?? node)?.textContent.trim() ?? '';
  };

  const close = (state, refocus) => {
    state.done = true;
    if (refocus) {
      state.td.querySelector('[data-celerik-edit]')?.focus();
    }
  };

  const cancel = (state, refocus) => {
    if (state.done) {
      return;
    }
    state.td.replaceChildren(...state.original);
    close(state, refocus);
  };

  const showError = (state, message, refocus) => {
    state.control.classList.add('is-invalid');
    state.control.setAttribute('aria-invalid', 'true');
    state.error.textContent = message;
    if (refocus) {
      state.control.focus();
    } else if (!state.td.contains(document.activeElement)) {
      announce(message);
    }
  };

  const apply = (state, saved) => {
    const { td, tr, column } = state;
    if (serverSide) {
      write(grid.row(tr).data(), column.field, saved);
      grid.cell(td).invalidate('data');
    } else {
      const row = { [column.field]: saved };
      write(row, titleField, column.field === titleField ? saved : state.title);
      td.innerHTML = editableCellHtml(column, row, edit, titleField);
      grid.cell(td).invalidate('dom');
    }
    td.dataset.value = editValue(column.editor, saved);
  };

  const save = async (state, refocus) => {
    if (state.done || state.saving) {
      return;
    }
    const { column, td, control: field } = state;
    const raw = field.value;
    const value = column.editor.type === 'text' ? raw.trim() : raw;
    if (value === state.start) {
      cancel(state, refocus);
      return;
    }
    const problem = field.validity?.badInput ? labels.invalid : checkValue(column.editor, value, labels);
    if (problem) {
      showError(state, problem, refocus);
      return;
    }

    const headers = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
    const body = new FormData();
    body.append('field', column.field);
    body.append('value', value);
    if (edit.method !== 'POST') {
      body.append('_method', edit.method);
    }
    if (edit.csrf) {
      body.append(edit.csrf.field, edit.csrf.token);
      headers[edit.csrf.header] = edit.csrf.token;
    }
    const url = new URL(fill(edit.url, rowData(state.tr), true), document.baseURI).href;

    state.saving = true;
    td.setAttribute('aria-busy', 'true');
    field.setAttribute('aria-disabled', 'true');
    let detail;
    try {
      const response = await send(url, { method: 'POST', headers, body, credentials: 'same-origin' });
      const answer = await readAnswer(response);
      if (response.ok) {
        let saved = typed(column.editor, value);
        if (answer && Object.hasOwn(answer, 'value')) {
          saved = answer.value;
        } else if (answer?.data && typeof answer.data === 'object') {
          saved = read(answer.data, column.field) ?? saved;
        }
        // Did the user stay in the field? Then focus goes back to the button.
        const stayed = refocus || td.contains(document.activeElement) || document.activeElement === document.body;
        apply(state, saved);
        close(state, stayed);
        if (typeof answer?.message === 'string' && answer.message !== '') {
          await notify('success', answer.message);
        } else {
          announce(name(labels.saved, column, state.title));
        }
        detail = { field: column.field, value: saved, ok: true, status: response.status, data: answer };
      } else {
        showError(state, refusal(answer, column.field) ?? labels.editFailed, td.contains(document.activeElement));
        detail = { field: column.field, value, ok: false, status: response.status, data: answer };
      }
    } catch (error) {
      showError(state, labels.editFailed, td.contains(document.activeElement));
      detail = { field: column.field, value, ok: false, status: 0, data: null, error };
    } finally {
      state.saving = false;
      td.removeAttribute('aria-busy');
      field.removeAttribute('aria-disabled');
    }
    td.dispatchEvent(new CustomEvent('celerik:edit', { bubbles: true, detail }));
  };

  const start = (td, field) => {
    const column = columns.get(field);
    if (!column || td.querySelector('.celerik-grid-editor')) {
      return;
    }
    const tr = td.closest('tr');
    const title = rowTitle(tr);
    const startValue = column.editor.type === 'date' ? (td.dataset.value ?? '').slice(0, 10) : (td.dataset.value ?? '');
    const state = {
      td, tr, column, title, start: startValue, original: [...td.childNodes], done: false, saving: false,
    };

    state.control = control(column.editor, startValue, labels);
    state.control.setAttribute('aria-label', name(labels.editField, column, title));
    state.error = document.createElement('div');
    editors += 1;
    state.error.id = `celerik-edit-error-${editors}`;
    state.error.className = 'invalid-feedback';
    state.control.setAttribute('aria-describedby', state.error.id);
    const wrapper = document.createElement('div');
    wrapper.className = 'celerik-grid-editor';
    wrapper.append(state.control, state.error);
    td.replaceChildren(wrapper);

    state.control.addEventListener('keydown', (event) => {
      if (event.key === 'Enter') {
        event.preventDefault();
        save(state, true);
      } else if (event.key === 'Escape') {
        event.preventDefault();
        event.stopPropagation();
        cancel(state, true);
      }
    });
    state.control.addEventListener('focusout', (event) => {
      if (!wrapper.contains(event.relatedTarget)) {
        save(state, false);
      }
    });
    state.control.focus();
    if (state.control.select && state.control.type === 'text') {
      state.control.select();
    }
  };

  element.addEventListener('click', (event) => {
    const button = event.target.closest('[data-celerik-edit]');
    if (button && element.contains(button)) {
      start(button.closest('td'), button.dataset.celerikEdit);
    }
  });
  element.addEventListener('dblclick', (event) => {
    const td = event.target.closest('td.celerik-grid-editable');
    const button = td?.querySelector('[data-celerik-edit]');
    if (button && !event.target.closest('.celerik-grid-editor')) {
      start(td, button.dataset.celerikEdit);
    }
  });
}
