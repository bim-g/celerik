// Column totals (Column::total()) and row groups (Grid::groupBy()). PHP writes the footer and,
// for a client-side grid, its first totals (src/Data/Aggregate.php): this module totals the
// rows that pass the search and filters on every draw, or shows the endpoint's totals, and
// writes a heading row before each group of the page.

const NUMBER = /^[+-]?(\d+\.?\d*|\.\d+)([eE][+-]?\d+)?$/;

function toNumber(value) {
  if (typeof value === 'number') {
    return Number.isFinite(value) ? value : null;
  }
  if (typeof value === 'boolean') {
    return value ? 1 : 0;
  }
  const text = String(value).trim();
  return NUMBER.test(text) ? Number(text) : null;
}

// As Aggregate::compute(): empty values are left out, `count` counts the others, and the
// other functions only read numbers. With no number, only `sum` and `count` give 0.
export function aggregate(fn, values) {
  const numbers = [];
  let count = 0;
  for (const value of values) {
    if (value === null || value === undefined || value === '') {
      continue;
    }
    count += 1;
    const number = toNumber(value);
    if (number !== null) {
      numbers.push(number);
    }
  }
  const sum = numbers.reduce((total, number) => total + number, 0);
  switch (fn) {
    case 'count': return count;
    case 'sum': return sum;
    case 'avg': return numbers.length ? sum / numbers.length : null;
    case 'min': return numbers.length ? Math.min(...numbers) : null;
    case 'max': return numbers.length ? Math.max(...numbers) : null;
    default: return null;
  }
}

// As Aggregate::format(): without decimals, none for a count or a whole number, 2 otherwise.
export function formatTotal(value, fn, decimals, { decimal = '.', thousands = ',' } = {}) {
  const number = value === null || value === undefined ? null : toNumber(value);
  if (number === null) {
    return '';
  }
  let places = decimals;
  if (places === undefined || places === null) {
    if (fn === 'count') {
      places = 0;
    } else if (fn === 'avg') {
      places = 2;
    } else {
      places = Number.isInteger(number) ? 0 : 2;
    }
  }
  const fixed = Math.abs(number).toFixed(places);
  const [whole, fraction] = fixed.split('.');
  const sign = number < 0 && Number(fixed) !== 0 ? '-' : '';
  return `${sign}${whole.replace(/\B(?=(\d{3})+(?!\d))/g, thousands)}${fraction ? decimal + fraction : ''}`;
}

// As Aggregate::groupKey(): 3 and '3', true and 1 are the same group.
export function groupKey(value) {
  if (value === null || value === undefined) {
    return '\0';
  }
  if (typeof value === 'boolean') {
    return value ? '1' : '0';
  }
  return String(value);
}

// "Total 12,500" into a cell, as Grid::renderTotal() writes it: nothing while unknown.
export function writeTotal(cell, column, value, labels) {
  const text = formatTotal(value, column.total.function, column.total.decimals, labels);
  cell.replaceChildren();
  if (text === '') {
    return;
  }
  const label = document.createElement('span');
  label.className = 'caelrik-grid-total-label';
  label.textContent = labels[column.total.function] ?? column.total.function;
  const number = document.createElement('span');
  number.className = 'caelrik-grid-total-value';
  number.textContent = text;
  cell.append(label, ' ', number);
}

// The endpoint's totals or groups, from its last answer's meta.
function answer(grid, key) {
  return grid.ajax.json()?.meta?.[key];
}

function read(data, path) {
  return path.split('.').reduce((value, key) => (value == null ? undefined : value[key]), data);
}

export function connectSummary(grid, element, options) {
  const labels = options.summary;
  if (!labels) {
    return;
  }
  const serverSide = Boolean(options.url);
  const totals = (options.columns ?? []).filter((column) => column.total);
  const index = (field) => grid.column(`${field}:name`).index();
  const groupBy = options.groupBy ?? null;
  const groupIndex = groupBy ? index(groupBy) : null;

  // A client-side cell's value: the number PHP wrote in data-value, or its text.
  const cellValue = (row, field) => {
    const node = grid.cell(row, index(field)).node();
    return node ? (node.dataset.value ?? node.textContent) : '';
  };
  const filteredRows = () => grid.rows({ search: 'applied' }).indexes().toArray();
  const clientTotals = (rows) => Object.fromEntries(totals.map((column) => [
    column.field, aggregate(column.total.function, rows.map((row) => cellValue(row, column.field))),
  ]));

  const updateFooter = () => {
    const values = serverSide ? (answer(grid, 'totals') ?? {}) : clientTotals(filteredRows());
    for (const column of totals) {
      const cell = grid.column(index(column.field)).footer();
      if (cell) {
        writeTotal(cell, column, values[column.field] ?? null, labels);
      }
    }
  };

  const groupText = (row) => grid.cell(row, groupIndex).node()?.textContent.trim() ?? '';
  const groupOf = (row) => (serverSide ? groupKey(read(grid.row(row).data(), groupBy)) : groupText(row));

  // Every group of the filtered rows (client-side), or the endpoint's groups of the page.
  const groupInfo = () => {
    const info = new Map();
    if (serverSide) {
      for (const group of answer(grid, 'groups') ?? []) {
        info.set(groupKey(group.value), { count: group.count, totals: group.totals ?? {} });
      }
      return info;
    }
    const members = new Map();
    for (const row of filteredRows()) {
      const key = groupText(row);
      members.set(key, [...(members.get(key) ?? []), row]);
    }
    for (const [key, rows] of members) {
      info.set(key, { count: rows.length, totals: clientTotals(rows) });
    }
    return info;
  };

  const heading = (name, group, visible) => {
    const byIndex = new Map(totals.map((column) => [index(column.field), column]));
    const first = visible.findIndex((column) => byIndex.has(column));
    const span = first === -1 ? visible.length : Math.max(first, 1);

    const row = document.createElement('tr');
    row.className = 'caelrik-grid-group';
    const th = document.createElement('th');
    th.scope = 'rowgroup';
    th.colSpan = span;
    const label = document.createElement('span');
    label.className = 'caelrik-grid-group-name';
    label.textContent = name === '' ? labels.noGroup : name;
    th.append(label);
    if (group) {
      const count = document.createElement('span');
      count.className = 'caelrik-grid-group-count';
      const template = group.count === 1 ? labels.groupRow : labels.groupRows;
      count.textContent = template.replaceAll('{count}', formatTotal(group.count, 'count', 0, labels));
      th.append(' ', count);
    }
    row.append(th);
    // A total in the first column has no cell of its own: it follows the group's name.
    if (first === 0 && group) {
      const extra = document.createElement('span');
      extra.className = 'caelrik-grid-total';
      const column = byIndex.get(visible[0]);
      writeTotal(extra, column, group.totals[column.field] ?? null, labels);
      th.append(' ', extra);
    }
    for (const column of visible.slice(span)) {
      const cell = document.createElement('td');
      const total = byIndex.get(column);
      if (total) {
        cell.className = 'caelrik-grid-total';
        if (group) {
          writeTotal(cell, total, group.totals[total.field] ?? null, labels);
        }
      }
      row.append(cell);
    }
    return row;
  };

  const paintGroups = () => {
    element.tBodies[0]?.querySelectorAll('tr.caelrik-grid-group').forEach((row) => row.remove());
    const rows = grid.rows({ page: 'current' }).indexes().toArray();
    if (groupIndex === null || groupIndex === undefined || rows.length === 0) {
      return;
    }
    const info = groupInfo();
    const visible = grid.columns().indexes().toArray().filter((column) => grid.column(column).visible());
    let previous = null;
    for (const row of rows) {
      const key = groupOf(row);
      if (key === previous) {
        continue;
      }
      previous = key;
      const node = grid.row(row).node();
      node?.before(heading(groupText(row), info.get(key), visible));
    }
  };

  grid.on('draw', () => {
    updateFooter();
    paintGroups();
  });
  grid.on('column-visibility', paintGroups);
  // A cell changed in place (grid-edit.js): a client-side grid totals again. A server-side
  // grid's totals come with its next page of rows.
  element.addEventListener('caelrik:edit', (event) => {
    if (event.detail?.ok && !serverSide) {
      updateFooter();
      paintGroups();
    }
  });
  if (!serverSide) {
    updateFooter();
    paintGroups();
  }
}
