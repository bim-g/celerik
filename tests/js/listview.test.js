import {
  compareValues, initListView, itemHtml, pageWindow, safeUrl,
} from '../../resources/js/listview.js';
import { readOptions } from '../../resources/js/core.js';
import { loadFixture } from './helpers.js';

const flush = () => new Promise((resolve) => { setTimeout(resolve, 0); });
const titles = (element) => [...element.querySelectorAll('.celerik-listview-items .celerik-listview-title')]
  .map((node) => node.textContent);
const pages = (element) => [...element.querySelectorAll('.celerik-listview-pager button')].map((node) => node.textContent);

describe('listview helpers', () => {
  test('pageWindow offers the first, the last and the current page with its neighbours', () => {
    expect(pageWindow(1, 1)).toEqual([1]);
    expect(pageWindow(1, 3)).toEqual([1, 2, 3]);
    expect(pageWindow(5, 10)).toEqual([1, null, 4, 5, 6, null, 10]);
    expect(pageWindow(10, 10)).toEqual([1, null, 9, 10]);
  });

  test('compareValues sorts numbers by value, texts naturally, empty values last', () => {
    expect([10, 9, '100'].sort(compareValues)).toEqual([9, 10, '100']);
    expect(['item 10', 'Item 9'].sort(compareValues)).toEqual(['Item 9', 'item 10']);
    expect([null, 'b', '', 'a'].sort(compareValues)).toEqual(['a', 'b', null, '']);
  });

  test('safeUrl keeps relative and http(s) pictures only', () => {
    expect(safeUrl('/covers/1.jpg')).toBe('/covers/1.jpg');
    expect(safeUrl('https://example.com/a.png')).toBe('https://example.com/a.png');
    expect(safeUrl('javascript:alert(1)')).toBeNull();
    expect(safeUrl('java\tscript:alert(1)')).toBeNull();
    expect(safeUrl('data:image/png;base64,AAAA')).toBeNull();
    expect(safeUrl(null)).toBeNull();
  });

  test('itemHtml writes an item from the endpoint as PHP does, escaped', () => {
    const options = {
      item: {
        title: 'title', link: '/a/{id}', description: 'summary', meta: ['author'], heading: 2,
      },
      actions: [{
        name: 'delete', label: 'Delete', url: '/a/{id}', request: true, method: 'DELETE', className: 'btn',
      }],
    };
    const html = itemHtml(options, {
      id: 'x/1', title: '<i>T</i>', summary: 'S', author: null,
    });
    expect(html).toContain('<h2 class="celerik-listview-title"><a href="/a/x%2F1">&lt;i&gt;T&lt;/i&gt;</a></h2>');
    expect(html).not.toContain('celerik-listview-meta');
    expect(html).toContain('aria-label="Delete &lt;i&gt;T&lt;/i&gt;"');
  });
});

describe('a client-side list', () => {
  let element;

  beforeEach(() => {
    element = loadFixture('listview');
    initListView(element, readOptions(element));
  });

  test('shows the toolbar, the first page in the first order, the status and the pager', () => {
    expect(element.querySelector('.celerik-listview-toolbar').hidden).toBe(false);
    expect(titles(element)).toEqual(['Structured Programming', 'On Computing Machinery', 'Go To Statement']);
    expect(element.querySelector('[role=status]').textContent).toBe('1–3 of 7');
    const nav = element.querySelector('nav');
    expect(nav.hidden).toBe(false);
    expect(nav.getAttribute('aria-label')).toBe('Pages of Articles');
    expect(pages(element)).toEqual(['Previous', '1', '2', '3', 'Next']);
    expect(nav.querySelector('[aria-current=page]').textContent).toBe('1');
    expect(nav.querySelector('button').disabled).toBe(true);
  });

  test('a page button shows that page and moves focus to the list', async () => {
    element.querySelector('nav button[aria-label="Page 3"]').click();
    await flush();

    expect(titles(element)).toEqual(['Notes on the Engine']);
    expect(element.querySelector('[role=status]').textContent).toBe('7–7 of 7');
    expect(document.activeElement).toBe(element.querySelector('.celerik-listview-items'));
    expect([...element.querySelectorAll('nav button')].pop().disabled).toBe(true);
  });

  test('the sort choice reorders from the first page', async () => {
    element.querySelector('nav button[aria-label="Page 2"]').click();
    const sort = element.querySelector('select');
    sort.value = 'title';
    sort.dispatchEvent(new Event('change'));
    await flush();

    expect(titles(element)).toEqual(['A Mathematical Theory', 'Computable Numbers', 'Go To Statement']);
    expect(element.querySelector('[aria-current=page]').textContent).toBe('1');
  });

  test('the search filters titles, descriptions and meta texts; no match offers to clear it', async () => {
    const search = element.querySelector('input[type=search]');
    search.value = 'edsger';
    search.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter' }));
    await flush();
    expect(titles(element)).toEqual(['Structured Programming', 'Go To Statement']);
    expect(element.querySelector('nav').hidden).toBe(true);

    search.value = 'zzz';
    search.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter' }));
    await flush();
    const empty = element.querySelector('.celerik-listview-empty');
    expect(element.querySelector('.celerik-listview-items').hidden).toBe(true);
    expect(empty.hidden).toBe(false);
    expect(empty.textContent).toContain('No items match your search');
    expect(element.querySelector('[role=status]').textContent).toBe('');

    empty.querySelector('button').click();
    await flush();
    await flush();
    expect(search.value).toBe('');
    expect(titles(element)).toHaveLength(3);
    expect(document.activeElement).toBe(search);
  });

  test('a deleted item leaves the list, and focus goes to the list', async () => {
    const form = element.querySelector('form.celerik-action');
    form.querySelector('button').focus();
    form.dispatchEvent(new CustomEvent('celerik:action', { bubbles: true, detail: { ok: true, method: 'DELETE' } }));
    await flush();

    expect(titles(element)).toEqual(['On Computing Machinery', 'Go To Statement', 'The Art of Programming']);
    expect(element.querySelector('[role=status]').textContent).toBe('1–3 of 6');
    expect(document.activeElement).toBe(element.querySelector('.celerik-listview-items'));
  });
});

test('a list without items shows its empty state', () => {
  const element = loadFixture('listview-empty');
  expect(element.querySelector('.celerik-listview-items').hidden).toBe(true);
  expect(element.querySelector('.celerik-listview-empty').hidden).toBe(false);
  initListView(element, readOptions(element));
  expect(element.querySelector('.celerik-listview-empty').textContent).toContain('No notes yet');
  expect(element.querySelector('nav').hidden).toBe(true);
});

describe('a server-side list', () => {
  const options = {
    url: '/articles/data',
    pageSize: 2,
    sorts: [['-published', 'Newest first']],
    item: { title: 'title', link: '/articles/{id}' },
    labels: { pages: 'Pages of {label}' },
  };

  function mount() {
    document.body.innerHTML = '<div id="l"><ul class="celerik-listview-items" tabindex="-1"></ul>'
      + '<div class="celerik-listview-empty" hidden><p>No items to show</p></div>'
      + '<div class="celerik-listview-footer"><p role="status" data-celerik-listview-status></p>'
      + '<nav class="celerik-listview-pager" hidden></nav></div></div>';
    return document.getElementById('l');
  }

  const reply = (data, meta) => ({ ok: true, status: 200, json: async () => ({ data, meta }) });

  test('asks the endpoint for each page with its sort and search', async () => {
    const element = mount();
    const fetch = jest.fn()
      .mockResolvedValueOnce(reply([{ id: 1, title: 'A' }, { id: 2, title: 'B' }], { total: 5, filtered: 5 }))
      .mockResolvedValueOnce(reply([{ id: 5, title: 'E' }], { total: 5, filtered: 5 }));
    const list = initListView(element, options, { fetch });
    await flush();

    expect(new URL(fetch.mock.calls[0][0]).search).toBe('?page=1&per_page=2&sort=-published');
    expect(titles(element)).toEqual(['A', 'B']);
    expect(element.querySelector('[role=status]').textContent).toBe('1–2 of 5');

    await list.goTo(3, true);
    expect(new URL(fetch.mock.calls[1][0]).searchParams.get('page')).toBe('3');
    expect(titles(element)).toEqual(['E']);
    expect(element.querySelector('a').getAttribute('href')).toBe('/articles/5');
    expect(document.activeElement).toBe(element.querySelector('ul'));
  });

  test('a failed page says so', async () => {
    const element = mount();
    initListView(element, options, { fetch: jest.fn().mockRejectedValue(new Error('offline')) });
    await flush();
    expect(element.querySelector('[role=status]').textContent).toBe('The items could not be loaded. Try again.');
    expect(element.hasAttribute('aria-busy')).toBe(false);
  });
});
