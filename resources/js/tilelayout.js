// TileLayout (PHP: Components\TileLayout): a dashboard of tiles the user rearranges.
// - Each tile's Arrange button shows its tools: Move earlier, Move later, Narrower, Wider,
//   Shorter, Taller, buttons for one click or the keyboard (WCAG 2.5.7); Escape hides them.
// - On the Arrange button, the arrow keys move the tile (Left and Up earlier, Right and Down
//   later); with Shift they resize it (Left and Right its width, Up and Down its height).
// - The Arrange button can be dragged onto another tile's place, the corner grip to a size.
// Tiles move in the page, so the reading and focus order follow what is seen. Spans are
// classes (no inline styles, for a strict CSP). Each change is announced, kept in the browser
// on request (remember), and fired as celerik:change with the layout.
export const LABELS = {
  moved: '{tile}: position {position} of {count}.',
  resized: '{tile}: width {cols} of {columns}, height {rows} of {maxRows}.',
};

export const MAX_ROWS = 6;
const DRAG_DISTANCE = 6;

const format = (template, values) => Object.entries(values)
  .reduce((text, [key, value]) => text.replaceAll(`{${key}}`, String(value)), template);

const clamp = (value, low, high) => Math.min(high, Math.max(low, value));

function readLayout(key) {
  try {
    const saved = JSON.parse(window.localStorage.getItem(key) ?? 'null');
    return Array.isArray(saved) ? saved.filter((tile) => tile && typeof tile.key === 'string') : null;
  } catch {
    return null;
  }
}

export function initTileLayout(element, options = {}) {
  const labels = { ...LABELS, ...options.labels };
  const columns = Number(options.columns ?? 3);
  const reorderable = Boolean(options.reorderable);
  const resizable = Boolean(options.resizable);
  const storageKey = `celerik-tilelayout:${element.id}`;
  const tiles = () => [...element.children].filter((child) => child.classList.contains('celerik-tile'));
  const titleOf = (tile) => tile.querySelector('.celerik-tile-title')?.textContent.trim() ?? '';
  const spanOf = (tile) => ({ cols: Number(tile.dataset.cols ?? 1), rows: Number(tile.dataset.rows ?? 1) });

  const layout = () => tiles().map((tile) => ({ key: tile.dataset.tile, ...spanOf(tile) }));

  // The tools' buttons that cannot act say so, and stay focusable.
  const refreshTools = () => {
    const all = tiles();
    for (const [index, tile] of all.entries()) {
      const { cols, rows } = spanOf(tile);
      const off = {
        earlier: index === 0,
        later: index === all.length - 1,
        narrower: cols <= 1,
        wider: cols >= columns,
        shorter: rows <= 1,
        taller: rows >= MAX_ROWS,
      };
      for (const button of tile.querySelectorAll('[data-tile-command]')) {
        if (off[button.dataset.tileCommand]) {
          button.setAttribute('aria-disabled', 'true');
        } else {
          button.removeAttribute('aria-disabled');
        }
      }
    }
  };

  const setSpan = (tile, cols, rows) => {
    const old = spanOf(tile);
    tile.classList.remove(`celerik-tile-cols-${old.cols}`, `celerik-tile-rows-${old.rows}`);
    tile.classList.add(`celerik-tile-cols-${cols}`, `celerik-tile-rows-${rows}`);
    tile.dataset.cols = String(cols);
    tile.dataset.rows = String(rows);
  };

  // A saved layout: its tiles in its order, then the tiles it does not know.
  if (options.remember) {
    const saved = readLayout(storageKey);
    if (saved) {
      const byKey = new Map(tiles().map((tile) => [tile.dataset.tile, tile]));
      const rest = tiles();
      let before = rest[0] ?? null;
      for (const entry of saved) {
        const tile = byKey.get(entry.key);
        if (!tile) {
          continue;
        }
        byKey.delete(entry.key);
        element.insertBefore(tile, before);
        before = tile.nextSibling;
        const cols = clamp(Math.round(Number(entry.cols) || 1), 1, columns);
        const rows = clamp(Math.round(Number(entry.rows) || 1), 1, MAX_ROWS);
        setSpan(tile, cols, rows);
      }
    }
  }
  for (const tile of tiles()) {
    const { cols, rows } = spanOf(tile);
    if (cols > columns) {
      setSpan(tile, columns, rows);
    }
  }

  const status = document.createElement('div');
  status.className = 'visually-hidden';
  status.setAttribute('role', 'status');
  element.append(status);
  const announce = (text) => {
    status.textContent = '';
    setTimeout(() => { status.textContent = text; }, 50);
  };

  const changed = () => {
    refreshTools();
    const current = layout();
    if (options.remember) {
      try {
        window.localStorage.setItem(storageKey, JSON.stringify(current));
      } catch {
        // Storage may be off: the layout lasts until the page is left.
      }
    }
    element.dispatchEvent(new CustomEvent('celerik:change', { bubbles: true, detail: { tiles: current } }));
  };

  const announceMove = (tile) => announce(format(labels.moved, {
    tile: titleOf(tile),
    position: tiles().indexOf(tile) + 1,
    count: tiles().length,
  }));
  const announceSize = (tile) => announce(format(labels.resized, {
    tile: titleOf(tile),
    columns,
    maxRows: MAX_ROWS,
    ...spanOf(tile),
  }));

  // Moves a tile to a position (from 0), keeping focus where it was.
  const moveTo = (tile, position) => {
    const all = tiles();
    const from = all.indexOf(tile);
    const to = clamp(position, 0, all.length - 1);
    if (from === to) {
      return false;
    }
    const focused = document.activeElement;
    const others = all.filter((other) => other !== tile);
    element.insertBefore(tile, to < others.length ? others[to] : others[others.length - 1].nextSibling);
    if (focused instanceof HTMLElement && focused !== document.activeElement) {
      focused.focus();
    }
    return true;
  };

  const resizeTo = (tile, cols, rows) => {
    const next = { cols: clamp(cols, 1, columns), rows: clamp(rows, 1, MAX_ROWS) };
    const { cols: oldCols, rows: oldRows } = spanOf(tile);
    if (next.cols === oldCols && next.rows === oldRows) {
      return false;
    }
    setSpan(tile, next.cols, next.rows);
    return true;
  };

  const run = (tile, command) => {
    const index = tiles().indexOf(tile);
    const { cols, rows } = spanOf(tile);
    const moves = { earlier: index - 1, later: index + 1 };
    const sizes = {
      narrower: [cols - 1, rows],
      wider: [cols + 1, rows],
      shorter: [cols, rows - 1],
      taller: [cols, rows + 1],
    };
    if (command in moves && reorderable) {
      if (moveTo(tile, moves[command])) {
        changed();
      }
      announceMove(tile);
    } else if (command in sizes && resizable) {
      if (resizeTo(tile, ...sizes[command])) {
        changed();
      }
      announceSize(tile);
    }
  };

  const KEYS = {
    ArrowLeft: ['earlier', 'narrower'],
    ArrowUp: ['earlier', 'shorter'],
    ArrowRight: ['later', 'wider'],
    ArrowDown: ['later', 'taller'],
  };

  for (const tile of tiles()) {
    const handle = tile.querySelector('.celerik-tile-handle');
    const tools = tile.querySelector('.celerik-tile-tools');
    if (!handle || !tools) {
      continue;
    }
    const show = (open) => {
      handle.setAttribute('aria-expanded', String(open));
      tools.hidden = !open;
    };
    let dragged = false;

    handle.addEventListener('click', () => {
      if (dragged) {
        dragged = false;
        return;
      }
      show(handle.getAttribute('aria-expanded') !== 'true');
    });

    handle.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && !tools.hidden) {
        event.preventDefault();
        show(false);
        return;
      }
      const commands = KEYS[event.key];
      if (!commands || event.altKey || event.ctrlKey || event.metaKey) {
        return;
      }
      if ((event.shiftKey && !resizable) || (!event.shiftKey && !reorderable)) {
        return;
      }
      event.preventDefault();
      run(tile, commands[event.shiftKey ? 1 : 0]);
    });

    tools.addEventListener('click', (event) => {
      const button = event.target.closest('[data-tile-command]');
      if (!button || button.getAttribute('aria-disabled') === 'true') {
        return;
      }
      run(tile, button.dataset.tileCommand);
    });
    tools.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') {
        event.preventDefault();
        show(false);
        handle.focus();
      }
    });

    // Dragging the Arrange button: the tile takes the place of the tile under the pointer.
    if (reorderable) {
      handle.addEventListener('pointerdown', (event) => {
        if (event.button !== 0) {
          return;
        }
        const startX = event.clientX;
        const startY = event.clientY;
        const from = tiles().indexOf(tile);
        let dragging = false;
        dragged = false;
        handle.setPointerCapture?.(event.pointerId);
        const move = (moveEvent) => {
          if (!dragging) {
            if (Math.hypot(moveEvent.clientX - startX, moveEvent.clientY - startY) < DRAG_DISTANCE) {
              return;
            }
            dragging = true;
            tile.classList.add('is-dragging');
          }
          const over = document.elementFromPoint(moveEvent.clientX, moveEvent.clientY)?.closest('.celerik-tile');
          if (over && over !== tile && over.parentElement === element) {
            moveTo(tile, tiles().indexOf(over));
          }
        };
        const end = () => {
          handle.removeEventListener('pointermove', move);
          handle.removeEventListener('pointerup', end);
          handle.removeEventListener('pointercancel', end);
          if (!dragging) {
            return;
          }
          dragged = true;
          tile.classList.remove('is-dragging');
          handle.focus();
          if (tiles().indexOf(tile) !== from) {
            changed();
          }
          announceMove(tile);
        };
        handle.addEventListener('pointermove', move);
        handle.addEventListener('pointerup', end);
        handle.addEventListener('pointercancel', end);
      });
    }

    // Dragging the corner grip: the tile spans the columns and rows it reaches.
    const grip = tile.querySelector('.celerik-tile-grip');
    if (resizable && grip) {
      grip.addEventListener('pointerdown', (event) => {
        if (event.button !== 0) {
          return;
        }
        event.preventDefault();
        grip.setPointerCapture?.(event.pointerId);
        const box = tile.getBoundingClientRect();
        const gap = parseFloat(getComputedStyle(element).columnGap) || 0;
        const rowGap = parseFloat(getComputedStyle(element).rowGap) || 0;
        const start = spanOf(tile);
        const colUnit = (element.clientWidth + gap) / columns;
        const rowUnit = (box.height + rowGap) / start.rows;
        tile.classList.add('is-resizing');
        const move = (moveEvent) => {
          resizeTo(
            tile,
            Math.round((moveEvent.clientX - box.left + gap) / (colUnit || 1)),
            Math.round((moveEvent.clientY - box.top + rowGap) / (rowUnit || 1)),
          );
        };
        const end = () => {
          grip.removeEventListener('pointermove', move);
          grip.removeEventListener('pointerup', end);
          grip.removeEventListener('pointercancel', end);
          tile.classList.remove('is-resizing');
          const now = spanOf(tile);
          if (now.cols !== start.cols || now.rows !== start.rows) {
            changed();
          }
          announceSize(tile);
        };
        grip.addEventListener('pointermove', move);
        grip.addEventListener('pointerup', end);
        grip.addEventListener('pointercancel', end);
      });
    }
  }

  refreshTools();
  return {
    layout,
    move: (key, position) => {
      const tile = tiles().find((candidate) => candidate.dataset.tile === key);
      if (tile && moveTo(tile, position)) {
        changed();
      }
    },
    resize: (key, cols, rows) => {
      const tile = tiles().find((candidate) => candidate.dataset.tile === key);
      if (tile && resizeTo(tile, cols, rows)) {
        changed();
      }
    },
  };
}
