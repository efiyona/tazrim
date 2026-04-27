/**
 * דף סידור עבודה — לוח, bottom sheet, משמרת: עבודה/סוג כצ׳יפים, שעות בלבד + חצות
 */
(function () {
  'use strict';

  const api = (typeof TAZRIM_WORK_SCHEDULE === 'object' && TAZRIM_WORK_SCHEDULE.api) ? TAZRIM_WORK_SCHEDULE.api : '';

  /** בוקר / ערב / ביניים / לילה — שמור ב־icon_preset בשרת */
  const SHIFT_PRESETS = [
    { id: 'morning', label: 'בוקר', fa: 'fa-sun' },
    { id: 'evening', label: 'ערב', fa: 'fa-cloud-sun' },
    { id: 'mid', label: 'ביניים', fa: 'fa-clock' },
    { id: 'night', label: 'לילה', fa: 'fa-moon' },
  ];

  const iconPresets = {
    '': { cls: '' },
    morning: { cls: 'fa-solid fa-sun' },
    evening: { cls: 'fa-solid fa-cloud-sun' },
    mid: { cls: 'fa-solid fa-clock' },
    night: { cls: 'fa-solid fa-moon' },
  };

  function el(id) { return document.getElementById(id); }

  function tazrimConfirmOrNative(opts) {
    opts = opts || {};
    if (typeof window.tazrimConfirm === 'function') {
      return window.tazrimConfirm(opts);
    }
    var msg = opts.message || '';
    var title = opts.title || '';
    var ok = window.confirm(title ? (title + '\n\n' + msg) : msg);
    return Promise.resolve(ok);
  }

  function formEncode(data) {
    return Object.keys(data).map(k => encodeURIComponent(k) + '=' + encodeURIComponent(data[k] == null ? '' : String(data[k]))).join('&');
  }

  function postAction(action, extra) {
    const body = Object.assign({ action: action }, extra || {});
    return fetch(api, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: formEncode(body),
      credentials: 'same-origin',
    }).then(r => r.json());
  }

  function closeModal(m) {
    if (!m) return;
    m.style.display = 'none';
    m.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('no-scroll');
    const prev = m._prevFocus;
    if (prev && typeof prev.focus === 'function') { try { prev.focus(); } catch (e) { /* */ } }
    m._prevFocus = null;
  }

  function openModal(m) {
    if (!m) return;
    m._prevFocus = document.activeElement;
    m.style.display = 'block';
    m.setAttribute('aria-hidden', 'false');
    document.body.classList.add('no-scroll');
    const first = m.querySelector('button, [href], input, [tabindex]:not([tabindex="-1"])');
    if (first) { try { first.focus(); } catch (e) { /* */ } }
  }

  let jobsCache = [];
  let shiftTypesCache = {};
  let currentMonth;
  let currentYear;
  let monthShifts = [];
  let selectedDayStr = null;
  var pendingOvernight = false;

  function setMonthNav(y, m) { currentYear = y; currentMonth = m; }

  function pad(n) { return (n < 10 ? '0' : '') + n; }

  function ymd(y, m, d) {
    return y + '-' + pad(m) + '-' + pad(d);
  }

  function todayYmd() {
    const t = new Date();
    return ymd(t.getFullYear(), t.getMonth() + 1, t.getDate());
  }

  function timeToMin(t) {
    if (!t || typeof t !== 'string') return 0;
    const p = t.split(':');
    if (p.length < 2) return 0;
    return (parseInt(p[0], 10) || 0) * 60 + (parseInt(p[1], 10) || 0);
  }

  function minToTime(mins) {
    const h = Math.floor(mins / 60) % 24;
    const m = mins % 60;
    return pad(h) + ':' + pad(m);
  }

  /** יום + שתי שעות (string HH:MM) — אם end <= start (בשעון) → סיום למחר */
  function computeStartsEndsFromDayAndTimes(dayYmd, startTime, endTime) {
    if (!dayYmd || !startTime || !endTime) return { err: 'נא למלא שעת התחלה וסיום.' };
    const p = dayYmd.split('-');
    if (p.length !== 3) return { err: 'תאריך לא תקין.' };
    const y = parseInt(p[0], 10);
    const mo = parseInt(p[1], 10);
    const d = parseInt(p[2], 10);
    const d0 = new Date(y, mo - 1, d);
    if (isNaN(d0.getTime())) return { err: 'תאריך לא תקין.' };
    const sm = timeToMin(startTime);
    const em = timeToMin(endTime);
    if (sm === em) return { err: 'שעת התחלה וסיום חייבות להיות שונות.' };
    const dStart = new Date(d0);
    dStart.setHours(Math.floor(sm / 60), sm % 60, 0, 0);
    let dEnd;
    if (em <= sm) {
      dEnd = new Date(d0);
      dEnd.setDate(dEnd.getDate() + 1);
      dEnd.setHours(Math.floor(em / 60), em % 60, 0, 0);
    } else {
      dEnd = new Date(d0);
      dEnd.setHours(Math.floor(em / 60), em % 60, 0, 0);
    }
    if (dEnd.getTime() <= dStart.getTime()) return { err: 'שעת סיום חייבת להיות אחרי שעת התחלה.' };
    return { start: dStart, end: dEnd, overnight: em <= sm && sm !== em };
  }

  function formatMysqlDt(d) {
    const p = n => (n < 10 ? '0' : '') + n;
    return d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate()) + ' ' + p(d.getHours()) + ':' + p(d.getMinutes()) + ':00';
  }

  function escapeHtml(s) {
    const d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
  }

  function iconClassForShiftRow(row) {
    const ic = (row.type_icon_class || '').trim();
    if (ic) return 'fa-solid ' + ic;
    const preset = row.icon_preset || '';
    return (iconPresets[preset] && iconPresets[preset].cls) ? iconPresets[preset].cls : '';
  }

  function typeIconHtmlForList(t) {
    if (!t) return '';
    const ic = (t.icon_class || '').trim();
    if (ic) {
      if (ic.indexOf('fa-solid') === 0) return '<i class="' + ic + '"></i> ';
      return '<i class="fa-solid ' + ic + '"></i> ';
    }
    const p = t.icon_preset || 'morning';
    const m = iconPresets[p];
    if (m && m.cls) return '<i class="' + m.cls + '"></i> ';
    return '';
  }

  function buildTypeRowHtml(prefix) {
    var presetBtns = SHIFT_PRESETS.map(function (p) {
      return '<button type="button" class="work-preset-ic-btn" data-preset="' + p.id + '"><i class="fa-solid ' + p.fa + '"></i><span class="work-preset-ic-label">' + p.label + '</span></button>';
    }).join('');
    return '<div class="work-type-card ' + prefix + '-type-row">' +
      '<div class="work-type-card__head"><input type="text" class="' + prefix + '-type-name work-input" placeholder="שם סוג (למשל: משמרת בוקר)" />' +
      '<button type="button" class="btn-small ' + prefix + '-type-rm" aria-label="הסרת שורה">×</button></div>' +
      '<div class="work-type-times"><label class="work-type-time-label"><span>התחלה</span><input type="time" class="' + prefix + '-type-tstart work-input work-input--time" step="60" /></label>' +
      '<label class="work-type-time-label"><span>סיום</span><input type="time" class="' + prefix + '-type-tend work-input work-input--time" step="60" /></label></div>' +
      '<span class="work-field-label" style="margin-top:8px">סוג (אייקון)</span>' +
      '<div class="work-preset-ic-row">' + presetBtns + '</div>' +
      '<input type="hidden" class="' + prefix + '-type-preset" value="morning" /></div>';
  }

  function bindTypeRow(row, prefix) {
    const hid = row.querySelector('.' + prefix + '-type-preset');
    const rm = row.querySelector('.' + prefix + '-type-rm');
    if (rm) rm.addEventListener('click', function () { row.remove(); });
    row.querySelectorAll('.work-preset-ic-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        const pr = btn.getAttribute('data-preset');
        if (hid) hid.value = pr || 'morning';
        row.querySelectorAll('.work-preset-ic-btn').forEach(b => b.classList.remove('work-preset-ic-btn--sel'));
        btn.classList.add('work-preset-ic-btn--sel');
      });
    });
    const firstP = (hid && hid.value) || 'morning';
    if (hid) {
      const selBtn = row.querySelector('.work-preset-ic-btn[data-preset="' + firstP + '"]') || row.querySelector('.work-preset-ic-btn');
      if (selBtn) { selBtn.classList.add('work-preset-ic-btn--sel'); if (hid) hid.value = firstP; }
    }
  }

  function renderCalendarShell() {
    const grid = el('work-cal-grid');
    if (!grid) return;
    const y = currentYear;
    const m = currentMonth;
    const first = new Date(y, m - 1, 1);
    const startPad = first.getDay();
    const daysIn = new Date(y, m, 0).getDate();
    const prevMonth = m === 1 ? 12 : m - 1;
    const prevYear = m === 1 ? y - 1 : y;
    const dimPrev = new Date(prevYear, prevMonth, 0).getDate();
    const weekDays = ['א׳', 'ב׳', 'ג׳', 'ד׳', 'ה׳', 'ו׳', 'ש׳'];
    var html = '<div class="work-cal-weekday-row" role="row">';
    for (var w = 0; w < 7; w++) {
      html += '<div class="work-cal-wd" role="columnheader">' + weekDays[w] + '</div>';
    }
    html += '</div>';
    const cells = [];
    for (var i = 0; i < startPad; i++) {
      const d = dimPrev - startPad + i + 1;
      cells.push({ inMonth: false, day: d, y: prevYear, mo: prevMonth });
    }
    for (var d0 = 1; d0 <= daysIn; d0++) { cells.push({ inMonth: true, day: d0, y: y, mo: m }); }
    const nextMo = m === 12 ? 1 : m + 1;
    const nextY = m === 12 ? y + 1 : y;
    var n = 1;
    while (cells.length % 7 !== 0) { cells.push({ inMonth: false, day: n++, y: nextY, mo: nextMo }); }
    while (cells.length < 42) { cells.push({ inMonth: false, day: n++, y: nextY, mo: nextMo }); }
    html += '<div class="work-cal-days" role="rowgroup">';
    for (var r = 0; r < cells.length; r += 7) {
      html += '<div class="work-cal-row work-cal-row--days" role="row">';
      for (var c = 0; c < 7; c++) {
        const cell = cells[r + c];
        const id = ymd(cell.y, cell.mo, cell.day);
        const isToday = id === todayYmd();
        const muted = !cell.inMonth ? ' work-cal-day--muted' : '';
        const todayCls = isToday ? ' work-cal-day--today' : '';
        if (cell.inMonth) {
          html += '<button type="button" class="work-cal-day' + muted + todayCls + '" data-day="' + id + '">' +
            '<span class="work-cal-daynum">' + cell.day + '</span><span class="work-cal-dots" id="dots-' + id + '"></span></button>';
        } else {
          html += '<div class="work-cal-day work-cal-day--muted work-cal-day--static" aria-hidden="true">' +
            '<span class="work-cal-daynum">' + cell.day + '</span><span class="work-cal-dots"></span></div>';
        }
      }
      html += '</div>';
    }
    html += '</div>';
    grid.innerHTML = html;
    grid.querySelectorAll('.work-cal-day[data-day]').forEach(function (btn) {
      btn.addEventListener('click', function () { openDaySheet(btn.getAttribute('data-day')); });
    });
  }

  function uniqueJobColorsForDay(dayStr) {
    const seen = {};
    const out = [];
    monthShifts.forEach(function (s) {
      if (!s.starts_at || s.starts_at.substring(0, 10) !== dayStr) return;
      const jid = String(s.job_id);
      if (seen[jid]) return;
      seen[jid] = true;
      out.push(s.job_color || '#5B8DEF');
    });
    return out;
  }

  function renderDots() {
    document.querySelectorAll('.work-cal-dots').forEach(function (d) { d.innerHTML = ''; });
    const seenDays = {};
    monthShifts.forEach(function (s) {
      if (!s.starts_at) return;
      const d0 = s.starts_at.substring(0, 10);
      seenDays[d0] = true;
    });
    Object.keys(seenDays).forEach(function (dayStr) {
      const host = el('dots-' + dayStr);
      if (!host) return;
      const colors = uniqueJobColorsForDay(dayStr);
      colors.slice(0, 4).forEach(function (col) {
        const dot = document.createElement('span');
        dot.className = 'work-cal-dot';
        dot.style.borderColor = col;
        host.appendChild(dot);
      });
      if (colors.length > 4) {
        const more = document.createElement('span');
        more.className = 'work-cal-dot work-cal-dot--more';
        more.textContent = '+';
        host.appendChild(more);
      }
    });
  }

  const HE_DOW = ['יום ראשון', 'יום שני', 'יום שלישי', 'יום רביעי', 'יום חמישי', 'יום שישי', 'שבת'];
  const HE_MONTHS = ['ינואר', 'פברואר', 'מרץ', 'אפריל', 'מאי', 'יוני', 'יולי', 'אוגוסט', 'ספטמבר', 'אוקטובר', 'נובמבר', 'דצמבר'];

  function formatSheetHeaderHtml(dayStr) {
    const p = dayStr.split('-');
    if (p.length !== 3) return '<span class="work-day-sheet__dmy-plain">' + escapeHtml(dayStr) + '</span>';
    const y = parseInt(p[0], 10);
    const mo = parseInt(p[1], 10);
    const d = parseInt(p[2], 10);
    const dt = new Date(y, mo - 1, d);
    if (isNaN(dt.getTime())) return '<span class="work-day-sheet__dmy-plain">' + escapeHtml(dayStr) + '</span>';
    const dow = HE_DOW[dt.getDay()];
    const mname = HE_MONTHS[mo - 1] || '';
    return '<div class="work-day-sheet__headline">' +
      '<span class="work-day-sheet__dow">' + escapeHtml(dow) + '</span>' +
      '<span class="work-day-sheet__dmy">' + d + ' ב' + escapeHtml(mname) + ' ' + y + '</span>' +
      '</div><p class="work-day-sheet__subhead">משמרות ביום זה</p>';
  }

  function formatTimeRangeLine(s, e) {
    const a = new Date(s);
    const b = new Date(e);
    if (isNaN(a) || isNaN(b)) return '';
    const op = { hour: '2-digit', minute: '2-digit' };
    return a.toLocaleTimeString('he-IL', op) + ' – ' + b.toLocaleTimeString('he-IL', op);
  }

  function closeDaySheet() {
    const wrap = el('work-day-sheet-wrap');
    if (!wrap) return;
    wrap.classList.remove('work-day-sheet-wrap--open');
    wrap.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('no-scroll');
    selectedDayStr = null;
    document.querySelectorAll('.work-cal-day--selected').forEach(function (x) { x.classList.remove('work-cal-day--selected'); });
  }

  function openDaySheet(dayStr) {
    selectedDayStr = dayStr;
    const wrap = el('work-day-sheet-wrap');
    const title = el('work-day-sheet-title');
    const list = el('work-day-sheet-list');
    if (!wrap || !title || !list) return;
    title.innerHTML = formatSheetHeaderHtml(dayStr);
    const rows = monthShifts.filter(function (s) {
      return s.starts_at && s.starts_at.substring(0, 10) === dayStr;
    }).sort(function (a, b) { return String(a.starts_at).localeCompare(String(b.starts_at)); });
    if (rows.length === 0) {
      list.innerHTML = '<div class="work-day-sheet__empty-state">' +
        '<div class="work-day-sheet__empty-ic" aria-hidden="true"><i class="fa-regular fa-calendar-xmark"></i></div>' +
        '<p class="work-day-sheet__empty">אין משמרות ביום זה</p>' +
        '<button type="button" class="btn-primary work-day-sheet__add" id="work-day-sheet-add-shift">הוספת משמרת</button></div>';
      const b = el('work-day-sheet-add-shift');
      if (b) b.addEventListener('click', function () { openAddShiftForDay(dayStr); });
    } else {
      list.innerHTML = '<div class="work-day-sheet__cards">' + rows.map(function (row) {
        const ic = iconClassForShiftRow(row);
        const icHtml = ic ? '<span class="work-day-card__ico"><i class="' + ic + '"></i></span>' : '';
        const jcol = row.job_color || '#5B8DEF';
        const timeLn = formatTimeRangeLine(row.starts_at, row.ends_at);
        const meta = [];
        if (row.type_name) meta.push(row.type_name);
        if (row.note) meta.push(row.note);
        const metaT = meta.length ? escapeHtml(meta.join(' · ')) : '';
        return '<div class="work-day-card__wrap">' +
          '<button type="button" class="work-day-card" data-shift-id="' + escapeHtml(String(row.id)) + '" style="--work-day-accent:' + escapeHtml(jcol) + '">' +
          '<span class="work-day-card__accent" aria-hidden="true"></span>' +
          '<div class="work-day-card__inner">' +
          '<div class="work-day-card__time">' + escapeHtml(timeLn) + '</div>' +
          '<div class="work-day-card__row">' + icHtml +
          '<span class="work-day-card__job">' + escapeHtml(row.job_title || '') + '</span></div>' +
          (metaT ? '<div class="work-day-card__meta">' + metaT + '</div>' : '') +
          '</div></button>' +
          '<button type="button" class="work-day-card__trash" data-del-shift-id="' + escapeHtml(String(row.id)) + '" aria-label="מחיקת משמרת">' +
          '<i class="fa-solid fa-trash" aria-hidden="true"></i></button></div>';
      }).join('') + '</div>';
      list.querySelectorAll('.work-day-card').forEach(function (btn) {
        btn.addEventListener('click', function () {
          const id = btn.getAttribute('data-shift-id');
          const row = monthShifts.find(x => String(x.id) === String(id));
          if (row) openEditShiftModal(row);
        });
      });
      list.querySelectorAll('.work-day-card__trash').forEach(function (b) {
        b.addEventListener('click', function (e) {
          e.stopPropagation();
          const id = b.getAttribute('data-del-shift-id');
          deleteShiftByIdFromSheet(id);
        });
      });
    }
    wrap.classList.add('work-day-sheet-wrap--open');
    wrap.setAttribute('aria-hidden', 'false');
    document.body.classList.add('no-scroll');
    document.querySelectorAll('.work-cal-day--selected').forEach(function (x) { x.classList.remove('work-cal-day--selected'); });
    const dayBtn = gridDayButton(dayStr);
    if (dayBtn) dayBtn.classList.add('work-cal-day--selected');
  }

  function deleteShiftByIdFromSheet(shiftId) {
    tazrimConfirmOrNative({
      title: 'מחיקת משמרת',
      message: 'למחוק את המשמרת?',
      confirmText: 'מחק',
      cancelText: 'ביטול',
      danger: true,
    }).then(function (ok) {
      if (!ok) return;
      postAction('delete_shift', { id: String(shiftId) }).then(function (res) {
        if (res && res.ok) return loadShifts();
        alert((res && res.message) || 'שגיאה');
      });
    });
  }

  function gridDayButton(dayStr) {
    const grid = el('work-cal-grid');
    if (!grid) return null;
    return grid.querySelector('.work-cal-day[data-day="' + dayStr + '"]');
  }

  function loadShifts() {
    return postAction('list_shifts', { year: String(currentYear), month: String(currentMonth) }).then(function (res) {
      if (res && res.ok && Array.isArray(res.data)) {
        monthShifts = res.data;
        renderDots();
        if (selectedDayStr) openDaySheet(selectedDayStr);
      }
    });
  }

  function loadJobs() {
    return postAction('list_jobs', {}).then(function (res) {
      if (res && res.ok && Array.isArray(res.data)) {
        jobsCache = res.data;
        renderLegend();
        renderJobChips();
      }
    });
  }

  function renderLegend() {
    const leg = el('work-cal-legend');
    if (!leg) return;
    if (!jobsCache.length) { leg.innerHTML = ''; return; }
    leg.innerHTML = '<div class="work-legend-items">' + jobsCache.map(function (j) {
      return '<span class="work-legend-item"><span class="work-legend-ring" style="border-color:' + j.color + '"></span> ' + escapeHtml(j.title) + '</span>';
    }).join('') + '</div>';
  }

  function renderJobChips() {
    const wrap = el('ws-modal-job-chips');
    const hid = el('ws-modal-job');
    if (!wrap || !hid) return;
    if (!jobsCache.length) {
      wrap.innerHTML = '<p class="work-field-hint">אין עבודות. הוסיפו ב«החשבון שלי».</p>';
      return;
    }
    const keep = hid.value;
    wrap.innerHTML = jobsCache.map(function (j) {
      return '<button type="button" class="work-store-chip" data-job-id="' + escapeHtml(String(j.id)) + '" style="--work-chip-c:' + escapeHtml(j.color) + '">' +
        '<span class="work-store-chip__dot" aria-hidden="true"></span><span class="work-store-chip__name">' + escapeHtml(j.title) + '</span></button>';
    }).join('');
    if (keep && jobsCache.some(x => String(x.id) === String(keep))) {
      hid.value = keep;
    } else if (jobsCache.length) {
      hid.value = String(jobsCache[0].id);
    } else { hid.value = ''; }
    markJobChipSelected();
    wrap.querySelectorAll('.work-store-chip').forEach(function (b) {
      b.addEventListener('click', function () {
        hid.value = b.getAttribute('data-job-id') || '';
        shiftTypesCache = {};
        markJobChipSelected();
        renderTypeChipsForModal(hid.value, '0', true);
      });
    });
  }

  function markJobChipSelected() {
    const hid = el('ws-modal-job');
    const jwrap = el('ws-modal-job-chips');
    if (!hid || !jwrap) return;
    const v = String(hid.value);
    jwrap.querySelectorAll('.work-store-chip').forEach(function (b) {
      b.classList.toggle('work-store-chip--sel', b.getAttribute('data-job-id') === v);
    });
  }

  function renderTypeChipsForModal(jobId, selectedId, isAdd) {
    const wrap = el('ws-modal-type-chips');
    const hid = el('ws-modal-type');
    if (!wrap || !hid) return;
    const sel = (selectedId == null || selectedId === '' || String(selectedId) === '0') ? '0' : String(selectedId);
    if (isAdd) hid.value = '0';
    else hid.value = sel;
    if (!jobId) {
      wrap.innerHTML = '<p class="work-field-hint">בחרו עבודה תחילה</p>';
      return;
    }
    loadShiftTypesForJob(jobId, function (list) {
      var html = '<button type="button" class="work-store-chip work-store-chip--st" data-type-id="0" aria-pressed="false">' +
        '<span class="work-store-chip__dot work-store-chip__dot--neutral" aria-hidden="true"></span>' +
        '<span class="work-store-chip__name">ללא</span></button>';
      list.forEach(function (t) {
        const lead = '<span class="work-store-chip__type-lead" aria-hidden="true">' + typeIconHtmlForList(t).replace(/^\s+/, '') + '</span>';
        html += '<button type="button" class="work-store-chip work-store-chip--st" data-type-id="' + escapeHtml(String(t.id)) + '" aria-pressed="false">' + lead +
          '<span class="work-store-chip__name">' + escapeHtml(t.name) + '</span></button>';
      });
      wrap.innerHTML = html;
      markTypeChipSelected(hid.value);
      wrap.querySelectorAll('.work-store-chip--st').forEach(function (b) {
        b.addEventListener('click', function () {
          const tid0 = b.getAttribute('data-type-id') || '0';
          hid.value = tid0;
          markTypeChipSelected(tid0);
          updateOvernightHint();
          applyDefaultTimesFromType();
        });
      });
      if (isAdd) applyDefaultTimesFromType();
    });
  }

  function markTypeChipSelected(tid) {
    const twrap = el('ws-modal-type-chips');
    if (!twrap) return;
    const t0 = (tid == null || tid === '') ? '0' : String(tid);
    twrap.querySelectorAll('.work-store-chip--st').forEach(function (b) {
      const v = b.getAttribute('data-type-id') || '0';
      const on = (v === t0);
      b.classList.toggle('work-store-chip--sel', on);
      b.setAttribute('aria-pressed', on ? 'true' : 'false');
    });
  }

  function loadShiftTypesForJob(jobId, callback) {
    if (!jobId) { if (typeof callback === 'function') callback([]); return; }
    if (shiftTypesCache[jobId]) { if (typeof callback === 'function') callback(shiftTypesCache[jobId]); return; }
    postAction('list_shift_types', { job_id: String(jobId) }).then(function (res) {
      const list = (res && res.ok && Array.isArray(res.data)) ? res.data : [];
      shiftTypesCache[jobId] = list;
      if (typeof callback === 'function') callback(list);
    });
  }

  function applyDefaultTimesFromType() {
    const jid = el('ws-modal-job') && el('ws-modal-job').value;
    const tid = el('ws-modal-type') && el('ws-modal-type').value;
    const tStart = el('ws-modal-start-time');
    const tEnd = el('ws-modal-end-time');
    if (!jid || !tStart || !tEnd || !tid || tid === '0') { updateOvernightHint(); return; }
    loadShiftTypesForJob(jid, function (list) {
      const t = list.find(x => String(x.id) === String(tid));
      if (!t) { updateOvernightHint(); return; }
      const ds = t.default_start_time ? String(t.default_start_time).substring(0, 5) : '';
      const de = t.default_end_time ? String(t.default_end_time).substring(0, 5) : '';
      if (ds) tStart.value = ds;
      if (de) tEnd.value = de;
      updateOvernightHint();
    });
  }

  function updateOvernightHint() {
    const h = el('ws-modal-overnight-hint');
    const t1 = el('ws-modal-start-time');
    const t2 = el('ws-modal-end-time');
    if (!h || !t1 || !t2 || !t1.value || !t2.value) { if (h) h.style.display = 'none'; return; }
    const sm = timeToMin(t1.value);
    const em = timeToMin(t2.value);
    pendingOvernight = (em > 0 && em <= sm);
    h.style.display = pendingOvernight ? 'block' : 'none';
  }

  function wireShiftTimeInputs() {
    const t1 = el('ws-modal-start-time');
    const t2 = el('ws-modal-end-time');
    [t1, t2].forEach(function (x) { if (x) x.addEventListener('input', updateOvernightHint); });
  }

  function resetShiftModal(mode) {
    const modeEl = el('ws-modal-mode');
    if (modeEl) modeEl.value = mode;
    const sid = el('ws-modal-shift-id');
    if (sid) sid.value = '';
    const job = el('ws-modal-job');
    if (job) job.value = jobsCache[0] ? String(jobsCache[0].id) : '';
    const tsel = el('ws-modal-type');
    if (tsel) tsel.value = '0';
    const note = el('ws-modal-note');
    if (note) note.value = '';
    const dday = el('ws-modal-day');
    if (dday) dday.value = todayYmd();
    const sT = el('ws-modal-start-time');
    if (sT) sT.value = '09:00';
    const eT = el('ws-modal-end-time');
    if (eT) eT.value = '17:00';
    const tt = el('work-shift-modal-title');
    if (tt) tt.textContent = mode === 'edit' ? 'עריכת משמרת' : 'הוספת משמרת';
    renderJobChips();
    var jid = (job && job.value) || '';
    renderTypeChipsForModal(jid, '0', true);
    updateOvernightHint();
  }

  window.openWorkShiftQuickModal = function (optDay) {
    resetShiftModal('add');
    if (optDay) {
      const d = el('ws-modal-day');
      if (d) d.value = String(optDay);
    }
    openModal(el('work-shift-quick-modal'));
  };

  function openAddShiftForDay(dayStr) {
    closeDaySheet();
    if (el('ws-modal-mode')) el('ws-modal-mode').value = 'add';
    if (el('ws-modal-shift-id')) el('ws-modal-shift-id').value = '';
    if (el('ws-modal-note')) el('ws-modal-note').value = '';
    var j = el('ws-modal-job');
    if (j) {
      if (!j.value && jobsCache[0]) j.value = String(jobsCache[0].id);
    }
    if (el('ws-modal-day')) el('ws-modal-day').value = dayStr;
    if (el('ws-modal-start-time')) el('ws-modal-start-time').value = '09:00';
    if (el('ws-modal-end-time')) el('ws-modal-end-time').value = '17:00';
    if (el('ws-modal-type')) el('ws-modal-type').value = '0';
    if (el('work-shift-modal-title')) el('work-shift-modal-title').textContent = 'הוספת משמרת';
    renderJobChips();
    renderTypeChipsForModal(j && j.value, '0', true);
    updateOvernightHint();
    openModal(el('work-shift-quick-modal'));
  }
  window.openAddShiftForSelectedDay = openAddShiftForDay;

  window.workScheduleOpenDeleteJob = function (id) {
    if (id == null || String(id) === '') return;
    tazrimConfirmOrNative({
      title: 'מחיקת עבודה',
      message: 'למחוק עבודה? יימחקו כל המשמרות וסוגי המשמרות. פעולה זו בלתי הפיכה.',
      confirmText: 'מחק', cancelText: 'ביטול', danger: true,
    }).then(function (ok) { if (ok) runDeleteJob(String(id)); });
  };

  function openEditShiftModal(row) {
    closeDaySheet();
    if (el('ws-modal-mode')) el('ws-modal-mode').value = 'edit';
    if (el('ws-modal-shift-id')) el('ws-modal-shift-id').value = String(row.id);
    var j = el('ws-modal-job');
    if (j) j.value = String(row.job_id);
    if (el('ws-modal-type')) {
      el('ws-modal-type').value = (row.shift_type_id && String(row.shift_type_id) !== '0') ? String(row.shift_type_id) : '0';
    }
    const s = new Date((row.starts_at || '').replace(' ', 'T'));
    const e = new Date((row.ends_at || '').replace(' ', 'T'));
    if (el('ws-modal-day') && !isNaN(s.getTime())) {
      el('ws-modal-day').value = ymd(s.getFullYear(), s.getMonth() + 1, s.getDate());
    }
    if (el('ws-modal-start-time') && !isNaN(s.getTime())) {
      el('ws-modal-start-time').value = pad(s.getHours()) + ':' + pad(s.getMinutes());
    }
    if (el('ws-modal-end-time') && !isNaN(e.getTime())) {
      el('ws-modal-end-time').value = pad(e.getHours()) + ':' + pad(e.getMinutes());
    }
    if (el('ws-modal-note')) el('ws-modal-note').value = row.note || '';
    if (el('work-shift-modal-title')) el('work-shift-modal-title').textContent = 'עריכת משמרת';
    renderJobChips();
    const jid = String(row.job_id);
    const tid = (row.shift_type_id && String(row.shift_type_id) !== '0') ? String(row.shift_type_id) : '0';
    renderTypeChipsForModal(jid, tid, false);
    updateOvernightHint();
    openModal(el('work-shift-quick-modal'));
  }

  function submitShiftModal() {
    const mode = el('ws-modal-mode') && el('ws-modal-mode').value;
    const jobId = el('ws-modal-job') && el('ws-modal-job').value;
    const typeId = (el('ws-modal-type') && el('ws-modal-type').value) || '0';
    const dayStr = (el('ws-modal-day') && el('ws-modal-day').value) || todayYmd();
    const tS = el('ws-modal-start-time') && el('ws-modal-start-time').value;
    const tE = el('ws-modal-end-time') && el('ws-modal-end-time').value;
    const noteEl = el('ws-modal-note');
    const idEl = el('ws-modal-shift-id');
    if (!jobId) { alert('נא לבחור עבודה.'); return; }
    if (!tS || !tE) { alert('נא למלא שעת התחלה וסיום.'); return; }
    const c = computeStartsEndsFromDayAndTimes(dayStr, tS, tE);
    if (c.err) { alert(c.err); return; }
    const p = {
      job_id: jobId,
      shift_type_id: (typeId && String(typeId) !== '0') ? typeId : '0',
      starts_at: formatMysqlDt(c.start),
      ends_at: formatMysqlDt(c.end),
      note: noteEl ? noteEl.value : '',
    };
    const chain = mode === 'edit' && idEl && idEl.value
      ? postAction('update_shift', Object.assign({ id: idEl.value }, p))
      : postAction('create_shift', p);
    chain.then(function (res) {
      if (res && res.ok) {
        closeModal(el('work-shift-quick-modal'));
        return loadShifts();
      }
      alert((res && res.message) || 'שגיאה');
    });
  }

  function getCfg() { return (typeof TAZRIM_WORK_SCHEDULE === 'object' && TAZRIM_WORK_SCHEDULE) ? TAZRIM_WORK_SCHEDULE : {}; }

  function collectTypesFromRows(prefix) {
    const types = [];
    document.querySelectorAll('.' + prefix + '-type-row').forEach(function (row) {
      const nameEl = row.querySelector('.' + prefix + '-type-name');
      const n = nameEl ? nameEl.value : '';
      if (!n || String(n).trim() === '') return;
      const tstartEl = row.querySelector('.' + prefix + '-type-tstart');
      const tendEl = row.querySelector('.' + prefix + '-type-tend');
      const tstart = tstartEl ? tstartEl.value : '';
      const tend = tendEl ? tendEl.value : '';
      const pEl = row.querySelector('.' + prefix + '-type-preset');
      const pr = pEl && pEl.value ? pEl.value : 'morning';
      types.push({
        name: String(n).trim(),
        icon_class: '',
        icon_preset: pr,
        default_start_time: tstart || '',
        default_end_time: tend || '',
      });
    });
    return types;
  }

  function submitWizard() {
    const title = (el('wiz-job-title') && el('wiz-job-title').value) || '';
    const color = (el('wiz-color') && el('wiz-color').value) || '#5B8DEF';
    const pd = (el('wiz-payday') && el('wiz-payday').value) || '1';
    const types = collectTypesFromRows('wiz');
    if (!title.trim()) { alert('נא להזין שם עבודה.'); return; }
    postAction('wizard_setup', {
      title: title.trim(),
      color: color,
      payday_day_of_month: String(parseInt(pd, 10) || 1),
      types: JSON.stringify(types),
    }).then(function (res) {
      if (res && res.ok) { window.location.reload(); return; }
      alert((res && res.message) || 'שמירה נכשלה.');
    });
  }

  function addWizTypeRow() {
    const wrap = el('wiz-types-wrap');
    if (!wrap) return;
    const row = document.createElement('div');
    row.innerHTML = buildTypeRowHtml('wiz');
    const root = row.firstElementChild;
    wrap.appendChild(root);
    bindTypeRow(root, 'wiz');
  }

  function runDeleteJob(id) {
    if (!id) return;
    postAction('delete_job', { id: String(id) }).then(function (res) {
      if (res && res.ok) { window.location.reload(); return; }
      alert((res && res.message) || 'מחיקה נכשלה');
    });
  }

  function init() {
    const cfg = getCfg();
    if (cfg.year && cfg.month) setMonthNav(cfg.year, cfg.month);
    const wf = el('wizard-finish-btn');
    if (wf) wf.addEventListener('click', submitWizard);
    const wadd = el('wiz-add-type');
    if (wadd) wadd.addEventListener('click', addWizTypeRow);
    wireShiftTimeInputs();
    const saveBtn = el('ws-modal-save');
    if (saveBtn) saveBtn.addEventListener('click', submitShiftModal);
    const closeB = el('work-shift-modal-close');
    if (closeB) closeB.addEventListener('click', function () { closeModal(el('work-shift-quick-modal')); });
    [el('work-shift-quick-modal')].forEach(function (mod) {
      if (!mod) return;
      mod.addEventListener('click', function (e) { if (e.target === mod) closeModal(mod); });
    });
    const bd = el('work-day-sheet-backdrop');
    if (bd) bd.addEventListener('click', closeDaySheet);
    const sc = el('work-day-sheet-close');
    if (sc) sc.addEventListener('click', closeDaySheet);
    document.addEventListener('keydown', function (e) {
      if (e.key !== 'Escape') return;
      const wrap = el('work-day-sheet-wrap');
      if (wrap && wrap.classList.contains('work-day-sheet-wrap--open')) { closeDaySheet(); return; }
      const q = el('work-shift-quick-modal');
      if (q && q.style.display === 'block') closeModal(q);
    });
    if (el('work-main-calendar')) {
      renderCalendarShell();
      loadJobs().then(function () { return loadShifts(); });
    }
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
