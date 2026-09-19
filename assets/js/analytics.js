'use strict';

(function () {
  var A = window.ANALYTICS;
  if (!A) return;

  if (typeof Chart === 'undefined') {
    document.querySelectorAll('.chart').forEach(function (c) {
      c.innerHTML = '<div class="muted chart-empty">Библиотека графиков не загрузилась. Проверьте подключение к интернету.</div>';
    });
    return;
  }

  var GREEN = '#23855b';
  var DARK = '#20242b';
  var GRAY = '#c7ccd2';

  Chart.defaults.font.family = '-apple-system,BlinkMacSystemFont,"Segoe UI",Arial,sans-serif';
  Chart.defaults.color = '#747a83';

  function base() {
    return {
      maintainAspectRatio: false,
      responsive: true,
      plugins: { legend: { display: true, position: 'bottom' } },
    };
  }

  function hasValues(arr) {
    return arr && arr.some(function (v) { return v !== null && v !== undefined; });
  }

  function canvas(id) {
    var el = document.getElementById(id);
    if (!el) return null;
    if (!el.parentElement) return null;
    return el;
  }

  // Работа по дням
  var w = A.workTrend;
  if (w && w.labels && w.labels.length) {
    new Chart(canvas('chart-work'), {
      type: 'bar',
      data: {
        labels: w.labels,
        datasets: [
          { label: 'Фокус, ч', data: w.focused, backgroundColor: DARK, borderRadius: 6, yAxisID: 'y' },
          { label: 'Результат, 1–5', data: w.result, type: 'line', borderColor: GREEN, backgroundColor: GREEN, tension: 0.3, yAxisID: 'y1' },
        ],
      },
      options: Object.assign(base(), {
        scales: {
          y: { beginAtZero: true, title: { display: true, text: 'Часы' } },
          y1: { position: 'right', min: 0, max: 5, grid: { drawOnChartArea: false }, title: { display: true, text: 'Результат' } },
        },
      }),
    });
  }

  // Текущая работа и работа над будущим (stacked, разные категории)
  var wf = A.workFuture;
  if (wf && wf.labels && wf.labels.length && (hasValues(wf.work) || hasValues(wf.future))) {
    new Chart(canvas('chart-workfuture'), {
      type: 'bar',
      data: {
        labels: wf.labels,
        datasets: [
          { label: 'Текущая работа, ч', data: wf.work, backgroundColor: DARK, borderRadius: 4, stack: 'h' },
          { label: 'Работа над будущим, ч', data: wf.future, backgroundColor: GREEN, borderRadius: 4, stack: 'h' },
        ],
      },
      options: Object.assign(base(), {
        scales: {
          x: { stacked: true },
          y: { stacked: true, beginAtZero: true, title: { display: true, text: 'Часы' } },
        },
      }),
    });
  }

  // Сон и энергия утром
  var s = A.sleepTrend;
  if (s && s.labels && s.labels.length) {
    new Chart(canvas('chart-sleep'), {
      type: 'line',
      data: {
        labels: s.labels,
        datasets: [
          { label: 'Сон, ч', data: s.sleep, borderColor: DARK, backgroundColor: DARK, tension: 0.3, yAxisID: 'y' },
          { label: 'Энергия утром, 1–5', data: s.morning, borderColor: GREEN, backgroundColor: GREEN, tension: 0.3, yAxisID: 'y1' },
        ],
      },
      options: Object.assign(base(), {
        scales: {
          y: { beginAtZero: true, title: { display: true, text: 'Часы' } },
          y1: { position: 'right', min: 0, max: 5, grid: { drawOnChartArea: false }, title: { display: true, text: 'Энергия' } },
        },
      }),
    });
  }

  function barChart(id, cfg, title) {
    var el = canvas(id);
    if (!el) return;
    if (!hasValues(cfg.values)) {
      el.parentElement.innerHTML = '<div class="muted chart-empty">Недостаточно данных</div>';
      return;
    }
    new Chart(el, {
      type: 'bar',
      data: {
        labels: cfg.labels,
        datasets: [{ label: title, data: cfg.values, backgroundColor: [DARK, GRAY], borderRadius: 6 }],
      },
      options: Object.assign(base(), {
        scales: { y: { min: 0, max: 5, beginAtZero: true } },
      }),
    });
  }

  barChart('chart-lefthome', A.leftHome, 'Средняя результативность');
  barChart('chart-exercise', A.exercise, 'Средняя результативность');
  barChart('chart-timeoutside', A.timeOutside, 'Средняя результативность');

  // Энергия вечером → готовность работать завтра
  var et = A.eveningTomorrow;
  if (et && et.points && et.points.length) {
    new Chart(canvas('chart-eveningtomorrow'), {
      type: 'scatter',
      data: {
        datasets: [{ label: 'Дни', data: et.points, backgroundColor: GREEN, pointRadius: 6, pointHoverRadius: 8 }],
      },
      options: Object.assign(base(), {
        scales: {
          x: { min: 0, max: 5, title: { display: true, text: 'Энергия вечером' } },
          y: { min: 0, max: 5, title: { display: true, text: 'Готовность работать завтра' } },
        },
      }),
    });
  }
})();
