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

    ackBtn.addEventListener('click', function () {
        if (idx >= queue.length) return;
        var cid = queue[idx].id;
        ackBtn.disabled = true;
        sendAck(cid)
            .then(function (data) {
                ackBtn.disabled = false;
                if (data.status !== 'ok') return;
                idx += 1;
                showCurrent();
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
