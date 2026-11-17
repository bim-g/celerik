import { deepMerge } from './core.js';

// Celerik endpoints answer {data, meta}; DataTables expects its own envelope.
export function toDataTablesResponse(payload) {
  const data = Array.isArray(payload?.data) ? payload.data : [];
  const meta = payload?.meta ?? {};
  return {
    draw: meta.draw ?? 0,
    recordsTotal: meta.total ?? data.length,
    recordsFiltered: meta.filtered ?? meta.total ?? data.length,
    data,
  };
}

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
    const controls = [...bar.querySelectorAll('[data-celerik-filter]')]
      .filter((control) => control.dataset.celerikFilter === column.field);
    if (type === 'dateRange') {
      const end = (name) => controls.find((control) => control.dataset.celerikFilterEnd === name)?.value ?? '';
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
// server-side request.
export function buildGridConfig(options, { filters = null } = {}) {
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
    })),
    pageLength: options.pageSize ?? 10,
    lengthMenu: options.pageSizes ?? [10, 25, 50, 100],
    // Column filters need DataTables' searching; without the search box, it is only hidden.
    searching: options.searchable !== false || hasFilters,
    autoWidth: false,
  };
  if (options.searchable === false && hasFilters) {
    config.layout = { topEnd: null };
  }

  if (options.url) {
    config.serverSide = true;
    config.processing = true;
    config.ajax = {
      url: options.url,
      dataFilter: (raw) => JSON.stringify(toDataTablesResponse(JSON.parse(raw))),
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
    config.layout = { ...config.layout, topStart: ['pageLength', { buttons: options.export }] };
  }

  return deepMerge(config, options.options);
}
