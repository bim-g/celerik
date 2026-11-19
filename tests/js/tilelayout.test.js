import { jest } from '@jest/globals';
import { initTileLayout } from '../../resources/js/tilelayout.js';
import { readOptions } from '../../resources/js/core.js';
import { loadFixture } from './helpers.js';

const keydown = (target, key, init = {}) => target.dispatchEvent(new KeyboardEvent('keydown', { key, bubbles: true, ...init }));

describe('tile layout', () => {
  let element;
  const keys = () => [...element.querySelectorAll('.celerik-tile')].map((tile) => tile.dataset.tile);
  const tile = (key) => element.querySelector(`[data-tile="${key}"]`);
  const handle = (key) => tile(key).querySelector('.celerik-tile-handle');
  const tools = (key) => tile(key).querySelector('.celerik-tile-tools');
  const command = (key, name) => tile(key).querySelector(`[data-tile-command="${name}"]`);
  const status = () => element.querySelector('[role=status]');

  beforeEach(() => {
    window.localStorage.clear();
    jest.useFakeTimers();
    element = loadFixture('tilelayout');
  });

  afterEach(() => {
    jest.useRealTimers();
  });

  test('PHP lays the tiles out: titled regions, spans as classes, tools hidden', () => {
    expect(keys()).toEqual(['revenue', 'orders', 'visits']);
    expect(element.classList.contains('celerik-tilelayout-cols-4')).toBe(true);
    expect(tile('revenue').classList.contains('celerik-tile-cols-2')).toBe(true);
    expect(tile('orders').classList.contains('celerik-tile-rows-2')).toBe(true);
    expect(tile('visits').getAttribute('aria-labelledby')).toBe('dashboard-tile-visits-title');
    expect(handle('visits').getAttribute('aria-label')).toBe('Arrange Visits <today>');
    expect(tools('revenue').hidden).toBe(true);
    expect(element.querySelector('[style]')).toBeNull();
  });

  test('the Arrange button shows the tools; Escape hides them', () => {
    initTileLayout(element, readOptions(element));
    handle('orders').click();
    expect(handle('orders').getAttribute('aria-expanded')).toBe('true');
    expect(tools('orders').hidden).toBe(false);
    command('orders', 'later').focus();
    keydown(command('orders', 'later'), 'Escape');
    expect(tools('orders').hidden).toBe(true);
    expect(document.activeElement).toBe(handle('orders'));
  });

  test('the tools move and resize the tile, say so, and cannot go past the limits', () => {
    initTileLayout(element, readOptions(element));
    const changes = [];
    element.addEventListener('celerik:change', (event) => changes.push(event.detail.tiles));

    expect(command('revenue', 'earlier').getAttribute('aria-disabled')).toBe('true');
    expect(command('visits', 'later').getAttribute('aria-disabled')).toBe('true');
    expect(command('visits', 'narrower').getAttribute('aria-disabled')).toBe('true');

    command('visits', 'earlier').focus();
    command('visits', 'earlier').click();
    expect(keys()).toEqual(['revenue', 'visits', 'orders']);
    expect(document.activeElement).toBe(command('visits', 'earlier'));
    jest.advanceTimersByTime(60);
    expect(status().textContent).toBe('Visits <today>: position 2 of 3.');
    expect(command('visits', 'later').hasAttribute('aria-disabled')).toBe(false);

    command('revenue', 'wider').click();
    command('revenue', 'wider').click();
    command('revenue', 'wider').click();
    expect(tile('revenue').classList.contains('celerik-tile-cols-4')).toBe(true);
    expect(tile('revenue').classList.contains('celerik-tile-cols-2')).toBe(false);
    expect(command('revenue', 'wider').getAttribute('aria-disabled')).toBe('true');
    jest.advanceTimersByTime(60);
    expect(status().textContent).toBe('Revenue: width 4 of 4, height 1 of 6.');

    command('orders', 'shorter').click();
    expect(tile('orders').dataset.rows).toBe('1');
    command('orders', 'shorter').click();
    expect(tile('orders').dataset.rows).toBe('1');

    expect(changes).toHaveLength(4);
    expect(changes.at(-1)).toEqual([
      { key: 'revenue', cols: 4, rows: 1 },
      { key: 'visits', cols: 1, rows: 1 },
      { key: 'orders', cols: 1, rows: 1 },
    ]);
  });

  test('on the Arrange button, arrows move the tile and Shift arrows resize it', () => {
    initTileLayout(element, readOptions(element));
    handle('revenue').focus();
    keydown(handle('revenue'), 'ArrowRight');
    expect(keys()).toEqual(['orders', 'revenue', 'visits']);
    expect(document.activeElement).toBe(handle('revenue'));
    keydown(handle('revenue'), 'ArrowDown');
    expect(keys()).toEqual(['orders', 'visits', 'revenue']);
    keydown(handle('revenue'), 'ArrowUp');
    expect(keys()).toEqual(['orders', 'revenue', 'visits']);

    keydown(handle('revenue'), 'ArrowLeft', { shiftKey: true });
    expect(tile('revenue').dataset.cols).toBe('1');
    keydown(handle('revenue'), 'ArrowDown', { shiftKey: true });
    expect(tile('revenue').dataset.rows).toBe('2');
  });

  test('a layout that is only reorderable ignores Shift arrows', () => {
    initTileLayout(element, { ...readOptions(element), resizable: false });
    keydown(handle('revenue'), 'ArrowRight', { shiftKey: true });
    expect(tile('revenue').dataset.cols).toBe('2');
    keydown(handle('revenue'), 'ArrowRight');
    expect(keys()).toEqual(['orders', 'revenue', 'visits']);
  });

  test('a remembered layout comes back, with new tiles after it and spans within limits', () => {
    window.localStorage.setItem('celerik-tilelayout:dashboard', JSON.stringify([
      { key: 'visits', cols: 3, rows: 1 },
      { key: 'gone', cols: 1, rows: 1 },
      { key: 'revenue', cols: 9, rows: 9 },
    ]));
    const api = initTileLayout(element, readOptions(element));
    expect(keys()).toEqual(['visits', 'revenue', 'orders']);
    expect(api.layout()).toEqual([
      { key: 'visits', cols: 3, rows: 1 },
      { key: 'revenue', cols: 4, rows: 6 },
      { key: 'orders', cols: 1, rows: 2 },
    ]);

    api.move('orders', 0);
    expect(JSON.parse(window.localStorage.getItem('celerik-tilelayout:dashboard')).map((entry) => entry.key))
      .toEqual(['orders', 'visits', 'revenue']);
  });

  test('broken saved data is ignored', () => {
    window.localStorage.setItem('celerik-tilelayout:dashboard', '{oops');
    initTileLayout(element, readOptions(element));
    expect(keys()).toEqual(['revenue', 'orders', 'visits']);
  });
});
