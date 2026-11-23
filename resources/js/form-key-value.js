// Key-value lists (Field::keyValue()): add and remove rows. PHP renders the rows and a
// <template> of an empty row whose index is a placeholder; a new row gets the next free
// index. Each input is named by its position ("Key 2"), renumbered as rows come and go.

const PLACEHOLDER = '__pair__';

// What is wrong with a row's key: 'missing' (a value without a key), 'duplicate' (a key
// already used in an earlier row of the list) or null. Keys are compared trimmed.
export function keyProblem(key, value, earlierKeys) {
  const trimmed = key.trim();
  if (trimmed === '') {
    return value.trim() === '' ? null : 'missing';
  }
  return earlierKeys.some((other) => other.trim() === trimmed) ? 'duplicate' : null;
}

// The key inputs of the list holding this one, before it.
function earlierKeys(input) {
  const keys = [...input.closest('.caelrik-key-value-rows').querySelectorAll('.caelrik-key-value-key')];
  return keys.slice(0, keys.indexOf(input)).map((key) => key.value);
}

function rowValue(input) {
  return input.closest('.caelrik-key-value-row').querySelector('.caelrik-key-value-value').value;
}

// Parsley validators for the key inputs (data-parsley-keyneeded, data-parsley-keyunique).
export const keyValidators = {
  keyneeded: (value, input) => keyProblem(value, rowValue(input), []) !== 'missing',
  keyunique: (value, input) => keyProblem(value, rowValue(input), earlierKeys(input)) !== 'duplicate',
};

// parsley: the form's Parsley instance, to check new inputs and re-check keys.
export function initKeyValues(container, parsley) {
  for (const root of container.querySelectorAll('[data-caelrik-key-value]')) {
    if (!root.caelrikKeyValue) {
      root.caelrikKeyValue = true;
      initKeyValue(root, parsley);
    }
  }
}

function initKeyValue(root, parsley) {
  const config = JSON.parse(root.getAttribute('data-caelrik-key-value'));
  const list = root.querySelector('.caelrik-key-value-rows');
  const template = root.querySelector('template');
  const add = root.querySelector('[data-caelrik-key-value-add]');
  const rows = () => [...list.children];
  const keyOf = (row) => row.querySelector('.caelrik-key-value-key');

  let next = rows().reduce((max, row) => {
    const index = row.getAttribute('data-caelrik-index');
    return /^\d+$/.test(index) ? Math.max(max, Number(index) + 1) : max;
  }, 0);

  // Re-checks the keys already shown as checked: fixing a duplicate in one row fixes the
  // other, and a value removed no longer needs its key.
  const recheck = () => {
    for (const field of parsley.fields) {
      const input = field.$element[0];
      if (root.contains(input) && (input.classList.contains('is-invalid') || input.classList.contains('is-valid'))) {
        field.validate();
      }
    }
  };

  const update = () => {
    rows().forEach((row, index) => {
      const position = String(index + 1);
      const [keyLabel, valueLabel] = row.querySelectorAll('label');
      keyLabel.textContent = `${config.key} ${position}`;
      valueLabel.textContent = `${config.value} ${position}`;
      row.querySelector('[data-caelrik-key-value-remove] .visually-hidden').textContent = config.remove.replace(
        '%d',
        position,
      );
      // A required list needs a key in its first row.
      keyOf(row).required = config.required && index === 0;
    });
    // The first key has the field's id: error summary links lead to it.
    const first = rows()[0];
    if (first && !document.getElementById(config.id)) {
      const key = keyOf(first);
      first.querySelector(`label[for="${key.id}"]`).htmlFor = config.id;
      key.id = config.id;
    }
    // A list error from the server ("headers.1.key") is stale once the rows change.
    root.querySelector(':scope > .caelrik-server-error')?.remove();
    parsley.refresh();
  };

  add.addEventListener('click', () => {
    list.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll(PLACEHOLDER, String(next)));
    next += 1;
    update();
    keyOf(list.lastElementChild).focus();
  });

  list.addEventListener('click', (event) => {
    const button = event.target.closest('[data-caelrik-key-value-remove]');
    if (!button) {
      return;
    }
    const row = button.closest('.caelrik-key-value-row');
    // Focus goes to the row that takes its place, the one before it, or Add.
    const neighbour = row.nextElementSibling ?? row.previousElementSibling;
    row.remove();
    // A required list keeps one row: removing the last one leaves an empty row.
    if (config.required && rows().length === 0) {
      add.click();
      return;
    }
    update();
    recheck();
    (neighbour ? keyOf(neighbour) : add).focus();
  });

  list.addEventListener('input', recheck);
}
