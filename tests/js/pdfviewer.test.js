import axe from 'axe-core';
import { readOptions } from '../../resources/js/core.js';
import { loadFixture } from './helpers.js';

// PDF.js needs a browser (workers, canvas); the tests replace it with a document of three A4
// pages. Drawing and text are checked in the browser (tests/browser/pdf.spec.mjs).
let mockFailing = false;
const mockRendered = [];
jest.mock('pdfjs-dist', () => ({
  GlobalWorkerOptions: {},
  getDocument: jest.fn(() => ({
    promise: mockFailing ? Promise.reject(new Error('Missing PDF')) : Promise.resolve({
      numPages: 3,
      destroy: jest.fn(),
      getPage: async (number) => ({
        getViewport: ({ scale }) => ({ width: 595 * scale, height: 842 * scale, scale }),
        render: () => {
          mockRendered.push(number);
          return { promise: Promise.resolve(), cancel: jest.fn() };
        },
        streamTextContent: () => null,
      }),
    }),
  })),
  TextLayer: class {
    constructor({ container }) {
      this.container = container;
    }

    async render() {
      this.container.textContent = 'Text of the page';
    }
  },
}));

import { currentPage, initPdfViewer, stepZoom } from '../../resources/js/pdfviewer.js';

beforeAll(() => {
  window.IntersectionObserver = class {
    constructor(callback) {
      this.callback = callback;
    }

    observe(target) {
      this.callback([{ isIntersecting: true, target }]);
    }

    disconnect() {}
  };
  window.ResizeObserver = class {
    observe() {}

    disconnect() {}
  };
  window.requestAnimationFrame = (callback) => callback();
  HTMLCanvasElement.prototype.getContext = () => ({});
});

const flush = () => new Promise((resolve) => setTimeout(resolve, 0));

describe('PDF viewer', () => {
  let element;
  let viewer;

  beforeEach(async () => {
    mockFailing = false;
    mockRendered.length = 0;
    element = loadFixture('pdfviewer');
    Object.defineProperty(element.querySelector('.caelrik-pdf-viewport'), 'clientWidth', { value: 627, configurable: true });
    viewer = await initPdfViewer(element, readOptions(element));
    await flush();
  });

  afterEach(() => viewer.destroy());

  const pages = () => [...element.querySelectorAll('.caelrik-pdf-page')];

  test('shows the toolbar and a named group per page, sized to the width', () => {
    expect(element.querySelector('.caelrik-pdf-toolbar').hidden).toBe(false);
    expect(element.querySelector('.caelrik-pdf-total').textContent).toBe('of 3');
    expect(element.querySelector('.caelrik-pdf-page-input').max).toBe('3');
    expect(pages().map((page) => page.getAttribute('aria-label'))).toEqual(['Page 1 of 3', 'Page 2 of 3', 'Page 3 of 3']);
    // 627 px wide, less 16 px on each side: 595 px, a scale of 1.
    expect(pages()[0].style.width).toBe('595px');
    expect(pages()[0].style.getPropertyValue('--total-scale-factor')).toBe('1');
    expect(pages()[0].querySelector('canvas').getAttribute('aria-hidden')).toBe('true');
    expect(pages()[2].querySelector('.textLayer').textContent).toBe('Text of the page');
    expect(mockRendered).toEqual([1, 2, 3]);
  });

  test('previous, next and the page number, announced', () => {
    const next = element.querySelector('.caelrik-pdf-next');
    const previous = element.querySelector('.caelrik-pdf-previous');
    const status = element.querySelector('.caelrik-pdf-status');
    expect(previous.disabled).toBe(true);
    next.click();
    expect(viewer.page).toBe(2);
    expect(element.querySelector('.caelrik-pdf-page-input').value).toBe('2');
    expect(status.textContent).toBe('Page 2 of 3');

    const input = element.querySelector('.caelrik-pdf-page-input');
    input.value = '9';
    input.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));
    expect(viewer.page).toBe(3);
    expect(next.disabled).toBe(true);
    expect(status.textContent).toBe('Page 3 of 3');
  });

  test('zoom by steps and by the select', async () => {
    element.querySelector('.caelrik-pdf-zoom-in').click();
    expect(pages()[0].style.width).toBe('743px');
    expect(element.querySelector('.caelrik-pdf-zoom').value).toBe('125');

    const select = element.querySelector('.caelrik-pdf-zoom');
    select.value = '50';
    select.dispatchEvent(new Event('change'));
    expect(pages()[0].style.width).toBe('297px');
    expect(element.querySelector('.caelrik-pdf-zoom-out').disabled).toBe(true);

    select.value = 'fit';
    select.dispatchEvent(new Event('change'));
    expect(pages()[0].style.width).toBe('595px');
  });

  test('a document that cannot be opened: a message and a download link', async () => {
    mockFailing = true;
    const other = loadFixture('pdfviewer');
    const events = [];
    other.addEventListener('caelrik:pdf-error', (event) => events.push(event.detail.error.message));
    await initPdfViewer(other, readOptions(other));
    const message = other.querySelector('.caelrik-pdf-viewport .caelrik-pdf-message');
    expect(message.textContent).toBe('This document could not be opened. Download it instead. Download the PDF');
    expect(message.querySelector('a').getAttribute('href')).toBe('/files/report.pdf');
    expect(other.querySelector('.caelrik-pdf-toolbar').hidden).toBe(true);
    expect(events).toEqual(['Missing PDF']);
  });

  test('no axe-core violations, before and after loading', async () => {
    const run = async (root) => (await axe.run(root, {
      runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'] },
      rules: { 'color-contrast': { enabled: false } },
    })).violations.map((violation) => violation.id);
    expect(await run(element)).toEqual([]);
    const fresh = loadFixture('pdfviewer');
    expect(await run(fresh)).toEqual([]);
  });
});

test('stepZoom goes to the next step up or down', () => {
  expect(stepZoom(1, 1)).toBe(1.25);
  expect(stepZoom(1, -1)).toBe(0.75);
  expect(stepZoom(1.07, 1)).toBe(1.25);
  expect(stepZoom(1.07, -1)).toBe(1);
  expect(stepZoom(3, 1)).toBe(3);
  expect(stepZoom(0.5, -1)).toBe(0.5);
});

test('currentPage is the page that fills most of the area', () => {
  const pages = [{ top: 0, height: 800 }, { top: 816, height: 800 }, { top: 1632, height: 800 }];
  expect(currentPage(pages, 0, 600)).toBe(1);
  expect(currentPage(pages, 600, 1200)).toBe(2);
  expect(currentPage(pages, 1900, 2500)).toBe(3);
});
