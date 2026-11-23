// The Columns menu of a grid (Grid::columnToggle()): a disclosure button and a group of
// checkboxes, one per column the user may show or hide. Escape, or focus or a click outside
// it, closes the panel. The choice is kept in localStorage when the grid asks for it. Built
// with DOM methods and textContent only (CSP).

function create(tag, attributes = {}, text = null) {
  const element = document.createElement(tag);
  for (const [name, value] of Object.entries(attributes)) {
    if (value !== null && value !== undefined && value !== false) {
      element.setAttribute(name, value === true ? '' : value);
    }
  }
  if (text !== null) {
    element.textContent = text;
  }
  return element;
}

// The columns the user may show or hide: hidden() and toggleable(false) ones are left out.
export function toggleableColumns(columns) {
  return (columns ?? []).filter((column) => column.toggleable !== false);
}

export function storageKey(tableId) {
  return `caelrik:grid:${tableId}:columns`;
}

export function readSaved(tableId, storage = window.localStorage) {
  try {
    const saved = JSON.parse(storage.getItem(storageKey(tableId)) ?? '{}');
    return saved && typeof saved === 'object' && !Array.isArray(saved) ? saved : {};
  } catch {
    return {}; // storage blocked, or a value written by something else
  }
}

function save(tableId, state, storage) {
  try {
    storage.setItem(storageKey(tableId), JSON.stringify(state));
  } catch {
    // Private mode or a full storage: the choice lasts for this page only.
  }
}

// Builds the menu, before DataTables places it in its layout.
export function createColumnMenu(tableId, columns, labels) {
  const panelId = `${tableId}-columns`;
  const wrapper = create('div', { class: 'caelrik-grid-columns' });
  const button = create('button', {
    type: 'button',
    class: 'btn btn-sm btn-outline-secondary caelrik-action-button',
    'aria-expanded': 'false',
    'aria-controls': panelId,
  }, labels.columns);
  const panel = create('fieldset', { id: panelId, class: 'caelrik-grid-columns-panel', hidden: true });
  panel.append(create('legend', { class: 'visually-hidden' }, labels.showColumns));
  for (const column of toggleableColumns(columns)) {
    const id = `${tableId}-column-${column.field.replaceAll('.', '-')}`;
    const check = create('div', { class: 'form-check' });
    check.append(
      create('input', { type: 'checkbox', class: 'form-check-input', id, 'data-caelrik-column': column.field }),
      create('label', { class: 'form-check-label', for: id }, column.title ?? column.field),
    );
    panel.append(check);
  }
  wrapper.append(button, panel);
  return wrapper;
}

// Wires the menu to the grid: saved choices first, then the checkboxes.
export function connectColumnMenu(menu, grid, { tableId, columns, remember, storage = window.localStorage }) {
  const button = menu.querySelector('button');
  const panel = menu.querySelector('fieldset');
  const boxes = [...panel.querySelectorAll('input[data-caelrik-column]')];
  const column = (field) => grid.column(`${field}:name`);
  // Columns always shown (toggleable(false), not hidden()) mean the last box may be cleared.
  const pinned = (columns ?? []).some((entry) => entry.toggleable === false && entry.visible !== false);

  if (remember) {
    const saved = readSaved(tableId, storage);
    for (const box of boxes) {
      const field = box.dataset.caelrikColumn;
      if (typeof saved[field] === 'boolean') {
        column(field).visible(saved[field], false);
      }
    }
    grid.columns.adjust();
  }

  const sync = () => {
    for (const box of boxes) {
      box.checked = column(box.dataset.caelrikColumn).visible();
    }
    // Keep at least one column to read: the last shown one cannot be hidden.
    const shown = boxes.filter((box) => box.checked);
    for (const box of boxes) {
      box.disabled = !pinned && shown.length === 1 && box.checked;
    }
  };

  panel.addEventListener('change', (event) => {
    const box = event.target.closest('input[data-caelrik-column]');
    if (!box) {
      return;
    }
    column(box.dataset.caelrikColumn).visible(box.checked);
    grid.columns.adjust();
    if (remember) {
      save(tableId, Object.fromEntries(boxes.map((entry) => [entry.dataset.caelrikColumn, entry.checked])), storage);
    }
    sync();
  });

  const open = (opened) => {
    panel.hidden = !opened;
    button.setAttribute('aria-expanded', String(opened));
  };
  button.addEventListener('click', () => open(panel.hidden));
  menu.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && !panel.hidden) {
      event.stopPropagation();
      open(false);
      button.focus();
    }
  });
  menu.addEventListener('focusout', (event) => {
    if (event.relatedTarget && !menu.contains(event.relatedTarget)) {
      open(false);
    }
  });
  document.addEventListener('click', (event) => {
    if (!menu.contains(event.target)) {
      open(false);
    }
  });

  sync();
}
