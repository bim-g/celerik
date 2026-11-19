// TaskBoard (PHP: Components\TaskBoard): columns of cards that move between columns.
// - Every card has a Move form (a column choice and a button): it posts without JavaScript,
//   and with it, moves the card at once, without dragging (WCAG 2.5.7).
// - Cards are dragged with SortableJS, within and between columns; a full column (a limit)
//   takes no more cards.
// - From the keyboard, on a focused card: Space picks it up, the arrow keys move it (Up and
//   Down in its column, Left and Right to the next columns), Space drops it, Escape puts it
//   back. Each step is announced.
// Each move is sent to the card's Move form URL: column, position (from 0), _method and
// the CSRF token. A refused move puts the card back and says why.
import Sortable from 'sortablejs';

export const LABELS = {
  grabbed: 'Picked up {card}, in {column}, position {position} of {count}.',
  over: '{card}: {column}, position {position} of {count}.',
  moved: 'Moved {card} to {column}, position {position} of {count}.',
  cancelled: 'Move cancelled. {card} is back in {column}.',
  failed: 'The card could not be moved. It is back in {column}.',
  full: '{column} is full.',
  count: '{count} cards',
  countOne: '1 card',
  countLimit: '{count} of {limit} cards',
};

const format = (template, values) => Object.entries(values)
  .reduce((text, [key, value]) => text.replaceAll(`{${key}}`, String(value)), template);

// deps.fetch: for tests.
export function initTaskBoard(element, options = {}, deps = {}) {
  const labels = { ...LABELS, ...options.labels };
  const columns = options.columns ?? [];
  const send = deps.fetch ?? ((...args) => fetch(...args));
  const lists = () => [...element.querySelectorAll('.celerik-board-cards')];
  const listOf = (card) => card.closest('.celerik-board-cards');
  const columnOf = (list) => columns.find((column) => column.key === list.dataset.column) ?? { key: list.dataset.column, label: list.dataset.column };
  const cardsIn = (list) => [...list.children].filter((child) => child.matches('.celerik-board-card'));
  const titleOf = (card) => card.querySelector('.celerik-board-card-title')?.textContent.trim() ?? '';
  const full = (list, card) => {
    const { limit } = columnOf(list);
    return Boolean(limit) && cardsIn(list).filter((other) => other !== card).length >= limit;
  };
  const describe = (card) => {
    const list = listOf(card);
    return {
      card: titleOf(card),
      column: columnOf(list).label,
      position: cardsIn(list).indexOf(card) + 1,
      count: cardsIn(list).length,
    };
  };

  const status = document.createElement('div');
  status.className = 'visually-hidden';
  status.setAttribute('role', 'status');
  element.append(status);
  const announce = (text) => {
    status.textContent = '';
    setTimeout(() => { status.textContent = text; }, 50);
  };

  // Counts in the headings, and each card's Move choice, follow the cards.
  const refresh = () => {
    for (const list of lists()) {
      const column = columnOf(list);
      const count = cardsIn(list).length;
      const badge = element.querySelector(`.celerik-board-column[data-column="${list.dataset.column}"] [data-celerik-board-count]`);
      if (badge) {
        const [short, long] = badge.children;
        short.textContent = column.limit ? `${count}/${column.limit}` : String(count);
        if (column.limit) {
          long.textContent = format(labels.countLimit, { count, limit: column.limit });
        } else {
          long.textContent = count === 1 ? labels.countOne : format(labels.count, { count });
        }
      }
      for (const card of cardsIn(list)) {
        const select = card.querySelector('.celerik-board-move select');
        if (select) {
          select.value = list.dataset.column;
        }
      }
    }
  };

  const place = (card, list, index) => {
    const others = cardsIn(list).filter((other) => other !== card);
    list.insertBefore(card, others[index] ?? null);
  };

  // Sends a move; a refused one puts the card back where it was.
  const save = async (card, from, fromIndex) => {
    const form = card.querySelector('.celerik-board-move');
    const list = listOf(card);
    if (!form || !options.moveUrl) {
      return true;
    }
    const body = new FormData();
    body.append('column', list.dataset.column);
    body.append('position', String(cardsIn(list).indexOf(card)));
    const headers = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
    if (options.method && options.method !== 'POST') {
      body.append('_method', options.method);
    }
    if (options.csrf) {
      body.append(options.csrf.field, options.csrf.token);
      headers[options.csrf.header] = options.csrf.token;
    }
    card.setAttribute('aria-busy', 'true');
    let ok;
    let message = null;
    try {
      const response = await send(form.action, { method: 'POST', headers, body, credentials: 'same-origin' });
      ok = response.ok;
      if (!ok && (response.headers?.get('Content-Type') ?? '').includes('json')) {
        const answer = await response.json().catch(() => null);
        message = typeof answer?.message === 'string' ? answer.message : null;
      }
    } catch {
      ok = false;
    } finally {
      card.removeAttribute('aria-busy');
    }
    if (!ok) {
      const hadFocus = card.contains(document.activeElement);
      place(card, from, fromIndex);
      refresh();
      if (hadFocus) {
        card.focus();
      }
      announce(`${message ? `${message} ` : ''}${format(labels.failed, { column: columnOf(from).label })}`);
    }
    element.dispatchEvent(new CustomEvent('celerik:move', {
      bubbles: true,
      detail: {
        id: card.dataset.id ?? null, column: listOf(card).dataset.column, position: cardsIn(listOf(card)).indexOf(card), ok,
      },
    }));
    return ok;
  };

  const moved = async (card, from, fromIndex) => {
    refresh();
    const text = format(labels.moved, describe(card));
    if (await save(card, from, fromIndex)) {
      announce(text);
    }
  };

  // The Move form: the card goes last in the chosen column.
  element.addEventListener('submit', (event) => {
    const form = event.target.closest('.celerik-board-move');
    if (!form) {
      return;
    }
    event.preventDefault();
    const card = form.closest('.celerik-board-card');
    const from = listOf(card);
    const fromIndex = cardsIn(from).indexOf(card);
    const select = form.querySelector('select');
    const target = lists().find((list) => list.dataset.column === select.value);
    if (!target || target === from) {
      return;
    }
    if (full(target, card)) {
      select.value = from.dataset.column;
      announce(format(labels.full, { column: columnOf(target).label }));
      return;
    }
    target.append(card);
    select.focus();
    moved(card, from, fromIndex);
  });

  // Keyboard moves: Space picks up and drops, arrows move, Escape cancels.
  let grab = null;
  let moving = false;
  const keep = (card, action) => {
    moving = true;
    action();
    card.focus();
    moving = false;
  };
  const cancel = () => {
    if (!grab) {
      return;
    }
    const { card, from, fromIndex } = grab;
    grab = null;
    card.classList.remove('is-grabbed');
    keep(card, () => place(card, from, fromIndex));
    refresh();
    announce(format(labels.cancelled, { card: titleOf(card), column: columnOf(from).label }));
  };

  element.addEventListener('keydown', (event) => {
    const card = event.target.matches?.('.celerik-board-card') ? event.target : null;
    if (!card || event.altKey || event.ctrlKey || event.metaKey || !options.moveUrl) {
      return;
    }
    if (event.key === ' ') {
      event.preventDefault();
      if (!grab) {
        grab = { card, from: listOf(card), fromIndex: cardsIn(listOf(card)).indexOf(card) };
        card.classList.add('is-grabbed');
        announce(format(labels.grabbed, describe(card)));
        return;
      }
      const { from, fromIndex } = grab;
      grab = null;
      card.classList.remove('is-grabbed');
      if (listOf(card) === from && cardsIn(from).indexOf(card) === fromIndex) {
        announce(format(labels.cancelled, { card: titleOf(card), column: columnOf(from).label }));
        return;
      }
      moved(card, from, fromIndex);
      return;
    }
    if (!grab || grab.card !== card) {
      return;
    }
    const list = listOf(card);
    const index = cardsIn(list).indexOf(card);
    const all = lists();
    switch (event.key) {
      case 'Escape':
        cancel();
        break;
      case 'ArrowUp':
        if (index > 0) {
          keep(card, () => place(card, list, index - 1));
        }
        break;
      case 'ArrowDown':
        if (index < cardsIn(list).length - 1) {
          keep(card, () => place(card, list, index + 1));
        }
        break;
      case 'ArrowLeft':
      case 'ArrowRight': {
        const target = all[all.indexOf(list) + (event.key === 'ArrowLeft' ? -1 : 1)];
        if (!target) {
          break;
        }
        if (full(target, card)) {
          announce(format(labels.full, { column: columnOf(target).label }));
          event.preventDefault();
          return;
        }
        keep(card, () => place(card, target, index));
        break;
      }
      default:
        return;
    }
    event.preventDefault();
    if (grab) {
      refresh();
      announce(format(labels.over, describe(card)));
    }
  });

  // Leaving a picked-up card puts it back.
  element.addEventListener('focusout', (event) => {
    if (grab && !moving && event.target === grab.card) {
      cancel();
    }
  });

  // Dragging, unless the board has no moveUrl (read only).
  if (options.moveUrl) {
    const reduce = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
    let origin = null;
    for (const list of lists()) {
      Sortable.create(list, {
        group: {
          name: element.id || 'celerik-board',
          put: (to, from, card) => !full(to.el, card),
        },
        draggable: '.celerik-board-card',
        // The card's own controls keep working: they do not start a drag.
        filter: 'select, button, a, input, label',
        preventOnFilter: false,
        animation: reduce ? 0 : 150,
        ghostClass: 'is-ghost',
        chosenClass: 'is-chosen',
        onStart: (event) => {
          origin = { from: event.from, index: event.oldIndex };
        },
        onEnd: (event) => {
          if (!origin || (event.from === event.to && event.oldIndex === event.newIndex)) {
            origin = null;
            return;
          }
          const { from, index } = origin;
          origin = null;
          moved(event.item, from, index);
        },
      });
    }
  }

  refresh();
  return { refresh };
}
