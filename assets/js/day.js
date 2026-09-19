'use strict';

(function () {
  var A = window.APP;
  if (!A) return;

  var form = document.getElementById('day-form');
  if (!form) return;

  var saveStatus = document.getElementById('save-status');
  var errorBox = document.getElementById('error-box');
  var progressLabel = document.getElementById('progress-label');
  var progressBar = document.getElementById('progress-bar');
  var sleepCalc = document.getElementById('sleep-calc');

  function getRadio(name) {
    var el = form.querySelector('input[name="' + name + '"]:checked');
    return el ? el.value : '';
  }

  function fieldValue(f) {
    var radios = form.querySelectorAll('input[name="' + f + '"]');
    if (radios.length && radios[0].type === 'radio') {
      var c = form.querySelector('input[name="' + f + '"]:checked');
      return c ? c.value : '';
    }
    var el = form.querySelector('[name="' + f + '"]');
    return el ? el.value : '';
  }

  function combinedSleep() {
    var h = parseInt(fieldValue('sleep_hours'), 10);
    var m = parseInt(fieldValue('sleep_minutes'), 10);
    var hasH = !isNaN(h);
    var hasM = !isNaN(m);
    if (!hasH && !hasM) return '';
    h = hasH ? h : 0;
    m = hasM ? m : 0;
    return String(h + m / 60);
  }

  function combinedFuture() {
    var h = parseInt(fieldValue('future_work_hours'), 10);
    var m = parseInt(fieldValue('future_work_minutes'), 10);
    var hasH = !isNaN(h);
    var hasM = !isNaN(m);
    if (!hasH && !hasM) return '';
    h = hasH ? h : 0;
    m = hasM ? m : 0;
    return String(h + m / 60);
  }

  function collect() {
    var data = {};
    A.fields.forEach(function (f) {
      if (f === 'sleep_hours') data[f] = combinedSleep();
      else if (f === 'future_work_hours') data[f] = combinedFuture();
      else data[f] = fieldValue(f);
    });
    // На плановом выходном часы работы берём из компактного блока.
    if (getRadio('day_type') === 'day_off') {
      data.focused_work_hours = fieldValue('dayoff_focused_hours');
      data.total_work_hours = fieldValue('dayoff_total_hours');
    }
    return data;
  }

  function clearField(f) {
    var radios = form.querySelectorAll('input[name="' + f + '"]');
    if (radios.length && radios[0].type === 'radio') {
      radios.forEach(function (r) { r.checked = false; });
      return;
    }
    var el = form.querySelector('[name="' + f + '"]');
    if (el) el.value = '';
  }

  function clearFields(fields) {
    fields.forEach(clearField);
  }

  var DEPS = {
    left_home: { hidden: function (v) { return v !== '1'; }, fields: ['time_outside'] },
    exercise: { hidden: function (v) { return v !== '1'; }, fields: ['exercise_type', 'exercise_duration', 'exercise_intensity'] },
    had_dip: { hidden: function (v) { return v !== '1'; }, fields: ['dip_action', 'returned_to_work', 'distraction_reason'] },
    dayoff_worked: { hidden: function (v) { return v !== '1'; }, fields: ['dayoff_focused_hours', 'dayoff_total_hours'] },
    future_work: { hidden: function (v) { return v !== '1'; }, fields: ['future_work_hours', 'future_work_minutes', 'future_work_note', 'future_work_importance'] },
  };

  function setRadio(name, value) {
    var el = form.querySelector('input[name="' + name + '"][value="' + value + '"]');
    if (el) el.checked = true;
  }

  function clearDependents(ctrl) {
    if (ctrl === 'day_type') {
      var v = getRadio('day_type');
      if (v === 'work') {
        clearFields(['dayoff_focused_hours', 'dayoff_total_hours']);
        setRadio('dayoff_worked', '0');
      } else {
        clearFields(A.workFields);
        clearFields(['dayoff_focused_hours', 'dayoff_total_hours']);
      }
      return;
    }
    if (DEPS[ctrl] && DEPS[ctrl].hidden(getRadio(ctrl))) {
      clearFields(DEPS[ctrl].fields);
    }
  }

  function applyConditional() {
    document.querySelectorAll('[data-show]').forEach(function (el) {
      var parts = el.getAttribute('data-show').split(':');
      var ctrl = parts[0];
      var val = parts[1];
      var cur = getRadio(ctrl);
      el.classList.toggle('hidden', cur !== val);
    });
    updateResetButtons();
  }

  function updateResetButtons() {
    document.querySelectorAll('.reset-btn').forEach(function (btn) {
      var name = btn.getAttribute('data-reset');
      var checked = form.querySelector('input[name="' + name + '"]:checked');
      btn.classList.toggle('visible', !!checked);
    });
  }

  function requiredFields() {
    var list = ['day_type', 'bed_time', 'wake_time', 'sleep_hours', 'sleep_quality', 'morning_energy', 'day_readiness', 'left_home', 'exercise', 'evening_energy', 'tomorrow_motivation'];
    if (getRadio('day_type') === 'work') list.push('focused_work_hours', 'work_result');
    if (getRadio('left_home') === '1') list.push('time_outside');
    if (getRadio('exercise') === '1') list.push('exercise_type', 'exercise_duration', 'exercise_intensity');
    return list;
  }

  function updateProgress() {
    if (!progressLabel || !progressBar) return;
    var list = requiredFields();
    var data = collect();
    var filled = 0;
    list.forEach(function (f) {
      var v = data[f];
      if (v !== '' && v !== null && v !== undefined) filled++;
    });
    var pct = list.length ? Math.round((filled / list.length) * 100) : 0;
    progressLabel.textContent = filled + ' из ' + list.length + ' заполнено';
    progressBar.style.width = pct + '%';
  }

  function calcSleep() {
    if (!sleepCalc) return;
    var bed = fieldValue('bed_time');
    var wake = fieldValue('wake_time');
    if (!bed || !wake) { sleepCalc.innerHTML = ''; return; }
    var b = bed.split(':').map(Number);
    var w = wake.split(':').map(Number);
    var m = (w[0] * 60 + w[1]) - (b[0] * 60 + b[1]);
    if (m < 0) m += 24 * 60;
    var h = Math.floor(m / 60);
    var mm = m % 60;
    var html = 'Время в постели: ' + (h ? h + ' ч ' : '') + mm + ' мин';
    var sh = parseFloat(combinedSleep());
    if (!isNaN(sh)) {
      var diff = m - Math.round(sh * 60);
      var dh = Math.floor(Math.abs(diff) / 60);
      var dmm = Math.abs(diff) % 60;
      var sign = diff >= 0 ? 'меньше' : 'больше';
      html += ' · Сон по часам на ' + (dh ? dh + ' ч ' : '') + dmm + ' мин ' + sign + ' времени в постели';
    }
    sleepCalc.innerHTML = html;
  }

  function setStatus(state, detail) {
    if (!saveStatus) return;
    if (state === 'dirty') saveStatus.textContent = 'Есть несохранённые изменения';
    else if (state === 'saved') saveStatus.textContent = 'Сохранено в ' + detail;
    else if (state === 'error') saveStatus.textContent = 'Ошибка сохранения';
    else saveStatus.textContent = 'Ещё не сохранено';
  }

  var dirty = false;
  function markDirty() {
    if (A.readonly) return;
    dirty = true;
    setStatus('dirty');
  }

  function save() {
    var data = collect();
    data.date = A.date;
    return postJSON(A.base + '/api/save-day', data).then(function (j) {
      if (j.ok) { dirty = false; setStatus('saved', j.saved_at); }
      else setStatus('error');
    }).catch(function () { setStatus('error'); });
  }

  function hideErrors() {
    if (errorBox) { errorBox.classList.add('hidden'); errorBox.innerHTML = ''; }
  }

  function showErrors(list) {
    if (!errorBox) return;
    errorBox.classList.remove('hidden');
    errorBox.innerHTML = '<b>Не хватает данных:</b><ul>' +
      list.map(function (e) { return '<li>' + esc(e) + '</li>'; }).join('') + '</ul>';
    errorBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }

  if (A.readonly) {
    applyConditional();
    calcSleep();
    return;
  }

  form.addEventListener('input', function () {
    updateProgress();
    calcSleep();
    markDirty();
  });
  form.addEventListener('change', function (e) {
    if (e.target && e.target.name) clearDependents(e.target.name);
    applyConditional();
    updateProgress();
    calcSleep();
    markDirty();
  });

  form.addEventListener('click', function (e) {
    var btn = e.target.closest('.reset-btn');
    if (!btn) return;
    e.preventDefault();
    var name = btn.getAttribute('data-reset');
    clearField(name);
    clearDependents(name);
    applyConditional();
    updateProgress();
    calcSleep();
    markDirty();
  });

  document.getElementById('save-btn').addEventListener('click', function () {
    save();
  });

  document.getElementById('complete-btn').addEventListener('click', function () {
    var data = collect();
    data.date = A.date;
    hideErrors();
    postJSON(A.base + '/api/complete-day', data).then(function (j) {
      if (j.ok) { location.reload(); }
      else if (j.errors) { showErrors(j.errors); }
      else { showErrors([j.error || 'Не удалось завершить день.']); }
    }).catch(function () { showErrors(['Не удалось завершить день.']); });
  });

  applyConditional();
  updateProgress();
  calcSleep();
})();
