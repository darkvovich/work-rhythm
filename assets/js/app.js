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
  var info = e.target && e.target.closest ? e.target.closest('.info') : null;
  if (info) {
    e.preventDefault();
    var wasOpen = info.classList.contains('open');
    document.querySelectorAll('.info.open').forEach(function (i) { i.classList.remove('open'); });
    if (!wasOpen) info.classList.add('open');
    return;
  }
  document.querySelectorAll('.info.open').forEach(function (i) { i.classList.remove('open'); });
});

document.addEventListener('keydown', function (e) {
  if (e.key === 'Escape') {
    document.querySelectorAll('.info.open').forEach(function (i) { i.classList.remove('open'); });
  }
});
