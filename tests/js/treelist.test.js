import { initTreeList } from '../../resources/js/treelist.js';
import { readOptions } from '../../resources/js/core.js';
import { loadFixture } from './helpers.js';

const row = (table, name) => [...table.tBodies[0].rows]
  .find((tr) => tr.querySelector('.celerik-treelist-text').textContent === name);
const shown = (table) => [...table.tBodies[0].rows].filter((tr) => !tr.hidden)
  .map((tr) => tr.querySelector('.celerik-treelist-text').textContent);
const press = (key) => document.activeElement.dispatchEvent(new KeyboardEvent('keydown', { key, bubbles: true }));
const focusedName = () => document.activeElement.closest('tr')?.querySelector('.celerik-treelist-text').textContent;

describe('tree list', () => {
  let table;

  beforeEach(() => {
    table = loadFixture('treelist');
  });

  test('PHP writes every row in tree order, indented, readable without JavaScript', () => {
    expect([...table.tBodies[0].rows].map((tr) => tr.querySelector('.celerik-treelist-text').textContent))
      .toEqual(['Design', 'Sketches <v1>', 'Logo', 'Review', 'Build', 'API', 'Launch']);
    expect(row(table, 'Logo').cells[0].classList.contains('celerik-treelist-level-3')).toBe(true);
    expect(row(table, 'Logo').hidden).toBe(false);
  });

  test('becomes a treegrid: levels, positions, open rows, one tab stop', () => {
    initTreeList(table, readOptions(table));

    expect(table.getAttribute('role')).toBe('treegrid');
    const design = row(table, 'Design');
    expect(design.getAttribute('aria-level')).toBe('1');
    expect(design.getAttribute('aria-setsize')).toBe('3');
    expect(design.getAttribute('aria-expanded')).toBe('true');
    expect(row(table, 'Sketches <v1>').getAttribute('aria-expanded')).toBe('false');
    expect(row(table, 'Review').getAttribute('aria-posinset')).toBe('2');
    expect(row(table, 'Launch').hasAttribute('aria-expanded')).toBe(false);
    expect(shown(table)).toEqual(['Design', 'Sketches <v1>', 'Review', 'Build', 'Launch']);
    expect(table.querySelectorAll('[tabindex="0"]')).toHaveLength(1);
    expect(design.tabIndex).toBe(0);
    expect(table.querySelector('.celerik-treelist-actions button').tabIndex).toBe(-1);
  });

  test('rows: arrows move, open and close; Left goes to the parent', () => {
    initTreeList(table, readOptions(table));
    row(table, 'Design').focus();
    press('ArrowDown');
    expect(focusedName()).toBe('Sketches <v1>');
    press('ArrowRight');
    expect(shown(table)).toContain('Logo');
    press('ArrowDown');
    expect(focusedName()).toBe('Logo');
    press('ArrowLeft');
    expect(focusedName()).toBe('Sketches <v1>');
    press('ArrowLeft');
    expect(row(table, 'Sketches <v1>').getAttribute('aria-expanded')).toBe('false');
    press('ArrowLeft');
    expect(focusedName()).toBe('Design');
    press('ArrowLeft');
    expect(shown(table)).toEqual(['Design', 'Build', 'Launch']);
    press('End');
    expect(focusedName()).toBe('Launch');
    press('Home');
    expect(focusedName()).toBe('Design');
    press('Enter');
    expect(row(table, 'Design').getAttribute('aria-expanded')).toBe('true');
  });

  test('cells: Right enters the row, arrows move along it and down its column', () => {
    initTreeList(table, readOptions(table));
    row(table, 'Launch').focus();
    press('ArrowRight');
    expect(document.activeElement).toBe(row(table, 'Launch').cells[0]);
    press('ArrowRight');
    expect(document.activeElement.textContent).toBe('Ada');
    press('ArrowUp');
    expect(document.activeElement.textContent).toBe('Grace');
    press('End');
    expect(document.activeElement.classList.contains('celerik-treelist-actions')).toBe(true);
    const clicked = jest.fn((event) => event.preventDefault());
    document.activeElement.querySelector('button').addEventListener('click', clicked);
    press('Enter');
    expect(clicked).toHaveBeenCalled();
    press('Home');
    press('ArrowLeft');
    expect(document.activeElement).toBe(row(table, 'Build'));
    expect(table.querySelectorAll('[tabindex="0"]')).toHaveLength(1);
  });

  test('Expand all and Collapse all', () => {
    initTreeList(table, readOptions(table));
    const [expand, collapse] = table.previousElementSibling.querySelectorAll('button');
    expect(expand.textContent).toBe('Expand all');
    expand.click();
    expect(shown(table)).toHaveLength(7);
    collapse.click();
    expect(shown(table)).toEqual(['Design', 'Build', 'Launch']);
  });

  test('a chevron click opens a row', () => {
    initTreeList(table, readOptions(table));
    row(table, 'Build').querySelector('.celerik-treelist-toggle').click();
    expect(shown(table)).toContain('API');
    expect(document.activeElement).toBe(row(table, 'Build'));
  });

  test('a deleted row goes with its descendants; its siblings are numbered again', () => {
    initTreeList(table, readOptions(table));
    const sketches = row(table, 'Sketches <v1>');
    sketches.querySelector('form').dispatchEvent(new CustomEvent('celerik:action', {
      bubbles: true, detail: { ok: true, method: 'DELETE' },
    }));

    expect(row(table, 'Logo')).toBeUndefined();
    expect(row(table, 'Review').getAttribute('aria-posinset')).toBe('1');
    expect(row(table, 'Review').getAttribute('aria-setsize')).toBe('1');
    expect(focusedName()).toBe('Review');

    row(table, 'Review').querySelector('form').dispatchEvent(new CustomEvent('celerik:action', {
      bubbles: true, detail: { ok: true, method: 'DELETE' },
    }));
    expect(row(table, 'Design').hasAttribute('aria-expanded')).toBe(false);
    expect(focusedName()).toBe('Build');
  });
});
