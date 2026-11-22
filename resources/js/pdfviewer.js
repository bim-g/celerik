// PDF viewer (Components\PdfViewer), with PDF.js: the pages in a scrolling area, each a
// canvas (hidden from assistive technology) under a text layer that screen readers read and
// the browser's search finds. Canvases are drawn near the visible area only; text layers are
// added to every page, so a search finds the whole document. Pages are sized by the CSSOM
// (no style attributes: strict CSP).
import { getDocument, GlobalWorkerOptions, TextLayer } from 'pdfjs-dist';
import workerUrl from 'pdfjs-dist/build/pdf.worker.min.mjs?url';

GlobalWorkerOptions.workerSrc = workerUrl;

const ZOOMS = [50, 75, 100, 125, 150, 200, 300];
// The gap between pages and around them, in pixels (as in the stylesheet).
const GAP = 16;

const dispatch = (element, name, detail) => element.dispatchEvent(
  new CustomEvent(`celerik:${name}`, { bubbles: true, detail }),
);

const text = (template, values) => template.replace(/\{(\w+)\}/g, (match, key) => values[key] ?? match);

// The next or previous zoom step from a scale (1 = 100%).
export function stepZoom(scale, direction) {
  const percent = Math.round(scale * 100);
  const steps = direction > 0 ? ZOOMS.filter((zoom) => zoom > percent) : ZOOMS.filter((zoom) => zoom < percent).reverse();
  return (steps[0] ?? percent) / 100;
}

// The page that fills most of the scrolling area.
export function currentPage(pages, top, bottom) {
  let best = 1;
  let shown = -1;
  pages.forEach((page, index) => {
    const visible = Math.min(bottom, page.top + page.height) - Math.max(top, page.top);
    if (visible > shown) {
      shown = visible;
      best = index + 1;
    }
  });
  return best;
}

export async function initPdfViewer(element, options) {
  const labels = options.labels ?? {};
  const toolbar = element.querySelector('.celerik-pdf-toolbar');
  const viewport = element.querySelector('.celerik-pdf-viewport');
  const status = element.querySelector('.celerik-pdf-status');
  const pageInput = element.querySelector('.celerik-pdf-page-input');
  const total = element.querySelector('.celerik-pdf-total');
  const zoomSelect = element.querySelector('.celerik-pdf-zoom');
  const buttons = {
    previous: element.querySelector('.celerik-pdf-previous'),
    next: element.querySelector('.celerik-pdf-next'),
    zoomOut: element.querySelector('.celerik-pdf-zoom-out'),
    zoomIn: element.querySelector('.celerik-pdf-zoom-in'),
  };

  const message = (content, link = false) => {
    const paragraph = document.createElement('p');
    paragraph.className = 'celerik-pdf-message';
    paragraph.textContent = content;
    if (link) {
      const anchor = document.createElement('a');
      anchor.className = 'celerik-pdf-download';
      anchor.href = options.url;
      anchor.download = '';
      anchor.textContent = labels.download;
      paragraph.append(' ', anchor);
    }
    viewport.replaceChildren(paragraph);
  };

  message(labels.loading);
  viewport.setAttribute('aria-busy', 'true');

  let pdf;
  try {
    pdf = await getDocument({ url: options.url, isEvalSupported: false }).promise;
  } catch (error) {
    message(labels.error, true);
    viewport.removeAttribute('aria-busy');
    dispatch(element, 'pdf-error', { error });
    return { destroy() {} };
  }

  const count = pdf.numPages;
  // The unscaled size of every page, read once (PDF pages may differ in size).
  const sizes = [];
  for (let number = 1; number <= count; number += 1) {
    const page = await pdf.getPage(number);
    const { width, height } = page.getViewport({ scale: 1 });
    sizes.push({ width, height });
  }

  let scale = 1;
  let zoom = String(options.zoom ?? 'fit');
  let pages = [];
  let observer = null;
  let generation = 0;
  let current = Math.min(Math.max(1, options.page ?? 1), count);

  const fitScale = () => {
    const widest = Math.max(...sizes.map((size) => size.width));
    return Math.max(0.25, (viewport.clientWidth - GAP * 2) / widest);
  };

  async function draw(entry, run) {
    if (entry.drawn) return;
    entry.drawn = true;
    const page = await pdf.getPage(entry.number);
    if (run !== generation) return;
    const view = page.getViewport({ scale });
    const ratio = window.devicePixelRatio || 1;
    const canvas = document.createElement('canvas');
    canvas.className = 'celerik-pdf-canvas';
    canvas.setAttribute('aria-hidden', 'true');
    canvas.width = Math.floor(view.width * ratio);
    canvas.height = Math.floor(view.height * ratio);
    canvas.style.width = `${Math.floor(view.width)}px`;
    canvas.style.height = `${Math.floor(view.height)}px`;
    entry.element.prepend(canvas);
    entry.task = page.render({
      canvas,
      viewport: view,
      transform: ratio === 1 ? undefined : [ratio, 0, 0, ratio, 0, 0],
    });
    try {
      await entry.task.promise;
    } catch {
      // Cancelled by a new zoom.
    }
  }

  async function addText(entry, run) {
    const page = await pdf.getPage(entry.number);
    if (run !== generation) return;
    const layer = document.createElement('div');
    layer.className = 'textLayer celerik-pdf-text';
    entry.element.append(layer);
    await new TextLayer({
      textContentSource: page.streamTextContent(),
      container: layer,
      viewport: page.getViewport({ scale }),
    }).render();
  }

  function layout() {
    generation += 1;
    const run = generation;
    observer?.disconnect();
    pages.forEach((entry) => entry.task?.cancel());
    scale = zoom === 'fit' ? fitScale() : Number(zoom) / 100;
    zoomSelect.value = zoom;

    const fragment = document.createDocumentFragment();
    pages = sizes.map((size, index) => {
      const page = document.createElement('div');
      page.className = 'celerik-pdf-page';
      page.setAttribute('role', 'group');
      page.setAttribute('aria-label', text(labels.pageName, { page: index + 1, total: count }));
      page.dataset.page = String(index + 1);
      // The CSS variables of PDF.js' text layer.
      page.style.setProperty('--scale-factor', String(scale));
      page.style.setProperty('--total-scale-factor', String(scale));
      page.style.setProperty('--scale-round-x', '1px');
      page.style.setProperty('--scale-round-y', '1px');
      page.style.width = `${Math.floor(size.width * scale)}px`;
      page.style.height = `${Math.floor(size.height * scale)}px`;
      fragment.append(page);
      return { number: index + 1, element: page, drawn: false, task: null };
    });
    viewport.replaceChildren(fragment);

    observer = new IntersectionObserver((entries) => {
      for (const entry of entries) {
        if (entry.isIntersecting) {
          draw(pages[Number(entry.target.dataset.page) - 1], run);
        }
      }
    }, { root: viewport, rootMargin: '400px 0px' });
    pages.forEach((entry) => observer.observe(entry.element));

    // Text layers for every page, one after another.
    (async () => {
      for (const entry of pages) {
        if (run !== generation) return;
        await addText(entry, run);
      }
    })();
  }

  // Where the viewer scrolled to itself: that scroll keeps the page asked for (the last page
  // of a document may not reach the top of the area).
  let shownAt = null;

  function show(number, announce = false) {
    current = Math.min(Math.max(1, number), count);
    viewport.scrollTop = pages[current - 1].element.offsetTop - GAP;
    shownAt = viewport.scrollTop;
    update();
    if (announce) {
      status.textContent = text(labels.pageName, { page: current, total: count });
    }
  }

  function update() {
    pageInput.value = String(current);
    buttons.previous.disabled = current <= 1;
    buttons.next.disabled = current >= count;
    buttons.zoomOut.disabled = Math.round(scale * 100) <= ZOOMS[0];
    buttons.zoomIn.disabled = Math.round(scale * 100) >= ZOOMS[ZOOMS.length - 1];
  }

  function rezoom(value) {
    const keep = current;
    zoom = value;
    layout();
    show(keep);
  }

  pageInput.max = String(count);
  total.textContent = text(labels.of, { total: count });
  buttons.previous.addEventListener('click', () => show(current - 1, true));
  buttons.next.addEventListener('click', () => show(current + 1, true));
  pageInput.addEventListener('change', () => show(Number(pageInput.value) || 1, true));
  pageInput.addEventListener('keydown', (event) => {
    if (event.key === 'Enter') {
      event.preventDefault();
      show(Number(pageInput.value) || 1, true);
    }
  });
  buttons.zoomOut.addEventListener('click', () => rezoom(String(Math.round(stepZoom(scale, -1) * 100))));
  buttons.zoomIn.addEventListener('click', () => rezoom(String(Math.round(stepZoom(scale, 1) * 100))));
  zoomSelect.addEventListener('change', () => rezoom(zoomSelect.value));

  // The page number follows the scrolling.
  let ticking = false;
  viewport.addEventListener('scroll', () => {
    if (ticking) return;
    ticking = true;
    requestAnimationFrame(() => {
      ticking = false;
      const top = viewport.scrollTop;
      if (shownAt !== null && Math.abs(top - shownAt) < 2) return;
      shownAt = null;
      const tops = pages.map((entry) => ({ top: entry.element.offsetTop, height: entry.element.offsetHeight }));
      current = currentPage(tops, top, top + viewport.clientHeight);
      update();
    });
  });

  // "Fit width" follows the width of the viewer.
  let width = viewport.clientWidth;
  const resizer = new ResizeObserver(() => {
    if (zoom === 'fit' && Math.abs(viewport.clientWidth - width) > 1) {
      width = viewport.clientWidth;
      rezoom('fit');
    }
  });
  resizer.observe(viewport);

  toolbar.hidden = false;
  viewport.removeAttribute('aria-busy');
  layout();
  show(current);
  dispatch(element, 'pdf-loaded', { pages: count });

  return {
    get page() {
      return current;
    },
    get pages() {
      return count;
    },
    goTo: (number) => show(number, true),
    zoom: (value) => rezoom(String(value)),
    destroy() {
      observer?.disconnect();
      resizer.disconnect();
      pdf.destroy();
    },
  };
}
