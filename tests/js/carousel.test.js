import { jest } from '@jest/globals';
import { initCarousel } from '../../resources/js/carousel.js';
import { readOptions } from '../../resources/js/core.js';
import { loadFixture } from './helpers.js';

const active = (root) => [...root.querySelectorAll('.celerik-carousel-slide')]
  .findIndex((slide) => slide.classList.contains('is-active'));
const button = (root, name) => root.querySelector(`.celerik-carousel-${name}`);
const dots = (root) => [...root.querySelectorAll('.celerik-carousel-dot')];

describe('carousel', () => {
  let root;
  let carousel;

  beforeEach(() => {
    root = loadFixture('carousel');
    carousel = initCarousel(root, readOptions(root));
  });

  test('Next and Previous move through the slides and loop, and say which one is current', () => {
    const changes = [];
    root.addEventListener('celerik:change', (event) => changes.push(event.detail.index));
    expect(active(root)).toBe(0);
    expect(root.querySelector('.celerik-carousel-slides').getAttribute('aria-live')).toBe('polite');

    button(root, 'next').click();
    expect(active(root)).toBe(1);
    expect(dots(root)[1].getAttribute('aria-current')).toBe('true');
    expect(dots(root)[0].hasAttribute('aria-current')).toBe(false);

    button(root, 'next').click();
    button(root, 'next').click();
    expect(active(root)).toBe(0);
    button(root, 'previous').click();
    expect(active(root)).toBe(2);
    expect(changes).toEqual([1, 2, 0, 2]);
    expect(carousel.index).toBe(2);
  });

  test('a slide button shows its slide', () => {
    dots(root)[2].click();
    expect(active(root)).toBe(2);
    expect(dots(root)[2].getAttribute('aria-current')).toBe('true');
    // The same slide again changes nothing.
    const changes = [];
    root.addEventListener('celerik:change', (event) => changes.push(event.detail.index));
    dots(root)[2].click();
    expect(changes).toEqual([]);
  });

  test('a horizontal swipe on a touch screen moves; a mouse drag does not', () => {
    const region = root.querySelector('.celerik-carousel-slides');
    const swipe = (pointerType, from, to) => {
      region.dispatchEvent(Object.assign(new Event('pointerdown'), { pointerType, clientX: from }));
      region.dispatchEvent(Object.assign(new Event('pointerup'), { pointerType, clientX: to }));
    };
    swipe('touch', 200, 100);
    expect(active(root)).toBe(1);
    swipe('touch', 100, 200);
    expect(active(root)).toBe(0);
    swipe('touch', 100, 120);
    expect(active(root)).toBe(0);
    swipe('mouse', 200, 50);
    expect(active(root)).toBe(0);
  });
});

describe('carousel with automatic rotation', () => {
  let root;
  let carousel;
  const region = () => root.querySelector('.celerik-carousel-slides');

  beforeEach(() => {
    jest.useFakeTimers();
    root = loadFixture('carousel-autoplay');
    carousel = initCarousel(root, readOptions(root));
  });

  afterEach(() => {
    jest.useRealTimers();
  });

  test('it rotates silently and stops on the last slide without loop', () => {
    expect(carousel.playing).toBe(true);
    expect(region().getAttribute('aria-live')).toBe('off');
    expect(button(root, 'previous').getAttribute('aria-disabled')).toBe('true');
    jest.advanceTimersByTime(5000);
    expect(active(root)).toBe(1);
    expect(button(root, 'previous').hasAttribute('aria-disabled')).toBe(false);
    jest.advanceTimersByTime(5000);
    expect(active(root)).toBe(2);
    expect(button(root, 'next').getAttribute('aria-disabled')).toBe('true');
    jest.advanceTimersByTime(5000);
    expect(active(root)).toBe(2);
    expect(carousel.playing).toBe(false);
    expect(region().getAttribute('aria-live')).toBe('polite');
    expect(button(root, 'rotate').getAttribute('aria-label')).toBe('Start automatic slide show');
  });

  test('Stop and Start, with the button named after what it does', () => {
    const rotate = button(root, 'rotate');
    rotate.click();
    expect(carousel.playing).toBe(false);
    expect(root.classList.contains('is-playing')).toBe(false);
    expect(rotate.getAttribute('aria-label')).toBe('Start automatic slide show');
    jest.advanceTimersByTime(20000);
    expect(active(root)).toBe(0);

    rotate.click();
    expect(rotate.getAttribute('aria-label')).toBe('Stop automatic slide show');
    expect(region().getAttribute('aria-live')).toBe('off');
    jest.advanceTimersByTime(5000);
    expect(active(root)).toBe(1);
  });

  test('the pointer or focus in the carousel pauses it; picking a slide stops it', () => {
    root.dispatchEvent(new Event('pointerenter'));
    jest.advanceTimersByTime(12000);
    expect(active(root)).toBe(0);
    root.dispatchEvent(new Event('pointerleave'));
    jest.advanceTimersByTime(5000);
    expect(active(root)).toBe(1);

    button(root, 'rotate').focus();
    jest.advanceTimersByTime(12000);
    expect(active(root)).toBe(1);
    expect(carousel.playing).toBe(true);
    button(root, 'rotate').blur();
    jest.advanceTimersByTime(5000);
    expect(active(root)).toBe(2);

    button(root, 'previous').click();
    expect(active(root)).toBe(1);
    expect(carousel.playing).toBe(false);
    // At the start, Previous is unavailable but keeps its focus.
    button(root, 'previous').click();
    button(root, 'previous').click();
    expect(active(root)).toBe(0);
  });

  test('it does not start for reduced motion', () => {
    const matchMedia = window.matchMedia;
    window.matchMedia = (query) => ({ matches: query.includes('reduce') });
    try {
      root = loadFixture('carousel-autoplay');
      carousel = initCarousel(root, readOptions(root));
      expect(carousel.playing).toBe(false);
      expect(region().getAttribute('aria-live')).toBe('polite');
      jest.advanceTimersByTime(20000);
      expect(active(root)).toBe(0);
    } finally {
      window.matchMedia = matchMedia;
    }
  });
});
