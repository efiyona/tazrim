/**
 * ניהול עבודות / סוגי משמרת — עמוד החשבון שלי
 */
(function () {
  'use strict';

  var C = (typeof TAZRIM_USER_WORK === 'object' && TAZRIM_USER_WORK) ? TAZRIM_USER_WORK : { api: '', panelUrl: '' };

  function fe(data) {
    return Object.keys(data).map(function (k) {
      return encodeURIComponent(k) + '=' + encodeURIComponent(data[k] == null ? '' : String(data[k]));
    }).join('&');
  }
  function postAction(action, extra) {
    return fetch(C.api, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: fe(Object.assign({ action: action }, extra || {})),
      credentials: 'same-origin',
    }).then(function (r) { return r.json(); });
  }

  function upWorkRefreshPanel() {
    var w = document.getElementById('user-profile-work-panel-wrap');
    if (!w || !C.panelUrl) return;
    w.style.opacity = '0.55';
    w.style.pointerEvents = 'none';
    fetch(C.panelUrl, { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data.ok && typeof data.html === 'string') w.innerHTML = data.html;
        else if (window.tazrimAlert) tazrimAlert({ title: 'שגיאה', message: 'לא ניתן לרענן את הרשימה.' });
      })
      .catch(function () {
        if (window.tazrimAlert) tazrimAlert({ title: 'שגיאה', message: 'שגיאת תקשורת.' });
      })
      .finally(function () {
        w.style.opacity = '';
        w.style.pointerEvents = '';
      });
  }

  function showMsg(elId, text, isErr) {
    var b = document.getElementById(elId);
    if (!b) return;
    b.style.display = 'block';
    b.textContent = text;
    b.style.backgroundColor = isErr ? '#fee2e2' : '#dcfce7';
    b.style.color = isErr ? 'var(--error, #b91c1c)' : '#166534';
  }
  function hideMsg(elId) {
    var b = document.getElementById(elId);
    if (b) b.style.display = 'none';
  }

  window.upWorkOpenAddJob = function () {
    var m = document.getElementById('up-work-job-modal');
    var h = document.getElementById('up-work-job-h');
    if (h) h.textContent = 'עבודה חדשה';
    document.getElementById('up-work-job-id').value = '';
    document.getElementById('up-work-job-title').value = '';
    document.getElementById('up-work-job-payday').value = '10';
    var first = document.querySelector('#up-work-job-palette .up-work-pal');
    if (first) {
      var c = first.getAttribute('data-color') || '#5B8DEF';
      document.getElementById('up-work-job-color').value = c;
      document.querySelectorAll('#up-work-job-palette .up-work-pal').forEach(function (x) {
        x.classList.remove('work-palette-swatch--selected');
        x.setAttribute('aria-pressed', 'false');
      });
      first.classList.add('work-palette-swatch--selected');
      first.setAttribute('aria-pressed', 'true');
    }
    hideMsg('up-work-job-msg');
    if (m) { m.style.display = 'block'; m.setAttribute('aria-hidden', 'false'); }
  };
  window.upWorkCloseJobModal = function () {
    var m = document.getElementById('up-work-job-modal');
    if (m) { m.style.display = 'none'; m.setAttribute('aria-hidden', 'true'); }
  };
  window.upWorkOpenEditJob = function (id, title, color, payday) {
    var m = document.getElementById('up-work-job-modal');
    var h = document.getElementById('up-work-job-h');
    if (h) h.textContent = 'עריכת עבודה';
    document.getElementById('up-work-job-id').value = String(id);
    document.getElementById('up-work-job-title').value = title || '';
    document.getElementById('up-work-job-payday').value = String(payday || 10);
    document.getElementById('up-work-job-color').value = color || '#5B8DEF';
    document.querySelectorAll('#up-work-job-palette .up-work-pal').forEach(function (x) {
      var c = x.getAttribute('data-color');
      x.classList.toggle('work-palette-swatch--selected', c === color);
      x.setAttribute('aria-pressed', c === color ? 'true' : 'false');
    });
    hideMsg('up-work-job-msg');
    if (m) { m.style.display = 'block'; m.setAttribute('aria-hidden', 'false'); }
  };
  window.upWorkSaveJob = function () {
    var id = document.getElementById('up-work-job-id').value;
    var title = (document.getElementById('up-work-job-title') || {}).value || '';
    var color = (document.getElementById('up-work-job-color') || {}).value || '#5B8DEF';
    var pd = (document.getElementById('up-work-job-payday') || {}).value || '1';
    if (!title.trim()) { showMsg('up-work-job-msg', 'נא שם עבודה', true); return; }
    var btn = document.getElementById('up-work-job-save');
    if (btn) { btn.disabled = true; }
    var p;
    if (id) p = postAction('update_job', { id: id, title: title.trim(), color: color, payday_day_of_month: String(parseInt(pd, 10) || 1) });
    else p = postAction('create_job', { title: title.trim(), color: color, payday_day_of_month: String(parseInt(pd, 10) || 1), sort_order: '0' });
    p.then(function (res) {
      if (res && res.ok) {
        upWorkCloseJobModal();
        upWorkRefreshPanel();
        return;
      }
      showMsg('up-work-job-msg', (res && res.message) || 'שגיאה', true);
    }).finally(function () { if (btn) btn.disabled = false; });
  };
  window.upWorkDeleteJob = function (id) {
    if (!id) return;
    function runDelete(idd) {
      postAction('delete_job', { id: idd }).then(function (res) {
        if (res && res.ok) upWorkRefreshPanel();
        else if (window.tazrimAlert) tazrimAlert({ title: 'שגיאה', message: (res && res.message) || '' });
      });
    }
    if (window.tazrimConfirm) {
      window.tazrimConfirm({
        title: 'מחיקת עבודה',
        message: 'יימחקו כל המשמרות וסוגי המשמרות. להמשיך?',
        confirmText: 'מחק', cancelText: 'ביטול', danger: true,
      }).then(function (ok) { if (ok) runDelete(String(id)); });
    } else if (window.confirm('למחוק עבודה?')) { runDelete(String(id)); }
  };
  (function jobPal() {
    var pal = document.getElementById('up-work-job-palette');
    if (!pal) return;
    pal.addEventListener('click', function (e) {
      var b = e.target.closest('.up-work-pal');
      if (!b) return;
      var c = b.getAttribute('data-color');
      document.querySelectorAll('#up-work-job-palette .up-work-pal').forEach(function (x) {
        x.classList.remove('work-palette-swatch--selected');
        x.setAttribute('aria-pressed', 'false');
      });
      b.classList.add('work-palette-swatch--selected');
      b.setAttribute('aria-pressed', 'true');
      var h = document.getElementById('up-work-job-color');
      if (h && c) h.value = c;
    });
  })();

  function setTypePreset(p) {
    var h = document.getElementById('up-work-type-preset');
    if (h) h.value = p || 'morning';
    document.querySelectorAll('#up-work-type-preset-row .work-preset-ic-btn').forEach(function (b) {
      b.classList.toggle('work-preset-ic-btn--sel', (b.getAttribute('data-preset') || '') === (p || 'morning'));
    });
  }
  (function typePreset() {
    var row = document.getElementById('up-work-type-preset-row');
    if (!row) return;
    row.addEventListener('click', function (e) {
      var b = e.target.closest('.work-preset-ic-btn');
      if (!b) return;
      setTypePreset(b.getAttribute('data-preset'));
    });
  })();

  window.upWorkOpenAddType = function (jobId) {
    var m = document.getElementById('up-work-type-modal');
    var h = document.getElementById('up-work-type-h');
    if (h) h.textContent = 'סוג משמרת חדש';
    document.getElementById('up-work-type-id').value = '';
    document.getElementById('up-work-type-sort').value = '0';
    document.getElementById('up-work-type-job-id').value = String(jobId);
    document.getElementById('up-work-type-name').value = '';
    document.getElementById('up-work-type-t1').value = '';
    document.getElementById('up-work-type-t2').value = '';
    setTypePreset('morning');
    document.getElementById('up-work-type-del').style.display = 'none';
    hideMsg('up-work-type-msg');
    if (m) { m.style.display = 'block'; m.setAttribute('aria-hidden', 'false'); }
  };
  window.upWorkCloseTypeModal = function () {
    var m = document.getElementById('up-work-type-modal');
    if (m) { m.style.display = 'none'; m.setAttribute('aria-hidden', 'true'); }
  };
  window.upWorkOpenEditType = function (id, jobId, name, preset, t1, t2, sortOrder) {
    var m = document.getElementById('up-work-type-modal');
    var h = document.getElementById('up-work-type-h');
    if (h) h.textContent = 'עריכת סוג משמרת';
    document.getElementById('up-work-type-id').value = String(id);
    document.getElementById('up-work-type-job-id').value = String(jobId);
    document.getElementById('up-work-type-sort').value = (sortOrder != null) ? String(sortOrder) : '0';
    document.getElementById('up-work-type-name').value = name || '';
    document.getElementById('up-work-type-t1').value = t1 || '';
    document.getElementById('up-work-type-t2').value = t2 || '';
    setTypePreset(preset || 'morning');
    document.getElementById('up-work-type-del').style.display = '';
    hideMsg('up-work-type-msg');
    if (m) { m.style.display = 'block'; m.setAttribute('aria-hidden', 'false'); }
  };
  window.upWorkSaveType = function () {
    var tid = document.getElementById('up-work-type-id').value;
    var jid = document.getElementById('up-work-type-job-id').value;
    var name = (document.getElementById('up-work-type-name') || {}).value || '';
    var t1 = (document.getElementById('up-work-type-t1') || {}).value || '';
    var t2 = (document.getElementById('up-work-type-t2') || {}).value || '';
    var pr = (document.getElementById('up-work-type-preset') || {}).value || 'morning';
    if (!name.trim()) { showMsg('up-work-type-msg', 'נא שם', true); return; }
    var btn = document.getElementById('up-work-type-save');
    if (btn) btn.disabled = true;
    if (tid) {
      var so = (document.getElementById('up-work-type-sort') || {}).value || '0';
      postAction('update_shift_type', {
        id: tid, name: name.trim(), icon_preset: pr, icon_class: '', default_start_time: t1, default_end_time: t2, sort_order: so,
      }).then(fin);
    } else {
      postAction('create_shift_type', {
        job_id: jid, name: name.trim(), icon_preset: pr, icon_class: '', default_start_time: t1, default_end_time: t2, sort_order: '99',
      }).then(fin);
    }
    function fin(res) {
      if (res && res.ok) {
        upWorkCloseTypeModal();
        upWorkRefreshPanel();
      } else showMsg('up-work-type-msg', (res && res.message) || 'שגיאה', true);
      if (btn) btn.disabled = false;
    }
  };
  window.upWorkDeleteType = function () {
    var id = document.getElementById('up-work-type-id').value;
    if (!id) return;
    var run = function () {
      postAction('delete_shift_type', { id: id }).then(function (res) {
        if (res && res.ok) { upWorkCloseTypeModal(); upWorkRefreshPanel(); }
        else if (window.tazrimAlert) tazrimAlert({ title: 'שגיאה', message: (res && res.message) || '' });
      });
    };
    if (window.tazrimConfirm) {
      window.tazrimConfirm({ title: 'מחיקת סוג', message: 'למחוק סוג משמרת?', confirmText: 'מחק', danger: true })
        .then(function (o) { if (o) run(); });
    } else { if (window.confirm('למחוק?')) run(); }
  };

  document.addEventListener('DOMContentLoaded', function () {
    var jm = document.getElementById('up-work-job-modal');
    if (jm) jm.addEventListener('click', function (e) { if (e.target === jm) window.upWorkCloseJobModal(); });
    var tm = document.getElementById('up-work-type-modal');
    if (tm) tm.addEventListener('click', function (e) { if (e.target === tm) window.upWorkCloseTypeModal(); });
    var wrap = document.getElementById('user-profile-work-panel-wrap');
    if (wrap) {
      wrap.addEventListener('keydown', function (e) {
        var m = e.target.closest('.user-profile-work-type__main[role="button"]');
        if (!m || (e.key !== 'Enter' && e.key !== ' ')) return;
        e.preventDefault();
        m.click();
      });
    }
  });
})();
