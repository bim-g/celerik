import { initOrgChart } from '../../resources/js/orgchart.js';
import { readOptions } from '../../resources/js/core.js';
import { loadFixture } from './helpers.js';

const toggle = (root, id) => root.querySelector(`.caelrik-orgchart-toggle[aria-controls="company-team-${id}"]`);
const team = (id) => document.getElementById(`company-team-${id}`);

describe('org chart', () => {
  let root;
  let api;

  beforeEach(() => {
    root = loadFixture('orgchart');
    api = initOrgChart(root, readOptions(root));
  });

  test('teams below the open levels start hidden', () => {
    // expandLevels(2): Grace (level 1) open; Ada's team (level 2) and Alan's (level 3) hidden.
    expect(toggle(root, 'grace').getAttribute('aria-expanded')).toBe('true');
    expect(team('grace').hidden).toBe(false);
    expect(toggle(root, 'ada').getAttribute('aria-expanded')).toBe('false');
    expect(team('ada').hidden).toBe(true);
    expect(toggle(root, 'alan').getAttribute('aria-expanded')).toBe('false');
    expect(toggle(root, 'ada').getAttribute('aria-label')).toBe("Ada Lovelace's team, 2 people");
  });

  test('a button shows and hides its team, and says so', () => {
    const events = [];
    root.addEventListener('caelrik:toggle', (event) => events.push(event.detail));
    toggle(root, 'ada').click();
    expect(toggle(root, 'ada').getAttribute('aria-expanded')).toBe('true');
    expect(team('ada').hidden).toBe(false);
    expect(toggle(root, 'ada').closest('.caelrik-orgchart-item').classList.contains('is-collapsed')).toBe(false);
    toggle(root, 'grace').click();
    expect(team('grace').hidden).toBe(true);
    expect(events).toEqual([
      { id: 'company-team-ada', expanded: true },
      { id: 'company-team-grace', expanded: false },
    ]);
  });

  test('expandAll and collapseAll', () => {
    api.expandAll();
    expect([...root.querySelectorAll('.caelrik-orgchart-team')].every((list) => !list.hidden)).toBe(true);
    api.collapseAll();
    expect([...root.querySelectorAll('.caelrik-orgchart-toggle')]
      .every((button) => button.getAttribute('aria-expanded') === 'false')).toBe(true);
  });

  test('without expandLevels everything stays open', () => {
    root = loadFixture('orgchart');
    initOrgChart(root, {});
    expect([...root.querySelectorAll('.caelrik-orgchart-team')].every((list) => !list.hidden)).toBe(true);
  });
});
