// Signature fields (Field::signature()): a pad to draw on with a mouse, a finger or a pen,
// and a name input, the way for keyboards and screen readers. A typed name is drawn on the
// pad as handwriting. Either way the field submits a PNG image (a data URL) under its name,
// from a hidden input; without JavaScript the pad stays hidden and the name is submitted.
import $ from 'jquery';

// The submitted image, in pixels: ink on a transparent background.
export const IMAGE = { width: 600, height: 200 };
const INK = '#000';
const HANDWRITING = '"Segoe Script", "Brush Script MT", "Snell Roundhand", "URW Chancery L", cursive';

// Strokes are lists of points in units of the pad's width, so they redraw at any size.
export function drawStrokes(context, strokes, width) {
  context.strokeStyle = INK;
  context.fillStyle = INK;
  context.lineWidth = Math.max(1.5, width * 0.006);
  context.lineCap = 'round';
  context.lineJoin = 'round';
  for (const stroke of strokes) {
    const points = stroke.map(([x, y]) => [x * width, y * width]);
    context.beginPath();
    if (points.length === 1) {
      // A dot.
      context.arc(points[0][0], points[0][1], context.lineWidth / 2, 0, Math.PI * 2);
      context.fill();
      continue;
    }
    context.moveTo(...points[0]);
    // Through the middles of the segments, with the points as control points: a smooth line.
    for (let i = 1; i < points.length - 1; i += 1) {
      const [x, y] = points[i];
      const [nextX, nextY] = points[i + 1];
      context.quadraticCurveTo(x, y, (x + nextX) / 2, (y + nextY) / 2);
    }
    context.lineTo(...points[points.length - 1]);
    context.stroke();
  }
}

// A typed name as handwriting, as large as fits.
export function drawName(context, name, width, height) {
  let size = height * 0.45;
  context.font = `${size}px ${HANDWRITING}`;
  const measured = context.measureText(name).width;
  if (measured > width * 0.9) {
    size *= (width * 0.9) / measured;
    context.font = `${size}px ${HANDWRITING}`;
  }
  context.fillStyle = INK;
  context.textAlign = 'center';
  context.textBaseline = 'alphabetic';
  context.fillText(name, width / 2, height * 0.66);
}

export function initSignature(fieldset) {
  if (fieldset.celerikSignature) {
    return fieldset.celerikSignature;
  }
  const canvas = fieldset.querySelector('.celerik-signature-canvas');
  const context = canvas.getContext('2d');
  // No canvas (a very old browser): the name input keeps working on its own.
  if (!context) {
    return null;
  }
  const labels = JSON.parse(fieldset.getAttribute('data-celerik-signature') || '{}');
  const pad = fieldset.querySelector('.celerik-signature-pad');
  const typed = fieldset.querySelector('.celerik-signature-typed');
  const value = fieldset.querySelector('.celerik-signature-value');
  const initial = value.value;

  // The image is submitted under the field's name. A required field is checked by the
  // "signature" validator (form.js): a drawing or a name.
  value.name = typed.name;
  typed.removeAttribute('name');
  if (typed.required) {
    typed.required = false;
    typed.setAttribute('data-parsley-signature', 'true');
    typed.setAttribute('data-parsley-validate-if-empty', 'true');
  }
  pad.hidden = false;

  let strokes = [];
  let stroke = null;
  // A submitted image (a form shown again with its errors), until the pad changes.
  let image = null;

  const signed = () => Boolean(image || strokes.length || typed.value.trim());

  function render(target, width, height) {
    target.clearRect(0, 0, width, height);
    if (image) {
      target.drawImage(image, 0, 0, width, height);
    } else if (strokes.length) {
      drawStrokes(target, strokes, width);
    } else if (typed.value.trim()) {
      drawName(target, typed.value.trim(), width, height);
    }
  }

  function redraw() {
    render(context, canvas.width, canvas.height);
  }

  function resize() {
    const box = canvas.getBoundingClientRect();
    const ratio = window.devicePixelRatio || 1;
    canvas.width = Math.max(1, Math.round(box.width * ratio));
    canvas.height = Math.max(1, Math.round(box.height * ratio));
    redraw();
  }

  function png() {
    const copy = document.createElement('canvas');
    copy.width = IMAGE.width;
    copy.height = IMAGE.height;
    render(copy.getContext('2d'), IMAGE.width, IMAGE.height);
    return copy.toDataURL('image/png');
  }

  // After every change: the submitted image, the pad's name, and the message if shown.
  function update() {
    value.value = signed() ? png() : '';
    canvas.setAttribute('aria-label', signed() ? labels.signed : labels.empty);
    value.dispatchEvent(new Event('change', { bubbles: true }));
    if (typed.classList.contains('is-invalid') || typed.classList.contains('is-valid')) {
      $(typed).parsley().validate();
    }
  }

  function load(source) {
    image = null;
    if (source) {
      const picture = new Image();
      picture.addEventListener('load', () => {
        if (image === picture) redraw();
      });
      picture.src = source;
      image = picture;
    }
  }

  const point = (event) => {
    const box = canvas.getBoundingClientRect();
    return [(event.clientX - box.left) / box.width, (event.clientY - box.top) / box.width];
  };

  canvas.addEventListener('pointerdown', (event) => {
    if (event.button !== 0) return;
    event.preventDefault();
    canvas.setPointerCapture?.(event.pointerId);
    // Drawing replaces a typed name or a submitted image.
    image = null;
    typed.value = '';
    stroke = [point(event)];
    strokes.push(stroke);
    redraw();
  });
  canvas.addEventListener('pointermove', (event) => {
    if (!stroke) return;
    const events = event.getCoalescedEvents?.() ?? [];
    for (const each of events.length ? events : [event]) {
      stroke.push(point(each));
    }
    redraw();
  });
  const end = () => {
    if (stroke) {
      stroke = null;
      update();
    }
  };
  canvas.addEventListener('pointerup', end);
  canvas.addEventListener('pointercancel', end);

  // Typing replaces a drawing.
  typed.addEventListener('input', () => {
    strokes = [];
    image = null;
    redraw();
    update();
  });

  const clear = () => {
    strokes = [];
    image = null;
    typed.value = '';
    redraw();
    update();
  };
  fieldset.querySelector('.celerik-signature-clear').addEventListener('click', clear);

  // A reset brings back the name or the image the page came with, after the reset event.
  typed.form?.addEventListener('reset', () => setTimeout(() => {
    strokes = [];
    load(initial);
    value.value = initial;
    redraw();
    canvas.setAttribute('aria-label', signed() ? labels.signed : labels.empty);
  }));

  if (typeof ResizeObserver === 'function') {
    new ResizeObserver(resize).observe(canvas);
  }
  load(initial);
  resize();
  canvas.setAttribute('aria-label', signed() ? labels.signed : labels.empty);
  // A name typed without JavaScript, then shown again with errors: submit it as an image.
  if (!initial && signed()) {
    value.value = png();
  }

  const api = {
    clear,
    isSigned: signed,
    toDataURL: () => value.value,
  };
  fieldset.celerikSignature = api;
  return api;
}

export function initSignatures(container) {
  for (const fieldset of container.querySelectorAll('fieldset[data-celerik-signature]')) {
    initSignature(fieldset);
  }
}

// The "signature" validator: a drawing or a name.
export function signatureGiven(input) {
  const fieldset = input.closest('fieldset[data-celerik-signature]');
  return Boolean(fieldset?.querySelector('.celerik-signature-value')?.value);
}
