// Actions (PHP: Action::link(), Action::request()): a request action is a form that posts
// without JavaScript. With it, a submit asks for confirmation in a dialog when the action
// has one, sends the request with fetch and shows the answer's message, then fires
// `celerik:action` on the form (a grid reloads or removes the row).
// Dialogs are built with DOM methods and textContent only (CSP).
import { escapeHtml } from './notification-config.js';

export const LABELS = {
  cancel: 'Cancel',
  failed: 'The action failed. Try again.',
};

const PLACEHOLDER = /\{([A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)?)\}/g;

// A row value as PHP's Arr::toText() writes it.
function toText(value) {
  if (value === null || value === undefined) {
    return '';
  }
  if (typeof value === 'boolean') {
    return value ? 'Yes' : 'No';
  }
  return typeof value === 'object' ? JSON.stringify(value) : String(value);
}

// `{field}` (or `{relation.field}`) takes the row's value, URL-encoded when `encode` is set.
export function fill(template, row, encode = false) {
  return String(template ?? '').replace(PLACEHOLDER, (match, field) => {
    const value = toText(field.split('.').reduce((object, key) => object?.[key], row));
    return encode ? encodeURIComponent(value) : value;
  });
}

// The same markup as Action::render() in PHP, for rows a grid draws in the browser.
export function actionHtml(action, row, ariaLabel = null, small = true) {
  const attribute = (name, value) => (value === null || value === undefined ? '' : ` ${name}="${escapeHtml(value)}"`);
  const name = action.ariaLabel ? fill(action.ariaLabel, row) : ariaLabel;
  const className = `${action.className}${small ? ' btn-sm' : ''}`;
  const url = fill(action.url, row, true);
  const label = escapeHtml(action.label);

  if (!action.request) {
    return `<a${attribute('href', url)}${attribute('class', className)}${attribute('aria-label', name)}>${label}</a>`;
  }

  let fields = '';
  if (action.method !== 'POST') {
    fields += `<input type="hidden" name="_method"${attribute('value', action.method)}>`;
  }
  if (action.csrf) {
    fields += `<input type="hidden"${attribute('name', action.csrf.field)}${attribute('value', action.csrf.token)}>`;
  }
  return `<form method="post"${attribute('action', url)} class="celerik-action"`
    + `${attribute('data-celerik-action', action.name)}${attribute('data-method', action.method)}`
    + `${attribute('data-confirm', action.confirm ? fill(action.confirm, row) : null)}`
    + `${attribute('data-confirm-detail', action.confirmDetail ? fill(action.confirmDetail, row) : null)}`
    + `${attribute('data-csrf-header', action.csrf?.header)}`
    + `${attribute('data-labels', action.labels ? JSON.stringify(action.labels) : null)}>`
    + `${fields}<button type="submit"${attribute('class', className)}${attribute('aria-label', name)}>${label}</button></form>`;
}

// A grid row's buttons, as Grid::renderActions() writes them: each named with its label
// and the row's title ("Delete Ada Lovelace") unless it has its own ariaLabel.
export function rowActionsHtml(actions, row, rowTitle) {
  const title = rowTitle ? fill(`{${rowTitle}}`, row) : '';
  const buttons = actions.map((action) => actionHtml(action, row, `${action.label} ${title}`.trim())).join('');
  return `<div class="celerik-grid-action-list">${buttons}</div>`;
}

// A row's checkbox (Grid::selectable()), as Grid::renderCheckbox() writes it.
export function rowCheckboxHtml(selection, row, rowTitle) {
  const attribute = (name, value) => (value === null || value === undefined ? '' : ` ${name}="${escapeHtml(value)}"`);
  const title = rowTitle ? fill(`{${rowTitle}}`, row) : '';
  return `<input type="checkbox" class="form-check-input"${attribute('name', `${selection.name}[]`)}`
    + `${attribute('value', fill(`{${selection.key}}`, row))}${attribute('form', selection.form)}`
    + `${attribute('aria-label', `${selection.labels.select} ${title}`.trim())} data-celerik-select>`;
}

let counter = 0;

function create(tag, attributes = {}, text = null) {
  const element = document.createElement(tag);
  for (const [name, value] of Object.entries(attributes)) {
    if (value !== null && value !== undefined) {
      element.setAttribute(name, value);
    }
  }
  if (text !== null) {
    element.textContent = text;
  }
  return element;
}

// A modal alert dialog: the question as its title, the detail as its description, Cancel
// (focused first, so Enter does not confirm by accident) and the action's own label.
// Resolves with true when confirmed.
export function confirmAction({ question, detail = null, confirmLabel, cancelLabel = LABELS.cancel, danger = false }) {
  counter += 1;
  const id = `celerik-action-dialog-${counter}`;
  const dialog = create('dialog', {
    class: 'celerik-action-dialog',
    role: 'alertdialog',
    'aria-labelledby': `${id}-title`,
    'aria-describedby': detail ? `${id}-detail` : null,
  });
  dialog.append(create('h2', { id: `${id}-title`, class: 'h5 mb-3' }, question));
  if (detail) {
    dialog.append(create('p', { id: `${id}-detail` }, detail));
  }
  const buttons = create('div', { class: 'd-flex flex-wrap gap-2 justify-content-end' });
  const cancel = create('button', { type: 'button', class: 'btn btn-secondary' }, cancelLabel);
  const confirm = create('button', { type: 'button', class: `btn ${danger ? 'btn-danger' : 'btn-primary'}` }, confirmLabel);
  buttons.append(cancel, confirm);
  dialog.append(buttons);

  const opener = document.activeElement;
  document.body.append(dialog);

  return new Promise((resolve) => {
    let result = false;
    const close = (value) => {
      result = value;
      if (typeof dialog.close === 'function' && dialog.open) {
        dialog.close();
      } else {
        dialog.dispatchEvent(new Event('close'));
      }
    };
    dialog.addEventListener('close', () => {
      dialog.remove();
      if (opener?.isConnected) {
        opener.focus();
      }
      resolve(result);
    }, { once: true });
    cancel.addEventListener('click', () => close(false));
    confirm.addEventListener('click', () => close(true));

    if (typeof dialog.showModal === 'function') {
      dialog.showModal();
    } else {
      dialog.setAttribute('open', ''); // environments without <dialog> support (jsdom)
    }
    cancel.focus();
  });
}

function labelsOf(form) {
  try {
    return { ...LABELS, ...JSON.parse(form.dataset.labels ?? '{}') };
  } catch {
    return LABELS;
  }
}

async function readMessage(response) {
  if (!(response.headers.get('Content-Type') ?? '').includes('json')) {
    return { data: null, message: null };
  }
  try {
    const data = await response.json();
    return { data, message: typeof data?.message === 'string' ? data.message : null };
  } catch {
    return { data: null, message: null };
  }
}

const pending = new WeakSet();

// Runs a request action's form: confirmation, request, message, `celerik:action` event.
// A grid's bulk form (Grid::bulkActions()) has a submit button per action: `submitter` is
// the one pressed, and carries the action's URL, method, confirmation and labels. The form's
// `celerikSelection()` (set by the grid) gives the selected rows, sent as `name[]`, and
// `{count}` in the confirmation is their number.
// options: { submitter, fetch, notify, confirm } (the last three for tests and custom UIs).
// Resolves with the event's detail, or null when cancelled or already running.
export async function submitAction(form, options = {}) {
  const submitter = options.submitter?.dataset?.celerikAction ? options.submitter : null;
  const source = submitter ?? form;
  if (pending.has(form) || submitter?.disabled) {
    return null;
  }
  const labels = labelsOf(source);
  const button = submitter ?? form.querySelector('button[type=submit], button:not([type])');
  const selection = typeof form.celerikSelection === 'function' ? form.celerikSelection() : null;
  const ask = options.confirm ?? confirmAction;
  if (source.dataset.confirm) {
    const count = String(selection?.length ?? 0);
    const confirmed = await ask({
      question: source.dataset.confirm.replaceAll('{count}', count),
      detail: source.dataset.confirmDetail?.replaceAll('{count}', count) ?? null,
      confirmLabel: button?.textContent.trim() || 'OK',
      cancelLabel: labels.cancel,
      danger: Boolean(button?.className.includes('danger')),
    });
    if (!confirmed) {
      return null;
    }
  }

  const notify = options.notify ?? (async (type, message) => (await import('./notification.js')).notify(type, message));
  const method = source.dataset.method || 'POST';
  const headers = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
  const header = source.dataset.csrfHeader;
  const token = [...form.querySelectorAll('input[type=hidden]')].find((input) => input.name !== '_method');
  if (header && token) {
    headers[header] = token.value;
  }
  const body = new FormData(form);
  if (submitter?.name) {
    body.append(submitter.name, submitter.value);
  }
  if (selection && form.dataset.celerikBulk) {
    const field = `${form.dataset.celerikBulk}[]`;
    body.delete(field);
    selection.forEach((key) => body.append(field, key));
  }
  const url = submitter?.hasAttribute('formaction')
    ? new URL(submitter.getAttribute('formaction'), document.baseURI).href
    : form.action;
  const name = source.dataset.celerikAction;

  pending.add(form);
  button?.setAttribute('aria-disabled', 'true');
  let detail;
  try {
    const response = await (options.fetch ?? fetch)(url, {
      method, headers, body, credentials: 'same-origin',
    });
    const { data, message } = await readMessage(response);
    detail = { name, method, ok: response.ok, status: response.status, data };
    if (response.ok) {
      if (message) {
        await notify('success', message);
      }
    } else {
      await notify('error', message ?? labels.failed);
    }
  } catch (error) {
    detail = { name, method, ok: false, status: 0, data: null, error };
    await notify('error', labels.failed);
  } finally {
    pending.delete(form);
    button?.removeAttribute('aria-disabled');
  }
  form.dispatchEvent(new CustomEvent('celerik:action', { bubbles: true, detail }));
  return detail;
}
