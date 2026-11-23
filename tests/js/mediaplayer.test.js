import axe from 'axe-core';
import { readOptions } from '../../resources/js/core.js';
import { loadFixture } from './helpers.js';

// Plyr needs a browser that plays media: the tests replace it with a player that records its
// configuration and moves its time. Playing is checked in the browser (tests/browser/media.spec.mjs).
const mockPlayers = [];
jest.mock('plyr', () => class {
  constructor(media, config) {
    this.media = media;
    this.config = config;
    this.currentTime = 0;
    this.handlers = {};
    this.played = 0;
    mockPlayers.push(this);
  }

  on(name, handler) {
    this.handlers[name] = handler;
  }

  play() {
    this.played += 1;
    return Promise.resolve();
  }

  destroy() {}
});

import { activeCue, initMediaPlayer } from '../../resources/js/mediaplayer.js';

describe('media player', () => {
  let element;
  let player;

  beforeEach(() => {
    mockPlayers.length = 0;
    element = loadFixture('mediaplayer');
    ({ player } = initMediaPlayer(element, readOptions(element)));
  });

  test('Plyr gets the title, the bundled icons, visible controls and no CDN', () => {
    expect(player.media).toBe(element.querySelector('audio'));
    expect(player.config).toMatchObject({
      title: 'Episode 12',
      blankVideo: '',
      hideControls: false,
      keyboard: { focused: true, global: false },
      captions: { active: false },
    });
    expect(String(player.config.iconUrl)).not.toMatch(/cdn/);
  });

  test('transcript times become buttons that play from there', () => {
    const buttons = [...element.querySelectorAll('.caelrik-media-seek')];
    expect(buttons.map((button) => button.getAttribute('aria-label'))).toEqual([
      'Play from 0:00', 'Play from 1:05', 'Play from 2:10',
    ]);
    // The <time> stays inside the button.
    expect(buttons[1].querySelector('time').getAttribute('datetime')).toBe('PT65S');
    buttons[1].click();
    expect(player.currentTime).toBe(65);
    expect(player.played).toBe(1);
  });

  test('the line being spoken is marked', () => {
    const cues = [...element.querySelectorAll('.caelrik-media-cue')];
    player.currentTime = 70;
    player.handlers.timeupdate();
    expect(cues.map((cue) => cue.getAttribute('aria-current'))).toEqual([null, 'true', null]);
    player.currentTime = 200;
    player.handlers.seeked();
    expect(cues.map((cue) => cue.getAttribute('aria-current'))).toEqual([null, null, 'true']);
  });

  test('raw options reach Plyr', () => {
    const other = loadFixture('mediaplayer');
    initMediaPlayer(other, { ...readOptions(other), options: { i18n: { play: 'Lire' }, hideControls: true } });
    expect(mockPlayers[1].config.i18n).toEqual({ play: 'Lire' });
    expect(mockPlayers[1].config.hideControls).toBe(true);
  });

  test('no axe-core violations', async () => {
    element.querySelector('details').open = true;
    const results = await axe.run(element, {
      runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'] },
      rules: { 'color-contrast': { enabled: false } },
    });
    expect(results.violations.map((violation) => violation.id)).toEqual([]);
  });
});

test('activeCue is the last line that has started', () => {
  expect(activeCue([0, 65, 130], 0)).toBe(0);
  expect(activeCue([5, 65], 2)).toBe(-1);
  expect(activeCue([0, 65, 130], 64.9)).toBe(0);
  expect(activeCue([0, 65, 130], 999)).toBe(2);
});
