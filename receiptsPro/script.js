const form = document.querySelector('#lead-form');
const phone = document.querySelector('#phone');
const telegram = document.querySelector('#telegram');
const email = document.querySelector('#email');
const comment = document.querySelector('#comment');
const counter = document.querySelector('#counter');
const toast = document.querySelector('#toast');
const submitButton = form.querySelector('.submit-button');
const statusLine = form.querySelector('.form-status');

const digitsOnly = (value) => value.replace(/\D/g, '');

function formatPhone(value) {
  let digits = digitsOnly(value);
  if (digits.startsWith('8')) digits = `7${digits.slice(1)}`;
  if (!digits.startsWith('7')) digits = `7${digits}`;
  digits = digits.slice(0, 11);

  let result = '+7';
  if (digits.length > 1) result += ` (${digits.slice(1, 4)}`;
  if (digits.length >= 4) result += ')';
  if (digits.length > 4) result += ` ${digits.slice(4, 7)}`;
  if (digits.length > 7) result += `-${digits.slice(7, 9)}`;
  if (digits.length > 9) result += `-${digits.slice(9, 11)}`;
  return result;
}

phone.addEventListener('input', () => {
  phone.value = formatPhone(phone.value);
  clearError(phone);
});
phone.addEventListener('focus', () => {
  if (!phone.value) phone.value = '+7';
});

comment.addEventListener('input', () => {
  counter.textContent = comment.value.length;
});

[telegram, email].forEach((input) => input.addEventListener('input', () => clearError(input)));

function setError(input, message) {
  const field = input.closest('.field');
  field.classList.add('has-error');
  field.querySelector('.error').textContent = message;
}

function clearError(input) {
  const field = input.closest('.field');
  field.classList.remove('has-error');
  field.querySelector('.error').textContent = '';
}

function validate() {
  let valid = true;
  [phone, telegram, email].forEach(clearError);

  if (digitsOnly(phone.value).length !== 11 || !digitsOnly(phone.value).startsWith('7')) {
    setError(phone, 'Введите телефон полностью');
    valid = false;
  }

  const telegramPattern = /^(?:@|https?:\/\/(?:t\.me|telegram\.me)\/)?[A-Za-z][A-Za-z0-9_]{4,31}\/?$/;
  if (telegram.value.trim() && !telegramPattern.test(telegram.value.trim())) {
    setError(telegram, 'Укажите @username или ссылку t.me/username');
    valid = false;
  }

  if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/i.test(email.value.trim())) {
    setError(email, 'Введите корректный email');
    valid = false;
  }

  return valid;
}

form.addEventListener('submit', async (event) => {
  event.preventDefault();
  statusLine.textContent = '';
  if (!validate()) return;

  submitButton.disabled = true;
  submitButton.classList.add('is-loading');
  submitButton.querySelector('span').textContent = 'Отправляем…';

  try {
    const response = await fetch(form.action, {
      method: 'POST',
      body: new FormData(form),
      headers: { Accept: 'application/json' }
    });
    const result = await response.json();
    if (!response.ok || !result.success) throw new Error(result.message || 'Не удалось отправить заявку');

    form.reset();
    counter.textContent = '0';
    toast.classList.add('show');
    setTimeout(() => toast.classList.remove('show'), 5000);
  } catch (error) {
    statusLine.textContent = error.message || 'Ошибка соединения. Попробуйте ещё раз.';
  } finally {
    submitButton.disabled = false;
    submitButton.classList.remove('is-loading');
    submitButton.querySelector('span').textContent = 'Отправить заявку';
  }
});
