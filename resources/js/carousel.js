// Carousel (PHP: Components\Carousel), the WAI-ARIA carousel pattern.
// - Previous, Next and a button per slide show a slide; the slides are a polite live region,
//   so the new slide is read. Without loop, Previous and Next are aria-disabled at the ends
//   (they keep their focus).
// - Automatic rotation (options.autoplay, in seconds): the slides' region is silent while
//   they rotate. It pauses while the pointer or focus is in the carousel, stops for good once
//   the user picks a slide or presses Stop, and does not start for reduced motion (WCAG 2.2.2).
// - A horizontal swipe on a touch screen or with a pen shows the next or previous slide.
// - Each change fires celerik:change with { index } (0-based).

const SWIPE = 40;

const disable = (button, disabled) => {
  if (disabled) {
    button?.setAttribute('aria-disabled', 'true');
  } else {
    button?.removeAttribute('aria-disabled');
  }
};

export function initCarousel(element, options = {}) {
  const region = element.querySelector(':scope > .celerik-carousel-slides');
  const slides = [...(region?.querySelectorAll(':scope > .celerik-carousel-slide') ?? [])];
  if (slides.length === 0) {
    return null;
  }
  const labels = { start: 'Start automatic slide show', stop: 'Stop automatic slide show', ...options.labels };
  const loop = options.loop !== false;
  const delay = Math.max(0, Number(options.autoplay) || 0) * 1000;
  const controls = element.querySelector(':scope > .celerik-carousel-controls');
  const previousButton = controls?.querySelector('.celerik-carousel-previous');
  const nextButton = controls?.querySelector('.celerik-carousel-next');
  const rotateButton = controls?.querySelector('.celerik-carousel-rotate');
  const dots = [...(controls?.querySelectorAll('.celerik-carousel-dot') ?? [])];

  let index = Math.max(0, slides.findIndex((slide) => slide.classList.contains('is-active')));
  let playing = false;
  let hovered = false;
  let focused = false;
  let timer = null;

  const update = () => {
    slides.forEach((slide, i) => slide.classList.toggle('is-active', i === index));
    dots.forEach((dot, i) => (i === index ? dot.setAttribute('aria-current', 'true') : dot.removeAttribute('aria-current')));
    disable(previousButton, !loop && index === 0);
    disable(nextButton, !loop && index === slides.length - 1);
  };

  const show = (target) => {
    const next = loop
      ? ((target % slides.length) + slides.length) % slides.length
      : Math.min(slides.length - 1, Math.max(0, target));
    if (next === index) {
      return index;
    }
    index = next;
    update();
    element.dispatchEvent(new CustomEvent('celerik:change', { bubbles: true, detail: { index } }));
    return index;
  };

  const schedule = () => {
    clearTimeout(timer);
    timer = null;
    if (playing && !hovered && !focused) {
      timer = setTimeout(() => {
        // Without loop, the rotation ends on the last slide.
        if (!loop && index === slides.length - 1) {
          stop();
          return;
        }
        show(index + 1);
        schedule();
      }, delay);
    }
  };

  const setPlaying = (value) => {
    playing = value;
    element.classList.toggle('is-playing', playing);
    region.setAttribute('aria-live', playing ? 'off' : 'polite');
    rotateButton?.setAttribute('aria-label', playing ? labels.stop : labels.start);
    schedule();
  };
  function stop() {
    setPlaying(false);
  }
  const start = () => {
    if (delay > 0 && slides.length > 1) {
      setPlaying(true);
    }
  };

  // The user takes over: rotation stops for good.
  const byUser = (target) => {
    stop();
    return show(target);
  };

  const listen = (button, action) => button?.addEventListener('click', () => {
    if (button.getAttribute('aria-disabled') !== 'true') {
      action();
    }
  });
  listen(previousButton, () => byUser(index - 1));
  listen(nextButton, () => byUser(index + 1));
  dots.forEach((dot, i) => dot.addEventListener('click', () => byUser(i)));
  rotateButton?.addEventListener('click', () => (playing ? stop() : start()));

  element.addEventListener('pointerenter', () => {
    hovered = true;
    schedule();
  });
  element.addEventListener('pointerleave', () => {
    hovered = false;
    schedule();
  });
  element.addEventListener('focusin', () => {
    focused = true;
    schedule();
  });
  element.addEventListener('focusout', (event) => {
    if (!element.contains(event.relatedTarget)) {
      focused = false;
      schedule();
    }
  });

  let swipeFrom = null;
  region.addEventListener('pointerdown', (event) => {
    swipeFrom = event.pointerType === 'mouse' ? null : event.clientX;
  });
  region.addEventListener('pointerup', (event) => {
    if (swipeFrom === null) {
      return;
    }
    const distance = event.clientX - swipeFrom;
    swipeFrom = null;
    if (Math.abs(distance) >= SWIPE) {
      // The start of a slide is on the right in right-to-left pages.
      const rtl = getComputedStyle(element).direction === 'rtl';
      byUser(index + ((distance < 0) !== rtl ? 1 : -1));
    }
  });
  region.addEventListener('pointercancel', () => {
    swipeFrom = null;
  });

  update();
  const reduced = typeof window.matchMedia === 'function' && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (delay > 0 && !reduced) {
    start();
  } else {
    stop();
  }

  return {
    show: byUser,
    next: () => byUser(index + 1),
    previous: () => byUser(index - 1),
    start,
    stop,
    get index() {
      return index;
    },
    get playing() {
      return playing;
    },
  };
}
