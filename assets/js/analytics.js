'use strict';

/**
 * Логика инициализации и отрисовки аналитических графиков Chart.js.
 * Поддерживает адаптацию палитры под светлую и тёмную темы при смене оформления.
 */
(function () {
  var A = window.ANALYTICS;
  if (!A) return;

  if (typeof Chart === 'undefined') {
    document.querySelectorAll('.chart').forEach(function (c) {
      c.innerHTML = '<div class="muted chart-empty">Библиотека графиков не загрузилась. Проверьте подключение к интернету.</div>';
    });
    return;
  }

  /**
   * Проверяет, активна ли сейчас тёмная тема интерфейса.
   *
   * @return {boolean} True, если активна тёмная тема.
   */
  function isDarkTheme() {
    return document.documentElement.classList.contains('theme-dark');
  }

  /**
   * Возвращает палитру цветов для графиков в зависимости от текущей темы.
   *
   * @return {object} Набор цветов для текста, сетки, линий и столбцов.
   */
  function getPalette() {
    var dark = isDarkTheme();
    return {
      dark: dark,
      text: dark ? '#8b929c' : '#747a83',
      grid: dark ? '#262b34' : '#e3e6ea',
      primary: dark ? '#93c5fd' : '#20242b',
      green: dark ? '#34d399' : '#23855b',
      gray: dark ? '#475569' : '#c7ccd2',
    };
  }

  var pal = getPalette();
  Chart.defaults.font.family = '-apple-system,BlinkMacSystemFont,"Segoe UI",Arial,sans-serif';
  Chart.defaults.color = pal.text;
  Chart.defaults.borderColor = pal.grid;

  var charts = [];

  /**
   * Возвращает базовую конфигурацию Chart.js для однообразия графиков.
   *
   * @return {object} Опции графика.
   */
  function base() {
    return {
      maintainAspectRatio: false,
      responsive: true,
      plugins: { legend: { display: true, position: 'bottom' } },
    };
  }

  /**
   * Проверяет, есть ли в массиве хотя бы одно непустое значение.
   *
   * @param {Array} arr Массив значений.
   * @return {boolean} True, если значения присутствуют.
   */
  function hasValues(arr) {
    return arr && arr.some(function (v) { return v !== null && v !== undefined; });
  }

  /**
   * Находит canvas элемент по ID, если родительский контейнер существует.
   *
   * @param {string} id ID элемента canvas.
   * @return {HTMLCanvasElement|null} Элемент canvas или null.
   */
  function canvas(id) {
    var el = document.getElementById(id);
    if (!el) return null;
    if (!el.parentElement) return null;
    return el;
  }

  // Работа по дням
  var w = A.workTrend;
  if (w && w.labels && w.labels.length) {
    var workChart = new Chart(canvas('chart-work'), {
      type: 'bar',
      data: {
        labels: w.labels,
        datasets: [
          { label: 'Фокус, ч', data: w.focused, backgroundColor: pal.primary, borderRadius: 6, yAxisID: 'y' },
          { label: 'Результат, 1–5', data: w.result, type: 'line', borderColor: pal.green, backgroundColor: pal.green, tension: 0.3, yAxisID: 'y1' },
        ],
      },
      options: Object.assign(base(), {
        scales: {
          y: { beginAtZero: true, grid: { color: pal.grid }, ticks: { color: pal.text }, title: { display: true, text: 'Часы', color: pal.text } },
          y1: { position: 'right', min: 0, max: 5, grid: { drawOnChartArea: false }, ticks: { color: pal.text }, title: { display: true, text: 'Результат', color: pal.text } },
          x: { grid: { color: pal.grid }, ticks: { color: pal.text } },
        },
      }),
    });
    charts.push({ type: 'work', chart: workChart });
  }

  // Текущая работа и работа над будущим (stacked, разные категории)
  var wf = A.workFuture;
  if (wf && wf.labels && wf.labels.length && (hasValues(wf.work) || hasValues(wf.future))) {
    var wfChart = new Chart(canvas('chart-workfuture'), {
      type: 'bar',
      data: {
        labels: wf.labels,
        datasets: [
          { label: 'Текущая работа, ч', data: wf.work, backgroundColor: pal.primary, borderRadius: 4, stack: 'h' },
          { label: 'Работа над будущим, ч', data: wf.future, backgroundColor: pal.green, borderRadius: 4, stack: 'h' },
        ],
      },
      options: Object.assign(base(), {
        scales: {
          x: { stacked: true, grid: { color: pal.grid }, ticks: { color: pal.text } },
          y: { stacked: true, beginAtZero: true, grid: { color: pal.grid }, ticks: { color: pal.text }, title: { display: true, text: 'Часы', color: pal.text } },
        },
      }),
    });
    charts.push({ type: 'workfuture', chart: wfChart });
  }

  // Сон и энергия утром
  var s = A.sleepTrend;
  if (s && s.labels && s.labels.length) {
    var sleepChart = new Chart(canvas('chart-sleep'), {
      type: 'line',
      data: {
        labels: s.labels,
        datasets: [
          { label: 'Сон, ч', data: s.sleep, borderColor: pal.primary, backgroundColor: pal.primary, tension: 0.3, yAxisID: 'y' },
          { label: 'Энергия утром, 1–5', data: s.morning, borderColor: pal.green, backgroundColor: pal.green, tension: 0.3, yAxisID: 'y1' },
        ],
      },
      options: Object.assign(base(), {
        scales: {
          y: { beginAtZero: true, grid: { color: pal.grid }, ticks: { color: pal.text }, title: { display: true, text: 'Часы', color: pal.text } },
          y1: { position: 'right', min: 0, max: 5, grid: { drawOnChartArea: false }, ticks: { color: pal.text }, title: { display: true, text: 'Энергия', color: pal.text } },
          x: { grid: { color: pal.grid }, ticks: { color: pal.text } },
        },
      }),
    });
    charts.push({ type: 'sleep', chart: sleepChart });
  }

  /**
   * Создает простой столбчатый график сравнения для факторов продуктивности.
   *
   * @param {string} id ID canvas-элемента.
   * @param {object} cfg Конфигурация с labels и values.
   * @param {string} title Название показателя.
   */
  function barChart(id, cfg, title) {
    var el = canvas(id);
    if (!el) return;
    if (!hasValues(cfg.values)) {
      el.parentElement.innerHTML = '<div class="muted chart-empty">Недостаточно данных</div>';
      return;
    }
    var bc = new Chart(el, {
      type: 'bar',
      data: {
        labels: cfg.labels,
        datasets: [{ label: title, data: cfg.values, backgroundColor: [pal.primary, pal.gray], borderRadius: 6 }],
      },
      options: Object.assign(base(), {
        scales: {
          y: { min: 0, max: 5, beginAtZero: true, grid: { color: pal.grid }, ticks: { color: pal.text } },
          x: { grid: { color: pal.grid }, ticks: { color: pal.text } },
        },
      }),
    });
    charts.push({ type: 'bar', chart: bc });
  }

  barChart('chart-lefthome', A.leftHome, 'Средняя результативность');
  barChart('chart-exercise', A.exercise, 'Средняя результативность');
  barChart('chart-timeoutside', A.timeOutside, 'Средняя результативность');

  // Энергия вечером → готовность работать завтра
  var et = A.eveningTomorrow;
  if (et && et.points && et.points.length) {
    var etChart = new Chart(canvas('chart-eveningtomorrow'), {
      type: 'scatter',
      data: {
        datasets: [{ label: 'Дни', data: et.points, backgroundColor: pal.green, pointRadius: 6, pointHoverRadius: 8 }],
      },
      options: Object.assign(base(), {
        scales: {
          x: { min: 0, max: 5, grid: { color: pal.grid }, ticks: { color: pal.text }, title: { display: true, text: 'Энергия вечером', color: pal.text } },
          y: { min: 0, max: 5, grid: { color: pal.grid }, ticks: { color: pal.text }, title: { display: true, text: 'Готовность работать завтра', color: pal.text } },
        },
      }),
    });
    charts.push({ type: 'scatter', chart: etChart });
  }

  /**
   * Обновляет цветовую палитру и оси всех графиков при смене темы.
   */
  function updateAllCharts() {
    var p = getPalette();
    Chart.defaults.color = p.text;
    Chart.defaults.borderColor = p.grid;

    charts.forEach(function (item) {
      var c = item.chart;
      if (!c) return;

      if (item.type === 'work') {
        c.data.datasets[0].backgroundColor = p.primary;
        c.data.datasets[1].borderColor = p.green;
        c.data.datasets[1].backgroundColor = p.green;
      } else if (item.type === 'workfuture') {
        c.data.datasets[0].backgroundColor = p.primary;
        c.data.datasets[1].backgroundColor = p.green;
      } else if (item.type === 'sleep') {
        c.data.datasets[0].borderColor = p.primary;
        c.data.datasets[0].backgroundColor = p.primary;
        c.data.datasets[1].borderColor = p.green;
        c.data.datasets[1].backgroundColor = p.green;
      } else if (item.type === 'bar') {
        c.data.datasets[0].backgroundColor = [p.primary, p.gray];
      } else if (item.type === 'scatter') {
        c.data.datasets[0].backgroundColor = p.green;
      }

      if (c.options.scales) {
        Object.keys(c.options.scales).forEach(function (k) {
          var s = c.options.scales[k];
          if (s.grid) s.grid.color = p.grid;
          if (s.ticks) s.ticks.color = p.text;
          if (s.title) s.title.color = p.text;
        });
      }

      c.update();
    });
  }

  window.addEventListener('themechange', updateAllCharts);
})();
