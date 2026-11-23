import { jest } from '@jest/globals';
import { initChip, initTooltip } from '../../resources/js/display.js';
import { initUserMenu } from '../../resources/js/usermenu.js';
import { readOptions } from '../../resources/js/core.js';
import { loadFixture } from './helpers.js';

describe('removable chips', () => {
  let root;
  const chip = (id) => document.getElementById(id);
  const removeButton = (id) => chip(id).querySelector('.caelrik-chip-remove');

  beforeEach(() => {
    jest.useFakeTimers();
    // loadFixture returns the first chip; the chips share a container.
    root = loadFixture('chips').parentElement;
    for (const element of root.querySelectorAll('[data-caelrik="chip"]')) {
      initChip(element, readOptions(element));
    }
  });

  afterEach(() => {
    jest.useRealTimers();
  });

  test('Remove takes the chip and its field away, moves focus to the next chip and says so', () => {
    const removed = [];
    root.addEventListener('caelrik:remove', (event) => removed.push(event.detail));
    removeButton('chip-js').focus();
    removeButton('chip-js').click();

    expect(chip('chip-js')).toBeNull();
    expect([...root.querySelectorAll('input[name="skills[]"]')].map((input) => input.value)).toEqual(['php', 'go']);
    expect(document.activeElement).toBe(removeButton('chip-go'));
    expect(removed).toEqual([{ label: 'JavaScript', value: 'js' }]);
    jest.advanceTimersByTime(60);
    expect(document.getElementById('caelrik-chip-status').textContent).toBe('JavaScript removed.');

    // The last chip gives focus to the one before it.
    removeButton('chip-go').click();
    expect(document.activeElement).toBe(removeButton('chip-php'));
  });

  test('a cancelled caelrik:remove keeps the chip', () => {
    root.addEventListener('caelrik:remove', (event) => event.preventDefault());
    removeButton('chip-php').click();
    expect(chip('chip-php')).not.toBeNull();
  });

  test('a filter chip is a real checkbox, labelled by the chip', () => {
    const box = root.querySelector('#chip-filter');
    expect(box.type).toBe('checkbox');
    expect(box.checked).toBe(true);
    expect(root.querySelector('label[for="chip-filter"]').textContent).toBe('Remote');
  });
});

describe('tooltip', () => {
  let element;
  let button;
  let tip;

  beforeEach(() => {
    jest.useFakeTimers();
    element = loadFixture('tooltip');
    button = element.querySelector('button');
    tip = element.querySelector('[role=tooltip]');
    initTooltip(element, readOptions(element));
  });

  afterEach(() => {
    jest.useRealTimers();
  });

  test('describes its control, which keeps its own name', () => {
    expect(button.getAttribute('aria-describedby')).toBe('save-tip-tip');
    expect(button.textContent).toBe('Save');
    expect(tip.hidden).toBe(true);
  });

  test('shows on focus, hides on Escape without moving focus, and on blur', () => {
    const escapes = [];
    document.addEventListener('keydown', (event) => escapes.push(event.key));
    button.focus();
    expect(tip.hidden).toBe(false);
    expect(tip.classList.contains('is-above') || tip.classList.contains('is-below')).toBe(true);

    button.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
    expect(tip.hidden).toBe(true);
    expect(document.activeElement).toBe(button);
    // The Escape that hid the tooltip goes no further (a dialog around it stays open).
    expect(escapes).toEqual([]);

    button.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
    expect(escapes).toEqual(['Escape']);

    button.blur();
    button.focus();
    expect(tip.hidden).toBe(false);
    button.blur();
    expect(tip.hidden).toBe(true);
  });

  test('stays while the pointer is on the control or the tooltip, hides shortly after it leaves', () => {
    element.dispatchEvent(new Event('pointerenter'));
    expect(tip.hidden).toBe(false);
    element.dispatchEvent(new Event('pointerleave'));
    jest.advanceTimersByTime(100);
    expect(tip.hidden).toBe(false);
    // Back on the tooltip in time: it stays.
    element.dispatchEvent(new Event('pointerenter'));
    jest.advanceTimersByTime(500);
    expect(tip.hidden).toBe(false);
    element.dispatchEvent(new Event('pointerleave'));
    jest.advanceTimersByTime(200);
    expect(tip.hidden).toBe(true);
  });

  test('keeps descriptions the control already has', () => {
    element = loadFixture('tooltip');
    element.querySelector('button').setAttribute('aria-describedby', 'hint');
    initTooltip(element, {});
    expect(element.querySelector('button').getAttribute('aria-describedby')).toBe('hint save-tip-tip');
  });
});

describe('popover', () => {
  test('a disclosure: Escape closes it and returns focus to its button', () => {
    const element = loadFixture('popover');
    initUserMenu(element);
    const summary = element.querySelector('summary');
    expect(summary.getAttribute('aria-label')).toBe('About pricing');
    element.open = true;
    element.querySelector('a').focus();
    element.querySelector('a').dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
    expect(element.open).toBe(false);
    expect(document.activeElement).toBe(summary);
  });
});
