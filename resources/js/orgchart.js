// Org chart (PHP: Components\OrgChart). The chart is nested lists drawn by CSS; this adds:
// - each manager's button (collapsible()) shows or hides their team: aria-expanded on the
//   button, hidden on the team's list; it fires caelrik:toggle with { id, expanded }.
// - options.expandLevels: the levels open at first (deeper teams start hidden).
// Without JavaScript the buttons are hidden and everyone is shown.

export function initOrgChart(element, options = {}) {
  const buttons = [...element.querySelectorAll('.caelrik-orgchart-toggle')];
  const teamOf = (button) => element.ownerDocument.getElementById(button.getAttribute('aria-controls'));

  const set = (button, expanded) => {
    const team = teamOf(button);
    if (!team) {
      return;
    }
    button.setAttribute('aria-expanded', String(expanded));
    team.hidden = !expanded;
    button.closest('.caelrik-orgchart-item')?.classList.toggle('is-collapsed', !expanded);
  };

  for (const button of buttons) {
    button.addEventListener('click', () => {
      const expanded = button.getAttribute('aria-expanded') !== 'true';
      set(button, expanded);
      element.dispatchEvent(new CustomEvent('caelrik:toggle', {
        bubbles: true,
        detail: { id: button.getAttribute('aria-controls'), expanded },
      }));
    });
  }

  const levels = Number(options.expandLevels) || 0;
  if (levels > 0) {
    for (const button of buttons) {
      const level = Number(button.closest('.caelrik-orgchart-item')?.dataset.level ?? 1);
      set(button, level < levels);
    }
  }

  return {
    expandAll: () => buttons.forEach((button) => set(button, true)),
    collapseAll: () => buttons.forEach((button) => set(button, false)),
  };
}
