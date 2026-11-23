import { rowActionsHtml, rowCheckboxHtml } from './action.js';
import { deepMerge } from './core.js';
import { editableCellHtml, editValue } from './grid-edit.js';

// Caelrik endpoints answer {data, meta}: where DataTables finds the rows, the draw counter and
// the row counts in it. DataTables 3 makes its own requests, without jQuery's dataFilter.
export const ENDPOINT_FIELDS = {
  data: 'data',
  draw: 'meta.draw',
  recordsTotal: 'meta.total',
  recordsFiltered: 'meta.filtered',
};

// The values of the filter bar, from PHP's column filters: [{field, type, value}] for text,
// select and yes/no filters, [{field, type, from, to}] for date ranges. Empty ones are left out.
export function readFilters(bar, columns) {
  if (!bar) {
    return [];
  }
  const filters = [];
  for (const column of columns ?? []) {
    const type = column.filter?.type;
    if (!type) {
      continue;
    }
    const controls = [...bar.querySelectorAll('[data-caelrik-filter]')]
      .filter((control) => control.dataset.caelrikFilter === column.field);
    if (type === 'dateRange') {
      const end = (name) => controls.find((control) => control.dataset.caelrikFilterEnd === name)?.value ?? '';
      const [from, to] = [end('from'), end('to')];
      if (from || to) {
        filters.push({ field: column.field, type, from, to });
      }
      continue;
    }
    const value = (controls[0]?.value ?? '').trim();
    if (value !== '') {
      filters.push({ field: column.field, type, value });
    }
  }
  return filters;
}

// The filters as the endpoint reads them (Grid::respond()): filter[name]=ada,
// filter[joined][from]=2026-01-01.
export function filterParams(filters) {
  const params = {};
  for (const filter of filters) {
    if (filter.type === 'dateRange') {
      params[filter.field] = {
        ...(filter.from ? { from: filter.from } : {}),
        ...(filter.to ? { to: filter.to } : {}),
      };
    } else {
      params[filter.field] = filter.value;
    }
  }
  return params;
}

// Whether a client-side row passes every filter, as respond() does on the server.
// read(field) gives the cell's value: its text, or for select, yes/no and date filters the
// value PHP wrote in data-value (1 or 0, a date written Y-m-d).
export function matchesFilters(filters, read) {
  return filters.every((filter) => {
    const value = read(filter.field) ?? '';
    if (filter.type === 'text') {
      return value.toLocaleLowerCase().includes(filter.value.toLocaleLowerCase());
    }
    if (filter.type === 'dateRange') {
      const day = value.slice(0, 10);
      return day !== '' && (!filter.from || day >= filter.from) && (!filter.to || day <= filter.to);
    }
    return value === filter.value;
  });
}

// filters: a function returning the current filters (readFilters()), sent with every
// server-side request. columnMenu: the Columns menu (grid-columns.js), placed after the
// page length and the export buttons.
export function buildGridConfig(options, { filters = null, columnMenu = null } = {}) {
  const hasFilters = (options.columns ?? []).some((column) => column.filter);
  const config = {
    columns: (options.columns ?? []).map((column) => ({
      data: column.field,
      name: column.field,
      title: column.title,
      orderable: column.sortable !== false,
      searchable: column.searchable !== false,
      visible: column.visible !== false,
      className: column.className ?? '',
      width: column.width ?? null,
      defaultContent: '',
      // An editable cell of a row from the endpoint: the same markup PHP writes in client-side
      // rows (Grid::renderEditable()).
      ...(column.editor && options.edit && options.url ? {
        render: (data, type, row) => (type === 'display' ? editableCellHtml(column, row, options.edit, options.rowTitle) : data),
        createdCell: (td, data) => {
          td.classList.add('caelrik-grid-editable');
          td.dataset.value = editValue(column.editor, data);
        },
      } : {}),
    })),
    pageLength: options.pageSize ?? 10,
    lengthMenu: options.pageSizes ?? [10, 25, 50, 100],
    // Column filters need DataTables' searching; without the search box, it is only hidden.
    searching: options.searchable !== false || hasFilters,
    autoWidth: false,
  };
  // The actions column (Grid::actions()): PHP renders its buttons in client-side rows; rows
  // from the endpoint get the same markup here.
  const actions = options.actions ?? [];
  if (actions.length > 0) {
    config.columns.push({
      data: options.url ? null : 'caelrikActions',
      title: options.actionsTitle ?? 'Actions',
      orderable: false,
      searchable: false,
      className: 'caelrik-grid-actions',
      defaultContent: '',
      ...(options.url ? {
        render: (data, type, row) => (type === 'display' ? rowActionsHtml(actions, row, options.rowTitle) : ''),
      } : {}),
    });
  }
  // The selection column (Grid::selectable()), first: PHP renders its checkboxes in
  // client-side rows. Rows are sorted by the first data column, not by the checkboxes.
  const selection = options.selection ?? null;
  if (selection) {
    config.columns.unshift({
      data: options.url ? null : 'caelrikSelect',
      orderable: false,
      searchable: false,
      className: 'caelrik-grid-select',
      defaultContent: '',
      ...(options.url ? {
        render: (data, type, row) => (type === 'display' ? rowCheckboxHtml(selection, row, options.rowTitle) : ''),
      } : {}),
    });
    config.order = [[1, 'asc']];
  }
  // A grouped grid (Grid::groupBy()) is sorted by its group first, whatever the user sorts.
  if (options.groupBy) {
    const group = config.columns.findIndex((column) => column.name === options.groupBy);
    if (group !== -1) {
      config.orderFixed = { pre: [[group, 'asc']] };
    }
  }
  if (options.searchable === false && hasFilters) {
    config.layout = { topEnd: null };
  }

  if (options.url) {
    config.serverSide = true;
    config.processing = true;
    config.ajax = {
      url: options.url,
      dataSrc: { ...ENDPOINT_FIELDS },
    };
    if (filters) {
      config.ajax.data = (data) => {
        const params = filterParams(filters());
        if (Object.keys(params).length > 0) {
          data.filter = params;
        }
      };
    }
  }

  if (options.export?.length) {
    // Exports leave the actions and selection columns out.
    const buttons = actions.length > 0 || selection
      ? options.export.map((extend) => ({
        extend, exportOptions: { columns: ':not(.caelrik-grid-actions):not(.caelrik-grid-select)' },
      }))
      : options.export;
    config.layout = { ...config.layout, topStart: ['pageLength', { buttons }] };
  }

  // The empty state and the "no rows match" state (Grid::emptyState()), written by PHP.
  if (options.emptyHtml || options.noMatchesHtml) {
    config.language = {
      ...(options.emptyHtml ? { emptyTable: options.emptyHtml } : {}),
      ...(options.noMatchesHtml ? { zeroRecords: options.noMatchesHtml } : {}),
    };
  }

  if (columnMenu) {
    const start = config.layout?.topStart ?? 'pageLength';
    config.layout = { ...config.layout, topStart: [...(Array.isArray(start) ? start : [start]), columnMenu] };
  }

  return deepMerge(config, options.options);
}
