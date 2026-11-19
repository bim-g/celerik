// Chips and tooltips (PHP: Components\Chip, Components\Tooltip).
// - A removable chip's Remove button takes the chip (and its hidden field) away, after a
//   cancelable celerik:remove; focus goes to the next chip's Remove button, or the previous
//   one, and the removal is announced.
// - A tooltip (the WAI-ARIA tooltip pattern, WCAG 1.4.13): its control is described by it;
//   it shows on hover and focus, stays while the pointer is on the control or on it, hides
//   on Escape (focus stays), when focus leaves, and shortly after the pointer leaves. It
//   flips below (or above) and slides inside the viewport, with classes (no inline styles).

const format = (template, values) => Object.entries(values)
  .reduce((text, [key, value]) => text.replaceAll(`{${key}}`, String(value)), template);

// One status region for the page's chip removals.
function announcer() {
  let status = document.getElementById('celerik-chip-status');
  if (!status) {
    status = document.createElement('div');
    status.id = 'celerik-chip-status';
    status.className = 'visually-hidden';
    status.setAttribute('role', 'status');
    document.body.append(status);
  }
  return (text) => {
    status.textContent = '';
    setTimeout(() => { status.textContent = text; }, 50);
  };
}

export function initChip(element, options = {}) {
  const labels = { removed: '{label} removed.', ...options.labels };
  const button = element.querySelector('.celerik-chip-remove');
  const label = element.querySelector('.celerik-chip-label')?.textContent.trim() ?? '';
  const remove = () => {
    const value = element.querySelector('input[type=hidden]')?.value ?? label;
    const event = new CustomEvent('celerik:remove', { bubbles: true, cancelable: true, detail: { label, value } });
    if (!element.dispatchEvent(event)) {
      return false;
    }
    // The next chip among the following elements of the same parent, else the previous one.
    const siblings = [...(element.parentElement?.querySelectorAll('.celerik-chip-remove') ?? [])];
    const index = siblings.indexOf(button);
    const next = siblings[index + 1] ?? siblings[index - 1] ?? null;
    element.remove();
    next?.focus();
    announcer()(format(labels.removed, { label }));
    return true;
  };
  button?.addEventListener('click', remove);
  return { remove };
}

const HIDE_DELAY = 150;
const MARGIN = 8;

export function initTooltip(element, options = {}) {
  const tip = element.querySelector(':scope > .celerik-tooltip');
  const control = element.querySelector('a[href], button, input, select, textarea, summary, [tabindex]:not([tabindex="-1"])');
  if (!tip || !control) {
    return null;
  }
  const described = (control.getAttribute('aria-describedby') ?? '').split(/\s+/).filter(Boolean);
  if (!described.includes(tip.id)) {
    control.setAttribute('aria-describedby', [...described, tip.id].join(' '));
  }

  let timer = null;
  const place = () => {
    tip.classList.remove('is-below', 'is-above', 'is-start', 'is-end');
    const below = options.placement === 'bottom';
    tip.classList.add(below ? 'is-below' : 'is-above');
    let box = tip.getBoundingClientRect();
    if (!below && box.top < MARGIN) {
      tip.classList.replace('is-above', 'is-below');
    } else if (below && box.bottom > window.innerHeight - MARGIN) {
      tip.classList.replace('is-below', 'is-above');
    }
    box = tip.getBoundingClientRect();
    if (box.left < MARGIN) {
      tip.classList.add('is-start');
    } else if (box.right > document.documentElement.clientWidth - MARGIN) {
      tip.classList.add('is-end');
    }
  };
  const show = () => {
    clearTimeout(timer);
    if (tip.hidden) {
      tip.hidden = false;
      place();
    }
  };
  const hide = () => {
    clearTimeout(timer);
    tip.hidden = true;
  };
  const hideSoon = () => {
    clearTimeout(timer);
    timer = setTimeout(hide, HIDE_DELAY);
  };
  // Escape hides it wherever focus is, and only it: a dialog around it stays open.
  const onKey = (event) => {
    if (event.key === 'Escape' && !tip.hidden) {
      event.stopPropagation();
      event.preventDefault();
      hide();
    }
  };

  element.addEventListener('pointerenter', show);
  element.addEventListener('pointerleave', () => {
    if (!element.contains(document.activeElement)) {
      hideSoon();
    }
  });
  control.addEventListener('focus', show);
  control.addEventListener('blur', hide);
  document.addEventListener('keydown', onKey, true);
  return { show, hide, destroy: () => document.removeEventListener('keydown', onKey, true) };
}
