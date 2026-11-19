// Accordion and Splitter.
// - Accordion (https://www.w3.org/WAI/ARIA/apg/patterns/accordion/): each heading's button
//   shows or hides its panel; one open at a time unless `multiple`; Up, Down, Home and End
//   move between the headings. PHP shows every panel, for pages without JavaScript.
// - Splitter (https://www.w3.org/WAI/ARIA/apg/patterns/windowsplitter/): each divider is a
//   separator valued by the size of the pane before it. Arrow keys resize by 5%, Home and End
//   go to the limits, Enter collapses the pane to its minimum and back; dividers are dragged
//   with any pointer. Sizes are set through the CSSOM (allowed by a strict CSP).

export function initAccordion(element, options = {}) {
  const buttons = [...element.querySelectorAll(':scope > .accordion-item > .accordion-header > .accordion-button')];
  const panelOf = (button) => document.getElementById(button.getAttribute('aria-controls'));
  const set = (button, open) => {
    button.setAttribute('aria-expanded', String(open));
    button.classList.toggle('collapsed', !open);
    const panel = panelOf(button);
    if (panel) {
      panel.hidden = !open;
    }
  };
  buttons.forEach((button) => set(button, panelOf(button)?.dataset.open === 'true'));

  for (const button of buttons) {
    button.addEventListener('click', () => {
      const open = button.getAttribute('aria-expanded') !== 'true';
      if (open && !options.multiple) {
        buttons.filter((other) => other !== button).forEach((other) => set(other, false));
      }
      set(button, open);
    });
    button.addEventListener('keydown', (event) => {
      const index = buttons.indexOf(button);
      const next = {
        ArrowDown: buttons[(index + 1) % buttons.length],
        ArrowUp: buttons[(index - 1 + buttons.length) % buttons.length],
        Home: buttons[0],
        End: buttons[buttons.length - 1],
      }[event.key];
      if (next) {
        event.preventDefault();
        next.focus();
      }
    });
  }
  return {
    set: (index, open) => {
      if (buttons[index]) {
        if (open && !options.multiple) {
          buttons.filter((_, other) => other !== index).forEach((other) => set(other, false));
        }
        set(buttons[index], open);
      }
    },
  };
}

const STEP = 5;

function readSizes(key) {
  try {
    const saved = JSON.parse(window.localStorage.getItem(key) ?? 'null');
    return Array.isArray(saved) && saved.every((size) => typeof size === 'number') ? saved : null;
  } catch {
    return null;
  }
}

export function initSplitter(element, options = {}) {
  const vertical = options.orientation === 'vertical';
  const panes = [...element.children].filter((child) => child.classList.contains('celerik-splitter-pane'));
  const handles = [...element.children].filter((child) => child.classList.contains('celerik-splitter-handle'));
  const limits = panes.map((pane) => ({ min: Number(pane.dataset.min ?? 0), max: Number(pane.dataset.max ?? 100) }));
  const key = `celerik-splitter:${element.id}`;

  // The panes' sizes in percent: saved ones, or PHP's, the rest sharing what is left.
  let sizes = options.remember ? readSizes(key) : null;
  if (!sizes || sizes.length !== panes.length) {
    const given = panes.map((pane) => (pane.dataset.size === undefined ? null : Number(pane.dataset.size)));
    const free = given.filter((size) => size === null).length;
    const rest = Math.max(0, 100 - given.reduce((sum, size) => sum + (size ?? 0), 0));
    sizes = given.map((size) => size ?? (free ? rest / free : 0));
  }
  const collapsed = new Map();

  const apply = () => {
    panes.forEach((pane, index) => {
      // Proportions, whatever the dividers' widths.
      pane.style.flex = `${sizes[index]} 1 0px`;
    });
    handles.forEach((handle, index) => {
      handle.setAttribute('aria-valuenow', String(Math.round(sizes[index])));
    });
  };
  const save = () => {
    if (options.remember) {
      try {
        window.localStorage.setItem(key, JSON.stringify(sizes.map((size) => Math.round(size * 10) / 10)));
      } catch {
        // Storage may be off: sizes last until the page is left.
      }
    }
    element.dispatchEvent(new CustomEvent('celerik:resize', { bubbles: true, detail: { sizes: [...sizes] } }));
  };

  // Sets pane i's size; pane i + 1 gives or takes the difference, both within their limits.
  const resize = (index, wanted) => {
    const pair = sizes[index] + sizes[index + 1];
    const low = Math.max(limits[index].min, pair - limits[index + 1].max);
    const high = Math.min(limits[index].max, pair - limits[index + 1].min);
    const size = Math.min(high, Math.max(low, wanted));
    sizes[index] = size;
    sizes[index + 1] = pair - size;
    apply();
  };

  handles.forEach((handle, index) => {
    handle.addEventListener('keydown', (event) => {
      const less = vertical ? 'ArrowUp' : 'ArrowLeft';
      const more = vertical ? 'ArrowDown' : 'ArrowRight';
      let wanted = null;
      if (event.key === less) {
        wanted = sizes[index] - STEP;
      } else if (event.key === more) {
        wanted = sizes[index] + STEP;
      } else if (event.key === 'Home') {
        wanted = 0;
      } else if (event.key === 'End') {
        wanted = 100;
      } else if (event.key === 'Enter') {
        if (collapsed.has(index)) {
          wanted = collapsed.get(index);
          collapsed.delete(index);
        } else {
          collapsed.set(index, sizes[index]);
          wanted = 0;
        }
      }
      if (wanted === null) {
        return;
      }
      event.preventDefault();
      resize(index, wanted);
      save();
    });

    handle.addEventListener('pointerdown', (event) => {
      if (event.button !== 0) {
        return;
      }
      event.preventDefault();
      handle.focus();
      handle.setPointerCapture?.(event.pointerId);
      const box = element.getBoundingClientRect();
      const length = vertical ? box.height : box.width;
      const start = vertical ? event.clientY : event.clientX;
      const from = sizes[index];
      handle.classList.add('is-dragging');
      const move = (moveEvent) => {
        const position = vertical ? moveEvent.clientY : moveEvent.clientX;
        resize(index, from + ((position - start) / (length || 1)) * 100);
      };
      const end = () => {
        handle.classList.remove('is-dragging');
        handle.removeEventListener('pointermove', move);
        handle.removeEventListener('pointerup', end);
        handle.removeEventListener('pointercancel', end);
        save();
      };
      handle.addEventListener('pointermove', move);
      handle.addEventListener('pointerup', end);
      handle.addEventListener('pointercancel', end);
    });
  });

  apply();
  return { sizes: () => [...sizes], resize: (index, size) => { resize(index, size); save(); } };
}
