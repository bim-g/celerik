import { readOptions } from '../../resources/js/core.js';
import { readSaved, storageKey, toggleableColumns } from '../../resources/js/grid-columns.js';
import { initGrid } from '../../resources/js/grid.js';
import { loadFixture } from './helpers.js';

function setup() {
  const table = loadFixture('grid-columns');
  const grid = initGrid(table, readOptions(table));
  const menu = document.querySelector('.caelrik-grid-columns');
  const button = menu.querySelector('button');
  const panel = menu.querySelector('fieldset');
  const box = (label) => [...panel.querySelectorAll('input')].find((input) => input.labels[0].textContent === label);
  const toggle = (label, checked) => {
    const input = box(label);
    input.checked = checked;
    input.dispatchEvent(new Event('change', { bubbles: true }));
  };
  const headers = () => [...table.querySelectorAll('thead th')].map((th) => th.textContent);
  return { table, grid, menu, button, panel, box, toggle, headers };
}

describe('columns menu', () => {
  beforeEach(() => window.localStorage.clear());

  test('lists the columns the user may toggle, as labelled checkboxes in a named group', () => {
    const { grid, menu, button, panel, box } = setup();

    expect(menu.closest('.dt-container')).not.toBeNull();
    expect(button.textContent).toBe('Columns');
    expect(button.getAttribute('aria-controls')).toBe(panel.id);
    expect(panel.querySelector('legend').textContent).toBe('Show columns');
    expect([...panel.querySelectorAll('label')].map((label) => label.textContent)).toEqual(['Name', 'E-mail', 'Phone']);
    expect([box('Name').checked, box('E-mail').checked, box('Phone').checked]).toEqual([true, true, false]);
    expect(toggleableColumns([{ field: 'a', toggleable: false }, { field: 'b' }])).toEqual([{ field: 'b' }]);
    grid.destroy();
  });

  test('opens and closes as a disclosure; Escape returns focus to the button', () => {
    const { grid, button, panel, box } = setup();

    button.click();
    expect([panel.hidden, button.getAttribute('aria-expanded')]).toEqual([false, 'true']);
    box('Name').focus();
    box('Name').dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
    expect([panel.hidden, button.getAttribute('aria-expanded')]).toEqual([true, 'false']);
    expect(document.activeElement).toBe(button);

    button.click();
    document.body.click();
    expect(panel.hidden).toBe(true);
    grid.destroy();
  });

  test('shows and hides columns, keeps the last one, and remembers the choice', () => {
    const first = setup();
    first.toggle('Phone', true);
    first.toggle('E-mail', false);
    expect(first.headers()).toEqual(['Name', 'Phone']);
    expect(readSaved('contacts')).toEqual({ name: true, email: false, phone: true });

    first.toggle('Phone', false);
    expect(first.box('Name').disabled).toBe(true);
    first.grid.destroy();

    // The next visit starts with the saved choice.
    const second = setup();
    expect(second.headers()).toEqual(['Name']);
    expect(second.box('E-mail').checked).toBe(false);
    second.grid.destroy();
  });

  test('a broken saved value is ignored', () => {
    window.localStorage.setItem(storageKey('contacts'), '[not json');
    expect(readSaved('contacts')).toEqual({});
    const { grid, headers } = setup();
    expect(headers()).toEqual(['Name', 'E-mail']);
    grid.destroy();
  });
});
