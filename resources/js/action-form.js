// Actions that open a form in a modal (Action::form()). The action's button opens the modal,
// with the form reset and filled with the row's values (data-values) and the title written
// for the row. The form checks its fields (Parsley, as any Caelrik form), then Save sends
// them to the action's URL: a form-encoded POST with `_method` and the action's CSRF token.
// - A 2xx answer closes the modal; focus goes back to the button, the answer's `message` is
//   shown, and `caelrik:action` fires on the action (a grid reloads its rows).
// - Otherwise `errors` ({"field": ["..."]}) are shown on their fields, as PHP shows server
//   errors, and anything else in an alert at the top of the form.
// Elements are built with DOM methods and textContent only (CSP).
import $ from 'jquery';
import 'parsleyjs';
import { bootstrapComponent } from './bootstrap-global.js';
import { LABELS } from './action.js';

// The open action of each modal form.
const contexts = new WeakMap();

// `lines.0.product` (Laravel's error keys) is the field `lines[0][product]`.
function names(name) {
  const bracketed = name.includes('.')
    ? name.split('.').map((part, index) => (index === 0 ? part : `[${part}]`)).join('')
    : name;
  return [name, `${name}[]`, bracketed, `${bracketed}[]`];
}

function controlsNamed(form, name) {
  const wanted = names(name);
  return [...form.elements].filter((control) => wanted.includes(control.name) && control.type !== 'hidden');
}

// A value as an input of this type takes it: a day for a date, `T` in a date-time.
function inputValue(control, value) {
  if (value === null || value === undefined) {
    return '';
  }
  if (typeof value === 'boolean') {
    return value ? '1' : '0';
  }
  const text = typeof value === 'object' ? JSON.stringify(value) : String(value);
  if (control.type === 'date') {
    return text.slice(0, 10);
  }
  if (control.type === 'datetime-local') {
    return text.replace(' ', 'T').slice(0, 16);
  }
  if (control.type === 'time') {
    return text.slice(0, 5);
  }
  return text;
}

// Puts the row's values in the form's fields. Fields the row has no value for keep the
// form's own.
export function fillValues(form, values) {
  for (const [name, value] of Object.entries(values ?? {})) {
    const list = Array.isArray(value) ? value.map(String) : null;
    for (const control of controlsNamed(form, name)) {
      if (control.type === 'file') {
        continue;
      }
      if (control.type === 'checkbox') {
        control.checked = list ? list.includes(control.value) : [true, 1, '1', 'true', 'on', control.value].includes(value);
      } else if (control.type === 'radio') {
        control.checked = String(value) === control.value;
      } else if (control.multiple) {
        [...control.options].forEach((option) => {
          option.selected = (list ?? [String(value)]).includes(option.value);
        });
      } else {
        control.value = inputValue(control, value);
      }
      control.tomselect?.setValue(list ?? inputValue(control, value), true);
    }
  }
}

export function clearErrors(form) {
  form.querySelectorAll('.caelrik-server-error, .caelrik-action-form-error').forEach((node) => node.remove());
  for (const control of form.querySelectorAll('[aria-invalid]')) {
    control.removeAttribute('aria-invalid');
    control.classList.remove('is-invalid');
  }
  for (const control of form.querySelectorAll('[aria-describedby]')) {
    const ids = control.getAttribute('aria-describedby').split(/\s+/).filter((id) => !id.endsWith('-action-error'));
    if (ids.length > 0) {
      control.setAttribute('aria-describedby', ids.join(' '));
    } else {
      control.removeAttribute('aria-describedby');
    }
  }
  $(form).parsley?.().reset?.();
}

// The answer's errors on their fields, the rest in an alert; focus goes to the first.
export function showErrors(form, errors, message) {
  clearErrors(form);
  const general = [];
  let first = null;
  for (const [name, list] of Object.entries(errors ?? {})) {
    const text = String(Array.isArray(list) ? list[0] : list);
    const control = controlsNamed(form, name)[0];
    if (!control) {
      general.push(text);
      continue;
    }
    const feedback = document.createElement('div');
    feedback.id = `${control.id || `${form.id}-${control.name}`}-action-error`;
    feedback.className = 'invalid-feedback d-block caelrik-server-error';
    feedback.textContent = text;
    (control.closest('.mb-3') ?? control.parentElement).append(feedback);
    control.setAttribute('aria-invalid', 'true');
    control.classList.add('is-invalid');
    const described = control.getAttribute('aria-describedby');
    control.setAttribute('aria-describedby', described ? `${described} ${feedback.id}` : feedback.id);
    first ??= control.tomselect?.control_input ?? control;
  }
  if (general.length === 0 && !first && message) {
    general.push(message);
  }
  let alert = null;
  if (general.length > 0) {
    alert = document.createElement('div');
    alert.className = 'alert alert-danger caelrik-action-form-error';
    alert.setAttribute('role', 'alert');
    alert.tabIndex = -1;
    alert.textContent = general.join(' ');
    form.prepend(alert);
  }
  (first ?? alert)?.focus();
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

async function send(form, modalElement, modal, context) {
  if (context.sending) {
    return;
  }
  const { trigger } = context;
  const method = trigger.dataset.method || 'POST';
  const body = new FormData(form);
  body.delete('_method');
  if (method !== 'POST') {
    body.set('_method', method);
  }
  const headers = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
  const token = [...trigger.querySelectorAll('input[type=hidden]')].find((input) => input.name !== '_method');
  if (token) {
    body.set(token.name, token.value);
    if (trigger.dataset.csrfHeader) {
      headers[trigger.dataset.csrfHeader] = token.value;
    }
  }

  const submit = modalElement.querySelector('[data-caelrik-form-submit]');
  context.sending = true;
  submit?.setAttribute('aria-disabled', 'true');
  form.setAttribute('aria-busy', 'true');
  const name = trigger.dataset.caelrikAction;
  try {
    const response = await (context.deps.fetch ?? fetch)(trigger.action, {
      method: 'POST', headers, body, credentials: 'same-origin',
    });
    const data = await readAnswer(response);
    context.detail = { name, method, ok: response.ok, status: response.status, data };
    if (response.ok) {
      modal.hide();
    } else {
      const message = typeof data?.message === 'string' && data.message !== '' ? data.message : context.labels.failed;
      showErrors(form, data?.errors, message);
    }
  } catch (error) {
    context.detail = { name, method, ok: false, status: 0, data: null, error };
    showErrors(form, null, context.labels.failed);
  } finally {
    context.sending = false;
    submit?.removeAttribute('aria-disabled');
    form.removeAttribute('aria-busy');
  }
}

function connect(form, modalElement, modal) {
  if (form.caelrikActionForm) {
    return;
  }
  form.caelrikActionForm = true;
  // After the form's own checks passed: send it here, not as a page submit.
  $(form).parsley().on('form:submit', () => {
    const context = contexts.get(form);
    if (!context) {
      return true;
    }
    send(form, modalElement, modal, context);
    return false;
  });
  // Closed (saved or not): focus goes back to the action's button; once saved, its message
  // is shown and the action's event fires.
  modalElement.addEventListener('hidden.bs.modal', () => {
    const context = contexts.get(form);
    contexts.delete(form);
    if (!context) {
      return;
    }
    if (context.button?.isConnected) {
      context.button.focus();
    }
    const { detail } = context;
    if (detail?.ok) {
      const message = detail.data?.message;
      if (typeof message === 'string' && message !== '') {
        context.deps.notify('success', message);
      }
      context.trigger.dispatchEvent(new CustomEvent('caelrik:action', { bubbles: true, detail }));
    }
  });
}

// Opens the action's form for its row. deps: { fetch, notify } for tests and custom UIs.
export function openActionForm(trigger, deps = {}) {
  const modalElement = document.getElementById(trigger.dataset.caelrikFormModal ?? '');
  const form = modalElement?.querySelector('form');
  const Modal = bootstrapComponent('Modal');
  if (!form || !Modal || contexts.has(form)) {
    return null;
  }
  const modal = Modal.getOrCreateInstance(modalElement);
  const notify = deps.notify ?? (async (type, message) => (await import('./notification.js')).notify(type, message));
  let labels = LABELS;
  try {
    labels = { ...LABELS, ...JSON.parse(trigger.dataset.labels ?? '{}') };
  } catch {
    // Keep the defaults.
  }

  form.reset();
  clearErrors(form);
  try {
    fillValues(form, JSON.parse(trigger.dataset.values ?? '{}'));
  } catch {
    // An action without values opens the form as it is.
  }
  const title = modalElement.querySelector('.modal-title');
  if (title && trigger.dataset.title) {
    title.textContent = trigger.dataset.title;
  }

  const button = trigger.querySelector('button[type=submit], button:not([type])');
  const context = {
    trigger, button, labels, detail: null, sending: false, deps: { fetch: deps.fetch, notify },
  };
  contexts.set(form, context);
  connect(form, modalElement, modal);
  // Focus starts in the form's first field, not on the dialog itself.
  modalElement.addEventListener('shown.bs.modal', () => {
    form.querySelector('input:not([type=hidden]), select, textarea')?.focus();
  }, { once: true });
  modal.show(button);
  return context;
}
