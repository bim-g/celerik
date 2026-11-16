import {
  buildChartConfig, LINE_DASHES, POINT_STYLES, renderDataTable, withColors,
} from '../../resources/js/chart-config.js';
import { readOptions } from '../../resources/js/core.js';
import { loadFixture } from './helpers.js';

describe('chart config', () => {
  test('builds a Chart.js config from the PHP options', () => {
    const figure = loadFixture('chart');
    const config = buildChartConfig(readOptions(figure));

    expect(config.type).toBe('bar');
    expect(config.data.labels).toEqual(['Jan', 'Feb', 'Mar']);
    expect(config.data.datasets[0]).toEqual({ label: 'Revenue', data: [1200, 1900, 1500] });
    expect(config.options.plugins.title).toEqual({ display: true, text: 'Monthly revenue', font: { size: 14, weight: '600' } });
    expect(config.options.maintainAspectRatio).toBe(true);
  });

  test('a fixed height disables the aspect ratio and raw options are merged', () => {
    const config = buildChartConfig({ height: 300, options: { plugins: { legend: { display: false } } } });

    expect(config.options.maintainAspectRatio).toBe(false);
    expect(config.options.plugins.legend.display).toBe(false);
    expect(config.options.plugins.title.display).toBe(false);
  });

  test('reduced motion turns animation off, unless PHP options set it', () => {
    expect(buildChartConfig({}).options.animation).toBeUndefined();
    expect(buildChartConfig({}, { reducedMotion: true }).options.animation).toBe(false);

    const config = buildChartConfig({ options: { animation: { duration: 200 } } }, { reducedMotion: true });
    expect(config.options.animation).toEqual({ duration: 200 });
  });

  test('line charts tell series apart without color; dataset options from PHP win', () => {
    const data = { labels: ['Jan'], datasets: [{ label: 'North', data: [1] }, { label: 'South', data: [2], pointStyle: 'cross' }] };
    const config = buildChartConfig({ type: 'line', data });

    expect(config.data.datasets[0]).toMatchObject({ pointStyle: POINT_STYLES[0], borderDash: LINE_DASHES[0], label: 'North' });
    expect(config.data.datasets[1]).toMatchObject({ pointStyle: 'cross', borderDash: LINE_DASHES[1] });
    expect(config.options.plugins.legend.labels.usePointStyle).toBe(true);
    expect(new Set(POINT_STYLES).size).toBe(POINT_STYLES.length);
    expect(data.datasets[0]).not.toHaveProperty('pointStyle'); // the PHP options are not mutated
  });

  test('patterns: on for line, radar and scatter (no dashes), opt-in for others, opt-out', () => {
    const data = { labels: ['Jan'], datasets: [{ label: 'A', data: [1] }] };
    expect(buildChartConfig({ type: 'radar', data }).data.datasets[0].pointStyle).toBe('circle');
    expect(buildChartConfig({ type: 'scatter', data }).data.datasets[0]).not.toHaveProperty('borderDash');
    expect(buildChartConfig({ type: 'bar', data }).data.datasets[0]).not.toHaveProperty('pointStyle');
    expect(buildChartConfig({ type: 'bar', data, patterns: true }).data.datasets[0].pointStyle).toBe('circle');
    expect(buildChartConfig({ type: 'line', data, patterns: false }).data.datasets[0]).not.toHaveProperty('pointStyle');
  });

  test('renders an accessible data table for remote data', () => {
    document.body.innerHTML = '<figure id="f"></figure>';
    const table = renderDataTable(document.getElementById('f'), 'Signups', {
      labels: ['Mon', 'Tue'],
      datasets: [{ label: 'Users', data: [3, 5] }],
    });

    expect(table.caption.textContent).toBe('Signups');
    expect(table.querySelector('tbody tr:nth-child(2)').textContent).toBe('Tue5');
    expect(table.classList.contains('visually-hidden')).toBe(true);
  });
});

describe('flat colors', () => {
  const theme = {
    colors: ['#0d6efd', '#d63384', '#198754'], title: '#212529', surface: '#ffffff',
  };
  const data = { labels: ['Jan', 'Feb', 'Mar', 'Apr'], datasets: [{ label: 'A', data: [1, 2, 3, 4] }, { label: 'B', data: [4, 3, 2, 1] }] };

  test('lines and their points take solid palette colors, in order', () => {
    const [a, b] = withColors(data, 'line', theme).datasets;
    expect([a.borderColor, a.backgroundColor, a.pointBackgroundColor]).toEqual(['#0d6efd', '#0d6efd', '#0d6efd']);
    expect(b.borderColor).toBe('#d63384');
  });

  test('bars are solid, a radar area is a light tint, slices take a color each', () => {
    expect(withColors(data, 'bar', theme).datasets[1]).toMatchObject({ backgroundColor: '#d63384', borderWidth: 0 });
    expect(withColors(data, 'radar', theme).datasets[0].backgroundColor).toBe('#0d6efd33');
    expect(withColors(data, 'pie', theme).datasets[0]).toMatchObject({
      backgroundColor: ['#0d6efd', '#d63384', '#198754', '#0d6efd'], borderColor: '#ffffff', borderWidth: 2,
    });
  });

  test('colors from PHP, a mixed chart and a missing palette are respected', () => {
    const own = { ...data, datasets: [{ label: 'A', data: [1], borderColor: '#000000' }, { label: 'B', type: 'bar', data: [2] }] };
    const [first, second] = withColors(own, 'line', theme).datasets;
    expect(first).toEqual(own.datasets[0]);
    expect(second.backgroundColor).toBe('#d63384');
    expect(withColors(data, 'line', { colors: [] })).toBe(data);
  });

  test('buildChartConfig colors the data before the patterns, and themes the title', () => {
    const config = buildChartConfig({ type: 'line', data, title: 'Revenue' }, { theme });
    expect(config.data.datasets[0]).toMatchObject({ borderColor: '#0d6efd', pointStyle: 'circle' });
    expect(config.options.plugins.title).toMatchObject({ color: '#212529', font: { weight: '600' } });
    expect(buildChartConfig({ type: 'line', data }).data.datasets[0]).not.toHaveProperty('borderColor');
  });
});
