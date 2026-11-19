// UserMenu (PHP: Components\UserMenu): a disclosure (<details>) that opens and closes
// without JavaScript. This adds what users of a menu button expect: Escape closes it and
// returns focus to its button, and it closes when focus or a click goes elsewhere.
export function initUserMenu(element) {
  const summary = element.querySelector('summary');
  const close = (refocus) => {
    if (!element.open) {
      return;
    }
    element.open = false;
    if (refocus) {
      summary?.focus();
    }
  };

  element.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && element.open) {
      event.preventDefault();
      event.stopPropagation();
      close(true);
    }
  });
  element.addEventListener('focusout', (event) => {
    if (event.relatedTarget && !element.contains(event.relatedTarget)) {
      close(false);
    }
  });
  const outside = (event) => {
    if (!element.contains(event.target)) {
      close(false);
    }
  };
  document.addEventListener('pointerdown', outside);
  return { close: () => close(false), destroy: () => document.removeEventListener('pointerdown', outside) };
}
