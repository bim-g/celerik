// Image editor of a file field (Field::file()->imageEditor()): once a picture is chosen, it
// can be cropped, rotated and flipped, and the edited picture replaces the chosen file in the
// input, so the form sends it. The crop area moves with the pointer (drag it or its corners),
// with the arrow keys (Shift resizes), or by its position and size typed in number inputs,
// the way for screen readers. The picture is read with createImageBitmap (no blob: URL, so no
// change to a Content Security Policy) and every size is set through the CSSOM.

const MIN = 8; // The smallest crop, in pixels of the picture.

const text = (template, values) => template.replace(/\{(\w+)\}/g, (match, key) => values[key] ?? match);

// "16:9" → 16 / 9; "free" → null.
export function parseRatio(ratio) {
  const match = /^(\d+):(\d+)$/.exec(ratio ?? '');
  return match ? Number(match[1]) / Number(match[2]) : null;
}

// The largest crop of `ratio` in a picture of width × height, centered.
export function fitCrop(width, height, ratio) {
  if (!ratio) return { x: 0, y: 0, width, height };
  let w = width;
  let h = Math.round(w / ratio);
  if (h > height) {
    h = height;
    w = Math.round(h * ratio);
  }
  return { x: Math.round((width - w) / 2), y: Math.round((height - h) / 2), width: w, height: h };
}

// A crop kept inside the picture, at least MIN pixels, and of `ratio` when there is one
// (`keep` says which side was set: the other one follows).
export function clampCrop(crop, width, height, ratio, keep = 'width') {
  let { x, y, width: w, height: h } = crop;
  w = Math.max(MIN, Math.min(Math.round(w), width));
  h = Math.max(MIN, Math.min(Math.round(h), height));
  if (ratio) {
    if (keep === 'height') w = Math.round(h * ratio);
    else h = Math.round(w / ratio);
    if (w > width) {
      w = width;
      h = Math.round(w / ratio);
    }
    if (h > height) {
      h = height;
      w = Math.round(h * ratio);
    }
  }
  x = Math.max(0, Math.min(Math.round(x), width - w));
  y = Math.max(0, Math.min(Math.round(y), height - h));
  return { x, y, width: w, height: h };
}

// The output size: the crop, scaled down so that its longest side is at most maxSize.
export function outputSize(crop, maxSize) {
  const scale = maxSize ? Math.min(1, maxSize / Math.max(crop.width, crop.height)) : 1;
  return { width: Math.max(1, Math.round(crop.width * scale)), height: Math.max(1, Math.round(crop.height * scale)) };
}

function button(className, label, path) {
  const element = document.createElement('button');
  element.type = 'button';
  element.className = `btn btn-sm caelrik-action-button btn-outline-secondary ${className}`;
  element.setAttribute('aria-label', label);
  element.title = label;
  const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
  svg.setAttribute('viewBox', '0 0 16 16');
  svg.setAttribute('width', '16');
  svg.setAttribute('height', '16');
  svg.setAttribute('aria-hidden', 'true');
  svg.setAttribute('focusable', 'false');
  const drawing = document.createElementNS('http://www.w3.org/2000/svg', 'path');
  drawing.setAttribute('d', path);
  svg.append(drawing);
  element.append(svg);
  return element;
}

export function initImageEditor(input) {
  if (input.caelrikImageEditor) return input.caelrikImageEditor;
  const options = JSON.parse(input.getAttribute('data-caelrik-image-editor') || '{}');
  const labels = options.labels ?? {};
  const ratios = options.ratios?.length ? options.ratios : ['free'];
  const id = input.id;

  // The editor, hidden until a picture is chosen.
  const editor = document.createElement('div');
  editor.className = 'caelrik-image-editor';
  editor.setAttribute('role', 'group');
  editor.setAttribute('aria-label', labels.editor);
  editor.hidden = true;

  const tools = document.createElement('div');
  tools.className = 'caelrik-image-tools';
  const rotateLeft = button('caelrik-image-rotate-left', labels.rotateLeft, 'M3 7a5 5 0 1 1 1.5 3.5M3 3v4h4');
  const rotateRight = button('caelrik-image-rotate-right', labels.rotateRight, 'M13 7a5 5 0 1 0-1.5 3.5M13 3v4H9');
  const flipH = button('caelrik-image-flip-h', labels.flipHorizontal, 'M8 2v12M6 4L2 8l4 4zM10 4l4 4-4 4z');
  const flipV = button('caelrik-image-flip-v', labels.flipVertical, 'M2 8h12M4 6l4-4 4 4zM4 10l4 4 4-4z');
  const reset = button('caelrik-image-reset', labels.reset, 'M3 8a5 5 0 1 0 1.5-3.5M3 2v3h3');
  const ratioLabel = document.createElement('label');
  ratioLabel.className = 'caelrik-image-ratio-label';
  ratioLabel.htmlFor = `${id}-ratio`;
  ratioLabel.textContent = labels.ratio;
  const ratioSelect = document.createElement('select');
  ratioSelect.id = `${id}-ratio`;
  ratioSelect.className = 'form-select form-select-sm caelrik-image-ratio';
  for (const ratio of ratios) {
    ratioSelect.append(new Option(ratio === 'free' ? labels.free : ratio, ratio));
  }
  const ratioGroup = document.createElement('span');
  ratioGroup.className = 'caelrik-image-ratio-group';
  ratioGroup.append(ratioLabel, ratioSelect);
  ratioGroup.hidden = ratios.length < 2;
  tools.append(rotateLeft, rotateRight, flipH, flipV, ratioGroup, reset);

  const stage = document.createElement('div');
  stage.className = 'caelrik-image-stage';
  const canvas = document.createElement('canvas');
  canvas.className = 'caelrik-image-canvas';
  canvas.setAttribute('aria-hidden', 'true');
  const box = document.createElement('div');
  box.className = 'caelrik-image-crop';
  box.tabIndex = 0;
  box.setAttribute('role', 'group');
  box.setAttribute('aria-label', labels.crop);
  box.setAttribute('aria-describedby', `${id}-crop-help`);
  const corners = ['nw', 'ne', 'sw', 'se'].map((corner) => {
    const handle = document.createElement('span');
    handle.className = `caelrik-image-handle caelrik-image-handle-${corner}`;
    handle.dataset.corner = corner;
    handle.setAttribute('aria-hidden', 'true');
    box.append(handle);
    return handle;
  });
  stage.append(canvas, box);

  const help = document.createElement('p');
  help.id = `${id}-crop-help`;
  help.className = 'form-text caelrik-image-help';
  help.textContent = labels.cropHelp;

  const numbers = document.createElement('fieldset');
  numbers.className = 'caelrik-image-numbers';
  const legend = document.createElement('legend');
  legend.className = 'visually-hidden';
  legend.textContent = labels.crop;
  numbers.append(legend);
  const fields = {};
  for (const key of ['x', 'y', 'width', 'height']) {
    const wrap = document.createElement('div');
    wrap.className = 'caelrik-image-number';
    const label = document.createElement('label');
    label.htmlFor = `${id}-crop-${key}`;
    label.className = 'form-label';
    label.textContent = labels[{ x: 'left', y: 'top', width: 'width', height: 'height' }[key]];
    const number = document.createElement('input');
    number.type = 'number';
    number.id = `${id}-crop-${key}`;
    number.className = 'form-control form-control-sm';
    number.min = '0';
    number.step = '1';
    number.inputMode = 'numeric';
    wrap.append(label, number);
    numbers.append(wrap);
    fields[key] = number;
  }

  const status = document.createElement('p');
  status.className = 'form-text caelrik-image-result';
  status.setAttribute('role', 'status');

  editor.append(tools, stage, help, numbers, status);
  input.after(editor);

  let original = null; // The chosen file.
  let bitmap = null; // Its picture, oriented as the camera said.
  let oriented = null; // The picture rotated and flipped, on a canvas.
  let state = { rotation: 0, flipX: false, flipY: false };
  let crop = null;
  let ratio = parseRatio(ratios[0]);
  let committing = 0;

  const size = () => ({ width: oriented.width, height: oriented.height });

  function orient() {
    const turned = state.rotation % 180 !== 0;
    oriented = document.createElement('canvas');
    oriented.width = turned ? bitmap.height : bitmap.width;
    oriented.height = turned ? bitmap.width : bitmap.height;
    const context = oriented.getContext('2d');
    context.translate(oriented.width / 2, oriented.height / 2);
    context.rotate((state.rotation * Math.PI) / 180);
    context.scale(state.flipX ? -1 : 1, state.flipY ? -1 : 1);
    context.drawImage(bitmap, -bitmap.width / 2, -bitmap.height / 2);
  }

  function draw() {
    const { width, height } = size();
    // The preview as wide as the editor and at most 24rem high, sharp on dense screens.
    const shown = Math.max(1, Math.min(editor.clientWidth || width, (384 * width) / height));
    const ratioPx = window.devicePixelRatio || 1;
    stage.style.width = `${shown}px`;
    canvas.width = Math.max(1, Math.round(shown * ratioPx));
    canvas.height = Math.max(1, Math.round(((shown * height) / width) * ratioPx));
    canvas.style.aspectRatio = `${width} / ${height}`;
    canvas.getContext('2d').drawImage(oriented, 0, 0, canvas.width, canvas.height);
    place();
  }

  function place() {
    const { width, height } = size();
    box.style.left = `${(crop.x / width) * 100}%`;
    box.style.top = `${(crop.y / height) * 100}%`;
    box.style.width = `${(crop.width / width) * 100}%`;
    box.style.height = `${(crop.height / height) * 100}%`;
    fields.x.value = String(crop.x);
    fields.y.value = String(crop.y);
    fields.width.value = String(crop.width);
    fields.height.value = String(crop.height);
    fields.x.max = String(width - crop.width);
    fields.y.max = String(height - crop.height);
    fields.width.max = String(width);
    fields.height.max = String(height);
    fields.width.min = String(MIN);
    fields.height.min = String(MIN);
  }

  // The edited picture becomes the file of the input (after a pause, while the crop moves).
  let timer = null;
  function commit(now = false) {
    clearTimeout(timer);
    const run = () => {
      const job = ++committing;
      const out = outputSize(crop, options.maxSize);
      const result = document.createElement('canvas');
      result.width = out.width;
      result.height = out.height;
      result.getContext('2d').drawImage(oriented, crop.x, crop.y, crop.width, crop.height, 0, 0, out.width, out.height);
      const type = ['image/jpeg', 'image/png', 'image/webp'].includes(original.type) ? original.type : 'image/png';
      const name = type === original.type ? original.name : original.name.replace(/\.[^.]*$/, '') + '.png';
      return new Promise((resolve) => {
        result.toBlob((blob) => {
          if (job !== committing || !blob) return resolve();
          const transfer = new DataTransfer();
          transfer.items.add(new File([blob], name, { type, lastModified: Date.now() }));
          input.files = transfer.files;
          status.textContent = text(labels.result, { width: out.width, height: out.height });
          input.dispatchEvent(new CustomEvent('caelrik:image-edited', { bubbles: true, detail: { ...out, crop: { ...crop } } }));
          return resolve();
        }, type, 0.92);
      });
    };
    if (now) return run();
    return new Promise((resolve) => {
      timer = setTimeout(() => run().then(resolve), 300);
    });
  }

  function setCrop(next, keep) {
    const { width, height } = size();
    crop = clampCrop(next, width, height, ratio, keep);
    place();
    commit();
  }

  function restart() {
    orient();
    const { width, height } = size();
    crop = fitCrop(width, height, ratio);
    draw();
    return commit(true);
  }

  async function open(file) {
    original = file;
    bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });
    state = { rotation: 0, flipX: false, flipY: false };
    ratio = parseRatio(ratioSelect.value);
    editor.hidden = false;
    return restart();
  }

  input.addEventListener('change', () => {
    const file = input.files?.[0];
    if (file && file.type.startsWith('image/')) {
      open(file).catch(() => {
        editor.hidden = true;
      });
    } else {
      editor.hidden = true;
    }
  });

  rotateLeft.addEventListener('click', () => {
    state.rotation = (state.rotation + 270) % 360;
    restart();
  });
  rotateRight.addEventListener('click', () => {
    state.rotation = (state.rotation + 90) % 360;
    restart();
  });
  // A flip mirrors the picture as seen: across the screen's vertical or horizontal axis.
  flipH.addEventListener('click', () => {
    if (state.rotation % 180 === 0) state.flipX = !state.flipX;
    else state.flipY = !state.flipY;
    restart();
  });
  flipV.addEventListener('click', () => {
    if (state.rotation % 180 === 0) state.flipY = !state.flipY;
    else state.flipX = !state.flipX;
    restart();
  });
  ratioSelect.addEventListener('change', () => {
    ratio = parseRatio(ratioSelect.value);
    const { width, height } = size();
    crop = ratio ? fitCrop(width, height, ratio) : crop;
    place();
    commit(true);
  });
  reset.addEventListener('click', () => {
    state = { rotation: 0, flipX: false, flipY: false };
    ratioSelect.value = ratios[0];
    ratio = parseRatio(ratios[0]);
    restart();
  });

  for (const [key, number] of Object.entries(fields)) {
    const apply = () => {
      const value = Number(number.value);
      if (number.value === '' || !Number.isFinite(value)) {
        place();
        return;
      }
      setCrop({ ...crop, [key]: value }, key === 'height' ? 'height' : 'width');
    };
    number.addEventListener('change', apply);
    // Enter applies the number instead of sending the form.
    number.addEventListener('keydown', (event) => {
      if (event.key === 'Enter') {
        event.preventDefault();
        apply();
      }
    });
  }

  // The keyboard: arrows move by a step (1% of the picture); Shift resizes.
  box.addEventListener('keydown', (event) => {
    const moves = { ArrowLeft: [-1, 0], ArrowRight: [1, 0], ArrowUp: [0, -1], ArrowDown: [0, 1] }[event.key];
    if (!moves) return;
    event.preventDefault();
    const { width, height } = size();
    const step = Math.max(1, Math.round(Math.max(width, height) / 100));
    const [dx, dy] = moves;
    if (event.shiftKey) {
      setCrop({ ...crop, width: crop.width + dx * step, height: crop.height + dy * step }, dx === 0 ? 'height' : 'width');
    } else {
      setCrop({ ...crop, x: crop.x + dx * step, y: crop.y + dy * step });
    }
  });

  // The pointer: drag the area to move it, a corner to resize it.
  box.addEventListener('pointerdown', (event) => {
    if (event.button !== 0) return;
    event.preventDefault();
    box.focus();
    box.setPointerCapture?.(event.pointerId);
    const corner = event.target.dataset?.corner;
    const start = { x: event.clientX, y: event.clientY, crop: { ...crop } };
    const scale = size().width / canvas.getBoundingClientRect().width;
    const move = (moved) => {
      const dx = (moved.clientX - start.x) * scale;
      const dy = (moved.clientY - start.y) * scale;
      const from = start.crop;
      if (!corner) {
        setCrop({ ...from, x: from.x + dx, y: from.y + dy });
        return;
      }
      const west = corner.includes('w');
      const north = corner.includes('n');
      const width = from.width + (west ? -dx : dx);
      const height = ratio ? width / ratio : from.height + (north ? -dy : dy);
      // The opposite corner stays where it is.
      const next = clampCrop({ ...from, width, height }, size().width, size().height, ratio);
      setCrop({
        ...next,
        x: west ? from.x + from.width - next.width : from.x,
        y: north ? from.y + from.height - next.height : from.y,
      });
    };
    const up = () => {
      box.removeEventListener('pointermove', move);
      box.removeEventListener('pointerup', up);
      box.removeEventListener('pointercancel', up);
      commit(true);
    };
    box.addEventListener('pointermove', move);
    box.addEventListener('pointerup', up);
    box.addEventListener('pointercancel', up);
  });

  // The preview follows the width of the form.
  if (typeof ResizeObserver === 'function') {
    new ResizeObserver(() => {
      if (oriented && !editor.hidden) draw();
    }).observe(editor);
  }

  // A reset empties the input: the editor goes away.
  input.form?.addEventListener('reset', () => {
    editor.hidden = true;
  });

  const api = {
    open,
    get crop() {
      return crop && { ...crop };
    },
    rotate: (degrees) => {
      state.rotation = (((state.rotation + degrees) % 360) + 360) % 360;
      return restart();
    },
    commit: () => commit(true),
    handles: corners,
  };
  input.caelrikImageEditor = api;
  return api;
}

export function initImageEditors(container) {
  for (const input of container.querySelectorAll('input[type="file"][data-caelrik-image-editor]')) {
    initImageEditor(input);
  }
}
