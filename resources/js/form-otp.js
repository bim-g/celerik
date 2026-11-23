// One-time code fields (Field::otp()): one real input, which keeps paste, the code
// offered by the phone or browser (autocomplete="one-time-code") and screen readers
// working. The boxes are drawn behind it, hidden from assistive technology; the input is
// transparent on top of them, so a click anywhere focuses it.

// The characters of a code that the field accepts, at most `length`: "123 456" and
// "123-456" (as written in a message) become "123456".
export function cleanCode(text, length, alphanumeric = false) {
  return String(text).replace(alphanumeric ? /[^A-Za-z0-9]/g : /\D/g, '').slice(0, length);
}

export function initOtp(input) {
  const length = Number(input.getAttribute('data-caelrik-otp'));
  const alphanumeric = input.getAttribute('inputmode') === 'text';

  const wrapper = document.createElement('div');
  wrapper.className = 'caelrik-otp';
  wrapper.style.setProperty('--caelrik-otp-length', String(length));
  const slots = document.createElement('div');
  slots.className = 'caelrik-otp-slots';
  slots.setAttribute('aria-hidden', 'true');
  for (let index = 0; index < length; index += 1) {
    slots.appendChild(document.createElement('span')).className = 'caelrik-otp-slot';
  }
  input.before(wrapper);
  wrapper.append(slots, input);
  // The browser would cut a pasted "123 456" to "123 45": cleanCode() limits the length.
  input.removeAttribute('maxlength');

  const update = () => {
    const code = cleanCode(input.value, length, alphanumeric);
    if (code !== input.value) {
      input.value = code;
    }
    // The next box to fill shows the focus (the last one once the code is complete).
    const active = document.activeElement === input ? Math.min(code.length, length - 1) : -1;
    [...slots.children].forEach((slot, index) => {
      slot.textContent = code[index] ?? '';
      slot.classList.toggle('is-active', index === active);
    });
  };
  // Characters are always added at the end: the caret stays there.
  const caretToEnd = () => input.setSelectionRange(input.value.length, input.value.length);

  input.addEventListener('input', update);
  input.addEventListener('focus', () => {
    update();
    caretToEnd();
  });
  input.addEventListener('click', caretToEnd);
  input.addEventListener('blur', update);
  // A reset changes the value without an input event, after the reset event.
  input.form?.addEventListener('reset', () => setTimeout(update));
  update();
}

export function initOtps(container) {
  for (const input of container.querySelectorAll('input[data-caelrik-otp]')) {
    if (!input.parentElement.classList.contains('caelrik-otp')) {
      initOtp(input);
    }
  }
}
