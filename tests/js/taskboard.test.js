import { initTaskBoard } from '../../resources/js/taskboard.js';
import { readOptions } from '../../resources/js/core.js';
import { loadFixture } from './helpers.js';

const flush = async () => {
  for (let i = 0; i < 3; i += 1) {
    await new Promise((resolve) => { setTimeout(resolve, 0); });
  }
};
const reply = (status, body = {}) => ({
  ok: status >= 200 && status < 300,
  status,
  headers: { get: () => 'application/json' },
  json: async () => body,
});

describe('task board', () => {
  let element;
  let fetch;

  const titles = (column) => [...element.querySelectorAll(`.celerik-board-cards[data-column="${column}"] .celerik-board-card-title`)]
    .map((node) => node.textContent);
  const card = (title) => [...element.querySelectorAll('.celerik-board-card')]
    .find((node) => node.querySelector('.celerik-board-card-title').textContent === title);
  const press = (target, key) => target.dispatchEvent(new KeyboardEvent('keydown', { key, bubbles: true }));
  const count = (column) => element.querySelector(`.celerik-board-column[data-column="${column}"] .visually-hidden`).textContent;
  const said = async () => {
    await new Promise((resolve) => { setTimeout(resolve, 60); });
    return element.querySelector('[role=status]').textContent;
  };

  beforeEach(() => {
    element = loadFixture('taskboard');
    fetch = jest.fn().mockResolvedValue(reply(200));
    initTaskBoard(element, readOptions(element), { fetch });
  });

  test('PHP writes columns as named sections, with counts, cards and a Move form', () => {
    const section = element.querySelector('.celerik-board-column[data-column="doing"]');
    expect(document.getElementById(section.getAttribute('aria-labelledby')).textContent).toContain('In progress');
    expect(count('doing')).toBe('2 of 2 cards');
    expect(count('done')).toBe('1 card');
    expect(titles('todo')).toEqual(['Write <docs>', 'Fix login']);
    const form = card('Fix login').querySelector('form');
    expect(form.getAttribute('action')).toBe('/tasks/2/move');
    expect(form.querySelector('select').value).toBe('todo');
    expect(form.querySelector('label').textContent).toBe('Move Fix login to');
    expect(card('Fix login').getAttribute('aria-describedby')).toBe('sprint-help');
  });

  test('the Move form moves the card last in the chosen column and sends it', async () => {
    const form = card('Write <docs>').querySelector('form');
    form.querySelector('select').value = 'done';
    form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    await flush();

    expect(titles('done')).toEqual(['Plan', 'Write <docs>']);
    expect(count('done')).toBe('2 cards');
    expect(count('todo')).toBe('1 card');
    const [url, request] = fetch.mock.calls[0];
    expect(url).toBe(new URL('/tasks/1/move', document.baseURI).href);
    expect(Object.fromEntries(request.body.entries())).toEqual({
      column: 'done', position: '1', _method: 'PATCH', _token: 't0k3n',
    });
    expect(request.headers['X-CSRF-TOKEN']).toBe('t0k3n');
    expect(document.activeElement).toBe(form.querySelector('select'));
    expect(await said()).toBe('Moved Write <docs> to Done, position 2 of 2.');
  });

  test('a full column takes no more cards', async () => {
    const form = card('Fix login').querySelector('form');
    form.querySelector('select').value = 'doing';
    form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));

    expect(titles('doing')).toEqual(['Design board', 'API']);
    expect(form.querySelector('select').value).toBe('todo');
    expect(fetch).not.toHaveBeenCalled();
    expect(await said()).toBe('In progress is full.');
  });

  test('from the keyboard: Space picks up, arrows move, Space drops', async () => {
    const plan = card('Plan');
    plan.focus();
    press(plan, ' ');
    expect(plan.classList.contains('is-grabbed')).toBe(true);
    expect(await said()).toBe('Picked up Plan, in Done, position 1 of 1.');

    press(plan, 'ArrowLeft');
    expect(await said()).toBe('In progress is full.');
    expect(titles('done')).toEqual(['Plan']);

    // With room in the column, Left moves the card there at the same position; Up and Down
    // move it within the column.
    element.querySelector('[data-column="doing"] .celerik-board-card').remove();
    press(plan, 'ArrowLeft');
    expect(titles('doing')).toEqual(['Plan', 'API']);
    expect(document.activeElement).toBe(plan);
    press(plan, 'ArrowDown');
    expect(titles('doing')).toEqual(['API', 'Plan']);
    expect(await said()).toBe('Plan: In progress, position 2 of 2.');

    press(plan, ' ');
    await flush();
    expect(plan.classList.contains('is-grabbed')).toBe(false);
    expect(Object.fromEntries(fetch.mock.calls[0][1].body.entries())).toMatchObject({ column: 'doing', position: '1' });
    expect(await said()).toBe('Moved Plan to In progress, position 2 of 2.');
  });

  test('Escape puts a picked-up card back', async () => {
    const docs = card('Write <docs>');
    docs.focus();
    press(docs, ' ');
    press(docs, 'ArrowDown');
    // In progress is full: the card stays in To do.
    press(docs, 'ArrowRight');
    expect(titles('todo')).toEqual(['Fix login', 'Write <docs>']);
    press(docs, 'Escape');

    expect(titles('todo')).toEqual(['Write <docs>', 'Fix login']);
    expect(document.activeElement).toBe(docs);
    expect(fetch).not.toHaveBeenCalled();
    expect(await said()).toBe('Move cancelled. Write <docs> is back in To do.');
  });

  test('a refused move puts the card back and says why', async () => {
    fetch.mockResolvedValue(reply(422, { message: 'Only Ada can close this task.' }));
    const events = [];
    element.addEventListener('celerik:move', (event) => events.push(event.detail));
    const form = card('Fix login').querySelector('form');
    form.querySelector('select').value = 'done';
    form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    await flush();

    expect(titles('todo')).toEqual(['Write <docs>', 'Fix login']);
    expect(form.querySelector('select').value).toBe('todo');
    expect(events).toEqual([{ id: '2', column: 'todo', position: 1, ok: false }]);
    expect(await said()).toBe('Only Ada can close this task. The card could not be moved. It is back in To do.');
  });

  test('Space on a card control does not pick the card up', () => {
    const select = card('Plan').querySelector('select');
    press(select, ' ');
    expect(card('Plan').classList.contains('is-grabbed')).toBe(false);
  });
});
