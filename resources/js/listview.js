// ListView (PHP: Components\ListView): a list of items with a pager, a search box and a sort
// choice. Client-side, PHP wrote every item: this pages, searches and sorts them. Server-side,
// each page comes from the endpoint ({data, meta}, as respond() answers) and its items are
// written here, with the same markup as ListView::renderItem().
// Changing page moves focus to the list, so keyboard and screen reader users start reading
// at its first item; the status says which items are shown.
import { actionHtml, fill } from './action.js';
import { escapeHtml } from './notification-config.js';

const TYPING_DELAY = 300;

export const LABELS = {
  search: 'Search',
  sort: 'Sort by',
  pages: 'Pages of {label}',
  previous: 'Previous',
  next: 'Next',
  page: 'Page {page}',
  showing: '{from}–{to} of {total}',
  empty: 'No items to show',
  noMatches: 'No items match your search',
  reset: 'Clear search',
  loading: 'Loading…',
  failed: 'The items could not be loaded. Try again.',
};

function read(row, path) {
  return path.split('.').reduce((value, key) => (value == null ? undefined : value[key]), row);
}

// As Arr::toText().
function toText(value) {
  if (value === null || value === undefined) {
    return '';
  }
  if (typeof value === 'boolean') {
    return value ? 'Yes' : 'No';
  }
  return typeof value === 'object' ? JSON.stringify(value) : String(value);
}

// As ListView::safeUrl(): relative, http or https; anything else is left out.
export function safeUrl(value) {
  const url = toText(value).trim();
  // eslint-disable-next-line no-control-regex
  if (url === '' || /[\u0000- \u007f]/.test(url)) {
    return null;
  }
  const scheme = /^([A-Za-z][A-Za-z0-9+.-]*):/.exec(url);
  return !scheme || ['http', 'https'].includes(scheme[1].toLowerCase()) ? url : null;
}

// The same markup as ListView::renderItem(), for items from the endpoint.
export function itemHtml(options, row) {
  const item = options.item ?? {};
  const text = (field) => (field ? toText(read(row, field)) : '');
  const title = text(item.title);
  const level = item.heading ?? 3;

  let heading = escapeHtml(title);
  if (item.link) {
    heading = `<a href="${escapeHtml(fill(item.link, row, true))}">${heading}</a>`;
  }
  let body = `<h${level} class="celerik-listview-title">${heading}</h${level}>`;
  if (text(item.description) !== '') {
    body += `<p class="celerik-listview-description">${escapeHtml(text(item.description))}</p>`;
  }
  const meta = (item.meta ?? []).map(text).filter((value) => value !== '');
  if (meta.length > 0) {
    body += `<p class="celerik-listview-meta">${meta.map((value) => `<span>${escapeHtml(value)}</span>`).join('')}</p>`;
  }

  const src = item.image ? safeUrl(read(row, item.image)) : null;
  const image = src
    ? `<img class="celerik-listview-image" src="${escapeHtml(src)}" alt="${escapeHtml(text(item.imageAlt))}" loading="lazy">`
    : '';

  const actions = (options.actions ?? [])
    .map((action) => actionHtml(action, row, `${action.label} ${title}`.trim()))
    .join('');

  return `<li${options.layout === 'cards' ? ' class="col"' : ''}><div class="celerik-listview-item">${image}`
    + `<div class="celerik-listview-body">${body}</div>`
    + `${actions ? `<div class="celerik-listview-actions">${actions}</div>` : ''}</div></li>`;
}

// Page numbers to offer: the first, the last, and the current one with its neighbours;
// null marks a gap.
export function pageWindow(page, count) {
  const pages = [...new Set([1, page - 1, page, page + 1, count])]
    .filter((number) => number >= 1 && number <= count)
    .sort((a, b) => a - b);
  const result = [];
  pages.forEach((number, index) => {
    if (index > 0 && number - pages[index - 1] > 1) {
      result.push(null);
    }
    result.push(number);
  });
  return result;
}

// Numbers by value, texts by their letters and numbers; empty values last.
export function compareValues(a, b) {
  const empty = (value) => value === null || value === undefined || value === '';
  if (empty(a) || empty(b)) {
    return Number(empty(a)) - Number(empty(b));
  }
  const numeric = (value) => typeof value === 'number' || (typeof value === 'string' && /^-?\d+(\.\d+)?$/.test(value));
  if (numeric(a) && numeric(b)) {
    return Number(a) - Number(b);
  }
  return String(a).localeCompare(String(b), undefined, { numeric: true, sensitivity: 'base' });
}

function button(text, attributes = {}) {
  const element = document.createElement('button');
  element.type = 'button';
  element.className = 'page-link';
  element.textContent = text;
  for (const [name, value] of Object.entries(attributes)) {
    if (value !== null && value !== undefined && value !== false) {
      element.setAttribute(name, value === true ? '' : String(value));
    }
  }
  return element;
}

function renderPager(nav, page, count, labels) {
  const list = document.createElement('ul');
  list.className = 'pagination flex-wrap mb-0';
  const add = (content, state = '') => {
    const item = document.createElement('li');
    item.className = `page-item${state}`;
    item.append(content);
    list.append(item);
  };
  add(button(labels.previous, { 'data-page': page - 1, disabled: page <= 1 }), page <= 1 ? ' disabled' : '');
  for (const number of pageWindow(page, count)) {
    if (number === null) {
      const gap = document.createElement('span');
      gap.className = 'page-link';
      gap.setAttribute('aria-hidden', 'true');
      gap.textContent = '…';
      add(gap, ' disabled');
      continue;
    }
    add(button(String(number), {
      'data-page': number,
      'aria-label': labels.page.replaceAll('{page}', String(number)),
      'aria-current': number === page ? 'page' : null,
    }), number === page ? ' active' : '');
  }
  add(button(labels.next, { 'data-page': page + 1, disabled: page >= count }), page >= count ? ' disabled' : '');
  nav.replaceChildren(list);
  nav.hidden = count <= 1;
}

// deps.fetch: for tests.
export function initListView(element, options, deps = {}) {
  const list = element.querySelector('.celerik-listview-items');
  const empty = element.querySelector('.celerik-listview-empty');
  const status = element.querySelector('[data-celerik-listview-status]');
  const nav = element.querySelector('.celerik-listview-pager');
  const toolbar = element.querySelector('.celerik-listview-toolbar');
  const search = element.querySelector('[data-celerik-listview-search]');
  const sort = element.querySelector('[data-celerik-listview-sort]');
  const labels = { ...LABELS, ...options.labels };
  const serverSide = Boolean(options.url);
  const pageSize = options.pageSize ?? 10;
  const send = deps.fetch ?? ((...args) => fetch(...args));
  const emptyHtml = empty?.innerHTML ?? '';

  const state = {
    page: 1, search: '', sort: sort?.value ?? options.sorts?.[0]?.[0] ?? null, request: 0,
  };
  const items = serverSide ? [] : [...list.children].map((node) => {
    let values = {};
    try {
      values = JSON.parse(node.dataset.sort ?? '{}');
    } catch {
      // No sort values.
    }
    const body = node.querySelector('.celerik-listview-body') ?? node;
    return { node, text: body.textContent.toLocaleLowerCase(), values };
  });

  const noMatches = () => {
    const title = document.createElement('p');
    title.className = 'celerik-listview-empty-title';
    title.textContent = labels.noMatches;
    const reset = document.createElement('button');
    reset.type = 'button';
    reset.className = 'btn btn-sm btn-outline-secondary celerik-action-button';
    reset.dataset.celerikListviewReset = '';
    reset.textContent = labels.reset;
    const holder = document.createElement('div');
    holder.className = 'celerik-listview-empty-action';
    holder.append(reset);
    return [title, holder];
  };

  // Shows the page: the list, or why it is empty, the status and the pager.
  const show = (total, filtered, start, shown) => {
    const count = Math.max(1, Math.ceil(filtered / pageSize));
    list.hidden = shown === 0;
    if (empty) {
      empty.hidden = shown > 0;
      if (shown === 0 && total > 0 && filtered === 0) {
        empty.replaceChildren(...noMatches());
      } else if (shown === 0) {
        empty.innerHTML = emptyHtml;
      }
    }
    status.textContent = shown === 0 ? '' : labels.showing
      .replaceAll('{from}', String(start + 1))
      .replaceAll('{to}', String(start + shown))
      .replaceAll('{total}', String(filtered));
    renderPager(nav, state.page, count, labels);
  };

  const drawClient = () => {
    const query = state.search.toLocaleLowerCase();
    let visible = query ? items.filter((item) => item.text.includes(query)) : items;
    if (state.sort) {
      const descending = state.sort.startsWith('-');
      const field = state.sort.replace(/^-/, '');
      visible = [...visible].sort((a, b) => {
        const order = compareValues(a.values[field], b.values[field]);
        const missing = [a.values[field], b.values[field]].some((value) => value === null || value === undefined || value === '');
        return descending && !missing ? -order : order;
      });
    }
    const count = Math.max(1, Math.ceil(visible.length / pageSize));
    state.page = Math.min(state.page, count);
    const start = (state.page - 1) * pageSize;
    const page = visible.slice(start, start + pageSize);
    list.replaceChildren(...page.map((item) => item.node));
    show(items.length, visible.length, start, page.length);
  };

  const drawServer = async () => {
    const url = new URL(options.url, document.baseURI);
    url.searchParams.set('page', String(state.page));
    url.searchParams.set('per_page', String(pageSize));
    if (state.search) {
      url.searchParams.set('search', state.search);
    }
    if (state.sort) {
      url.searchParams.set('sort', state.sort);
    }
    state.request += 1;
    const request = state.request;
    element.setAttribute('aria-busy', 'true');
    try {
      const response = await send(url.href, {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
      });
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }
      const payload = await response.json();
      if (request !== state.request) {
        return;
      }
      const rows = Array.isArray(payload?.data) ? payload.data : [];
      const total = payload?.meta?.total ?? rows.length;
      const filtered = payload?.meta?.filtered ?? total;
      list.innerHTML = rows.map((row) => itemHtml(options, row)).join('');
      show(total, filtered, (state.page - 1) * pageSize, rows.length);
    } catch {
      if (request === state.request) {
        status.textContent = labels.failed;
      }
    } finally {
      if (request === state.request) {
        element.removeAttribute('aria-busy');
      }
    }
  };

  const draw = () => (serverSide ? drawServer() : Promise.resolve(drawClient()));

  const goTo = async (page, focus = false) => {
    state.page = Math.max(1, page);
    await draw();
    if (focus) {
      list.focus();
    }
  };

  nav.addEventListener('click', (event) => {
    const target = event.target.closest('button[data-page]');
    if (target && !target.disabled) {
      goTo(Number(target.dataset.page), true);
    }
  });

  let timer = null;
  const applySearch = () => {
    clearTimeout(timer);
    const value = search.value.trim();
    if (value !== state.search) {
      state.search = value;
      goTo(1);
    }
  };
  search?.addEventListener('input', () => {
    clearTimeout(timer);
    timer = setTimeout(applySearch, TYPING_DELAY);
  });
  search?.addEventListener('keydown', (event) => {
    if (event.key === 'Enter') {
      event.preventDefault();
      applySearch();
    }
  });
  sort?.addEventListener('change', () => {
    state.sort = sort.value;
    goTo(1);
  });
  element.addEventListener('click', async (event) => {
    if (!event.target.closest('[data-celerik-listview-reset]')) {
      return;
    }
    search.value = '';
    state.search = '';
    await goTo(1);
    search.focus();
  });

  // After an item's action: a server-side list reloads its page; a client-side list drops a
  // deleted item. Focus goes to the list, as the item may be gone.
  element.addEventListener('celerik:action', async (event) => {
    const { ok, method } = event.detail ?? {};
    const node = event.target.closest('li');
    if (!ok || !node || !list.contains(node)) {
      return;
    }
    if (!serverSide && method === 'DELETE') {
      const index = items.findIndex((item) => item.node === node);
      if (index !== -1) {
        items.splice(index, 1);
      }
    } else if (!serverSide) {
      return;
    }
    const hadFocus = node.contains(document.activeElement) || document.activeElement === document.body;
    await draw();
    if (hadFocus) {
      list.focus();
    }
  });

  if (toolbar) {
    toolbar.hidden = false;
  }
  draw();
  return { goTo, refresh: draw, state };
}
