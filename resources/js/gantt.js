// Gantt chart (PHP: Components\Gantt). The table and its bars are drawn by PHP; this adds:
// - arrows from each task to the tasks that depend on it (data-depends on a row), in an SVG
//   laid over the timeline, sized by attributes (no inline styles) and redrawn when the chart
//   changes size. They are decorative: the "Depends on" column says the same.
// - a first scroll that brings today's line into view when it is beyond the right edge.

const SVG = 'http://www.w3.org/2000/svg';
const GAP = 8;

const svgElement = (name, attributes = {}) => {
  const element = document.createElementNS(SVG, name);
  Object.entries(attributes).forEach(([key, value]) => element.setAttribute(key, String(value)));
  return element;
};

// The path from the end of one bar to the start of another: right, down (or up), right.
export function arrowPath(from, to) {
  const startX = from.right;
  const startY = from.top + from.height / 2;
  const endX = to.left;
  const endY = to.top + to.height / 2;
  if (endX - startX >= GAP * 2) {
    const turn = startX + GAP;
    return `M${startX} ${startY}H${turn}V${endY}H${endX}`;
  }
  // The next task starts before this one ends: go around, between the two rows.
  const middle = startY + (endY - startY) / 2;
  return `M${startX} ${startY}H${startX + GAP}V${middle}H${endX - GAP}V${endY}H${endX}`;
}

export function initGantt(element) {
  const canvas = element.querySelector('.caelrik-gantt-canvas');
  const scroll = element.querySelector('.caelrik-gantt-scroll');
  if (!canvas || !scroll) {
    return null;
  }
  const overlay = svgElement('svg', { class: 'caelrik-gantt-arrows', 'aria-hidden': 'true', focusable: 'false' });
  const markerId = `${element.id}-arrow`;
  const defs = svgElement('defs');
  const marker = svgElement('marker', {
    id: markerId, viewBox: '0 0 8 8', refX: 7, refY: 4, markerWidth: 8, markerHeight: 8, orient: 'auto-start-reverse',
  });
  marker.append(svgElement('path', { d: 'M0 0L8 4L0 8z', class: 'caelrik-gantt-arrowhead' }));
  defs.append(marker);
  overlay.append(defs);
  canvas.append(overlay);

  const draw = () => {
    const shapes = new Map([...canvas.querySelectorAll('.caelrik-gantt-row [data-task]')]
      .map((shape) => [shape.dataset.task, shape]));
    const shapeOf = (id) => shapes.get(id);
    const box = canvas.getBoundingClientRect();
    overlay.setAttribute('width', String(Math.round(box.width)));
    overlay.setAttribute('height', String(Math.round(box.height)));
    overlay.setAttribute('viewBox', `0 0 ${Math.round(box.width)} ${Math.round(box.height)}`);
    overlay.querySelectorAll('.caelrik-gantt-link').forEach((path) => path.remove());
    const relative = (rect) => ({
      left: rect.left - box.left,
      right: rect.right - box.left,
      top: rect.top - box.top,
      height: rect.height,
    });
    for (const row of canvas.querySelectorAll('tr[data-depends]')) {
      const target = shapeOf(row.dataset.task);
      for (const id of row.dataset.depends.split(' ')) {
        const source = shapeOf(id);
        if (source && target) {
          overlay.append(svgElement('path', {
            class: 'caelrik-gantt-link',
            d: arrowPath(relative(source.getBoundingClientRect()), relative(target.getBoundingClientRect())),
            'marker-end': `url(#${markerId})`,
            'data-from': id,
            'data-to': row.dataset.task,
          }));
        }
      }
    }
  };

  // Today's line out of view on the right: scroll it to a little after the task names.
  const today = canvas.querySelector('.caelrik-gantt-scale .caelrik-gantt-today');
  if (today) {
    const view = scroll.getBoundingClientRect();
    const line = today.getBoundingClientRect().left;
    if (line > view.left + scroll.clientWidth - GAP) {
      const names = canvas.querySelector('tbody .caelrik-gantt-name')?.getBoundingClientRect().width ?? 0;
      scroll.scrollLeft += line - view.left - names - 48;
    }
  }

  draw();
  let observer = null;
  if (typeof ResizeObserver === 'function') {
    observer = new ResizeObserver(() => draw());
    observer.observe(canvas);
  }
  return { draw, destroy: () => observer?.disconnect() };
}
