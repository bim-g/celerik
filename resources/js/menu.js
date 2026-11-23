// Menus, after the WAI-ARIA patterns (https://www.w3.org/WAI/ARIA/apg/patterns/menu-button/,
// https://www.w3.org/WAI/ARIA/apg/patterns/toolbar/):
// - a menu button (MenuButton, SplitButton's arrow): Enter, Space or Down opens its menu on
//   the first item, Up on the last; in the menu, Down, Up, Home, End and a letter move,
//   Enter and Space choose, Escape closes and returns focus to the button, Tab closes;
// - a context menu (ContextMenu): a right click, Shift+F10 or the Menu key on a target opens
//   it there, written for that target;
// - a toolbar (Toolbar): one tab stop; Left, Right, Home and End move between its controls;
// - a horizontal navigation (Navigation::horizontal()): its groups close with Escape, when
//   another opens, and when focus or a click leaves them.
// A menu's items are links and the buttons of request actions; choosing an action closes the
// menu and gives focus back to its button before the action runs, so a confirmation returns
// focus there.
import { actionHtml, fill } from './action.js';
import { escapeHtml } from './notification-config.js';

let menus = 0;

const FOCUSABLE = 'a[href], button:not([disabled]), input:not([type=hidden]):not([disabled]), select:not([disabled]), textarea:not([disabled]), summary, [tabindex]:not([tabindex="-1"])';

// Roles for a plain list of items (HasMenuItems' markup): the list is a menu, its items
// menuitems (a request action's button), its dividers separators.
export function menuRoles(list, label = null) {
  list.setAttribute('role', 'menu');
  if (label) {
    list.setAttribute('aria-label', label);
  }
  for (const li of list.children) {
    if (li.classList.contains('caelrik-menu-divider')) {
      li.setAttribute('role', 'separator');
      continue;
    }
    li.setAttribute('role', 'none');
    const item = li.querySelector('.caelrik-menu-item, form.caelrik-action button, a');
    if (!item) {
      continue;
    }
    item.setAttribute('role', 'menuitem');
    item.tabIndex = -1;
    if (item.dataset.disabled !== undefined) {
      item.setAttribute('aria-disabled', 'true');
    }
  }
}

// The keys and focus of an open menu; close(refocus) closes it.
function menuKeys(list, { close, owner }) {
  const items = () => [...list.querySelectorAll('[role=menuitem]')];
  const focus = (item) => item?.focus();
  list.addEventListener('keydown', (event) => {
    const all = items();
    const index = all.indexOf(document.activeElement);
    switch (event.key) {
      case 'ArrowDown': focus(all[(index + 1) % all.length]); break;
      case 'ArrowUp': focus(all[(index - 1 + all.length) % all.length]); break;
      case 'Home': focus(all[0]); break;
      case 'End': focus(all[all.length - 1]); break;
      case 'Escape': close(true); break;
      case 'Tab': close(false); return;
      case ' ':
      case 'Enter': {
        const item = event.target.closest('[role=menuitem]');
        if (item) {
          event.preventDefault();
          choose(item);
        }
        return;
      }
      default:
        if (event.key.length === 1 && /\S/.test(event.key)) {
          const key = event.key.toLocaleLowerCase();
          const ordered = [...all.slice(index + 1), ...all.slice(0, index + 1)];
          focus(ordered.find((item) => item.textContent.trim().toLocaleLowerCase().startsWith(key)));
          break;
        }
        return;
    }
    event.preventDefault();
  });
  // A click chooses too; a disabled item does nothing.
  list.addEventListener('click', (event) => {
    const item = event.target.closest('[role=menuitem]');
    if (item?.getAttribute('aria-disabled') === 'true') {
      event.preventDefault();
      return;
    }
    if (item) {
      event.preventDefault();
      choose(item);
    }
  });
  // Focus back on the menu's button, then the item does what it does.
  function choose(item) {
    if (item.getAttribute('aria-disabled') === 'true') {
      return;
    }
    close(true);
    if (item.matches('a[href]')) {
      window.location.assign(item.href);
    } else if (item.form) {
      item.form.requestSubmit(item.matches('button') ? item : undefined);
    } else {
      item.click();
    }
  }
  // Focus or a click elsewhere closes it.
  list.addEventListener('focusout', (event) => {
    if (event.relatedTarget && !list.contains(event.relatedTarget) && !owner?.contains(event.relatedTarget)) {
      close(false);
    }
  });
  return { items, focus };
}

// MenuButton and SplitButton: a button that opens the list after it.
export function initMenuButton(element) {
  const button = element.querySelector('.caelrik-menu-toggle');
  const list = element.querySelector('.caelrik-menu-list');
  if (!button || !list) {
    return null;
  }
  menus += 1;
  list.id ||= `caelrik-menu-${menus}`;
  menuRoles(list);
  list.setAttribute('aria-labelledby', button.id || (button.id = `${list.id}-button`));
  button.setAttribute('aria-haspopup', 'menu');
  button.setAttribute('aria-expanded', 'false');
  button.setAttribute('aria-controls', list.id);
  list.hidden = true;

  const outside = (event) => {
    if (!element.contains(event.target)) {
      close(false);
    }
  };
  function close(refocus) {
    if (list.hidden) {
      return;
    }
    list.hidden = true;
    button.setAttribute('aria-expanded', 'false');
    document.removeEventListener('pointerdown', outside);
    if (refocus) {
      button.focus();
    }
  }
  const keys = menuKeys(list, { close, owner: element });
  const open = (where = 'first') => {
    list.hidden = false;
    button.setAttribute('aria-expanded', 'true');
    document.addEventListener('pointerdown', outside);
    const items = keys.items().filter((item) => item.getAttribute('aria-disabled') !== 'true');
    keys.focus(where === 'last' ? items[items.length - 1] : items[0]);
  };

  button.addEventListener('click', () => (list.hidden ? open() : close(true)));
  button.addEventListener('keydown', (event) => {
    if (['ArrowDown', 'Enter', ' '].includes(event.key)) {
      event.preventDefault();
      open('first');
    } else if (event.key === 'ArrowUp') {
      event.preventDefault();
      open('last');
    } else if (event.key === 'Escape') {
      close(true);
    }
  });
  return { open, close: () => close(false) };
}

// ContextMenu: built for each target, from the PHP options.
export function itemsHtml(items, row) {
  return items.map((item) => {
    if (item.type === 'divider') {
      return '<li class="caelrik-menu-divider"></li>';
    }
    if (item.type === 'action') {
      return `<li>${actionHtml(item.action, row, null, false)}</li>`;
    }
    return `<li><a class="caelrik-menu-item" href="${escapeHtml(fill(item.url, row, true))}">${escapeHtml(fill(item.label, row))}</a></li>`;
  }).join('');
}

export function initContextMenu(element, options = {}) {
  const list = document.createElement('ul');
  list.className = 'caelrik-menu-list caelrik-contextmenu-list list-unstyled';
  menus += 1;
  list.id = `caelrik-menu-${menus}`;
  list.hidden = true;
  document.body.append(list);
  let opener = null;

  const outside = (event) => {
    if (!list.contains(event.target)) {
      close(false);
    }
  };
  function close(refocus) {
    if (list.hidden) {
      return;
    }
    list.hidden = true;
    document.removeEventListener('pointerdown', outside);
    if (refocus) {
      opener?.focus();
    }
  }
  const keys = menuKeys(list, { close });

  // A target's values: its data-* attributes, for {field} in the items.
  const open = (target, x, y) => {
    opener = target.matches(FOCUSABLE) ? target : target.querySelector(FOCUSABLE) ?? target;
    list.innerHTML = itemsHtml(options.items ?? [], { ...target.dataset });
    menuRoles(list, options.label ?? null);
    list.hidden = false;
    list.style.left = '0px';
    list.style.top = '0px';
    const { width, height } = list.getBoundingClientRect();
    list.style.left = `${Math.max(0, Math.min(x, window.innerWidth - width - 4))}px`;
    list.style.top = `${Math.max(0, Math.min(y, window.innerHeight - height - 4))}px`;
    document.addEventListener('pointerdown', outside);
    keys.focus(keys.items()[0]);
  };

  document.addEventListener('contextmenu', (event) => {
    const target = options.target ? event.target.closest(options.target) : null;
    if (!target) {
      return;
    }
    event.preventDefault();
    open(target, event.clientX, event.clientY);
  });
  document.addEventListener('keydown', (event) => {
    if (!(event.key === 'ContextMenu' || (event.key === 'F10' && event.shiftKey))) {
      return;
    }
    const target = options.target ? document.activeElement?.closest(options.target) : null;
    if (!target) {
      return;
    }
    event.preventDefault();
    const rect = target.getBoundingClientRect();
    open(target, rect.left + 8, rect.bottom);
  });
  window.addEventListener('scroll', () => close(false), { passive: true });
  return { open, close: () => close(false), element: list };
}

// Toolbar: one tab stop, arrows between its controls.
export function initToolbar(element) {
  const controls = () => [...element.querySelectorAll(FOCUSABLE)]
    .filter((control) => !control.closest('[role=menu]') && !control.closest('[hidden]'));
  const setStop = (target) => {
    controls().forEach((control) => { control.tabIndex = control === target ? 0 : -1; });
  };
  setStop(controls()[0]);
  element.addEventListener('focusin', (event) => {
    if (controls().includes(event.target)) {
      setStop(event.target);
    }
  });
  element.addEventListener('keydown', (event) => {
    const all = controls();
    const index = all.indexOf(event.target);
    if (index === -1) {
      return;
    }
    // A field keeps its own Left and Right.
    if (event.target.matches('input, textarea, select') && ['ArrowLeft', 'ArrowRight'].includes(event.key)) {
      return;
    }
    const next = {
      ArrowRight: all[(index + 1) % all.length],
      ArrowLeft: all[(index - 1 + all.length) % all.length],
      Home: all[0],
      End: all[all.length - 1],
    }[event.key];
    if (next) {
      event.preventDefault();
      setStop(next);
      next.focus();
    }
  });
  return { refresh: () => setStop(controls().find((control) => control.tabIndex === 0) ?? controls()[0]) };
}

// Navigation::horizontal(): its groups are disclosures that close like a menu.
export function initNavigation(element, options = {}) {
  if (!options.horizontal) {
    return true;
  }
  const groups = [...element.querySelectorAll('details.caelrik-nav-group')];
  const closeAll = (except = null) => groups.forEach((group) => {
    if (group !== except) {
      group.open = false;
    }
  });
  for (const group of groups) {
    group.addEventListener('toggle', () => {
      if (group.open) {
        closeAll(group);
      }
    });
    group.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && group.open) {
        event.preventDefault();
        group.open = false;
        group.querySelector('summary')?.focus();
      }
    });
    group.addEventListener('focusout', (event) => {
      if (event.relatedTarget && !group.contains(event.relatedTarget)) {
        group.open = false;
      }
    });
  }
  document.addEventListener('pointerdown', (event) => {
    if (!element.contains(event.target)) {
      closeAll();
    }
  });
  return true;
}
