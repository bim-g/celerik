import { buildGridConfig } from '../../resources/js/grid-config.js';
import {
  checkValue, editableCellHtml, editText, editValue,
} from '../../resources/js/grid-edit.js';
import { initGrid } from '../../resources/js/grid.js';
import { readOptions } from '../../resources/js/core.js';
import { loadFixture } from './helpers.js';

const LABELS = {
  edit: 'Edit {column} of {row}',
  editField: '{column} of {row}',
  saved: '{column} of {row} saved.',
  editFailed: 'The change was not saved. Try again.',
  required: 'Enter a value.',
  invalid: 'Enter a valid value.',
  tooLong: 'Use {max} characters at most.',
  tooLow: 'Enter {min} or more.',
  tooHigh: 'Enter {max} or less.',
  yes: 'Yes',
  no: 'No',
};

const flush = () => new Promise((resolve) => { setTimeout(resolve, 0); });

function answer(status, body) {
  return {
    ok: status >= 200 && status < 300,
    status,
    headers: { get: () => 'application/json' },
    json: async () => body,
  };
}

describe('inline editing helpers', () => {
  test('checkValue applies the editor\'s rules, as Editor::parse() does', () => {
    expect(checkValue({ type: 'text', required: true }, '', LABELS)).toBe('Enter a value.');
    expect(checkValue({ type: 'text' }, '', LABELS)).toBeNull();
    expect(checkValue({ type: 'text', maxLength: 3 }, 'abcd', LABELS)).toBe('Use 3 characters at most.');
    expect(checkValue({ type: 'text', maxLength: 3 }, '🙂🙂🙂', LABELS)).toBeNull();
    expect(checkValue({ type: 'number', min: 0 }, '-1', LABELS)).toBe('Enter 0 or more.');
    expect(checkValue({ type: 'number', max: 10 }, '11', LABELS)).toBe('Enter 10 or less.');
    expect(checkValue({ type: 'number' }, '1e', LABELS)).toBe('Enter a valid value.');
    expect(checkValue({ type: 'select', options: [['a', 'A']] }, 'b', LABELS)).toBe('Enter a valid value.');
    expect(checkValue({ type: 'boolean', required: true }, '1', LABELS)).toBeNull();
    expect(checkValue({ type: 'date' }, '2026-02-30x', LABELS)).toBe('Enter a valid value.');
    expect(checkValue({ type: 'date' }, '2026-02-03', LABELS)).toBeNull();
  });

  test('values and texts are written as PHP writes them', () => {
    const select = { type: 'select', options: [['1', 'One'], ['2', 'Two']] };
    expect(editValue({ type: 'boolean' }, true)).toBe('1');
    expect(editValue({ type: 'date' }, '2026-01-02 10:00:00')).toBe('2026-01-02');
    expect(editValue({ type: 'text' }, null)).toBe('');
    expect(editText(select, 2, LABELS)).toBe('Two');
    expect(editText({ type: 'boolean' }, false, LABELS)).toBe('No');
  });

  test('a server-side grid\'s editable cells get the same escaped markup as PHP\'s', () => {
    const column = { field: 'name', title: 'Name', editor: { type: 'text' } };
    const html = editableCellHtml(column, { name: '<b>Ada</b>' }, { labels: LABELS }, 'name');
    expect(html).toContain('<span class="celerik-grid-value">&lt;b&gt;Ada&lt;/b&gt;</span>');
    expect(html).toContain('aria-label="Edit Name of &lt;b&gt;Ada&lt;/b&gt;"');

    const config = buildGridConfig({ columns: [column], url: '/data', edit: { labels: LABELS }, rowTitle: 'name' });
    expect(config.columns[0].render('Ada', 'display', { name: 'Ada' })).toContain('data-celerik-edit="name"');
    expect(config.columns[0].render('Ada', 'sort', { name: 'Ada' })).toBe('Ada');
  });
});

describe('grid inline editing', () => {
  let table;
  let grid;

  beforeEach(() => {
    table = loadFixture('grid-edit');
    grid = initGrid(table, readOptions(table));
    global.fetch = jest.fn();
  });

  afterEach(() => {
    grid.destroy();
    delete global.fetch;
  });

  const button = (name) => table.querySelector(`button[aria-label="${name}"]`);
  const field = () => table.querySelector('.celerik-grid-editor input, .celerik-grid-editor select');
  const key = (target, name) => target.dispatchEvent(new KeyboardEvent('keydown', { key: name, bubbles: true }));

  test('PHP writes each editable cell with its value and a named edit button', () => {
    const cell = button('Edit Role of Grace <3').closest('td');
    expect(cell.dataset.value).toBe('dir');
    expect(cell.textContent).toBe('Director');
    expect(button('Edit Remote of Ada').closest('td').textContent).toBe('Yes');
  });

  test('the edit button opens a labelled field; Escape cancels and returns focus', () => {
    button('Edit Name of Ada').click();
    expect(field().getAttribute('aria-label')).toBe('Name of Ada');
    expect(field().value).toBe('Ada');
    expect(document.activeElement).toBe(field());

    field().value = 'Ada Lovelace';
    key(field(), 'Escape');

    expect(field()).toBeNull();
    expect(button('Edit Name of Ada').closest('td').textContent).toBe('Ada');
    expect(document.activeElement).toBe(button('Edit Name of Ada'));
    expect(fetch).not.toHaveBeenCalled();
  });

  test('a value the editor refuses is not sent: its message is linked to the field', () => {
    button('Edit Salary of Ada').click();
    field().value = '-5';
    key(field(), 'Enter');

    expect(fetch).not.toHaveBeenCalled();
    expect(field().getAttribute('aria-invalid')).toBe('true');
    expect(document.getElementById(field().getAttribute('aria-describedby')).textContent).toBe('Enter 0 or more.');
  });

  test('Enter saves: the request, the new cell, the totals, the focus and the event', async () => {
    fetch.mockResolvedValue(answer(200, { value: 150 }));
    const events = [];
    table.addEventListener('celerik:edit', (event) => events.push(event.detail));

    button('Edit Salary of Ada').click();
    field().value = '150';
    key(field(), 'Enter');
    await flush();

    const [url, request] = fetch.mock.calls[0];
    expect(url).toBe(new URL('/members/1', document.baseURI).href);
    expect(request.method).toBe('POST');
    expect(request.headers['X-CSRF-TOKEN']).toBe('t0k3n');
    expect(Object.fromEntries(request.body.entries())).toEqual({
      field: 'salary', value: '150', _method: 'PATCH', _token: 't0k3n',
    });

    const cell = button('Edit Salary of Ada').closest('td');
    expect(cell.textContent).toBe('150');
    expect(cell.dataset.value).toBe('150');
    expect(document.activeElement).toBe(button('Edit Salary of Ada'));
    expect(table.tFoot.textContent.replace(/\s+/g, ' ').trim()).toBe('Total 350');
    expect(events).toEqual([expect.objectContaining({ field: 'salary', value: 150, ok: true })]);
  });

  test('a choice shows its label once saved, and the search finds the new value', async () => {
    fetch.mockResolvedValue(answer(200, {}));
    button('Edit Role of Ada').click();
    expect(field().tagName).toBe('SELECT');
    field().value = 'dir';
    key(field(), 'Enter');
    await flush();

    expect(button('Edit Role of Ada').closest('td').textContent).toBe('Director');
    grid.search('Director').draw();
    expect(table.tBodies[0].rows).toHaveLength(2);
  });

  test('a value the server refuses keeps the field open with its message', async () => {
    fetch.mockResolvedValue(answer(422, { message: 'Invalid.', errors: { name: ['This name is taken.'] } }));
    button('Edit Name of Ada').click();
    field().value = 'Grace';
    key(field(), 'Enter');
    await flush();

    expect(field().value).toBe('Grace');
    expect(field().getAttribute('aria-invalid')).toBe('true');
    expect(table.querySelector('.invalid-feedback').textContent).toBe('This name is taken.');
    expect(document.activeElement).toBe(field());
  });

  test('a failed request says so in the field', async () => {
    fetch.mockRejectedValue(new Error('offline'));
    button('Edit Joined of Ada').click();
    expect(field().type).toBe('date');
    field().value = '2025-01-01';
    key(field(), 'Enter');
    await flush();

    expect(table.querySelector('.invalid-feedback').textContent).toBe('The change was not saved. Try again.');
  });

  test('an unchanged value closes the field without a request', () => {
    button('Edit Remote of Grace <3').click();
    expect(field().value).toBe('0');
    key(field(), 'Enter');

    expect(fetch).not.toHaveBeenCalled();
    expect(field()).toBeNull();
  });
});
