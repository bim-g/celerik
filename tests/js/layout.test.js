import { initAccordion, initSplitter } from '../../resources/js/layout.js';
import { readOptions } from '../../resources/js/core.js';
import { loadFixture } from './helpers.js';

const press = (key) => document.activeElement.dispatchEvent(new KeyboardEvent('keydown', { key, bubbles: true }));

describe('accordion', () => {
  let element;
  let buttons;
  const panel = (button) => document.getElementById(button.getAttribute('aria-controls'));

  beforeEach(() => {
    element = loadFixture('accordion');
    buttons = [...element.querySelectorAll('.accordion-button')];
  });

  test('without JavaScript every panel is shown; with it, each takes its own state', () => {
    expect(buttons.map((button) => button.getAttribute('aria-expanded'))).toEqual(['true', 'true', 'true']);
    initAccordion(element, readOptions(element));
    expect(buttons.map((button) => button.getAttribute('aria-expanded'))).toEqual(['true', 'false', 'false']);
    expect(buttons.map((button) => panel(button).hidden)).toEqual([false, true, true]);
    expect(panel(buttons[1]).getAttribute('role')).toBe('region');
    expect(document.getElementById(panel(buttons[1]).getAttribute('aria-labelledby'))).toBe(buttons[1]);
    expect(buttons[1].closest('h3')).not.toBeNull();
  });

  test('one panel open at a time; arrows, Home and End move between headings', () => {
    initAccordion(element, readOptions(element));
    buttons[1].click();
    expect(buttons.map((button) => button.getAttribute('aria-expanded'))).toEqual(['false', 'true', 'false']);
    buttons[1].click();
    expect(panel(buttons[1]).hidden).toBe(true);

    buttons[0].focus();
    press('ArrowDown');
    expect(document.activeElement).toBe(buttons[1]);
    press('End');
    expect(document.activeElement).toBe(buttons[2]);
    press('ArrowDown');
    expect(document.activeElement).toBe(buttons[0]);
    press('ArrowUp');
    expect(document.activeElement).toBe(buttons[2]);
  });

  test('multiple() lets panels open on their own', () => {
    initAccordion(element, { multiple: true });
    buttons[2].click();
    expect(buttons.map((button) => button.getAttribute('aria-expanded'))).toEqual(['true', 'false', 'true']);
  });
});

describe('card', () => {
  test('the title is a link that covers the card; the actions stay above it', () => {
    const card = loadFixture('card');
    const link = card.querySelector('.card-title a');
    expect(link.getAttribute('href')).toBe('/reports/revenue');
    expect(link.classList.contains('stretched-link')).toBe(true);
    expect(card.querySelector('.celerik-card-actions a').getAttribute('href')).toBe('/reports/revenue.csv');
    expect(card.querySelector('img').getAttribute('alt')).toBe('Revenue by week');
    expect(card.querySelector('.card-footer').textContent).toBe('Updated today');
  });
});

describe('splitter', () => {
  let element;
  let handle;
  let panes;

  beforeEach(() => {
    window.localStorage.clear();
    element = loadFixture('splitter');
    panes = [...element.querySelectorAll('.celerik-splitter-pane')];
    handle = element.querySelector('[role=separator]');
  });

  test('the divider is a named, focusable separator valued by the first pane\'s size', () => {
    initSplitter(element, readOptions(element));
    expect(handle.getAttribute('aria-label')).toBe('Resize Folders');
    expect(handle.getAttribute('aria-orientation')).toBe('vertical');
    expect(handle.getAttribute('aria-controls')).toBe(panes[0].id);
    expect(handle.getAttribute('aria-valuenow')).toBe('30');
    expect(panes.map((pane) => pane.style.flex)).toEqual(['30 1 0px', '70 1 0px']);
  });

  test('arrow keys resize within the limits; Home, End and Enter', () => {
    const resizes = [];
    element.addEventListener('celerik:resize', (event) => resizes.push(event.detail.sizes));
    initSplitter(element, readOptions(element));
    handle.focus();
    press('ArrowRight');
    expect(handle.getAttribute('aria-valuenow')).toBe('35');
    press('ArrowLeft');
    press('ArrowLeft');
    expect(handle.getAttribute('aria-valuenow')).toBe('25');
    press('Home');
    expect(handle.getAttribute('aria-valuenow')).toBe('20');
    press('End');
    expect(handle.getAttribute('aria-valuenow')).toBe('60');
    expect(panes[1].style.flex).toBe('40 1 0px');
    press('Enter');
    expect(handle.getAttribute('aria-valuenow')).toBe('20');
    press('Enter');
    expect(handle.getAttribute('aria-valuenow')).toBe('60');
    expect(resizes.at(-1)).toEqual([60, 40]);
  });

  test('remember() keeps the sizes in the browser', () => {
    const splitter = initSplitter(element, { orientation: 'horizontal', remember: true });
    splitter.resize(0, 45);

    const again = loadFixture('splitter');
    initSplitter(again, { orientation: 'horizontal', remember: true });
    expect(again.querySelector('[role=separator]').getAttribute('aria-valuenow')).toBe('45');
  });
});
