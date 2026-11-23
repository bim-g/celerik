// TreeList (PHP: Components\TreeList): a table whose rows nest like a tree. PHP writes every
// row in tree order, with its level, position and whether it opens; this makes the table a
// WAI-ARIA treegrid (https://www.w3.org/WAI/ARIA/apg/patterns/treegrid/):
// - rows open and close; a closed row's descendants are hidden;
// - one tab stop, on a row or a cell. On a row: Up and Down move, Right opens it or goes to
//   its first cell, Left closes it or goes to its parent, Home and End, Enter opens or
//   closes. On a cell: Left and Right move along the row (Left from the first cell goes to
//   the row), Up and Down along the column, Home and End to the row's ends, Enter uses the
//   cell's button or link;
// - Expand all and Collapse all buttons above the table, for pointer users.

export const LABELS = {
  expandAll: 'Expand all',
  collapseAll: 'Collapse all',
  actions: 'Actions',
};

const INTERACTIVE = 'a[href], button, input, select, textarea, [tabindex]:not([tabindex="-1"])';

export function initTreeList(element, options = {}) {
  const labels = { ...LABELS, ...options.labels };
  const body = element.tBodies[0];
  const rows = () => [...body.rows];
  const level = (row) => Number(row.dataset.level ?? 1);
  const expandable = (row) => row.hasAttribute('aria-expanded');
  const expanded = (row) => row.getAttribute('aria-expanded') === 'true';
  const byId = (id) => rows().find((row) => row.dataset.id === id) ?? null;
  const parentOf = (row) => (row.dataset.parent ? byId(row.dataset.parent) : null);
  const descendants = (row) => {
    const list = rows();
    const result = [];
    for (let index = list.indexOf(row) + 1; index < list.length && level(list[index]) > level(row); index += 1) {
      result.push(list[index]);
    }
    return result;
  };
  const visible = () => rows().filter((row) => !row.hidden);

  element.setAttribute('role', 'treegrid');

  // A closed row hides every row below it of a deeper level.
  const applyVisibility = () => {
    let hideBelow = null;
    for (const row of rows()) {
      if (hideBelow !== null && level(row) > hideBelow) {
        row.hidden = true;
        continue;
      }
      hideBelow = null;
      row.hidden = false;
      if (expandable(row) && !expanded(row)) {
        hideBelow = level(row);
      }
    }
  };

  const enhanceRow = (row) => {
    row.setAttribute('aria-level', row.dataset.level ?? '1');
    row.setAttribute('aria-setsize', row.dataset.size ?? '1');
    row.setAttribute('aria-posinset', row.dataset.position ?? '1');
    row.tabIndex = -1;
    if (row.dataset.expanded !== undefined) {
      row.setAttribute('aria-expanded', String(row.dataset.expanded === 'true' || Boolean(options.expandAll)));
    }
    const first = row.cells[0];
    if (first && !first.querySelector('.caelrik-treelist-toggle, .caelrik-treelist-spacer')) {
      const mark = document.createElement('span');
      mark.className = row.dataset.expanded !== undefined ? 'caelrik-treelist-toggle' : 'caelrik-treelist-spacer';
      mark.setAttribute('aria-hidden', 'true');
      first.prepend(mark);
    }
    for (const cell of row.cells) {
      cell.tabIndex = -1;
      // One tab stop for the whole table: a cell's controls are reached with Enter.
      cell.querySelectorAll(INTERACTIVE).forEach((control) => { control.tabIndex = -1; });
    }
  };

  const focusables = () => [...element.querySelectorAll('tbody tr, tbody td')];
  const focus = (target) => {
    if (!target) {
      return;
    }
    focusables().filter((node) => node.tabIndex === 0).forEach((node) => { node.tabIndex = -1; });
    target.tabIndex = 0;
    target.focus();
  };

  const setExpanded = (row, open) => {
    if (!expandable(row) || expanded(row) === open) {
      return;
    }
    row.setAttribute('aria-expanded', String(open));
    applyVisibility();
    element.dispatchEvent(new CustomEvent('caelrik:expand', {
      bubbles: true, detail: { id: row.dataset.id ?? null, expanded: open },
    }));
  };

  // Expand all and Collapse all, before the table.
  const toolbar = document.createElement('div');
  toolbar.className = 'caelrik-treelist-toolbar d-flex flex-wrap gap-2 mb-2';
  const tool = (text, open) => {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'btn btn-sm btn-outline-secondary caelrik-action-button';
    button.textContent = text;
    button.addEventListener('click', () => {
      rows().filter(expandable).forEach((row) => row.setAttribute('aria-expanded', String(open)));
      applyVisibility();
      // A focused row that is now hidden gives the focus to its top-level row.
      const current = element.querySelector('tbody [tabindex="0"]');
      const row = current?.closest('tr');
      if (row?.hidden) {
        let top = row;
        while (parentOf(top)) {
          top = parentOf(top);
        }
        top.tabIndex = 0;
        current.tabIndex = -1;
      }
    });
    return button;
  };
  toolbar.append(tool(labels.expandAll, true), tool(labels.collapseAll, false));
  element.before(toolbar);

  body.addEventListener('keydown', (event) => {
    if (event.altKey || event.ctrlKey || event.metaKey) {
      return;
    }
    const cell = event.target.matches('td') ? event.target : null;
    const row = event.target.matches('tr') ? event.target : cell?.closest('tr');
    if (!row || (!cell && event.target !== row)) {
      return;
    }
    const list = visible();
    const index = list.indexOf(row);
    let handled = true;
    if (!cell) {
      switch (event.key) {
        case 'ArrowDown': focus(list[index + 1]); break;
        case 'ArrowUp': focus(list[index - 1]); break;
        case 'Home': focus(list[0]); break;
        case 'End': focus(list[list.length - 1]); break;
        case 'ArrowRight':
          if (expandable(row) && !expanded(row)) {
            setExpanded(row, true);
          } else {
            focus(row.cells[0]);
          }
          break;
        case 'ArrowLeft':
          if (expanded(row)) {
            setExpanded(row, false);
          } else {
            focus(parentOf(row));
          }
          break;
        case 'Enter':
          setExpanded(row, !expanded(row));
          break;
        default:
          handled = false;
      }
    } else {
      const cells = [...row.cells];
      const column = cells.indexOf(cell);
      const sameColumn = (other) => other?.cells[Math.min(column, other.cells.length - 1)];
      switch (event.key) {
        case 'ArrowRight': focus(cells[column + 1]); break;
        case 'ArrowLeft': focus(column === 0 ? row : cells[column - 1]); break;
        case 'ArrowDown': focus(sameColumn(list[index + 1])); break;
        case 'ArrowUp': focus(sameColumn(list[index - 1])); break;
        case 'Home': focus(cells[0]); break;
        case 'End': focus(cells[cells.length - 1]); break;
        case 'Enter': {
          // The first control the user sees: not an action's hidden fields.
          const control = cell.querySelector('a[href], button, input:not([type=hidden]), select, textarea');
          if (control) {
            control.click();
          } else if (column === 0) {
            setExpanded(row, !expanded(row));
          }
          break;
        }
        default:
          handled = false;
      }
    }
    if (handled) {
      event.preventDefault();
    }
  });

  body.addEventListener('click', (event) => {
    const row = event.target.closest('tr');
    if (!row) {
      return;
    }
    if (event.target.closest('.caelrik-treelist-toggle')) {
      focus(row);
      setExpanded(row, !expanded(row));
      return;
    }
    if (!event.target.closest(INTERACTIVE)) {
      focus(event.target.closest('td') ?? row);
    }
  });

  // The tab stop follows focus, wherever it comes from.
  body.addEventListener('focusin', (event) => {
    const target = event.target.closest('td, tr');
    if (target && target.tabIndex !== 0 && element.contains(target)) {
      focusables().filter((node) => node.tabIndex === 0).forEach((node) => { node.tabIndex = -1; });
      target.tabIndex = 0;
    }
  });

  // A deleted row (a DELETE action's success) goes with its descendants; its siblings are
  // numbered again, and focus moves to the row that takes its place.
  element.addEventListener('caelrik:action', (event) => {
    const { ok, method } = event.detail ?? {};
    const row = event.target.closest('tr');
    if (!ok || method !== 'DELETE' || !row || !body.contains(row)) {
      return;
    }
    const parent = parentOf(row);
    const list = visible();
    const next = list[list.indexOf(row) + descendants(row).filter((other) => !other.hidden).length + 1]
      ?? list[list.indexOf(row) - 1];
    [row, ...descendants(row)].forEach((removed) => removed.remove());
    const siblings = rows().filter((other) => (other.dataset.parent ?? '') === (row.dataset.parent ?? ''));
    siblings.forEach((sibling, position) => {
      sibling.dataset.size = String(siblings.length);
      sibling.dataset.position = String(position + 1);
      sibling.setAttribute('aria-setsize', String(siblings.length));
      sibling.setAttribute('aria-posinset', String(position + 1));
    });
    if (parent && siblings.length === 0) {
      parent.removeAttribute('aria-expanded');
      delete parent.dataset.expanded;
      parent.querySelector('.caelrik-treelist-toggle')?.classList.replace('caelrik-treelist-toggle', 'caelrik-treelist-spacer');
    }
    if (next) {
      focus(next);
    } else {
      element.setAttribute('tabindex', '-1');
      element.focus();
    }
  });

  rows().forEach(enhanceRow);
  applyVisibility();
  const start = visible()[0];
  if (start) {
    start.tabIndex = 0;
  }

  return {
    expand: (id, open = true) => {
      const row = byId(String(id));
      if (row) {
        setExpanded(row, open);
      }
    },
  };
}
