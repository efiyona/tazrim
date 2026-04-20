<?php
/**
 * פופאפי מערכת מנוהלים — עיצוב כמו tazrim-app-dialog (ללא כפתור סגירה / ביטול).
 */
if (!defined('BASE_URL')) {
    require_once dirname(__DIR__, 2) . '/path.php';
}
?>
<div id="system-popup-overlay" class="modal tazrim-app-dialog tazrim-app-dialog--alert tazrim-system-popup" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="system-popup-title">
    <div class="modal-content tazrim-app-dialog__content">
        <div class="tazrim-app-dialog__hero">
            <div id="system-popup-stepper" class="tazrim-system-popup__stepper" aria-hidden="true"></div>
            <div class="tazrim-app-dialog__icon-wrap tazrim-app-dialog__icon-wrap--main" aria-hidden="true">
                <i class="fa-solid fa-bell"></i>
            </div>
        </div>
        <div class="modal-body tazrim-app-dialog__body">
            <h3 id="system-popup-title" class="tazrim-app-dialog__title"></h3>
            <div id="system-popup-body" class="tazrim-system-popup__html"></div>
            <div class="tazrim-app-dialog__actions">
                <button type="button" id="system-popup-ack" class="btn-primary tazrim-app-dialog__btn tazrim-app-dialog__btn--ok">קראתי</button>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    var base = <?php echo json_encode(rtrim(BASE_URL, '/') . '/', JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
    var overlay = document.getElementById('system-popup-overlay');
    var titleEl = document.getElementById('system-popup-title');
    var bodyEl = document.getElementById('system-popup-body');
    var stepperEl = document.getElementById('system-popup-stepper');
    var ackBtn = document.getElementById('system-popup-ack');
    if (!overlay || !titleEl || !bodyEl || !ackBtn) return;

    var queue = [];
    var idx = 0;

    function renderStepper() {
        if (!stepperEl) return;
        var n = queue.length;
        if (n <= 1) {
            stepperEl.innerHTML = '';
            stepperEl.setAttribute('aria-hidden', 'true');
            return;
        }
        stepperEl.setAttribute('aria-hidden', 'false');
        var html = '';
        for (var i = 0; i < n; i++) {
            html += '<span class="system-popup-dot' + (i === idx ? ' active' : '') + '"></span>';
        }
        stepperEl.innerHTML = html;
    }

    function showCurrent() {
        if (idx >= queue.length || queue.length === 0) {
            overlay.style.display = 'none';
            overlay.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('no-scroll');
            return;
        }
        var c = queue[idx];
        titleEl.textContent = c.title || '';
        bodyEl.innerHTML = c.body_html || '';
        renderStepper();
        overlay.style.display = 'block';
        overlay.setAttribute('aria-hidden', 'false');
        try {
            ackBtn.focus();
        } catch (e) {}
    }

    function load() {
        fetch(base + 'app/ajax/popup_campaigns_pending.php', { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.status !== 'ok' || !data.campaigns || data.campaigns.length === 0) {
                    return;
                }
                queue = data.campaigns;
                idx = 0;
                showCurrent();
            })
            .catch(function () {});
    }

    function sendAck(campaignId) {
        return fetch(base + 'app/ajax/popup_campaign_ack.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ campaign_id: campaignId })
        }).then(function (r) { return r.json(); });
    }

    function advanceAfterCampaignStep() {
        idx += 1;
        showCurrent();
    }

    /**
     * אוסף ערכי שדות עם name מתוך הטופס/גוף הפופאפ — נשלח לשרת יחד עם data-tazrim-popup-action.
     * פעולות נרשמות ב-PHP (whitelist); שדות חופשיים ב-HTML, היעד למסד נקבע ב-handler.
     */
    function collectNamedFieldValues(container) {
        var out = {};
        if (!container || !container.querySelectorAll) return out;
        var els = container.querySelectorAll('input[name], select[name], textarea[name]');
        for (var i = 0; i < els.length; i++) {
            var el = els[i];
            var n = el.name;
            if (!n || el.disabled) continue;
            var t = (el.type || '').toLowerCase();
            if (t === 'file') continue;
            if (t === 'checkbox') {
                out[n] = el.checked ? (el.value || '1') : '';
            } else if (t === 'radio') {
                if (el.checked) out[n] = el.value;
            } else {
                out[n] = el.value;
            }
        }
        return out;
    }

    function handlePopupCampaignAction(triggerEl, actionName) {
        if (idx >= queue.length) return;
        var cid = queue[idx].id;
        var container = triggerEl && triggerEl.closest ? triggerEl.closest('.tazrim-system-popup__html') : null;
        if (!container) container = bodyEl;
        var fields = collectNamedFieldValues(container);
        var payload = { campaign_id: cid, action: actionName };
        for (var key in fields) {
            if (Object.prototype.hasOwnProperty.call(fields, key)) {
                payload[key] = fields[key];
            }
        }
        var prevDisabled = ackBtn.disabled;
        ackBtn.disabled = true;
        if (triggerEl && triggerEl.disabled !== undefined) triggerEl.disabled = true;
        fetch(base + 'app/ajax/popup_campaign_action.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                ackBtn.disabled = prevDisabled;
                if (triggerEl && triggerEl.disabled !== undefined) triggerEl.disabled = false;
                if (data.status !== 'ok') {
                    window.alert(data.message || 'לא ניתן לשמור');
                    return;
                }
                advanceAfterCampaignStep();
            })
            .catch(function () {
                ackBtn.disabled = prevDisabled;
                if (triggerEl && triggerEl.disabled !== undefined) triggerEl.disabled = false;
            });
    }

    bodyEl.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form || !form.getAttribute || form.tagName !== 'FORM') return;
        var act = form.getAttribute('data-tazrim-popup-action');
        if (!act || !String(act).trim()) return;
        e.preventDefault();
        e.stopPropagation();
        handlePopupCampaignAction(form, String(act).trim());
    });

    bodyEl.addEventListener('click', function (e) {
        var actEl = e.target.closest('[data-tazrim-popup-action]');
        if (!actEl) return;
        var act = actEl.getAttribute('data-tazrim-popup-action');
        if (!act || !String(act).trim()) return;
        if (actEl.tagName === 'A') return;
        e.preventDefault();
        e.stopPropagation();
        handlePopupCampaignAction(actEl, String(act).trim());
    });

    ackBtn.addEventListener('click', function () {
        if (idx >= queue.length) return;
        var cid = queue[idx].id;
        ackBtn.disabled = true;
        sendAck(cid)
            .then(function (data) {
                ackBtn.disabled = false;
                if (data.status !== 'ok') return;
                advanceAfterCampaignStep();
            })
            .catch(function () {
                ackBtn.disabled = false;
            });
    });

    bodyEl.addEventListener('click', function (e) {
        var link = e.target.closest('a[href]');
        if (!link) return;
        var href = link.getAttribute('href');
        if (!href || href === '#') return;
        e.preventDefault();
        if (idx >= queue.length) { window.location.href = href; return; }
        var cid = queue[idx].id;
        sendAck(cid)
            .then(function () { window.location.href = href; })
            .catch(function () { window.location.href = href; });
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', load);
    } else {
        load();
    }
})();
</script>
