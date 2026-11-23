import { rowCheckboxHtml, submitAction } from '../../resources/js/action.js';
import { readOptions } from '../../resources/js/core.js';
import { buildGridConfig } from '../../resources/js/grid-config.js';
import { initGrid } from '../../resources/js/grid.js';
import { loadFixture } from './helpers.js';

const ok = { ok: true, status: 200, headers: { get: () => 'application/json' }, json: async () => ({}) };

function setup() {
  const table = loadFixture('grid-selection');
  const options = readOptions(table);
  const grid = initGrid(table, options);
  const bulk = document.getElementById('crew-bulk');
  const all = table.querySelector('[data-caelrik-select-all]');
  const box = (name) => [...table.querySelectorAll('input[data-caelrik-select]')]
    .find((input) => input.getAttribute('aria-label') === `Select ${name}`);
  const check = (input, checked = true) => {
    input.checked = checked;
    input.dispatchEvent(new Event('change', { bubbles: true }));
  };
  const status = () => bulk.querySelector('[data-caelrik-selected]').textContent;
  const buttons = () => [...bulk.querySelectorAll('button')].map((button) => button.disabled);
  return { table, options, grid, bulk, all, box, check, status, buttons };
}

describe('row selection', () => {
  test('server-side rows get the same checkbox as PHP', () => {
    const { table, options, grid } = setup();
    const parsed = document.createElement('div');
    parsed.innerHTML = rowCheckboxHtml(options.selection, { id: 1, name: 'Ada' }, options.rowTitle);

    expect(parsed.innerHTML).toBe(table.querySelector('tbody td.caelrik-grid-select').innerHTML);
    grid.destroy();
  });

  test('the page box shows all, some or none; the bar counts the selection and enables its buttons', () => {
    const { grid, all, box, check, status, buttons } = setup();

    expect(status()).toBe('No rows selected');
    expect(buttons()).toEqual([true, true]);

    check(box('Ada'));
    expect([all.checked, all.indeterminate]).toEqual([false, true]);
    expect(status()).toBe('1 selected');
    expect(buttons()).toEqual([false, false]);

    check(all);
    expect([all.checked, all.indeterminate]).toEqual([true, false]);
    expect(status()).toBe('2 selected');

    check(all, false);
    expect(status()).toBe('No rows selected');
    grid.destroy();
  });

  test('the selection is kept across pages and sent with a bulk action, {count} in its question', async () => {
    const { grid, bulk, all, box, check, status } = setup();
    check(box('Ada'));
    grid.page(1).draw(false);
    expect(all.checked).toBe(false);
    check(box('Grace'));
    expect(status()).toBe('2 selected');
    grid.page(0).draw(false);
    expect(box('Ada').checked).toBe(true);

    const fetch = jest.fn(async () => ok);
    const confirm = jest.fn(async () => true);
    const remove = bulk.querySelector('[data-caelrik-action="remove"]');
    await submitAction(bulk, { submitter: remove, fetch, confirm, notify: jest.fn() });

    expect(confirm.mock.calls[0][0]).toMatchObject({ question: 'Remove 2 people?', confirmLabel: 'Remove selected', danger: true });
    const [url, init] = fetch.mock.calls[0];
    expect(url).toBe('http://localhost/crew/remove');
    expect(init.method).toBe('DELETE');
    expect(init.headers['X-CSRF-TOKEN']).toBe('t0ken');
    expect(init.body.getAll('ids[]')).toEqual(['1', '3']);
    expect(init.body.get('_method')).toBe('DELETE');

    // The deleted rows leave the grid, the selection is cleared and focus is on the page box.
    expect(grid.rows().count()).toBe(1);
    expect(status()).toBe('No rows selected');
    expect(document.activeElement).toBe(all);
    grid.destroy();
  });

  test('a disabled bulk button sends nothing', async () => {
    const { grid, bulk } = setup();
    const fetch = jest.fn();
    expect(await submitAction(bulk, { submitter: bulk.querySelector('[data-caelrik-action="mail"]'), fetch })).toBeNull();
    expect(fetch).not.toHaveBeenCalled();
    grid.destroy();
  });

  test('the selection column comes first, unsorted and left out of exports', () => {
    const config = buildGridConfig({
      columns: [{ field: 'id' }], selection: { key: 'id', name: 'ids', labels: {} }, export: ['csv'],
    });

    expect(config.columns[0]).toMatchObject({ orderable: false, searchable: false, className: 'caelrik-grid-select' });
    expect(config.order).toEqual([[1, 'asc']]);
    expect(config.layout.topStart[1].buttons[0].exportOptions.columns).toBe(':not(.caelrik-grid-actions):not(.caelrik-grid-select)');
  });
});
