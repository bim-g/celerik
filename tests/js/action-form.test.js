import * as bootstrap from 'bootstrap';
import { actionHtml } from '../../resources/js/action.js';
import { fillValues, openActionForm, showErrors } from '../../resources/js/action-form.js';
import { initForm } from '../../resources/js/form.js';
import { initGrid } from '../../resources/js/grid.js';
import { readOptions } from '../../resources/js/core.js';
import { loadFixture } from './helpers.js';

// Waits for a condition rather than a fixed time: Bootstrap's modal finishes its
// transitions later on a busy machine.
async function until(check, timeout = 3000) {
  const end = Date.now() + timeout;
  while (!check()) {
    if (Date.now() > end) {
      throw new Error(`Timed out waiting for ${check}`);
    }
    await new Promise((resolve) => { setTimeout(resolve, 10); });
  }
}

function answer(status, body) {
  return {
    ok: status >= 200 && status < 300,
    status,
    headers: { get: () => 'application/json' },
    json: async () => body,
  };
}

beforeAll(() => { window.bootstrap = bootstrap; });
afterAll(() => { delete window.bootstrap; });

describe('form helpers', () => {
  test('fillValues puts row values in text, select, checkbox and date fields', () => {
    document.body.innerHTML = `<form id="f">
      <input name="name"><select name="role"><option value="a">A</option><option value="b">B</option></select>
      <input type="checkbox" name="active" value="1"><input type="date" name="joined">
      <select name="tags[]" multiple><option value="x">X</option><option value="y">Y</option></select>
      <input type="hidden" name="_token" value="keep"></form>`;
    const form = document.getElementById('f');
    fillValues(form, {
      name: 'Ada', role: 'b', active: true, joined: '2024-03-12 10:00:00', tags: ['y'], _token: 'no',
    });

    expect(form.elements.name.value).toBe('Ada');
    expect(form.elements.role.value).toBe('b');
    expect(form.elements.active.checked).toBe(true);
    expect(form.elements.joined.value).toBe('2024-03-12');
    expect([...form.elements['tags[]'].selectedOptions].map((option) => option.value)).toEqual(['y']);
    expect(form.elements._token.value).toBe('keep');
  });

  test('showErrors puts each error on its field and the rest in an alert', () => {
    document.body.innerHTML = `<form id="f"><div class="mb-3"><input id="f-name" name="name"></div>
      <div class="mb-3"><input id="f-line" name="lines[0][product]"></div></form>`;
    const form = document.getElementById('f');
    showErrors(form, { name: ['Taken.'], 'lines.0.product': 'Unknown product.', other: ['Not a field.'] });

    const name = form.elements.name;
    expect(name.getAttribute('aria-invalid')).toBe('true');
    expect(document.getElementById(name.getAttribute('aria-describedby')).textContent).toBe('Taken.');
    expect(document.getElementById(form.elements['lines[0][product]'].getAttribute('aria-describedby')).textContent)
      .toBe('Unknown product.');
    expect(form.querySelector('[role=alert]').textContent).toBe('Not a field.');
    expect(document.activeElement).toBe(name);
  });

  test('a server-side grid\'s row gets the same action markup as PHP writes', () => {
    const html = actionHtml({
      name: 'edit', label: 'Edit', url: '/people/{id}', request: true, method: 'PATCH', className: 'btn',
      form: { modal: 'person-form-modal', title: 'Edit {name}', fields: ['name', 'role', 'missing'] },
    }, { id: 3, name: 'Grace "G"', role: 'admin' }, 'Edit Grace');
    const holder = document.createElement('div');
    holder.innerHTML = html;
    const form = holder.querySelector('form');

    expect(form.dataset.caelrikFormModal).toBe('person-form-modal');
    expect(form.dataset.title).toBe('Edit Grace "G"');
    expect(JSON.parse(form.dataset.values)).toEqual({ name: 'Grace "G"', role: 'admin' });
    expect(form.querySelector('button').getAttribute('aria-haspopup')).toBe('dialog');
  });
});

describe('an action that opens a form', () => {
  let table;
  let grid;
  let modal;
  let form;

  beforeEach(async () => {
    table = loadFixture('action-form');
    grid = initGrid(table, readOptions(table));
    modal = document.getElementById('person-form-modal');
    form = document.getElementById('person-form');
    await initForm(form, readOptions(form));
  });

  afterEach(() => {
    bootstrap.Modal.getInstance(modal)?.dispose();
    grid.destroy();
  });

  const trigger = (name) => table.querySelector(`form[data-caelrik-form-modal] button[aria-label="Edit ${name}"]`)
    ?.closest('form');
  const submit = () => form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
  const open = async (name, deps) => {
    const shown = new Promise((resolve) => { modal.addEventListener('shown.bs.modal', resolve, { once: true }); });
    openActionForm(trigger(name), deps);
    await shown;
  };

  test('PHP renders the modal once, after the table', () => {
    expect(document.querySelectorAll('#person-form-modal')).toHaveLength(1);
    expect(modal.querySelector('.modal-title').textContent).toBe('Edit');
    expect(modal.querySelector('[data-caelrik-form-submit]').getAttribute('form')).toBe('person-form');
    expect(form.querySelector('button[type=submit]')).toBeNull();
  });

  test('opens filled with the row\'s values and titled for the row', async () => {
    await open('Alan', { fetch: jest.fn() });

    expect(modal.classList.contains('show')).toBe(true);
    expect(modal.querySelector('.modal-title').textContent).toBe('Edit Alan');
    expect(form.elements.name.value).toBe('Alan');
    expect(form.elements.role.value).toBe('user');
    expect(form.elements.active.checked).toBe(false);
  });

  test('an invalid form is not sent', async () => {
    const fetch = jest.fn();
    await open('Ada', { fetch });
    form.elements.name.value = '';
    submit();
    await until(() => form.elements.name.getAttribute('aria-invalid') === 'true');

    expect(fetch).not.toHaveBeenCalled();
    expect(form.elements.name.getAttribute('aria-invalid')).toBe('true');
  });

  test('saved: the request, the closed modal, focus on the button, the message and the event', async () => {
    const fetch = jest.fn().mockResolvedValue(answer(200, { message: 'Ada was updated.', data: { name: 'Ada King' } }));
    const notify = jest.fn();
    const events = [];
    table.addEventListener('caelrik:action', (event) => events.push(event.detail));
    const button = trigger('Ada').querySelector('button');
    button.focus();

    await open('Ada', { fetch, notify });
    form.elements.name.value = 'Ada King';
    submit();
    await until(() => events.length > 0);

    const [url, request] = fetch.mock.calls[0];
    expect(url).toBe(new URL('/people/1', document.baseURI).href);
    expect(request.method).toBe('POST');
    expect(request.headers['X-CSRF-TOKEN']).toBe('t0k3n');
    expect(Object.fromEntries(request.body.entries())).toMatchObject({
      name: 'Ada King', role: 'admin', active: '1', _method: 'PATCH', _token: 't0k3n',
    });
    expect(modal.classList.contains('show')).toBe(false);
    expect(document.activeElement).toBe(button);
    expect(notify).toHaveBeenCalledWith('success', 'Ada was updated.');
    expect(events).toEqual([expect.objectContaining({ name: 'edit', method: 'PATCH', ok: true })]);
    // The client-side row shows the saved value, and the form opens with it next time.
    expect(table.tBodies[0].rows[0].cells[0].textContent).toBe('Ada King');
    expect(JSON.parse(trigger('Ada').dataset.values).name).toBe('Ada King');
  });

  test('refused: the errors are on the fields, and the modal stays open', async () => {
    const fetch = jest.fn().mockResolvedValue(answer(422, { message: 'Invalid.', errors: { name: ['This name is taken.'] } }));
    await open('Ada', { fetch });
    form.elements.name.value = 'Alan';
    submit();
    await until(() => form.elements.name.getAttribute('aria-invalid') === 'true');

    expect(modal.classList.contains('show')).toBe(true);
    const name = form.elements.name;
    expect(document.activeElement).toBe(name);
    expect(name.getAttribute('aria-invalid')).toBe('true');
    expect(document.getElementById(name.getAttribute('aria-describedby').split(' ').pop()).textContent)
      .toBe('This name is taken.');
  });

  test('a failed request says so in the form; opening again starts clean', async () => {
    const fetch = jest.fn().mockRejectedValue(new Error('offline'));
    await open('Ada', { fetch });
    form.elements.name.value = 'Ada L.';
    submit();
    await until(() => form.querySelector('[role=alert]'));
    expect(form.querySelector('[role=alert]').textContent).toBe('The action failed. Try again.');

    const hidden = new Promise((resolve) => { modal.addEventListener('hidden.bs.modal', resolve, { once: true }); });
    bootstrap.Modal.getInstance(modal).hide();
    await hidden;
    await open('Alan', { fetch });
    expect(form.querySelector('[role=alert]')).toBeNull();
    expect(form.elements.name.value).toBe('Alan');
  });
});
