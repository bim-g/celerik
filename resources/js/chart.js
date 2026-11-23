import {
  ArcElement,
  BarController,
  BarElement,
  BubbleController,
  CategoryScale,
  Chart,
  Colors,
  DoughnutController,
  Filler,
  Legend,
  LinearScale,
  LineController,
  LineElement,
  LogarithmicScale,
  PieController,
  PointElement,
  PolarAreaController,
  RadarController,
  RadialLinearScale,
  ScatterController,
  SubTitle,
  Title,
  Tooltip,
} from 'chart.js';
import {
  buildChartConfig, chartShape, readChartTheme, renderDataTable, styleData,
} from './chart-config.js';
import { prefersReducedMotion } from './core.js';

// Everything the PHP Chart types and common options need. Time scales are left out:
// they require a date adapter that Caelrik does not ship.
Chart.register(
  LineController, BarController, PieController, DoughnutController,
  RadarController, PolarAreaController, ScatterController, BubbleController,
  LineElement, BarElement, ArcElement, PointElement,
  CategoryScale, LinearScale, LogarithmicScale, RadialLinearScale,
  Colors, Filler, Legend, Title, SubTitle, Tooltip,
);

export function initChart(element, options) {
  const canvas = element.querySelector('canvas');
  if (options.height) {
    canvas.parentElement.style.height = `${options.height}px`;
  } else {
    canvas.parentElement.classList.add(`caelrik-chart__canvas--${chartShape(options.type ?? 'line')}`);
  }

  // Flat colors that follow Bootstrap and the color mode: the text, grid lines and font of
  // every chart, and a palette for series without their own colors.
  const theme = readChartTheme(element);
  if (theme.text) {
    Chart.defaults.color = theme.text;
  }
  if (theme.grid) {
    Chart.defaults.borderColor = theme.grid;
  }
  if (theme.font) {
    Chart.defaults.font.family = theme.font;
  }

  const chart = new Chart(canvas, buildChartConfig(options, { reducedMotion: prefersReducedMotion(), theme }));

  if (options.url) {
    fetch(options.url, { headers: { Accept: 'application/json' } })
      .then((response) => {
        if (!response.ok) {
          throw new Error(`HTTP ${response.status}`);
        }
        return response.json();
      })
      .then((payload) => {
        chart.data = styleData(payload.data, chart.config.type, options, theme);
        chart.update();
        renderDataTable(element, canvas.getAttribute('aria-label') ?? '', payload.data);
      })
      .catch((error) => console.error(`Caelrik: failed to load chart #${element.id}`, error));
  }

  return chart;
}
