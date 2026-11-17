import {
  buildGridConfig, filterParams, matchesFilters, readFilters, toDataTablesResponse,
} from '../../resources/js/grid-config.js';
import { initGrid } from '../../resources/js/grid.js';
import { readOptions } from '../../resources/js/core.js';
import { loadFixture } from './helpers.js';

describe('grid config', () => {
  test('maps PHP columns to DataTables columns', () => {
    const config = buildGridConfig({
      columns: [{ field: 'name', title: 'Name', sortable: false, searchable: true, visible: true }],
      pageSize: 25,
    });

    expect(config.columns[0]).toMatchObject({ data: 'name', title: 'Name', orderable: false, searchable: true });
    expect(config.pageLength).toBe(25);
    expect(config.serverSide).toBeUndefined();
  });

  test('enables server-side mode and converts the Celerik envelope', () => {
    const config = buildGridConfig({ columns: [], url: '/users/data' });
    const raw = JSON.stringify({ data: [{ name: 'Ada' }], meta: { draw: 4, total: 10, filtered: 1 } });

    expect(config.serverSide).toBe(true);
    expect(JSON.parse(config.ajax.dataFilter(raw))).toEqual({
      draw: 4,
      recordsTotal: 10,
      recordsFiltered: 1,
      data: [{ name: 'Ada' }],
    });
  });

  test('raw options from PHP override generated ones', () => {
    expect(buildGridConfig({ columns: [], options: { pageLength: 5, order: [[0, 'desc']] } }))
      .toMatchObject({ pageLength: 5, order: [[0, 'desc']] });
  });

  test('toDataTablesResponse tolerates a missing meta block', () => {
    expect(toDataTablesResponse({ data: [1, 2] })).toEqual({ draw: 0, recordsTotal: 2, recordsFiltered: 2, data: [1, 2] });
  });
});

describe('grid integration (DataTables)', () => {
  test('enhances the PHP-rendered table with paging, search and export buttons', () => {
    const table = loadFixture('grid');
    const grid = initGrid(table, readOptions(table));

    expect(grid.rows().count()).toBe(3);
    expect(table.querySelectorAll('tbody tr')).toHaveLength(2);
    expect(document.querySelector('.dt-search input')).not.toBeNull();
    expect([...document.querySelectorAll('.dt-buttons button')].map((b) => b.textContent.trim()))
      .toEqual(['Copy', 'CSV']);

    grid.search('hopper').draw();
    expect(table.querySelectorAll('tbody tr')).toHaveLength(1);
    expect(table.querySelector('tbody td:nth-child(2)').textContent).toBe('Grace Hopper');

    grid.destroy();
  });
});

describe('grid filters', () => {
  const columns = [
    { field: 'name', filter: { type: 'text' } },
    { field: 'role', filter: { type: 'select' } },
    { field: 'joined', filter: { type: 'dateRange' } },
    { field: 'city' },
  ];

  test('readFilters keeps the filled filters, typed by their column', () => {
    document.body.innerHTML = `<div id="bar">
      <input data-celerik-filter="name" value="  ada ">
      <select data-celerik-filter="role"><option value="" selected>Any</option></select>
      <input data-celerik-filter="joined" data-celerik-filter-end="from" value="">
      <input data-celerik-filter="joined" data-celerik-filter-end="to" value="2026-01-31">
    </div>`;

    expect(readFilters(document.getElementById('bar'), columns)).toEqual([
      { field: 'name', type: 'text', value: 'ada' },
      { field: 'joined', type: 'dateRange', from: '', to: '2026-01-31' },
    ]);
    expect(readFilters(null, columns)).toEqual([]);
  });

  test('filterParams writes them as respond() reads them', () => {
    expect(filterParams([
      { field: 'name', type: 'text', value: 'ada' },
      { field: 'joined', type: 'dateRange', from: '', to: '2026-01-31' },
    ])).toEqual({ name: 'ada', joined: { to: '2026-01-31' } });
  });

  test('matchesFilters: text contains, ignoring case; exact values; date ranges with both days included', () => {
    const row = { name: 'Ada Lovelace', role: 'admin', joined: '2026-01-31 18:30:00', empty: '' };
    const read = (field) => row[field];

    expect(matchesFilters([{ field: 'name', type: 'text', value: 'LOVE' }], read)).toBe(true);
    expect(matchesFilters([{ field: 'role', type: 'select', value: 'adm' }], read)).toBe(false);
    expect(matchesFilters([{ field: 'joined', type: 'dateRange', from: '2026-01-01', to: '2026-01-31' }], read)).toBe(true);
    expect(matchesFilters([{ field: 'joined', type: 'dateRange', from: '2026-02-01', to: '' }], read)).toBe(false);
    expect(matchesFilters([{ field: 'empty', type: 'dateRange', from: '', to: '2026-12-31' }], read)).toBe(false);
  });

  test('server-side requests carry the filters; searching stays on without the search box', () => {
    const config = buildGridConfig(
      { columns, url: '/people', searchable: false },
      { filters: () => [{ field: 'role', type: 'select', value: 'admin' }] },
    );
    const data = { draw: 1 };
    config.ajax.data(data);

    expect(data.filter).toEqual({ role: 'admin' });
    expect(config.searching).toBe(true);
    expect(config.layout.topEnd).toBeNull();
    expect(buildGridConfig({ columns: [{ field: 'a' }], searchable: false }).searching).toBe(false);
  });

  test('the filter bar filters client-side rows and clears them', () => {
    jest.useFakeTimers();
    const table = loadFixture('grid-filters');
    const bar = document.getElementById('people-filters');
    const grid = initGrid(table, readOptions(table));
    const names = () => [...table.querySelectorAll('tbody tr td:first-child')].map((cell) => cell.textContent);
    const change = (id, value) => {
      const control = document.getElementById(id);
      control.value = value;
      control.dispatchEvent(new Event('change', { bubbles: true }));
    };

    expect(bar.hidden).toBe(false);
    expect(document.querySelector('.dt-search')).toBeNull();

    change('people-filter-role', 'admin');
    expect(names()).toEqual(['Ada Lovelace', 'Grace Hopper']);

    change('people-filter-joined-from', '2026-01-31');
    expect(names()).toEqual(['Ada Lovelace']);

    change('people-filter-role', '');
    change('people-filter-joined-from', '');
    change('people-filter-active', '0');
    expect(names()).toEqual(['Alan Turing']);

    change('people-filter-active', '');
    const name = document.getElementById('people-filter-name');
    name.value = 'TOR';
    name.dispatchEvent(new Event('input', { bubbles: true }));
    expect(names()).toHaveLength(4);
    jest.advanceTimersByTime(300);
    expect(names()).toEqual(['Linus Torvalds']);

    bar.querySelector('[data-celerik-filter-clear]').click();
    expect(name.value).toBe('');
    expect(names()).toHaveLength(4);

    grid.destroy();
    jest.useRealTimers();
  });
});
