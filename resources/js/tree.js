// TreeView (PHP: Components\TreeView). PHP writes nested lists that work without JavaScript;
// this turns them into a WAI-ARIA tree (https://www.w3.org/WAI/ARIA/apg/patterns/treeview/):
// - each node's row is the treeitem, named by its own text; it owns its children's group
//   (aria-owns), and the list items around them are presentational, as in the APG's
//   navigation tree;
// - one tab stop; Up and Down move, Right opens or goes to the first child, Left closes or
//   goes to the parent, Home and End, `*` opens the siblings, a letter jumps to the next
//   node starting with it;
// - Enter follows a node's link, or selects it, or opens it; Space selects or checks;
// - a selectable tree's radio buttons and a checkable tree's checkboxes stay the form's
//   values; a checked parent checks its children, and a parent with some is mixed;
// - a node marked `hasChildren` loads them from the tree's url when it first opens.
// New nodes are built with DOM methods and textContent only (CSP).
import { fill } from './action.js';

export const LABELS = {
  loading: 'Loading…',
  failed: 'The items could not be loaded.',
};

let trees = 0;

// As TreeView::safeUrl(): relative, http or https.
export function safeUrl(value) {
  const url = String(value ?? '').trim();
  // eslint-disable-next-line no-control-regex
  if (url === '' || /[\u0000- \u007f]/.test(url)) {
    return null;
  }
  const scheme = /^([A-Za-z][A-Za-z0-9+.-]*):/.exec(url);
  return !scheme || ['http', 'https'].includes(scheme[1].toLowerCase()) ? url : null;
}

// A node as TreeView::renderNode() writes it, for children from the endpoint.
export function createNode(node, options) {
  const li = document.createElement('li');
  li.className = 'caelrik-tree-node';
  const id = node.id === null || node.id === undefined ? null : String(node.id);
  if (id !== null) {
    li.dataset.id = id;
  }
  const children = Array.isArray(node.children) ? node.children : [];
  if (children.length > 0 || node.hasChildren) {
    li.dataset.expanded = node.expanded || options.expandAll ? 'true' : 'false';
  }
  if (node.hasChildren && children.length === 0) {
    li.dataset.hasChildren = '';
  }
  if (node.disabled) {
    li.dataset.disabled = '';
  }

  const row = document.createElement('div');
  row.className = 'caelrik-tree-row';
  const href = node.href ? safeUrl(node.href) : null;
  let content;
  if (options.mode && id !== null) {
    content = document.createElement('label');
    const input = document.createElement('input');
    input.type = options.mode === 'single' ? 'radio' : 'checkbox';
    input.className = 'form-check-input caelrik-tree-input';
    input.name = options.mode === 'single' ? options.name : `${options.name}[]`;
    input.value = id;
    input.checked = Boolean(node.checked);
    input.disabled = Boolean(node.disabled);
    const text = document.createElement('span');
    text.textContent = String(node.label ?? '');
    content.append(input, text);
  } else if (href) {
    content = document.createElement('a');
    content.href = href;
    content.textContent = String(node.label ?? '');
  } else {
    content = document.createElement('span');
    content.textContent = String(node.label ?? '');
  }
  content.classList.add('caelrik-tree-label');
  row.append(content);
  li.append(row);

  if (children.length > 0) {
    const group = document.createElement('ul');
    group.className = 'caelrik-tree-group';
    children.forEach((child) => group.append(createNode(child, options)));
    li.append(group);
  }
  return li;
}

// A treeitem is a node's row; its list item holds the node's data and its children's group.
const nodeOf = (item) => item.parentElement;
const groupOf = (item) => [...nodeOf(item).children].find((child) => child.matches('ul'));
const rowsIn = (list) => [...(list?.children ?? [])]
  .map((li) => li.querySelector(':scope > .caelrik-tree-row'))
  .filter(Boolean);
const childItems = (item) => rowsIn(groupOf(item));
const parentItem = (item) => nodeOf(item).parentElement?.closest('li')?.querySelector(':scope > .caelrik-tree-row') ?? null;
const inputOf = (item) => item.querySelector('.caelrik-tree-input');
const labelOf = (item) => item.querySelector('.caelrik-tree-label');
const expandable = (item) => item.hasAttribute('aria-expanded');
const expanded = (item) => item.getAttribute('aria-expanded') === 'true';
const disabled = (item) => item.getAttribute('aria-disabled') === 'true';

export function initTree(element, options = {}, deps = {}) {
  const root = element.querySelector('.caelrik-tree-root');
  const labels = { ...LABELS, ...options.labels };
  const mode = options.mode ?? null;
  const send = deps.fetch ?? ((...args) => fetch(...args));
  trees += 1;
  const prefix = `caelrik-tree-${trees}`;
  let groups = 0;

  const status = document.createElement('div');
  status.className = 'visually-hidden';
  status.setAttribute('role', 'status');
  element.append(status);
  const announce = (text) => {
    status.textContent = '';
    if (text) {
      setTimeout(() => { status.textContent = text; }, 50);
    }
  };

  root.setAttribute('role', 'tree');

  // A group: its role and id, owned by its node's row.
  const ownGroup = (item, group) => {
    groups += 1;
    group.id ||= `${prefix}-group-${groups}`;
    group.setAttribute('role', 'group');
    item.setAttribute('aria-owns', group.id);
  };

  // Roles, levels, positions and states for the nodes in a list, and their children.
  const enhance = (list, level) => {
    const nodes = [...list.children].filter((child) => child.matches('li'));
    nodes.forEach((li, index) => {
      const item = li.querySelector(':scope > .caelrik-tree-row');
      if (!item) {
        return;
      }
      li.setAttribute('role', 'none');
      item.setAttribute('role', 'treeitem');
      item.setAttribute('aria-level', String(level));
      item.setAttribute('aria-setsize', String(nodes.length));
      item.setAttribute('aria-posinset', String(index + 1));
      item.tabIndex = -1;
      // The row takes the focus, not its link or its control.
      const label = labelOf(item);
      if (label?.matches('a')) {
        label.tabIndex = -1;
      }
      if (li.dataset.disabled !== undefined) {
        item.setAttribute('aria-disabled', 'true');
      }
      const input = inputOf(item);
      if (input) {
        input.tabIndex = -1;
        input.setAttribute('aria-hidden', 'true');
        item.setAttribute(mode === 'single' ? 'aria-selected' : 'aria-checked', String(input.checked));
      }
      if (!item.querySelector('.caelrik-tree-toggle, .caelrik-tree-spacer')) {
        const mark = document.createElement('span');
        mark.className = li.dataset.expanded !== undefined ? 'caelrik-tree-toggle' : 'caelrik-tree-spacer';
        mark.setAttribute('aria-hidden', 'true');
        item.prepend(mark);
      }
      const group = groupOf(item);
      if (li.dataset.expanded !== undefined) {
        const open = li.dataset.expanded === 'true' || Boolean(options.expandAll);
        item.setAttribute('aria-expanded', String(open));
        if (group) {
          group.hidden = !open;
        }
      }
      if (group) {
        ownGroup(item, group);
        enhance(group, level + 1);
      }
    });
  };

  const all = () => [...root.querySelectorAll('.caelrik-tree-row[role=treeitem]')];
  const visible = () => all().filter((item) => !item.closest('ul[hidden]'));

  const focus = (item) => {
    if (!item) {
      return;
    }
    all().filter((other) => other.tabIndex === 0).forEach((other) => { other.tabIndex = -1; });
    item.tabIndex = 0;
    item.focus();
  };

  const changed = () => {
    const values = [...root.querySelectorAll('.caelrik-tree-input')]
      .filter((input) => input.checked)
      .map((input) => input.value);
    element.dispatchEvent(new CustomEvent('caelrik:change', {
      bubbles: true,
      detail: { value: mode === 'single' ? (values[0] ?? null) : values },
    }));
  };

  // A checkable tree: a node's state, then its parents' from their children.
  const setChecked = (item, state) => {
    item.setAttribute('aria-checked', String(state));
    const input = inputOf(item);
    if (input) {
      input.checked = state === true;
      input.indeterminate = state === 'mixed';
    }
  };
  const refreshAncestors = (item) => {
    for (let parent = parentItem(item); parent; parent = parentItem(parent)) {
      const states = childItems(parent).map((child) => child.getAttribute('aria-checked'));
      if (states.length === 0) {
        continue;
      }
      let state = 'mixed';
      if (states.every((value) => value === 'true')) {
        state = true;
      } else if (states.every((value) => value === 'false')) {
        state = false;
      }
      setChecked(parent, state);
    }
  };
  const checkTree = (item, state) => {
    setChecked(item, state);
    childItems(item).filter((child) => !disabled(child)).forEach((child) => checkTree(child, state));
  };

  const activate = (item) => {
    if (disabled(item) || !inputOf(item)) {
      return;
    }
    if (mode === 'single') {
      all().filter((other) => other.getAttribute('aria-selected') === 'true')
        .forEach((other) => other.setAttribute('aria-selected', 'false'));
      item.setAttribute('aria-selected', 'true');
      inputOf(item).checked = true;
    } else {
      checkTree(item, item.getAttribute('aria-checked') !== 'true');
      refreshAncestors(item);
    }
    changed();
  };

  const load = async (item) => {
    const li = nodeOf(item);
    delete li.dataset.hasChildren;
    item.setAttribute('aria-busy', 'true');
    announce(labels.loading);
    try {
      const url = new URL(fill(options.url, { id: li.dataset.id ?? '' }, true), document.baseURI);
      const response = await send(url.href, {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
      });
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }
      const payload = await response.json();
      const children = Array.isArray(payload?.data) ? payload.data : [];
      if (children.length === 0) {
        // No children after all: the node no longer opens.
        item.removeAttribute('aria-expanded');
        item.querySelector('.caelrik-tree-toggle')?.classList.replace('caelrik-tree-toggle', 'caelrik-tree-spacer');
        announce('');
        return;
      }
      const group = document.createElement('ul');
      group.className = 'caelrik-tree-group';
      children.forEach((child) => group.append(createNode(child, options)));
      group.hidden = !expanded(item);
      li.append(group);
      ownGroup(item, group);
      enhance(group, Number(item.getAttribute('aria-level')) + 1);
      if (mode === 'multiple') {
        // Children of a checked node are checked; otherwise the node follows its children.
        if (item.getAttribute('aria-checked') === 'true') {
          checkTree(item, true);
        } else {
          childItems(item).filter((child) => child.getAttribute('aria-checked') === 'true')
            .forEach((child) => checkTree(child, true));
          refreshAncestors(childItems(item)[0]);
        }
      }
      announce('');
    } catch {
      li.dataset.hasChildren = '';
      item.setAttribute('aria-expanded', 'false');
      announce(labels.failed);
    } finally {
      item.removeAttribute('aria-busy');
    }
  };

  const setExpanded = (item, open) => {
    if (!expandable(item) || expanded(item) === open) {
      return;
    }
    item.setAttribute('aria-expanded', String(open));
    const group = groupOf(item);
    if (group) {
      group.hidden = !open;
    }
    const li = nodeOf(item);
    element.dispatchEvent(new CustomEvent('caelrik:expand', {
      bubbles: true, detail: { id: li.dataset.id ?? null, expanded: open },
    }));
    if (open && li.dataset.hasChildren !== undefined && options.url) {
      load(item);
    }
  };

  root.addEventListener('keydown', (event) => {
    const item = event.target.closest('[role=treeitem]');
    if (!item || event.altKey || event.ctrlKey || event.metaKey) {
      return;
    }
    const items = visible();
    const index = items.indexOf(item);
    let handled = true;
    switch (event.key) {
      case 'ArrowDown':
        focus(items[index + 1]);
        break;
      case 'ArrowUp':
        focus(items[index - 1]);
        break;
      case 'Home':
        focus(items[0]);
        break;
      case 'End':
        focus(items[items.length - 1]);
        break;
      case 'ArrowRight':
        if (expandable(item) && !expanded(item)) {
          setExpanded(item, true);
        } else if (expanded(item)) {
          focus(childItems(item)[0]);
        }
        break;
      case 'ArrowLeft':
        if (expanded(item)) {
          setExpanded(item, false);
        } else {
          focus(parentItem(item));
        }
        break;
      case 'Enter': {
        const label = labelOf(item);
        if (label?.matches('a')) {
          label.click();
        } else if (mode && inputOf(item)) {
          activate(item);
        } else {
          setExpanded(item, !expanded(item));
        }
        break;
      }
      case ' ':
        if (mode && inputOf(item)) {
          activate(item);
        } else {
          setExpanded(item, !expanded(item));
        }
        break;
      case '*': {
        const parent = parentItem(item);
        (parent ? childItems(parent) : rowsIn(root)).forEach((sibling) => setExpanded(sibling, true));
        break;
      }
      default:
        if (event.key.length === 1 && /\S/.test(event.key)) {
          const key = event.key.toLocaleLowerCase();
          const ordered = [...items.slice(index + 1), ...items.slice(0, index + 1)];
          focus(ordered.find((other) => labelOf(other)?.textContent.trim().toLocaleLowerCase().startsWith(key)));
        } else {
          handled = false;
        }
    }
    if (handled) {
      event.preventDefault();
    }
  });

  root.addEventListener('click', (event) => {
    const item = event.target.closest('[role=treeitem]');
    if (!item) {
      return;
    }
    focus(item);
    if (event.target.closest('.caelrik-tree-toggle')) {
      setExpanded(item, !expanded(item));
      return;
    }
    if (mode && inputOf(item)) {
      // The label would also toggle its input: the tree does it, once.
      event.preventDefault();
      activate(item);
    } else if (!labelOf(item)?.matches('a')) {
      setExpanded(item, !expanded(item));
    }
  });

  // The tab stop follows focus, wherever it comes from.
  root.addEventListener('focusin', (event) => {
    const item = event.target.closest('[role=treeitem]');
    if (item && item.tabIndex !== 0) {
      all().filter((other) => other.tabIndex === 0).forEach((other) => { other.tabIndex = -1; });
      item.tabIndex = 0;
    }
  });

  enhance(root, 1);
  if (mode === 'multiple') {
    // A checked parent checks its children; then parents follow their children.
    all().filter((item) => item.getAttribute('aria-checked') === 'true').forEach((item) => checkTree(item, true));
    all().filter((item) => childItems(item).length === 0).forEach(refreshAncestors);
  }
  const start = visible().find((item) => item.getAttribute('aria-selected') === 'true'
    || item.getAttribute('aria-checked') === 'true') ?? visible()[0];
  if (start) {
    start.tabIndex = 0;
  }

  return {
    expand: (id, open = true) => {
      const item = all().find((row) => nodeOf(row).dataset.id === String(id));
      if (item) {
        setExpanded(item, open);
      }
    },
  };
}
