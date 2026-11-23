import { buildGridConfig } from '../../resources/js/grid-config.js';
import {
  aggregate, formatTotal, groupKey,
} from '../../resources/js/grid-summary.js';
import { initGrid } from '../../resources/js/grid.js';
import { readOptions } from '../../resources/js/core.js';
import { loadFixture } from './helpers.js';

const text = (node) => node.textContent.replace(/\s+/g, ' ').trim();
const footer = (table) => [...table.tFoot.rows[0].cells].map(text);
const headings = (table) => [...table.tBodies[0].querySelectorAll('tr.caelrik-grid-group')]
  .map((row) => [...row.cells].map(text));

describe('totals', () => {
  test('aggregate leaves empty values out and only totals numbers', () => {
    const values = ['10', 2.5, '', null, 'n/a', true];
    expect(aggregate('sum', values)).toBe(13.5);
    expect(aggregate('count', values)).toBe(4);
    expect(aggregate('avg', ['1', '2'])).toBe(1.5);
    expect(aggregate('min', ['3', '-1'])).toBe(-1);
    expect(aggregate('max', ['3', '-1'])).toBe(3);
    expect(aggregate('avg', [])).toBeNull();
    expect(aggregate('sum', [])).toBe(0);
  });

  test('formatTotal writes totals as PHP does', () => {
    const labels = { decimal: ',', thousands: ' ' };
    expect(formatTotal(1234567, 'sum')).toBe('1,234,567');
    expect(formatTotal(1234.5, 'sum')).toBe('1,234.50');
    expect(formatTotal(3, 'avg')).toBe('3.00');
    expect(formatTotal(12, 'count', null, labels)).toBe('12');
    expect(formatTotal(-1234.567, 'sum', 1, labels)).toBe('-1 234,6');
    expect(formatTotal(-0.001, 'sum', 2)).toBe('0.00');
    expect(formatTotal(null, 'avg')).toBe('');
  });

  test('groupKey matches values as Aggregate::groupKey() does', () => {
    expect(groupKey(3)).toBe(groupKey('3'));
    expect(groupKey(true)).toBe(groupKey(1));
    expect(groupKey(null)).not.toBe(groupKey(''));
  });

  test('a grouped grid is sorted by its group first, past the selection column', () => {
    const config = buildGridConfig({
      columns: [{ field: 'id' }, { field: 'team' }],
      groupBy: 'team',
      selection: { key: 'id', name: 'ids', labels: {} },
    });
    expect(config.orderFixed).toEqual({ pre: [[2, 'asc']] });
  });
});

describe('grid totals and groups', () => {
  let table;
  let grid;

  beforeEach(() => {
    table = loadFixture('grid-totals');
  });

  afterEach(() => {
    grid?.destroy();
    grid = null;
  });

  test('PHP writes the totals of all rows, and the rows in group order', () => {
    expect(footer(table)).toEqual(['', '', 'Total 4,950.50', 'Average 3.00']);
    expect([...table.tBodies[0].rows].map((row) => row.cells[0].textContent))
      .toEqual(['Design', 'Design', 'Sales', 'Sales', 'Support']);
  });

  test('each group of the page gets a heading with its count and totals over the whole group', () => {
    grid = initGrid(table, readOptions(table));

    expect(headings(table)).toEqual([
      ['Design 2 rows', 'Total 2,800', 'Average 3.00'],
      ['Sales 2 rows', 'Total 2,000.50', 'Average 4.00'],
    ]);
    const heading = table.querySelector('tr.caelrik-grid-group th');
    expect(heading.getAttribute('scope')).toBe('rowgroup');
    expect(heading.colSpan).toBe(2);

    grid.page(1).draw(false);
    expect(headings(table)).toEqual([['Sales 2 rows', 'Total 2,000.50', 'Average 4.00'], ['Support 1 row', 'Total 150', 'Average 1.00']]);
  });

  test('totals and groups follow the search', () => {
    grid = initGrid(table, readOptions(table));
    grid.search('Fonts').draw();

    expect(footer(table)[2]).toBe('Total 300');
    expect(headings(table)).toEqual([['Design 1 row', 'Total 300', 'Average 2.00']]);
  });

  test('sorting keeps the groups together', () => {
    grid = initGrid(table, readOptions(table));
    grid.page.len(10).order([2, 'desc']).draw();

    expect([...table.tBodies[0].querySelectorAll('tr:not(.caelrik-grid-group)')].map((row) => row.cells[1].textContent))
      .toEqual(['Laptop', 'Fonts', 'Flights', 'Hotel', 'Headsets']);
  });

  test('a heading spans the columns shown before the first total', () => {
    grid = initGrid(table, readOptions(table));
    grid.column(1).visible(false);

    const heading = table.querySelector('tr.caelrik-grid-group');
    expect(heading.cells[0].colSpan).toBe(1);
    expect(heading.cells).toHaveLength(3);
  });
});
