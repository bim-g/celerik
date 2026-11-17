import DataTable from 'datatables.net-bs5';
import 'datatables.net-buttons-bs5';
import 'datatables.net-buttons/js/buttons.html5.mjs';
import 'datatables.net-buttons/js/buttons.print.mjs';
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

  const grid = new DataTable(element, buildGridConfig(options, { filters: hasFilters ? current : null }));
  if (hasFilters) {
    enhanceFilters(grid, bar, columns, current, Boolean(options.url));
  }
  if (options.actions?.length) {
    refreshAfterActions(grid, element, Boolean(options.url));
  }
  return grid;
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
    const indexes = new Map(columns.map((column, index) => [column.field, index]));
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

  let timer = null;
  const redraw = () => {
    clearTimeout(timer);
    active = current();
    grid.draw();
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
}
