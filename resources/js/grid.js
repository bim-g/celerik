import DataTable from 'datatables.net-bs5';
import 'datatables.net-buttons-bs5';
import 'datatables.net-buttons/js/buttons.html5.mjs';
import 'datatables.net-buttons/js/buttons.print.mjs';
import { connectColumnMenu, createColumnMenu, toggleableColumns } from './grid-columns.js';
import { connectSummary } from './grid-summary.js';
import {
  buildGridConfig, matchesFilters, readFilters,
} from './grid-config.js';

// Typing in a text filter redraws once the user pauses.
const TYPING_DELAY = 300;

export function initGrid(element, options) {
  const columns = options.columns ?? [];
  const bar = element.id ? document.getElementById(`${element.id}-filters`) : null;
  const hasFilters = Boolean(bar) && columns.some((column) => column.filter);
  const current = () => readFilters(bar, columns);

  const toggle = options.columnToggle && element.id && toggleableColumns(columns).length > 0 ? options.columnToggle : null;
  const columnMenu = toggle ? createColumnMenu(element.id, columns, toggle.labels) : null;

  const grid = new DataTable(element, buildGridConfig(options, { filters: hasFilters ? current : null, columnMenu }));
  if (columnMenu) {
    connectColumnMenu(columnMenu, grid, { tableId: element.id, columns, remember: toggle.remember });
  }
  const filters = hasFilters ? enhanceFilters(grid, bar, columns, current, Boolean(options.url)) : null;
  if (options.actions?.length) {
    refreshAfterActions(grid, element, Boolean(options.url));
  }
  if (options.selection) {
    enableSelection(grid, element, options.selection, Boolean(options.url));
  }
  enableReset(grid, element, bar, filters);
  connectSummary(grid, element, options);
  return grid;
}

// "Clear filters and search" in the no-matches row: clears the search box and the filter bar
// and draws once. The button goes with the row, so focus moves to the search box, or the
// first filter, or the table.
function enableReset(grid, element, bar, filters) {
  element.addEventListener('click', (event) => {
    if (!event.target.closest('[data-celerik-grid-reset]')) {
      return;
    }
    const search = grid.table().container().querySelector('.dt-search input');
    filters?.clear();
    grid.search('').draw();
    const target = search ?? bar?.querySelector('[data-celerik-filter]');
    if (target) {
      target.focus();
    } else {
      element.setAttribute('tabindex', '-1');
      element.focus();
    }
  });
}

// Row selection (Grid::selectable()): the selected keys are kept across pages, filters and
// sorting, the page's box shows all, some (mixed) or none of its rows, and the bulk bar says
// how many rows are selected. Its buttons are disabled while none is.
function enableSelection(grid, element, { form, labels }, serverSide) {
  const selected = new Set();
  const bulk = form ? document.getElementById(form) : null;
  const status = bulk?.querySelector('[data-celerik-selected]');
  const buttons = bulk ? [...bulk.querySelectorAll('button[data-celerik-action]')] : [];
  const all = element.querySelector('[data-celerik-select-all]');
  const boxes = () => [...element.tBodies[0].querySelectorAll('input[data-celerik-select]')];

  const update = () => {
    const page = boxes();
    const checked = page.filter((box) => box.checked).length;
    if (all) {
      all.checked = page.length > 0 && checked === page.length;
      all.indeterminate = checked > 0 && checked < page.length;
    }
    if (status) {
      const text = selected.size > 0 ? labels.selected.replaceAll('{count}', String(selected.size)) : labels.noneSelected;
      if (status.textContent !== text) {
        status.textContent = text;
      }
    }
    buttons.forEach((button) => {
      button.disabled = selected.size === 0;
    });
  };
  const toggle = (box, checked) => {
    box.checked = checked;
    if (checked) {
      selected.add(box.value);
    } else {
      selected.delete(box.value);
    }
  };

  element.addEventListener('change', (event) => {
    if (event.target.matches('input[data-celerik-select]')) {
      toggle(event.target, event.target.checked);
    } else if (event.target === all) {
      boxes().forEach((box) => toggle(box, all.checked));
    } else {
      return;
    }
    update();
    element.dispatchEvent(new CustomEvent('celerik:selection', { bubbles: true, detail: { keys: [...selected] } }));
  });
  grid.on('draw', () => {
    boxes().forEach((box) => {
      box.checked = selected.has(box.value);
    });
    update();
  });

  // A row's own action that deleted it takes it out of the selection.
  element.addEventListener('celerik:action', (event) => {
    const box = event.target.closest('tr')?.querySelector('input[data-celerik-select]');
    if (event.detail?.ok && event.detail.method === 'DELETE' && box) {
      selected.delete(box.value);
      update();
    }
  });

  if (bulk) {
    bulk.celerikSelection = () => [...selected];
    // After a bulk action: a server-side grid reloads its page; a client-side grid removes
    // the rows a DELETE deleted. The selection is cleared and focus moves to the page's box,
    // as the bar's buttons are disabled again.
    bulk.addEventListener('celerik:action', (event) => {
      if (!event.detail?.ok) {
        return;
      }
      if (!serverSide && event.detail.method === 'DELETE') {
        grid.rows((index, data, node) => selected.has(node?.querySelector('input[data-celerik-select]')?.value)).remove();
      }
      selected.clear();
      if (serverSide) {
        grid.ajax.reload(null, false);
      } else {
        grid.draw(false);
      }
      update();
      all?.focus();
    });
  }
  update();
}

// After a row action succeeds: a server-side grid reloads its page; a client-side grid
// removes a deleted row. Focus moves to the same action in the row that takes its place,
// or to the table when the page is empty, so it is not lost with the row.
function refreshAfterActions(grid, element, serverSide) {
  element.addEventListener('celerik:action', (event) => {
    const { ok, method, name } = event.detail ?? {};
    const row = event.target.closest('tr');
    if (!ok || !row) {
      return;
    }
    const rows = [...element.tBodies[0].rows];
    const position = rows.indexOf(row);
    const hadFocus = row.contains(document.activeElement) || document.activeElement === document.body;
    const refocus = () => {
      if (!hadFocus) {
        return;
      }
      const current = [...element.tBodies[0].rows];
      const target = [...(current[Math.min(position, current.length - 1)]?.querySelectorAll('[data-celerik-action]') ?? [])]
        .find((form) => form.dataset.celerikAction === name)?.querySelector('button');
      if (target) {
        target.focus();
      } else {
        element.setAttribute('tabindex', '-1');
        element.focus();
      }
    };

    if (serverSide) {
      grid.one('draw', refocus);
      grid.ajax.reload(null, false);
    } else if (method === 'DELETE') {
      grid.row(row).remove().draw(false);
      refocus();
    }
  });
}

function enhanceFilters(grid, bar, columns, current, serverSide) {
  // Read once per draw, not once per row.
  let active = current();
  if (!serverSide) {
    // By name: a selection column may come first.
    const indexes = new Map(columns.map((column) => [column.field, grid.column(`${column.field}:name`).index()]));
    const types = new Map(columns.map((column) => [column.field, column.filter?.type]));
    grid.search.fixed('celerik-filters', (searchData, rowData, rowIndex) => {
      if (active.length === 0) {
        return true;
      }
      return matchesFilters(active, (field) => {
        const cell = grid.cell(rowIndex, indexes.get(field)).node();
        if (!cell) {
          return '';
        }
        return types.get(field) !== 'text' && cell.dataset.value !== undefined ? cell.dataset.value : cell.textContent;
      });
    });
  }

  // Draws only when the filters changed: leaving a text filter fires `change` with the value
  // already applied, and a redraw then would replace the rows under the user, e.g. the
  // no-matches row's button they are moving to.
  let timer = null;
  const redraw = () => {
    clearTimeout(timer);
    const next = current();
    if (JSON.stringify(next) === JSON.stringify(active)) {
      return;
    }
    active = next;
    grid.draw();
  };
  // Empties the fields and takes them as applied, for a caller that draws itself.
  const clear = () => {
    clearTimeout(timer);
    bar.querySelectorAll('[data-celerik-filter]').forEach((control) => {
      control.value = '';
    });
    active = current();
  };
  bar.addEventListener('input', (event) => {
    if (event.target.matches('input[type=search]')) {
      clearTimeout(timer);
      timer = setTimeout(redraw, TYPING_DELAY);
    }
  });
  bar.addEventListener('change', (event) => {
    if (event.target.matches('[data-celerik-filter]')) {
      redraw();
    }
  });
  // Enter applies a text filter at once.
  bar.addEventListener('keydown', (event) => {
    if (event.key === 'Enter' && event.target.matches('input[type=search]')) {
      event.preventDefault();
      redraw();
    }
  });
  bar.querySelector('[data-celerik-filter-clear]')?.addEventListener('click', () => {
    bar.querySelectorAll('[data-celerik-filter]').forEach((control) => {
      control.value = '';
    });
    redraw();
  });

  bar.hidden = false;
  return { clear };
}
