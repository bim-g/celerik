import { deepMerge } from './core.js';

// Point shapes and line dashes that tell series apart without color (WCAG 1.4.1).
export const POINT_STYLES = ['circle', 'triangle', 'rect', 'rectRot', 'crossRot', 'star', 'rectRounded'];
export const LINE_DASHES = [[], [6, 4], [2, 3], [10, 3, 2, 3], [12, 6], [4, 4, 1, 4], [1, 2]];
const PATTERN_TYPES = ['line', 'radar', 'scatter'];

// Per-dataset options from PHP (dataset(..., options: [...])) are never overwritten.
export function withPatterns(data, type) {
  return {
    ...data,
    datasets: (data.datasets ?? []).map((dataset, index) => ({
      pointStyle: POINT_STYLES[index % POINT_STYLES.length],
      pointRadius: 4,
      pointHoverRadius: 6,
      ...(type === 'scatter' ? {} : { borderDash: LINE_DASHES[index % LINE_DASHES.length] }),
      ...dataset,
    })),
  };
}

const PER_POINT_TYPES = ['pie', 'doughnut', 'polarArea'];
const HEX = /^#[0-9a-f]{6}$/i;

// The flat palette (celerik.css: --celerik-chart-1 to 6, light and dark) and the page's
// text, grid and font, read from the chart's element. Empty values are left out, so
// Chart.js keeps its own.
export function readChartTheme(element) {
  const style = getComputedStyle(element);
  const read = (name) => style.getPropertyValue(name).trim();
  return {
    colors: [1, 2, 3, 4, 5, 6].map((index) => read(`--celerik-chart-${index}`)).filter(Boolean),
    text: read('--bs-secondary-color'),
    title: read('--bs-body-color'),
    grid: read('--bs-border-color-translucent'),
    surface: read('--bs-body-bg'),
    font: read('--bs-body-font-family'),
  };
}

// Solid series colors from the theme's palette: a line and its points, a bar, or a slice
// per value (pie, doughnut, polar area), separated by the page background. A dataset with
// its own colors (from PHP) is left as it is.
export function withColors(data, type, theme) {
  const colors = theme?.colors ?? [];
  if (colors.length === 0) {
    return data;
  }
  const pick = (index) => colors[index % colors.length];
  return {
    ...data,
    datasets: (data.datasets ?? []).map((dataset, index) => {
      if (dataset.backgroundColor !== undefined || dataset.borderColor !== undefined) {
        return dataset;
      }
      const datasetType = dataset.type ?? type;
      if (PER_POINT_TYPES.includes(datasetType)) {
        const count = data.labels?.length ?? dataset.data?.length ?? 0;
        return {
          backgroundColor: Array.from({ length: count }, (_, point) => pick(point)),
          ...(theme.surface ? { borderColor: theme.surface } : {}),
          borderWidth: 2,
          ...dataset,
        };
      }
      const color = pick(index);
      if (datasetType === 'bar') {
        return { backgroundColor: color, borderColor: color, borderWidth: 0, ...dataset };
      }
      // A radar's area is a light tint, so the series under it stay visible.
      const area = datasetType === 'radar' && HEX.test(color) ? `${color}33` : color;
      return { borderColor: color, backgroundColor: area, pointBackgroundColor: color, ...dataset };
    }),
  };
}

export function usesPatterns(options) {
  return options.patterns ?? PATTERN_TYPES.includes(options.type ?? 'line');
}

// Colors and patterns of a chart's data, from the theme (readChartTheme()).
export function styleData(data, type, options, theme) {
  const colored = withColors(data, type, theme);
  return usesPatterns(options) ? withPatterns(colored, type) : colored;
}

// reducedMotion: the user asked for less motion. theme: readChartTheme(). Options from PHP
// still win.
export function buildChartConfig(options, { reducedMotion = false, theme = null } = {}) {
  const type = options.type ?? 'line';
  const patterns = usesPatterns(options);
  const data = options.data ?? { labels: [], datasets: [] };
  return {
    type,
    data: styleData(data, type, options, theme),
    options: deepMerge({
      responsive: true,
      maintainAspectRatio: !options.height,
      ...(reducedMotion ? { animation: false } : {}),
      plugins: {
        title: {
          display: Boolean(options.title),
          text: options.title ?? '',
          font: { size: 14, weight: '600' },
          ...(theme?.title ? { color: theme.title } : {}),
        },
        ...(patterns ? { legend: { labels: { usePointStyle: true } } } : {}),
      },
    }, options.options),
  };
}

// Text alternative for charts whose data arrives over HTTP (PHP renders it for embedded data).
export function renderDataTable(figure, caption, data) {
  figure.querySelector('table.celerik-chart__data')?.remove();
  const table = document.createElement('table');
  table.className = 'visually-hidden celerik-chart__data';
  table.createCaption().textContent = caption;

  const head = table.createTHead().insertRow();
  head.appendChild(document.createElement('th')).scope = 'col';
  for (const dataset of data.datasets ?? []) {
    const th = head.appendChild(document.createElement('th'));
    th.scope = 'col';
    th.textContent = dataset.label ?? '';
  }

  const body = table.createTBody();
  (data.labels ?? []).forEach((label, index) => {
    const row = body.insertRow();
    const th = row.appendChild(document.createElement('th'));
    th.scope = 'row';
    th.textContent = String(label);
    for (const dataset of data.datasets ?? []) {
      row.insertCell().textContent = dataset.data?.[index] ?? '';
    }
  });

  figure.appendChild(table);
  return table;
}
