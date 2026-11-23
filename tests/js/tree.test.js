import { createNode, initTree, safeUrl } from '../../resources/js/tree.js';
import { readOptions } from '../../resources/js/core.js';
import { loadFixture } from './helpers.js';

const flush = () => new Promise((resolve) => { setTimeout(resolve, 0); });
const item = (element, name) => [...element.querySelectorAll('[role=treeitem]')]
  .find((node) => node.querySelector('.caelrik-tree-label').textContent.trim() === name);
const press = (key) => document.activeElement.dispatchEvent(new KeyboardEvent('keydown', { key, bubbles: true }));
const focused = () => document.activeElement.querySelector('.caelrik-tree-label')?.textContent.trim();
const visibleNames = (element) => [...element.querySelectorAll('[role=treeitem]')]
  .filter((node) => !node.closest('ul[hidden]'))
  .map((node) => node.querySelector('.caelrik-tree-label').textContent.trim());

describe('tree helpers', () => {
  test('safeUrl keeps relative and http(s) links only', () => {
    expect(safeUrl('/a')).toBe('/a');
    expect(safeUrl('https://example.com')).toBe('https://example.com');
    expect(safeUrl('javascript:alert(1)')).toBeNull();
    expect(safeUrl(' java\nscript:x')).toBeNull();
  });

  test('createNode writes a loaded node with text only', () => {
    const node = createNode({ id: 9, label: '<b>x</b>', href: 'javascript:alert(1)' }, {});
    expect(node.querySelector('.caelrik-tree-label').tagName).toBe('SPAN');
    expect(node.textContent).toBe('<b>x</b>');
    expect(node.querySelector('b')).toBeNull();
  });
});

describe('a navigation tree', () => {
  let element;

  beforeEach(() => {
    element = loadFixture('treeview-links');
    initTree(element, readOptions(element));
  });

  test('becomes a tree with levels, positions, and one tab stop', () => {
    const tree = element.querySelector('[role=tree]');
    expect(tree.getAttribute('aria-label')).toBe('Site');
    const guide = item(element, 'Guide');
    expect(guide.getAttribute('aria-level')).toBe('1');
    expect(guide.getAttribute('aria-posinset')).toBe('1');
    expect(guide.getAttribute('aria-setsize')).toBe('2');
    expect(guide.getAttribute('aria-expanded')).toBe('true');
    expect(item(element, 'Install <now>').getAttribute('aria-level')).toBe('2');
    expect(item(element, 'Components').getAttribute('aria-expanded')).toBe('false');
    expect(element.querySelectorAll('[tabindex="0"]')).toHaveLength(1);
    expect(guide.tabIndex).toBe(0);
    // The row is the treeitem, named by its own text, and owns its children's group.
    expect(guide.matches('.caelrik-tree-row')).toBe(true);
    expect(guide.parentElement.getAttribute('role')).toBe('none');
    const group = document.getElementById(guide.getAttribute('aria-owns'));
    expect(group.getAttribute('role')).toBe('group');
    expect(group.contains(item(element, 'Install <now>'))).toBe(true);
    // An unsafe link is not a link.
    expect(item(element, 'Bad').querySelector('a')).toBeNull();
  });

  test('arrow keys move, open and close; Home, End and letters jump', () => {
    item(element, 'Guide').focus();
    press('ArrowDown');
    expect(focused()).toBe('Install <now>');
    press('ArrowDown');
    press('ArrowDown');
    expect(focused()).toBe('Components');
    press('ArrowRight');
    expect(item(element, 'Components').getAttribute('aria-expanded')).toBe('true');
    press('ArrowRight');
    expect(focused()).toBe('Grid');
    press('ArrowLeft');
    expect(focused()).toBe('Components');
    press('ArrowLeft');
    expect(item(element, 'Components').getAttribute('aria-expanded')).toBe('false');
    expect(visibleNames(element)).toEqual(['Guide', 'Install <now>', 'Bad', 'Components']);
    press('Home');
    expect(focused()).toBe('Guide');
    press('End');
    expect(focused()).toBe('Components');
    press('i');
    expect(focused()).toBe('Install <now>');
    expect(document.activeElement.tabIndex).toBe(0);
    expect(element.querySelectorAll('[tabindex="0"]')).toHaveLength(1);
  });

  test('Enter follows a link; a click on the chevron opens a node', () => {
    const link = item(element, 'Install <now>').querySelector('a');
    const follow = jest.fn((event) => event.preventDefault());
    link.addEventListener('click', follow);
    item(element, 'Install <now>').focus();
    press('Enter');
    expect(follow).toHaveBeenCalled();

    item(element, 'Components').querySelector('.caelrik-tree-toggle').click();
    expect(item(element, 'Components').getAttribute('aria-expanded')).toBe('true');
    expect(document.activeElement).toBe(item(element, 'Components'));
  });
});

describe('a checkable tree', () => {
  let element;
  let fetch;

  beforeEach(() => {
    element = loadFixture('treeview');
    fetch = jest.fn();
    initTree(element, readOptions(element), { fetch });
  });

  const checked = () => [...element.querySelectorAll('input:checked')].map((input) => input.value);

  test('parents follow their children: checked, mixed, or not', () => {
    expect(item(element, 'Invoices').getAttribute('aria-checked')).toBe('true');
    expect(item(element, 'Contracts').getAttribute('aria-checked')).toBe('false');
    expect(item(element, 'Documents').getAttribute('aria-checked')).toBe('mixed');
    expect(item(element, 'Documents').querySelector('input').indeterminate).toBe(true);
    expect(item(element, 'Archive').getAttribute('aria-disabled')).toBe('true');
  });

  test('Space checks a node and its children, and the form values follow', () => {
    const events = [];
    element.addEventListener('caelrik:change', (event) => events.push(event.detail.value));
    item(element, 'Contracts').focus();
    press(' ');

    expect(item(element, 'Signed').getAttribute('aria-checked')).toBe('true');
    expect(item(element, 'Documents').getAttribute('aria-checked')).toBe('true');
    expect(checked()).toEqual(['1', '2', '3', '4', '5']);
    expect(events.pop()).toEqual(['1', '2', '3', '4', '5']);

    item(element, 'Signed').focus();
    press(' ');
    expect(item(element, 'Contracts').getAttribute('aria-checked')).toBe('mixed');
    expect(item(element, 'Documents').getAttribute('aria-checked')).toBe('mixed');
    expect(checked()).toEqual(['2', '5']);
  });

  test('a click on a node checks it once, though its label holds the checkbox', () => {
    item(element, 'Invoices').querySelector('span:not(.caelrik-tree-spacer)').click();
    expect(item(element, 'Invoices').getAttribute('aria-checked')).toBe('false');
    expect(checked()).toEqual([]);
  });

  test('a disabled node is not checked', () => {
    item(element, 'Archive').focus();
    press(' ');
    expect(item(element, 'Archive').getAttribute('aria-checked')).toBe('false');
  });

  test('a node with hasChildren loads them when it opens', async () => {
    fetch.mockResolvedValue({
      ok: true,
      json: async () => ({ data: [{ id: 61, label: 'Holidays' }, { id: 62, label: 'Family', hasChildren: true }] }),
    });
    const pictures = item(element, 'Pictures');
    pictures.focus();
    press('ArrowRight');
    expect(pictures.getAttribute('aria-busy')).toBe('true');
    await flush();
    await flush();

    expect(fetch.mock.calls[0][0]).toBe(new URL('/folders/6/children', document.baseURI).href);
    expect(pictures.hasAttribute('aria-busy')).toBe(false);
    expect(item(element, 'Holidays').getAttribute('aria-level')).toBe('2');
    expect(item(element, 'Holidays').querySelector('input').name).toBe('folders[]');
    expect(document.getElementById(pictures.getAttribute('aria-owns')).getAttribute('role')).toBe('group');
    expect(item(element, 'Family').getAttribute('aria-expanded')).toBe('false');
    press('ArrowRight');
    expect(focused()).toBe('Holidays');

    // Closed and opened again: not loaded twice.
    press('ArrowLeft');
    press('ArrowLeft');
    press('ArrowRight');
    expect(fetch).toHaveBeenCalledTimes(1);
  });

  test('a failed load can be tried again', async () => {
    fetch.mockRejectedValueOnce(new Error('offline'));
    const pictures = item(element, 'Pictures');
    pictures.focus();
    press('ArrowRight');
    await flush();
    await flush();
    expect(pictures.getAttribute('aria-expanded')).toBe('false');

    fetch.mockResolvedValueOnce({ ok: true, json: async () => ({ data: [] }) });
    press('ArrowRight');
    await flush();
    await flush();
    expect(fetch).toHaveBeenCalledTimes(2);
    // No children after all: the node no longer opens.
    expect(pictures.hasAttribute('aria-expanded')).toBe(false);
  });
});

test('a selectable tree selects one node', () => {
  const element = loadFixture('treeview-select');
  initTree(element, readOptions(element));
  item(element, 'France').focus();
  press('Enter');
  expect(item(element, 'France').getAttribute('aria-selected')).toBe('true');
  item(element, 'Congo').focus();
  press(' ');
  expect(item(element, 'France').getAttribute('aria-selected')).toBe('false');
  expect(item(element, 'Congo').getAttribute('aria-selected')).toBe('true');
  expect(element.querySelector('input:checked').value).toBe('cd');
  expect(visibleNames(element)).toEqual(['Europe', 'France', 'Africa', 'Congo']);
});
