import { arrowPath, initGantt } from '../../resources/js/gantt.js';
import { loadFixture } from './helpers.js';

describe('gantt arrows', () => {
  test('a path goes right, then down, then right into the next bar', () => {
    const from = { left: 10, right: 100, top: 0, height: 20 };
    const to = { left: 140, right: 200, top: 40, height: 20 };
    expect(arrowPath(from, to)).toBe('M100 10H108V50H140');
  });

  test('when the next task starts before this one ends, the path goes around between the rows', () => {
    const from = { left: 10, right: 100, top: 0, height: 20 };
    const to = { left: 60, right: 200, top: 40, height: 20 };
    expect(arrowPath(from, to)).toBe('M100 10H108V30H52V50H60');
  });

  test('one decorative arrow per dependency, from the task to the task that waits for it', () => {
    const gantt = loadFixture('gantt');
    const api = initGantt(gantt);
    const overlay = gantt.querySelector('.celerik-gantt-arrows');
    expect(overlay.getAttribute('aria-hidden')).toBe('true');
    expect(overlay.querySelector('marker').id).toBe('launch-arrow');
    const links = [...overlay.querySelectorAll('.celerik-gantt-link')]
      .map((path) => `${path.dataset.from}>${path.dataset.to}`);
    expect(links).toEqual(['design>build', 'design>docs', 'build>release', 'docs>release']);
    expect(overlay.querySelector('.celerik-gantt-link').getAttribute('marker-end')).toBe('url(#launch-arrow)');

    // Redrawing replaces the arrows.
    api.draw();
    expect(overlay.querySelectorAll('.celerik-gantt-link')).toHaveLength(4);
    api.destroy();
  });

  test('nothing to do without a timeline', () => {
    document.body.innerHTML = '<div id="g"></div>';
    expect(initGantt(document.getElementById('g'))).toBeNull();
  });
});
