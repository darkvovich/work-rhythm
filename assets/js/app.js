'use strict';

/**
 * Экранирует спецсимволы HTML для безопасной вставки в разметку.
 *
 * @param {string} s Исходная строка.
 * @return {string} Экранированная строка.
 */
function esc(s) {
  return String(s).replace(/[&<>"']/g, function (c) {
    return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
  });
}

/**
 * Выполняет POST-запрос с JSON-телом и возвращает распарсенный ответ.
 *
 * @param {string} url Эндпоинт.
 * @param {object} data Отправляемые данные.
 * @return {Promise<object>} JSON-ответ.
 */
function postJSON(url, data) {
  return fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data),
  }).then(function (r) { return r.json(); });
}

// Подсказки к шкалам (ⓘ): открытие по клику, закрытие по клику вне / Escape.
document.addEventListener('click', function (e) {
  var hint = e.target && e.target.closest ? e.target.closest('.hint') : null;
  if (hint) {
    e.preventDefault();
    var wasOpen = hint.classList.contains('open');
    document.querySelectorAll('.hint.open').forEach(function (i) { i.classList.remove('open'); });
    if (!wasOpen) hint.classList.add('open');
    return;
  }
  document.querySelectorAll('.hint.open').forEach(function (i) { i.classList.remove('open'); });
});

document.addEventListener('keydown', function (e) {
  if (e.key === 'Escape') {
    document.querySelectorAll('.hint.open').forEach(function (i) { i.classList.remove('open'); });
  }
});

/**
 * Инициализирует переключатель тем (auto / light / dark).
 * Синхронизирует выбор с localStorage и Cookie, обновляет класс на <html>,
 * обновляет meta[name="theme-color"] и рассылает событие 'themechange'.
 */
function initTheme() {
  function getStored() {
    try {
      return localStorage.getItem('wr_theme') || 'auto';
    } catch (e) {
      return 'auto';
    }
  }

  function isDark(pref) {
    if (pref === 'dark') return true;
    if (pref === 'light') return false;
    return Boolean(window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
  }

  function applyTheme(pref) {
    var dark = isDark(pref);
    var root = document.documentElement;
    root.classList.remove('theme-dark', 'theme-light');
    if (pref === 'dark') {
      root.classList.add('theme-dark');
    } else if (pref === 'light') {
      root.classList.add('theme-light');
    } else if (dark) {
      root.classList.add('theme-dark');
    }

    try {
      document.cookie = 'wr_theme=' + encodeURIComponent(pref) + ';path=/;max-age=31536000;SameSite=Lax';
      localStorage.setItem('wr_theme', pref);
    } catch (e) {}

    var meta = document.querySelector('meta[name="theme-color"]');
    if (meta) {
      meta.setAttribute('content', dark ? '#0f1115' : '#20242b');
    }

    document.querySelectorAll('.theme-btn').forEach(function (b) {
      b.classList.toggle('active', b.getAttribute('data-theme-val') === pref);
    });

    window.dispatchEvent(new CustomEvent('themechange', { detail: { theme: pref, isDark: dark } }));
  }

  var current = getStored();
  applyTheme(current);

  document.addEventListener('click', function (e) {
    var btn = e.target && e.target.closest ? e.target.closest('.theme-btn') : null;
    if (!btn) return;
    var val = btn.getAttribute('data-theme-val');
    if (val) {
      applyTheme(val);
    }
  });

  if (window.matchMedia) {
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function () {
      if (getStored() === 'auto') {
        applyTheme('auto');
      }
    });
  }
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initTheme);
} else {
  initTheme();
}
