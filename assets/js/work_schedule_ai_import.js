/**
 * ייבוג סידור מצילום מסך — Gemini → עריכה → שמירה מרובת משמרות
 */
(function () {
  'use strict';

  function el(id) {
    return document.getElementById(id);
  }

  function cfg() {
    return typeof window.TAZRIM_WORK_SCHEDULE === 'object' && window.TAZRIM_WORK_SCHEDULE
      ? window.TAZRIM_WORK_SCHEDULE
      : {};
  }

  function pad(n) {
    return (n < 10 ? '0' : '') + n;
  }

  function timeToMin(t) {
    if (!t || typeof t !== 'string') return 0;
    const p = t.split(':');
    if (p.length < 2) return 0;
    return (parseInt(p[0], 10) || 0) * 60 + (parseInt(p[1], 10) || 0);
  }

  function normalizeHm(raw) {
    if (raw == null) return '';
    const s = String(raw).substring(0, 5);
    const p = s.split(':');
    if (p.length < 2) return '';
    const h = parseInt(p[0], 10);
    const m = parseInt(p[1], 10);
    if (isNaN(h) || isNaN(m)) return '';
    return pad(h) + ':' + pad(m);
  }

  /** התאמת שעות למשמרת מול סוג שמוגדר בעבודה (כולל לילה) */
  function matchShiftTypeId(startHm, endHm, types) {
    const sm = timeToMin(startHm);
    const em = timeToMin(endHm);
    const shiftOv = em > 0 && em <= sm;
    let bestId = '0';
    types.forEach(function (trow) {
      const ds = normalizeHm(trow.default_start_time);
      const de = normalizeHm(trow.default_end_time);
      if (!ds || !de) return;
      const tmS = timeToMin(ds);
      const tmE = timeToMin(de);
      const typeOv = tmE > 0 && tmE <= tmS;
      if (shiftOv !== typeOv) return;
      if (sm === tmS && em === tmE) bestId = String(trow.id);
    });
    return bestId;
  }

  function escapeHtml(s) {
    const d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
  }

  function computeStartsEndsFromDayAndTimes(dayYmd, startTime, endTime) {
    if (!dayYmd || !startTime || !endTime) return { err: 'נא למלא שעות.' };
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
    if (dEnd.getTime() <= dStart.getTime()) return { err: 'שעות לא תקינות.' };
    return { start: dStart, end: dEnd };
  }

  function formatMysqlDt(d) {
    const p = function (n) {
      return (n < 10 ? '0' : '') + n;
    };
    return (
      d.getFullYear()
      + '-'
      + p(d.getMonth() + 1)
      + '-'
      + p(d.getDate())
      + ' '
      + p(d.getHours())
      + ':'
      + p(d.getMinutes())
      + ':00'
    );
  }

  function formEncode(data) {
    return Object.keys(data)
      .map(function (k) {
        return encodeURIComponent(k) + '=' + encodeURIComponent(data[k] == null ? '' : String(data[k]));
      })
      .join('&');
  }

  function postApi(action, extra) {
    const c = cfg();
    const api = c.api || '';
    if (!api) return Promise.resolve({ ok: false, message: 'אין נתיב API' });
    const body = Object.assign({ action: action }, extra || {});
    return fetch(api, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: formEncode(body),
      credentials: 'same-origin',
    }).then(function (r) {
      return r.json();
    });
  }

  function populateImportTargets() {
    var sel = el('work-schedule-ai-import-period');
    if (!sel) return;
    var hm = cfg().hebrewMonths || {};
    var curY = Number(cfg().year) || new Date().getFullYear();
    var curM = Number(cfg().month) || new Date().getMonth() + 1;
    var want = curY + '-' + pad(curM);
    sel.innerHTML = '';
    for (var y = curY - 2; y <= curY + 2; y++) {
      for (var mi = 1; mi <= 12; mi++) {
        var opt = document.createElement('option');
        opt.value = y + '-' + pad(mi);
        var label = hm[mi] != null ? hm[mi] + ' ' + y : pad(mi) + '/' + y;
        opt.textContent = label;
        sel.appendChild(opt);
      }
    }
    var matched = false;
    for (var oi = 0; oi < sel.options.length; oi++) {
      if (sel.options[oi].value === want) {
        matched = true;
        break;
      }
    }
    if (matched) sel.value = want;
    else if (sel.options[0]) sel.selectedIndex = 0;
  }

  function getImportYm() {
    var sel = el('work-schedule-ai-import-period');
    var raw = sel && sel.value ? String(sel.value).trim() : '';
    var parts = raw.split('-');
    var yr = parseInt(parts[0], 10);
    var mo = parseInt(parts[1], 10);
    if (isNaN(mo) || mo < 1 || mo > 12) mo = Number(cfg().month) || new Date().getMonth() + 1;
    if (isNaN(yr) || yr < 2000 || yr > 2100) yr = Number(cfg().year) || new Date().getFullYear();
    return { yr: yr, mo: mo };
  }

  function mysqlDtToTs(s) {
    if (!s || typeof s !== 'string') return NaN;
    var p = s.trim().split(/[\sT]/);
    if (p.length < 2) return NaN;
    var dp = p[0].split('-');
    var tp = p[1].split(':');
    if (dp.length < 3) return NaN;
    return new Date(
      parseInt(dp[0], 10),
      parseInt(dp[1], 10) - 1,
      parseInt(dp[2], 10),
      parseInt(tp[0], 10) || 0,
      parseInt(tp[1], 10) || 0,
      parseInt(tp[2], 10) || 0
    ).getTime();
  }

  function dupShiftKey(row) {
    var st = normalizeHm(row.start_time);
    var en = normalizeHm(row.end_time);
    return String(row.date || '') + '|' + st + '|' + en;
  }

  function computeRowDupConflictMeta(rows) {
    var jid = state.selectedJobId ? String(state.selectedJobId) : '';
    var counts = {};
    (rows || []).forEach(function (r) {
      var k = dupShiftKey(r);
      counts[k] = (counts[k] || 0) + 1;
    });
    return (rows || []).map(function (r) {
      var comp = computeStartsEndsFromDayAndTimes(r.date, r.start_time, r.end_time);
      var overlap = false;
      if (!comp.err && state.existingShifts && state.existingShifts.length && jid) {
        var ns = comp.start.getTime();
        var ne = comp.end.getTime();
        for (var i = 0; i < state.existingShifts.length; i++) {
          var ex = state.existingShifts[i];
          if (!ex || String(ex.job_id) !== jid) continue;
          var es = mysqlDtToTs(ex.starts_at);
          var ee = mysqlDtToTs(ex.ends_at);
          if (isNaN(es) || isNaN(ee)) continue;
          if (ns < ee && ne > es) {
            overlap = true;
            break;
          }
        }
      }
      return {
        isDup: counts[dupShiftKey(r)] > 1,
        hasOverlap: overlap,
      };
    });
  }

  function renderDupConflictBanners(meta) {
    var dupEl = el('work-schedule-ai-dup-banner');
    var dupAct = el('work-schedule-ai-dup-actions');
    var coEl = el('work-schedule-ai-conflict-banner');
    var dupRows = meta.filter(function (x) {
      return x.isDup;
    }).length;
    var ovRows = meta.filter(function (x) {
      return x.hasOverlap;
    }).length;
    if (dupEl) {
      if (dupRows < 1) {
        dupEl.style.display = 'none';
        dupEl.innerHTML = '';
      } else {
        dupEl.style.display = 'block';
        dupEl.innerHTML =
          '<strong id="work-schedule-ai-dup-banner-title">כפילויות ברשימה</strong>'
          + '<p>נמצאו ' + dupRows + ' שורות בסימון כפילות (אותו תאריך, אותן שעות). לא יוסר אוטומטית — השתמשו ב«הסר כפילויות» למחיקה מתוכן או התאימו ידנית.</p>';
      }
    }
    if (dupAct) {
      dupAct.style.display = dupRows > 0 ? 'flex' : 'none';
    }
    if (coEl) {
      if (ovRows < 1) {
        coEl.style.display = 'none';
        coEl.innerHTML = '';
      } else {
        coEl.style.display = 'block';
        coEl.innerHTML =
          '<strong id="work-schedule-ai-conflict-banner-title">כבר קיימת משמרת בטווח הזמן</strong>'
          + '<p>נמצאו ' + ovRows + ' משמרות שחופפות למשמרת קיימת בלוח (אותה עבודה, חודש הייבוא). ניתן לשמור — זו התראה בלבד; ודאו שלא מתכננים כפל.</p>';
      }
    }
  }

  var dupConflictUiTimer;

  function scheduleRefreshDupConflictUi() {
    clearTimeout(dupConflictUiTimer);
    dupConflictUiTimer = setTimeout(refreshDupConflictUi, 80);
  }

  function refreshDupConflictUi() {
    var step = el('work-schedule-ai-step-review');
    if (!step || step.style.display === 'none') return;
    syncReviewRowsFromDomIfOpen();
    var meta = computeRowDupConflictMeta(state.reviewRows);
    var list = el('work-schedule-ai-shifts-list');
    if (list) {
      var rows = list.querySelectorAll('.work-schedule-ai-shift-row');
      rows.forEach(function (rowEl, idx) {
        var m = meta[idx];
        if (!m) return;
        rowEl.classList.toggle('work-schedule-ai-shift-row--dup', !!m.isDup);
        rowEl.classList.toggle('work-schedule-ai-shift-row--overlap', !!m.hasOverlap);
      });
    }
    renderDupConflictBanners(meta);
  }

  function dedupeReviewRowsConfirmed() {
    if (state.loading) return;
    syncReviewRowsFromDomIfOpen();
    var seen = {};
    var next = [];
    state.reviewRows.forEach(function (r) {
      var k = dupShiftKey(r);
      if (seen[k]) return;
      seen[k] = true;
      next.push(r);
    });
    if (next.length === state.reviewRows.length) return;
    var nDrop = state.reviewRows.length - next.length;
    if (
      !confirm(
        'להסיר '
          + String(nDrop)
          + ' משמרות כפולות? בכל קבוצה של אותו תאריך ואותן שעות תישאר הרשומה הראשונה בלבד.'
      )
    ) {
      return;
    }
    state.reviewRows = next;
    sortReviewRows();
    renderReview();
    updateReviewSummaryAndThumbs();
  }

  function uploadReportItemMessage(r) {
    if (!r || typeof r !== 'object') return '';
    return typeof r.message === 'string' ? r.message : '';
  }

  function announceAriaLive(txt) {
    var live = el('work-schedule-ai-upload-live');
    if (!live || !txt) return;
    live.textContent = '';
    setTimeout(function () {
      live.textContent = txt;
    }, 30);
  }

  function showUploadValidation(report, headline) {
    var box = el('work-schedule-ai-msg');
    if (!box) return;
    var arr = Array.isArray(report) ? report : [];
    var lines = arr.map(uploadReportItemMessage).filter(function (x) {
      return x;
    });
    var speak = headline + (lines.length ? ' — ' + lines.join(' ') : '');
    announceAriaLive(speak);
    box.style.display = 'block';
    box.classList.add('work-modal-msg--err');
    box.classList.remove('work-modal-msg--ok');
    box.style.background = '#fee2e2';
    box.style.color = '#b91c1c';
    if (!lines.length) {
      box.textContent = headline;
      return;
    }
    box.innerHTML =
      '<strong>'
      + escapeHtml(headline)
      + '</strong><ul role="list" style="margin:8px 0 0;padding-right:1.1em;text-align:start;">'
      + lines.map(function (ln) {
        return '<li>' + escapeHtml(ln) + '</li>';
      }).join('')
      + '</ul>';
  }

  const state = {
    loading: false,
    jobs: [],
    shiftTypesForJob: [],
    selectedJobId: '',
    files: [],
    reviewRows: [],
    warnings: [],
    existingShifts: [],
    importYear: null,
    importMonth: null,
  };

  function thinkingHtml(label) {
    return (
      '<div class="work-schedule-ai-thinking" role="status">'
      + '<div class="work-schedule-ai-thinking__glow" aria-hidden="true"></div>'
      + '<div class="work-schedule-ai-thinking__orb" aria-hidden="true">'
      + '<i class="fa-solid fa-wand-magic-sparkles"></i></div>'
      + '<div class="work-schedule-ai-thinking__dots" aria-hidden="true">'
      + '<span></span><span></span><span></span></div>'
      + '<p class="work-schedule-ai-thinking__label">' + escapeHtml(label || 'מעבד…') + '</p>'
      + '</div>'
    );
  }

  function setLoading(on, mode) {
    state.loading = !!on;
    const ex = el('work-schedule-ai-extract-btn');
    const sv = el('work-schedule-ai-save-btn');
    const bk = el('work-schedule-ai-back-btn');
    const upl = el('work-schedule-ai-upload-btn');
    const cl = el('work-schedule-ai-close-btn');
    const addR = el('work-schedule-ai-add-shift-btn');
    const ded = el('work-schedule-ai-dedupe-btn');
    [ex, sv, bk, upl, cl, addR, ded].forEach(function (b) {
      if (b) b.disabled = !!on;
    });
    const st = el('work-schedule-ai-status');
    if (st) {
      if (on) {
        st.style.display = 'block';
        const label = mode === 'save' ? 'שומר משמרות…' : 'הבינה מנתחת את התמונות…';
        st.innerHTML = thinkingHtml(label);
      } else {
        st.style.display = 'none';
        st.innerHTML = '';
      }
    }
  }

  function showMsg(id, msg, isErr) {
    const box = typeof id === 'string' ? el(id.replace('#', '')) : id;
    if (!box) return;
    if (!msg) {
      box.style.display = 'none';
      box.textContent = '';
      box.classList.remove('work-modal-msg--err', 'work-modal-msg--ok');
      return;
    }
    box.style.display = 'block';
    box.textContent = msg;
    box.style.background = isErr ? '#fee2e2' : '#ecfdf3';
    box.style.color = isErr ? '#b91c1c' : 'var(--main, #047857)';
  }

  function openAiModal() {
    if (!window.__TAZRIM_GEMINI_CONFIGURED__) {
      if (typeof window.tazrimRequireGeminiKey === 'function') {
        window.tazrimRequireGeminiKey();
      } else if (window.tazrimGeminiKeyModal && window.tazrimGeminiKeyModal.open) {
        window.tazrimGeminiKeyModal.open({});
      }
      return;
    }
    releasePreviewUrls();
    state.files = [];
    state.reviewRows = [];
    state.warnings = [];
    state.shiftTypesForJob = [];
    state.existingShifts = [];
    state.importYear = null;
    state.importMonth = null;
    if (el('work-schedule-ai-images')) el('work-schedule-ai-images').value = '';
    renderFilesList();
    populateImportTargets();
    el('work-schedule-ai-step-review').style.display = 'none';
    el('work-schedule-ai-step-input').style.display = 'block';
    showMsg('work-schedule-ai-msg', '', false);
    showMsg('work-schedule-ai-review-msg', '', false);
    el('work-schedule-ai-warnings').style.display = 'none';
    el('work-schedule-ai-warnings').innerHTML = '';
    var dupB = el('work-schedule-ai-dup-banner');
    var coB = el('work-schedule-ai-conflict-banner');
    var dupA = el('work-schedule-ai-dup-actions');
    if (dupB) {
      dupB.style.display = 'none';
      dupB.innerHTML = '';
    }
    if (coB) {
      coB.style.display = 'none';
      coB.innerHTML = '';
    }
    if (dupA) dupA.style.display = 'none';
    var uplive = el('work-schedule-ai-upload-live');
    if (uplive) uplive.textContent = '';

    var m = el('work-schedule-ai-modal');
    if (m) {
      m.style.display = 'block';
      m.setAttribute('aria-hidden', 'false');
      document.body.classList.add('no-scroll');
    }
    setLoading(false, '');
    loadJobsIntoModal();
  }

  function closeAiModal() {
    if (state.loading) return;
    var m = el('work-schedule-ai-modal');
    if (m) {
      m.style.display = 'none';
      m.setAttribute('aria-hidden', 'true');
      document.body.classList.remove('no-scroll');
    }
    releasePreviewUrls();
    state.files = [];
    renderFilesList();
  }

  function releasePreviewUrls() {
    (state.files || []).forEach(function (e) {
      if (e && e.previewUrl && String(e.previewUrl).indexOf('blob:') === 0) {
        try {
          URL.revokeObjectURL(e.previewUrl);
        } catch (err) {}
      }
    });
  }

  function loadJobsIntoModal() {
    postApi('list_jobs', {}).then(function (res) {
      const rows = res && res.ok && Array.isArray(res.data) ? res.data : [];
      state.jobs = rows;
      const wrap = el('work-schedule-ai-job-chips');
      if (!wrap) return;
      if (!rows.length) {
        wrap.innerHTML = '<p class="work-field-hint">אין עבודות — הוסיפו בהגדרות החשבון.</p>';
        state.selectedJobId = '';
        return;
      }
      wrap.innerHTML = rows
        .map(function (j) {
          return (
            '<button type="button" class="work-store-chip work-schedule-ai-job-chip" data-job-id="'
            + escapeHtml(String(j.id))
            + '" style="--work-chip-c:'
            + escapeHtml(j.color || '#5B8DEF')
            + '"><span class="work-store-chip__dot" aria-hidden="true"></span>'
            + '<span class="work-store-chip__name">'
            + escapeHtml(j.title || '')
            + '</span></button>'
          );
        })
        .join('');
      wrap.querySelectorAll('.work-schedule-ai-job-chip').forEach(function (b) {
        b.addEventListener('click', function () {
          const jid = b.getAttribute('data-job-id') || '';
          state.selectedJobId = jid;
          wrap.querySelectorAll('.work-store-chip').forEach(function (x) {
            x.classList.toggle('work-store-chip--sel', x === b);
          });
          var sRev = el('work-schedule-ai-step-review');
          if (sRev && sRev.style.display !== 'none') {
            var ymJob = getImportYm();
            postApi('list_shifts', { year: String(ymJob.yr), month: String(ymJob.mo) }).then(function (lr) {
              state.existingShifts = lr && lr.ok && Array.isArray(lr.data) ? lr.data : [];
              refreshDupConflictUi();
            });
          }
        });
      });
      state.selectedJobId = String(rows[0].id);
      const first = wrap.querySelector('[data-job-id="' + state.selectedJobId + '"]');
      if (first) {
        wrap.querySelectorAll('.work-store-chip').forEach(function (x) {
          x.classList.toggle('work-store-chip--sel', x === first);
        });
      }
    });
  }

  function loadShiftTypesAndMatch(jobId, shiftsPayload) {
    return postApi('list_shift_types', { job_id: jobId }).then(function (res) {
      const list = res && res.ok && Array.isArray(res.data) ? res.data : [];
      state.shiftTypesForJob = list;
      shiftsPayload.forEach(function (row) {
        row.shift_type_id = matchShiftTypeId(row.start_time, row.end_time, list);
      });
      return shiftsPayload;
    });
  }

  function filePreviewSrc(entry) {
    if (!entry) return '';
    if (entry.previewDataUrl) return entry.previewDataUrl;
    if (entry.previewUrl) return entry.previewUrl;
    return '';
  }

  function renderFilesList() {
    const host = el('work-schedule-ai-files-list');
    if (!host) return;
    host.innerHTML = '';
    state.files.forEach(function (entry, i) {
      if (entry && entry.file && !entry.previewDataUrl && !entry.previewUrl) {
        try {
          entry.previewUrl = URL.createObjectURL(entry.file);
        } catch (e) {}
      }
      const chip = document.createElement('div');
      chip.className = 'shopping-recipe-file-chip work-schedule-ai-file-chip';
      const img = document.createElement('img');
      img.alt = 'תצוגה מקדימה';
      img.loading = 'eager';
      img.decoding = 'async';
      const src = filePreviewSrc(entry);
      if (src) img.src = src;
      img.addEventListener(
        'error',
        function () {
          if (!entry || !entry.file || typeof FileReader === 'undefined') return;
          var fr = new FileReader();
          fr.onload = function (ev) {
            var data = ev && ev.target ? ev.target.result : '';
            if (typeof data === 'string' && data.indexOf('data:') === 0) {
              entry.previewDataUrl = data;
              if (entry.previewUrl && String(entry.previewUrl).indexOf('blob:') === 0) {
                try {
                  URL.revokeObjectURL(entry.previewUrl);
                } catch (e2) {}
              }
              entry.previewUrl = null;
              img.src = data;
            }
          };
          try {
            fr.readAsDataURL(entry.file);
          } catch (e3) {}
        },
        { once: true }
      );
      var rm = document.createElement('button');
      rm.type = 'button';
      rm.className = 'shopping-recipe-file-remove work-schedule-ai-file-remove';
      rm.setAttribute('aria-label', 'מחיקת תמונה');
      rm.dataset.idx = String(i);
      rm.innerHTML = '<i class="fa-solid fa-xmark"></i>';
      chip.appendChild(img);
      chip.appendChild(rm);
      host.appendChild(chip);
    });
    host.querySelectorAll('.shopping-recipe-file-remove').forEach(function (btn) {
      btn.addEventListener('click', function () {
        const ix = parseInt(btn.getAttribute('data-idx'), 10);
        if (!isNaN(ix) && state.files[ix]) {
          const pv = state.files[ix].previewUrl;
          if (pv && String(pv).indexOf('blob:') === 0) {
            try {
              URL.revokeObjectURL(pv);
            } catch (e2) {}
          }
          state.files.splice(ix, 1);
          renderFilesList();
        }
      });
    });
  }

  function sortReviewRows() {
    state.reviewRows.sort(function (a, b) {
      const c = String(a.date || '').localeCompare(String(b.date || ''));
      if (c !== 0) return c;
      return timeToMin(a.start_time) - timeToMin(b.start_time);
    });
  }

  function syncReviewRowsFromDomIfOpen() {
    const step = el('work-schedule-ai-step-review');
    if (!step || step.style.display === 'none') return;
    state.reviewRows = collectReviewFromDom();
  }

  function updateReviewSummaryAndThumbs() {
    var sumEl = el('work-schedule-ai-review-summary');
    var n = Array.isArray(state.reviewRows) ? state.reviewRows.length : 0;
    if (sumEl) {
      if (n < 1) {
        sumEl.style.display = 'none';
        sumEl.innerHTML = '';
      } else {
        sumEl.style.display = 'block';
        sumEl.innerHTML =
          '<span class="work-schedule-ai-review-summary__badge">' + escapeHtml(String(n)) + '</span>'
          + ' <span class="work-schedule-ai-review-summary__txt">משמרות ברשימה (ניתן לערוך / להוסיף / למחוק)</span>';
      }
    }
    var th = el('work-schedule-ai-review-thumbs');
    if (!th) return;
    if (!state.files || !state.files.length) {
      th.style.display = 'none';
      th.innerHTML = '';
      return;
    }
    th.style.display = 'flex';
    th.textContent = '';
    state.files.forEach(function (entry, ix) {
      var s = filePreviewSrc(entry);
      if (!s) return;
      var d = document.createElement('div');
      d.className = 'work-schedule-ai-thumb';
      d.title = 'תמונה ' + String(ix + 1);
      var img = document.createElement('img');
      img.alt = '';
      img.loading = 'lazy';
      img.decoding = 'async';
      img.src = s;
      d.appendChild(img);
      th.appendChild(d);
    });
  }

  function pickDefaultInsertDate() {
    syncReviewRowsFromDomIfOpen();
    sortReviewRows();
    if (state.reviewRows.length) return String(state.reviewRows[state.reviewRows.length - 1].date);
    const c = cfg();
    var y =
      state.importYear != null && state.importYear > 0
        ? state.importYear
        : Number(c.year) || new Date().getFullYear();
    var m =
      state.importMonth != null && state.importMonth >= 1 && state.importMonth <= 12
        ? state.importMonth
        : Number(c.month) || new Date().getMonth() + 1;
    return y + '-' + pad(m) + '-01';
  }

  function addEmptyShiftRow() {
    syncReviewRowsFromDomIfOpen();
    const jid = state.selectedJobId;
    const types = state.shiftTypesForJob || [];
    const row = {
      date: pickDefaultInsertDate(),
      start_time: '09:00',
      end_time: '17:00',
      shift_type_id: matchShiftTypeId('09:00', '17:00', types),
      note: '',
    };
    state.reviewRows.push(row);
    sortReviewRows();
    renderReview();
    updateReviewSummaryAndThumbs();
  }

  function typeSelectOptions(selectedId) {
    let html = '<option value="0">ללא סוג</option>';
    state.shiftTypesForJob.forEach(function (t) {
      const sid = String(t.id);
      const sel = sid === selectedId ? ' selected' : '';
      const label =
        escapeHtml(t.name || '') +
        (t.default_start_time && t.default_end_time
          ? ' (' + normalizeHm(t.default_start_time) + '–' + normalizeHm(t.default_end_time) + ')'
          : '');
      html += '<option value="' + escapeHtml(sid) + '"' + sel + '>' + label + '</option>';
    });
    return html;
  }

  function renderReview() {
    const list = el('work-schedule-ai-shifts-list');
    if (!list) return;
    var rowMeta = computeRowDupConflictMeta(state.reviewRows);
    list.innerHTML = state.reviewRows
      .map(function (row, idx) {
        var m = rowMeta[idx] || { isDup: false, hasOverlap: false };
        var rowCls = 'work-schedule-ai-shift-row';
        if (m.isDup) rowCls += ' work-schedule-ai-shift-row--dup';
        if (m.hasOverlap) rowCls += ' work-schedule-ai-shift-row--overlap';
        return (
          '<div class="' + rowCls + '" data-idx="' + idx + '">'
          + '<input type="date" class="work-input work-schedule-ai-inp work-schedule-ai-inp-date" value="'
          + escapeHtml(row.date)
          + '">'
          + '<input type="time" class="work-input work-input--time work-schedule-ai-inp" step="60" value="'
          + escapeHtml(row.start_time)
          + '">'
          + '<input type="time" class="work-input work-input--time work-schedule-ai-inp" step="60" value="'
          + escapeHtml(row.end_time)
          + '">'
          + '<select class="work-input work-schedule-ai-sel">' + typeSelectOptions(String(row.shift_type_id || '0')) + '</select>'
          + '<input type="text" class="work-input work-schedule-ai-inp work-schedule-ai-inp-note" maxlength="500" value="'
          + escapeHtml(row.note || '') + '" placeholder="הערה">'
          + '<button type="button" class="work-schedule-ai-rm-row" aria-label="הסרת שורה" data-del-idx="'
          + idx
          + '"><i class="fa-solid fa-trash"></i></button>'
          + '</div>'
        );
      })
      .join('');

    list.querySelectorAll('.work-schedule-ai-rm-row').forEach(function (b) {
      b.addEventListener('click', function () {
        const ix = parseInt(b.getAttribute('data-del-idx'), 10);
        if (!isNaN(ix)) {
          state.reviewRows.splice(ix, 1);
          renderReview();
          updateReviewSummaryAndThumbs();
        }
      });
    });

    updateReviewSummaryAndThumbs();
    renderDupConflictBanners(rowMeta);
  }

  function collectReviewFromDom() {
    const list = el('work-schedule-ai-shifts-list');
    if (!list) return [];
    const rows = list.querySelectorAll('.work-schedule-ai-shift-row');
    const out = [];
    rows.forEach(function (rowEl) {
      const date = rowEl.querySelector('.work-schedule-ai-inp-date');
      const t1 = rowEl.querySelectorAll('.work-schedule-ai-inp[type="time"]')[0];
      const t2 = rowEl.querySelectorAll('.work-schedule-ai-inp[type="time"]')[1];
      const sel = rowEl.querySelector('.work-schedule-ai-sel');
      const noteEl = rowEl.querySelector('.work-schedule-ai-inp-note');
      const d = date && date.value ? date.value.trim() : '';
      const s = t1 && t1.value ? t1.value.substring(0, 5) : '';
      const e = t2 && t2.value ? t2.value.substring(0, 5) : '';
      const tid = sel && sel.value ? sel.value : '0';
      const note = noteEl ? noteEl.value.trim() : '';
      if (!d || !s || !e) return;
      out.push({
        date: d,
        start_time: s,
        end_time: e,
        shift_type_id: tid,
        note: note,
      });
    });
    return out;
  }

  function extractFromImages() {
    if (state.loading) return;
    const jid = state.selectedJobId;
    if (!jid) {
      showMsg('work-schedule-ai-msg', 'יש לבחור עבודה.', true);
      return;
    }
    const entries = state.files || [];
    if (!entries.length) {
      showMsg('work-schedule-ai-msg', 'יש לצרף לפחות תמונה אחת.', true);
      return;
    }
    var uplive = el('work-schedule-ai-upload-live');
    if (uplive) uplive.textContent = '';
    showMsg('work-schedule-ai-msg', '', false);
    setLoading(true, 'extract');

    const c = cfg();
    var ym0 = getImportYm();
    state.importYear = ym0.yr;
    state.importMonth = ym0.mo;
    const fd = new FormData();
    fd.append('job_id', jid);
    fd.append('year', String(ym0.yr));
    fd.append('month', String(ym0.mo));
    entries.forEach(function (en) {
      if (en && en.file) fd.append('schedule_images[]', en.file);
    });

    var listP = postApi('list_shifts', { year: String(ym0.yr), month: String(ym0.mo) });

    fetch(c.extractScheduleUrl || '', {
      method: 'POST',
      body: fd,
      credentials: 'same-origin',
    })
      .then(function (r) {
        var ct = (r.headers.get('content-type') || '').toLowerCase();
        if (!ct.includes('application/json')) {
          return { status: 'error', message: 'תשובת שרת לא צפויה (לא JSON). נסו שוב.' };
        }
        return r.json().catch(function () {
          return { status: 'error', message: 'לא ניתן לפרש את תשובת השרת.' };
        });
      })
      .then(function (res) {
        return listP.then(function (listRes) {
          return { res: res, listRes: listRes };
        });
      })
      .then(function (pair) {
        var res = pair.res;
        var listRes = pair.listRes;
        state.existingShifts = listRes && listRes.ok && Array.isArray(listRes.data) ? listRes.data : [];

        if (res.code === 'gemini_key_missing') {
          setLoading(false, '');
          if (typeof window.tazrimRequireGeminiKey === 'function') window.tazrimRequireGeminiKey();
          else if (window.tazrimGeminiKeyModal && window.tazrimGeminiKeyModal.open) window.tazrimGeminiKeyModal.open({});
          return;
        }
        if (res.code === 'upload_validation_failed') {
          setLoading(false, '');
          showUploadValidation(
            res.upload_report,
            res.message || 'לא נקלטה אף תמונה תקינה.'
          );
          return;
        }
        if (res.status !== 'success') {
          setLoading(false, '');
          var errBox = el('work-schedule-ai-msg');
          if (Array.isArray(res.upload_report) && res.upload_report.length) {
            showUploadValidation(res.upload_report, res.message || 'חילוץ נכשל.');
          } else if (errBox) {
            errBox.style.display = 'block';
            errBox.textContent = res.message || 'חילוץ נכשל.';
            errBox.style.background = '#fee2e2';
            errBox.style.color = '#b91c1c';
          }
          return;
        }
        var rawList = Array.isArray(res.shifts) ? res.shifts : [];
        if (!rawList.length) {
          setLoading(false, '');
          showMsg('work-schedule-ai-msg', 'לא נמצאו משמרות.', true);
          return;
        }

        state.warnings = Array.isArray(res.warnings) ? res.warnings.slice() : [];
        (Array.isArray(res.upload_report) ? res.upload_report : []).forEach(function (pi) {
          var msg = uploadReportItemMessage(pi);
          if (msg) state.warnings.push('קבצים שהושמטו: ' + msg);
        });
        state.importYear =
          typeof res.year === 'number' && res.year > 0 ? res.year : ym0.yr;
        state.importMonth =
          typeof res.month === 'number' && res.month >= 1 && res.month <= 12 ? res.month : ym0.mo;
        const warnsBox = el('work-schedule-ai-warnings');
        if (warnsBox) {
          if (state.warnings.length) {
            warnsBox.style.display = 'block';
            warnsBox.innerHTML =
              '<strong>שימו לב:</strong><ul><li>'
              + state.warnings.map(escapeHtml).join('</li><li>')
              + '</li></ul>';
          } else {
            warnsBox.style.display = 'none';
            warnsBox.innerHTML = '';
          }
        }

        return loadShiftTypesAndMatch(jid, rawList.slice()).then(function (enriched) {
          state.reviewRows = enriched.slice();
          sortReviewRows();
          showMsg('work-schedule-ai-msg', '', false);
          renderReview();
          updateReviewSummaryAndThumbs();
          refreshDupConflictUi();
          el('work-schedule-ai-step-input').style.display = 'none';
          el('work-schedule-ai-step-review').style.display = 'block';
          setLoading(false, '');
          showMsg('work-schedule-ai-review-msg', '', false);
        });
      })
      .catch(function () {
        setLoading(false, '');
        showMsg('work-schedule-ai-msg', 'שגיאת תקשורת עם השרת.', true);
      });
  }

  function saveBulk() {
    if (state.loading) return;
    syncReviewRowsFromDomIfOpen();
    sortReviewRows();
    const jid = state.selectedJobId;
    if (!jid) {
      showMsg('work-schedule-ai-review-msg', 'אין עבודה נבחרת.', true);
      return;
    }
    if (!state.reviewRows.length) {
      showMsg('work-schedule-ai-review-msg', 'אין משמרות לשמירה.', true);
      return;
    }

    const payload = [];
    for (let i = 0; i < state.reviewRows.length; i++) {
      const row = state.reviewRows[i];
      const computed = computeStartsEndsFromDayAndTimes(row.date, row.start_time, row.end_time);
      if (computed.err) {
        showMsg(
          'work-schedule-ai-review-msg',
          computed.err + ' (שורה ' + (i + 1) + ')',
          true
        );
        return;
      }
      payload.push({
        shift_type_id: row.shift_type_id && row.shift_type_id !== '0' ? row.shift_type_id : '0',
        starts_at: formatMysqlDt(computed.start),
        ends_at: formatMysqlDt(computed.end),
        note: row.note || '',
      });
    }

    setLoading(true, 'save');
    showMsg('work-schedule-ai-review-msg', '', false);

    fetch(cfg().api, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: formEncode({
        action: 'bulk_create_shifts',
        job_id: jid,
        shifts: JSON.stringify(payload),
      }),
      credentials: 'same-origin',
    })
      .then(function (r) {
        return r.json();
      })
      .then(function (res) {
        setLoading(false, '');
        if (!res.ok) {
          showMsg('work-schedule-ai-review-msg', res.message || 'שמירה נכשלה.', true);
          return;
        }
        const n = res.data && typeof res.data.created === 'number' ? res.data.created : payload.length;
        closeAiModal();
        if (typeof window.workScheduleReloadShifts === 'function') {
          window.workScheduleReloadShifts();
        }
        if (typeof window.tazrimAlert === 'function') {
          window.tazrimAlert({
            title: 'נשמר',
            message: 'נוספו ' + String(n) + ' משמרות ללוח.',
          });
        } else {
          alert('נוספו ' + String(n) + ' משמרות.');
        }
      })
      .catch(function () {
        setLoading(false, '');
        showMsg('work-schedule-ai-review-msg', 'שגיאת תקשורת בשמירה.', true);
      });
  }

  function initIfPresent() {
    const modalRoot = el('work-schedule-ai-modal');
    if (!modalRoot) return;

    const openBtn = el('work-schedule-ai-open-btn');
    if (openBtn) openBtn.addEventListener('click', openAiModal);

    var closeAi = el('work-schedule-ai-close-btn');
    if (closeAi) closeAi.addEventListener('click', closeAiModal);
    modalRoot.addEventListener('click', function (e) {
      if (e.target === modalRoot) closeAiModal();
    });
    var uplBt = el('work-schedule-ai-upload-btn');
    var imgInp = el('work-schedule-ai-images');
    if (uplBt && imgInp) {
      uplBt.addEventListener('click', function () {
        imgInp.click();
      });
      imgInp.addEventListener('change', function () {
        var fl = imgInp.files ? imgInp.files : [];
        var maxMore = Math.max(0, 8 - state.files.length);
        var picked = [];
        for (let i = 0; i < fl.length && picked.length < maxMore; i++) {
          picked.push(fl[i]);
        }
        imgInp.value = '';
        if (!picked.length) return;

        var pending = picked.length;

        picked.forEach(function (file) {
          var entry = { file: file, previewUrl: null, previewDataUrl: '' };
          state.files.push(entry);
          if (typeof FileReader === 'undefined') {
            pending--;
            if (pending === 0) {
              renderFilesList();
              showMsg('work-schedule-ai-msg', '', false);
            }
            return;
          }
          var fr = new FileReader();
          fr.onload = function (ev) {
            var data = ev && ev.target ? ev.target.result : '';
            if (typeof data === 'string' && data.indexOf('data:') === 0) {
              entry.previewDataUrl = data;
            }
            pending--;
            if (pending === 0) {
              renderFilesList();
              showMsg('work-schedule-ai-msg', '', false);
            }
          };
          fr.onerror = function () {
            pending--;
            if (pending === 0) {
              renderFilesList();
              showMsg('work-schedule-ai-msg', '', false);
            }
          };
          try {
            fr.readAsDataURL(file);
          } catch (e) {
            pending--;
            if (pending === 0) {
              renderFilesList();
              showMsg('work-schedule-ai-msg', '', false);
            }
          }
        });
      });
    }
    var extBt = el('work-schedule-ai-extract-btn');
    if (extBt) extBt.addEventListener('click', extractFromImages);
    var backBt = el('work-schedule-ai-back-btn');
    if (backBt) {
      backBt.addEventListener('click', function () {
        if (state.loading) return;
        var s1 = el('work-schedule-ai-step-review');
        var s0 = el('work-schedule-ai-step-input');
        if (s1) s1.style.display = 'none';
        if (s0) s0.style.display = 'block';
        showMsg('work-schedule-ai-review-msg', '', false);
      });
    }
    var saveBt = el('work-schedule-ai-save-btn');
    if (saveBt) saveBt.addEventListener('click', saveBulk);

    var addShiftBt = el('work-schedule-ai-add-shift-btn');
    if (addShiftBt) addShiftBt.addEventListener('click', addEmptyShiftRow);

    var dedupeBt = el('work-schedule-ai-dedupe-btn');
    if (dedupeBt) dedupeBt.addEventListener('click', dedupeReviewRowsConfirmed);

    var shiftList = el('work-schedule-ai-shifts-list');
    if (shiftList && shiftList.dataset.dupOvBound !== '1') {
      shiftList.dataset.dupOvBound = '1';
      shiftList.addEventListener('change', scheduleRefreshDupConflictUi);
      shiftList.addEventListener('input', scheduleRefreshDupConflictUi);
    }

    document.addEventListener('keydown', function (e) {
      if (e.key !== 'Escape') return;
      const m = el('work-schedule-ai-modal');
      if (!m || m.style.display !== 'block') return;
      closeAiModal();
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initIfPresent);
  } else initIfPresent();

  window.tazrimOpenWorkScheduleAiImport = openAiModal;
})();
