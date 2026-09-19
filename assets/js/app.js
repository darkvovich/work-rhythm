'use strict';

function esc(s) {
  return String(s).replace(/[&<>"']/g, function (c) {
    return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
  });
}

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
