// Media player (Components\MediaPlayer), with Plyr: the same accessible controls in every
// browser (named buttons, a seek bar and volume as sliders, captions and speed menus, the
// keyboard when the player has focus). Plyr's icons come from the bundle, never from its
// CDN. The transcript's times become buttons that play the media from there, and the line
// being spoken is marked while the media plays.
import Plyr from 'plyr';
import 'plyr/dist/plyr.css';
import iconUrl from '@plyr-dist/plyr.svg?url';
import { deepMerge } from './core.js';

const text = (template, values) => template.replace(/\{(\w+)\}/g, (match, key) => values[key] ?? match);

// The index of the line being spoken at `time`: the last one that has started.
export function activeCue(times, time) {
  let active = -1;
  times.forEach((start, index) => {
    if (start <= time) active = index;
  });
  return active;
}

export function initMediaPlayer(element, options = {}) {
  const media = element.querySelector('.caelrik-media-element');
  const labels = options.labels ?? {};
  const hasCaptions = Boolean(media.querySelector('track[kind="captions"][default], track[kind="subtitles"][default]'));

  const player = new Plyr(media, deepMerge({
    title: options.title,
    iconUrl,
    // Plyr loads this when it is destroyed; nothing is loaded from its CDN.
    blankVideo: '',
    captions: { active: hasCaptions, language: 'auto', update: true },
    keyboard: { focused: true, global: false },
    tooltips: { controls: true, seek: true },
    // Plyr hides its controls during playback; keyboard and screen reader users need them.
    hideControls: false,
    controls: ['play-large', 'play', 'progress', 'current-time', 'duration', 'mute', 'volume', 'captions',
      'settings', 'pip', 'fullscreen'],
  }, options.options));

  const cues = [...element.querySelectorAll('.caelrik-media-cue')];
  const times = cues.map((cue) => Number(cue.dataset.time));
  for (const cue of cues) {
    const time = cue.querySelector('.caelrik-media-time');
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'btn btn-link caelrik-media-seek';
    button.setAttribute('aria-label', text(labels.playFrom ?? 'Play from {time}', { time: time.textContent }));
    time.replaceWith(button);
    button.append(time);
    button.addEventListener('click', () => {
      player.currentTime = Number(cue.dataset.time);
      player.play()?.catch?.(() => {});
    });
  }

  let current = -1;
  const follow = () => {
    const index = activeCue(times, player.currentTime);
    if (index === current) return;
    cues[current]?.removeAttribute('aria-current');
    cues[index]?.setAttribute('aria-current', 'true');
    current = index;
  };
  if (cues.length) {
    player.on('timeupdate', follow);
    player.on('seeked', follow);
  }

  return {
    player,
    destroy() {
      player.destroy();
    },
  };
}
