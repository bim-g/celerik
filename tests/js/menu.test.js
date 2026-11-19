import {
  initContextMenu, initMenuButton, initNavigation, initToolbar,
} from '../../resources/js/menu.js';
import { readOptions } from '../../resources/js/core.js';
import { loadFixture } from './helpers.js';

const press = (key, init = {}) => document.activeElement.dispatchEvent(new KeyboardEvent('keydown', { key, bubbles: true, ...init }));
const focusedText = () => document.activeElement.textContent.trim();

describe('menu button', () => {
  let toolbar;
  let button;
  let menu;

  beforeEach(() => {
    toolbar = loadFixture('menus');
    initMenuButton(document.getElementById('export'));
    initMenuButton(document.getElementById('save'));
    button = document.getElementById('export-button');
    menu = document.getElementById('export-menu');
  });

  test('without JavaScript the items are a plain list; with it, an ARIA menu behind its button', () => {
    expect(button.getAttribute('aria-haspopup')).toBe('menu');
    expect(button.getAttribute('aria-expanded')).toBe('false');
    expect(button.getAttribute('aria-controls')).toBe('export-menu');
    expect(menu.getAttribute('role')).toBe('menu');
    expect(menu.getAttribute('aria-labelledby')).toBe('export-button');
    expect(menu.hidden).toBe(true);
    const roles = [...menu.children].map((li) => li.getAttribute('role'));
    expect(roles).toEqual(['none', 'none', 'none', 'separator', 'none']);
    expect([...menu.querySelectorAll('[role=menuitem]')].map((item) => item.textContent))
      .toEqual(['CSV', 'Excel', 'PDF (soon)', 'E-mail me the list']);
    expect(menu.querySelector('[aria-disabled=true]').textContent).toBe('PDF (soon)');
  });

  test('Down opens on the first item; arrows, Home, End and letters move; Escape returns focus', () => {
    button.focus();
    press('ArrowDown');
    expect(menu.hidden).toBe(false);
    expect(button.getAttribute('aria-expanded')).toBe('true');
    expect(focusedText()).toBe('CSV');
    press('ArrowDown');
    expect(focusedText()).toBe('Excel');
    press('End');
    expect(focusedText()).toBe('E-mail me the list');
    press('ArrowDown');
    expect(focusedText()).toBe('CSV');
    press('e');
    expect(focusedText()).toBe('Excel');
    press('e');
    expect(focusedText()).toBe('E-mail me the list');
    press('Escape');
    expect(menu.hidden).toBe(true);
    expect(document.activeElement).toBe(button);

    press('ArrowUp');
    expect(focusedText()).toBe('E-mail me the list');
    press('Tab');
    expect(menu.hidden).toBe(true);
  });

  test('choosing an action closes the menu and gives focus back before submitting', () => {
    const submitted = [];
    document.addEventListener('submit', (event) => {
      event.preventDefault();
      submitted.push([event.target.getAttribute('action'), document.activeElement]);
    }, { once: true });
    button.focus();
    press('ArrowUp');
    press('Enter');
    expect(menu.hidden).toBe(true);
    expect(submitted).toEqual([['/members/email', button]]);
  });

  test('a disabled item does nothing; a click outside closes the menu', () => {
    button.click();
    const disabled = menu.querySelector('[aria-disabled=true]');
    disabled.click();
    expect(menu.hidden).toBe(false);
    document.body.dispatchEvent(new Event('pointerdown', { bubbles: true }));
    expect(menu.hidden).toBe(true);
  });

  test('the split button: a main action and a "More options" menu', () => {
    const more = document.getElementById('save-button');
    expect(more.getAttribute('aria-label')).toBe('More options');
    expect(document.querySelector('#save form.celerik-action button').textContent).toBe('Save');
    more.focus();
    press('Enter');
    expect(focusedText()).toBe('Preview');
  });

  test('the toolbar: one tab stop, arrows between its controls', () => {
    initToolbar(toolbar);
    expect(toolbar.getAttribute('role')).toBe('toolbar');
    expect(toolbar.getAttribute('aria-label')).toBe('Members');
    const stops = [...toolbar.querySelectorAll('[tabindex="0"]')];
    expect(stops).toHaveLength(1);
    expect(stops[0].textContent).toBe('New member');
    stops[0].focus();
    press('ArrowRight');
    expect(document.activeElement).toBe(button);
    press('End');
    expect(document.activeElement.getAttribute('aria-label')).toBe('More options');
    press('ArrowRight');
    expect(focusedText()).toBe('New member');
    expect(toolbar.querySelectorAll('[tabindex="0"]')).toHaveLength(1);
  });
});

describe('context menu', () => {
  let list;

  beforeEach(() => {
    const element = loadFixture('contextmenu');
    document.querySelector('main').insertAdjacentHTML('beforeend', '<ul id="files">'
      + '<li data-id="7" data-name="Report &lt;Q3&gt;.pdf" tabindex="0">Report</li>'
      + '<li data-id="8" data-name="Notes.txt" tabindex="0">Notes</li></ul>');
    list = initContextMenu(element, readOptions(element)).element;
  });

  test('a right click on a target opens its menu, written with the target\'s values', () => {
    const target = document.querySelector('[data-id="7"]');
    const event = new MouseEvent('contextmenu', { bubbles: true, cancelable: true, clientX: 40, clientY: 50 });
    target.dispatchEvent(event);

    expect(event.defaultPrevented).toBe(true);
    expect(list.hidden).toBe(false);
    expect(list.getAttribute('role')).toBe('menu');
    expect(list.getAttribute('aria-label')).toBe('File actions');
    const link = list.querySelector('a[role=menuitem]');
    expect(link.textContent).toBe('Open Report <Q3>.pdf');
    expect(link.getAttribute('href')).toBe('/files/7');
    expect(list.querySelector('form').getAttribute('action')).toBe('/files/7');
    expect(list.querySelector('form').dataset.confirm).toBe('Delete Report <Q3>.pdf?');
    expect(document.activeElement).toBe(link);

    press('Escape');
    expect(list.hidden).toBe(true);
    expect(document.activeElement).toBe(target);
  });

  test('Shift+F10 on a focused target opens it from the keyboard; elsewhere, nothing', () => {
    document.querySelector('[data-id="8"]').focus();
    press('F10', { shiftKey: true });
    expect(list.hidden).toBe(false);
    expect(list.querySelector('a').getAttribute('href')).toBe('/files/8');

    press('Escape');
    const outside = document.createElement('button');
    document.body.append(outside);
    outside.focus();
    press('ContextMenu');
    expect(list.hidden).toBe(true);
  });
});

test('a horizontal navigation: one group open at a time, Escape closes it', () => {
  const nav = loadFixture('nav-horizontal');
  initNavigation(nav, readOptions(nav));
  const [products, company] = nav.querySelectorAll('details');
  expect(products.open).toBe(false);
  expect(nav.querySelector('[aria-current=page]').textContent).toBe('Grid');

  products.open = true;
  products.dispatchEvent(new Event('toggle'));
  company.open = true;
  company.dispatchEvent(new Event('toggle'));
  expect(products.open).toBe(false);

  const link = company.querySelector('a');
  link.focus();
  press('Escape');
  expect(company.open).toBe(false);
  expect(document.activeElement).toBe(company.querySelector('summary'));
});
