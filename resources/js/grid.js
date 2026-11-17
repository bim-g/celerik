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
  return grid;
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
