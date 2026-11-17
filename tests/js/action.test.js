import {
  actionHtml, confirmAction, fill, rowActionsHtml, submitAction,
} from '../../resources/js/action.js';
import { readOptions } from '../../resources/js/core.js';
import { buildGridConfig } from '../../resources/js/grid-config.js';
import { initGrid } from '../../resources/js/grid.js';
import { loadFixture } from './helpers.js';

function response(status, body = null) {
  return {
    ok: status >= 200 && status < 300,
    status,
    headers: { get: () => (body === null ? 'text/html' : 'application/json') },
    json: async () => body,
  };
}

function form(html) {
  document.body.innerHTML = html;
  return document.querySelector('form');
}

describe('action templates', () => {
  test('fill takes row values, nested ones too, URL-encoded when asked', () => {
    const row = { id: 7, name: 'Ada & co', active: true, team: { slug: 'a/b c' } };

    expect(fill('/people/{id}/{team.slug}', row, true)).toBe('/people/7/a%2Fb%20c');
    expect(fill('Delete {name}? ({active}, {missing})', row)).toBe('Delete Ada & co? (Yes, )');
  });

  test('rows a server-side grid draws get the same markup as PHP', () => {
    const table = loadFixture('grid-actions');
    const options = readOptions(table);
    const row = { id: 1, name: 'Ada <Lovelace> & "co"', team: { slug: 'a/b c' } };

    // Parsed the same way: the DOM writes attributes in its own style.
    const parsed = document.createElement('div');
    parsed.innerHTML = rowActionsHtml(options.actions, row, options.rowTitle);
    expect(parsed.innerHTML).toBe(table.querySelector('tbody td.celerik-grid-actions').innerHTML);
  });

  test('a link keeps a row value from becoming a script URL', () => {
    const html = actionHtml({ name: 'open', label: 'Open', url: '{url}', className: 'btn' }, { url: 'javascript:alert(1)' });

    expect(html).toContain('href="javascript%3Aalert(1)"');
  });
});

describe('confirmation dialog', () => {
  test('an alert dialog titled by the question, with Cancel focused first; focus returns', async () => {
    document.body.innerHTML = '<button id="opener">Delete</button>';
    document.getElementById('opener').focus();

    const answer = confirmAction({ question: 'Delete Ada?', detail: 'This cannot be undone.', confirmLabel: 'Delete', danger: true });
    const dialog = document.querySelector('dialog.celerik-action-dialog');

    expect(dialog.getAttribute('role')).toBe('alertdialog');
    expect(document.getElementById(dialog.getAttribute('aria-labelledby')).textContent).toBe('Delete Ada?');
    expect(document.getElementById(dialog.getAttribute('aria-describedby')).textContent).toBe('This cannot be undone.');
    expect(document.activeElement.textContent).toBe('Cancel');

    const confirm = [...dialog.querySelectorAll('button')].find((button) => button.textContent === 'Delete');
    expect(confirm.className).toBe('btn btn-danger');
    confirm.click();

    await expect(answer).resolves.toBe(true);
    expect(document.querySelector('dialog')).toBeNull();
    expect(document.activeElement.id).toBe('opener');
  });

  test('Cancel resolves false', async () => {
    const answer = confirmAction({ question: 'Archive?', confirmLabel: 'Archive', cancelLabel: 'Keep' });
    const dialog = document.querySelector('dialog');
    expect(dialog.hasAttribute('aria-describedby')).toBe(false);

    [...dialog.querySelectorAll('button')].find((button) => button.textContent === 'Keep').click();
    await expect(answer).resolves.toBe(false);
  });
});

describe('submitAction', () => {
  const deleteForm = `<form method="post" action="/people/7" class="celerik-action" data-celerik-action="delete"
      data-method="DELETE" data-confirm="Delete Ada?" data-csrf-header="X-CSRF-TOKEN" data-labels='{"cancel":"Keep"}'>
      <input type="hidden" name="_method" value="DELETE"><input type="hidden" name="_token" value="t0ken">
      <button type="submit" class="btn btn-outline-danger">Delete</button></form>`;

  test('asks first: nothing is sent when the user cancels', async () => {
    const fetch = jest.fn();
    const confirm = jest.fn(async () => false);

    expect(await submitAction(form(deleteForm), { fetch, confirm })).toBeNull();
    expect(confirm).toHaveBeenCalledWith({
      question: 'Delete Ada?', detail: null, confirmLabel: 'Delete', cancelLabel: 'Keep', danger: true,
    });
    expect(fetch).not.toHaveBeenCalled();
  });

  test('sends the request with its method and token, shows the message and fires celerik:action', async () => {
    const fetch = jest.fn(async () => response(200, { message: 'Ada was deleted.' }));
    const notify = jest.fn();
    const element = form(deleteForm);
    const events = [];
    document.addEventListener('celerik:action', (event) => events.push(event.detail), { once: true });

    const detail = await submitAction(element, { fetch, notify, confirm: async () => true });

    const [url, init] = fetch.mock.calls[0];
    expect(url).toBe('http://localhost/people/7');
    expect(init.method).toBe('DELETE');
    expect(init.headers).toMatchObject({ 'X-CSRF-TOKEN': 't0ken', Accept: 'application/json' });
    expect(init.body.get('_token')).toBe('t0ken');
    expect(notify).toHaveBeenCalledWith('success', 'Ada was deleted.');
    expect(detail).toMatchObject({ name: 'delete', method: 'DELETE', ok: true, status: 200 });
    expect(events).toEqual([detail]);
    expect(element.querySelector('button').hasAttribute('aria-disabled')).toBe(false);
  });

  test('an error shows the answer\'s message, or the failed label', async () => {
    const notify = jest.fn();
    const post = form('<form action="/x" class="celerik-action" data-celerik-action="archive" data-method="POST"><button>Archive</button></form>');

    await submitAction(post, { fetch: async () => response(422, { message: 'Already archived.' }), notify });
    await submitAction(post, { fetch: async () => response(500), notify });
    const detail = await submitAction(post, { fetch: async () => { throw new TypeError('offline'); }, notify });

    expect(notify.mock.calls).toEqual([
      ['error', 'Already archived.'],
      ['error', 'The action failed. Try again.'],
      ['error', 'The action failed. Try again.'],
    ]);
    expect(detail).toMatchObject({ ok: false, status: 0 });
  });

  test('a second submit while the first runs is ignored', async () => {
    let finish;
    const fetch = jest.fn(() => new Promise((resolve) => { finish = resolve; }));
    const post = form('<form action="/x" class="celerik-action" data-celerik-action="archive"><button>Archive</button></form>');

    const first = submitAction(post, { fetch, notify: jest.fn() });
    expect(await submitAction(post, { fetch, notify: jest.fn() })).toBeNull();
    expect(post.querySelector('button').getAttribute('aria-disabled')).toBe('true');
    finish(response(204));
    await first;
    expect(fetch).toHaveBeenCalledTimes(1);
  });
});

describe('grid row actions', () => {
  test('a deleted row leaves a client-side grid, and focus moves to the next row\'s button', async () => {
    const table = loadFixture('grid-actions');
    const grid = initGrid(table, readOptions(table));
    const deleteButton = (index) => table.querySelectorAll('tbody tr')[index].querySelector('[data-celerik-action="delete"] button');

    expect(table.querySelector('thead th.celerik-grid-actions').textContent).toBe('Actions');
    deleteButton(0).focus();
    await submitAction(deleteButton(0).form, {
      fetch: async () => response(200, {}), notify: jest.fn(), confirm: async () => true,
    });

    expect(grid.rows().count()).toBe(2);
    expect(document.activeElement).toBe(deleteButton(0));
    expect(document.activeElement.getAttribute('aria-label')).toBe('Delete Alan Turing');
    grid.destroy();
  });

  test('exports leave the actions column out', () => {
    const table = loadFixture('grid-actions');
    const { columns } = readOptions(table);
    const config = buildGridConfig({ columns, actions: [{ name: 'a', label: 'A', url: '/', className: 'btn' }], export: ['csv'] });

    expect(config.layout.topStart[1].buttons).toEqual([{ extend: 'csv', exportOptions: { columns: ':not(.celerik-grid-actions):not(.celerik-grid-select)' } }]);
    expect(config.columns.at(-1)).toMatchObject({ data: 'celerikActions', orderable: false, searchable: false, className: 'celerik-grid-actions' });
  });
});

