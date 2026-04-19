/**
 * פופאפ אישור/התראה גלובלי (תחליף ל־confirm / alert).
 *
 * tazrimConfirm({ title, message, confirmText, cancelText, danger }) -> Promise<boolean>
 * tazrimAlert({ title, message, okText }) -> Promise<void>
 */
(function () {
    'use strict';

    var MODAL_ID = 'tazrim-app-dialog';
    var resolvePending = null;
    var mode = 'confirm';
    var keyHandler = null;

    function updateBodyScrollLock() {
        var open = false;
        document.querySelectorAll('.modal').forEach(function (m) {
            if (m.style.display === 'block') open = true;
        });
        document.body.classList.toggle('no-scroll', open);
    }

    function detachKeyHandler() {
        if (keyHandler) {
            document.removeEventListener('keydown', keyHandler);
            keyHandler = null;
        }
    }

    function closeDialog(confirmed) {
        var modal = document.getElementById(MODAL_ID);
        if (modal) modal.style.display = 'none';
        detachKeyHandler();
        var fn = resolvePending;
        resolvePending = null;
        updateBodyScrollLock();
        if (!fn) return;
        if (mode === 'alert') fn();
        else fn(confirmed === true);
    }

    function attachKeyHandler() {
        detachKeyHandler();
        keyHandler = function (e) {
            if (e.key === 'Escape') {
                e.preventDefault();
                if (mode === 'alert') closeDialog(true);
                else closeDialog(false);
            }
        };
        document.addEventListener('keydown', keyHandler);
    }

    function ensureMounted() {
        var existing = document.getElementById(MODAL_ID);
        if (existing && existing.getAttribute('data-dialog-layout') === '2') return;
        if (existing) existing.remove();

        document.body.insertAdjacentHTML(
            'beforeend',
            '<div id="' +
                MODAL_ID +
                '" class="modal tazrim-app-dialog" role="dialog" aria-modal="true" aria-labelledby="tazrim-app-dialog-title" data-dialog-layout="2" style="display:none;">' +
                '<div class="modal-content tazrim-app-dialog__content">' +
                '<div class="tazrim-app-dialog__hero">' +
                '<button type="button" class="close-modal-btn tazrim-app-dialog__close" id="tazrim-app-dialog-x" aria-label="סגור" title="סגור">' +
                '<i class="fa-solid fa-xmark" aria-hidden="true"></i></button>' +
                '<div class="tazrim-app-dialog__icon-wrap" id="tazrim-app-dialog-icon-wrap" aria-hidden="true">' +
                '<i class="fa-solid fa-triangle-exclamation" id="tazrim-app-dialog-icon"></i>' +
                '</div>' +
                '</div>' +
                '<div class="modal-body tazrim-app-dialog__body">' +
                '<h3 id="tazrim-app-dialog-title" class="tazrim-app-dialog__title"></h3>' +
                '<p id="tazrim-app-dialog-message" class="tazrim-app-dialog__message"></p>' +
                '<div class="tazrim-app-dialog__actions">' +
                '<button type="button" class="tazrim-app-dialog__btn tazrim-app-dialog__btn--cancel" id="tazrim-app-dialog-cancel">ביטול</button>' +
                '<button type="button" class="btn-primary tazrim-app-dialog__btn tazrim-app-dialog__btn--ok" id="tazrim-app-dialog-ok">אישור</button>' +
                '</div>' +
                '</div>' +
                '</div>' +
                '</div>'
        );

        var modal = document.getElementById(MODAL_ID);
        document.getElementById('tazrim-app-dialog-ok').addEventListener('click', function () {
            closeDialog(true);
        });
        document.getElementById('tazrim-app-dialog-cancel').addEventListener('click', function () {
            closeDialog(false);
        });
        document.getElementById('tazrim-app-dialog-x').addEventListener('click', function () {
            closeDialog(mode === 'alert' ? true : false);
        });
        modal.addEventListener('click', function (e) {
            if (e.target === modal) {
                closeDialog(mode === 'alert' ? true : false);
            }
        });
    }

    function setDialogIcon(kind) {
        var wrap = document.getElementById('tazrim-app-dialog-icon-wrap');
        var icon = document.getElementById('tazrim-app-dialog-icon');
        if (!wrap || !icon) return;
        wrap.classList.remove(
            'tazrim-app-dialog__icon-wrap--main',
            'tazrim-app-dialog__icon-wrap--notice',
            'tazrim-app-dialog__icon-wrap--error'
        );
        if (kind === 'alert') {
            wrap.classList.add('tazrim-app-dialog__icon-wrap--notice');
            icon.className = 'fa-solid fa-triangle-exclamation';
        } else if (kind === 'confirm-danger') {
            wrap.classList.add('tazrim-app-dialog__icon-wrap--error');
            icon.className = 'fa-solid fa-triangle-exclamation';
        } else {
            wrap.classList.add('tazrim-app-dialog__icon-wrap--main');
            icon.className = 'fa-solid fa-circle-info';
        }
    }

    window.tazrimConfirm = function (options) {
        options = options || {};
        ensureMounted();
        return new Promise(function (resolve) {
            var modal = document.getElementById(MODAL_ID);
            var titleEl = document.getElementById('tazrim-app-dialog-title');
            var messageEl = document.getElementById('tazrim-app-dialog-message');
            var btnOk = document.getElementById('tazrim-app-dialog-ok');
            var btnCancel = document.getElementById('tazrim-app-dialog-cancel');

            mode = 'confirm';
            resolvePending = resolve;

            setDialogIcon(options.danger ? 'confirm-danger' : 'confirm');

            titleEl.textContent = options.title || 'לאשר פעולה';
            messageEl.textContent = options.message || '';
            messageEl.style.whiteSpace = 'pre-line';

            btnOk.textContent = options.confirmText || 'אישור';
            btnCancel.textContent = options.cancelText || 'ביטול';
            btnCancel.style.display = '';

            if (options.danger) btnOk.classList.add('tazrim-app-dialog__btn--danger');
            else btnOk.classList.remove('tazrim-app-dialog__btn--danger');

            modal.classList.remove('tazrim-app-dialog--alert');
            modal.classList.add('tazrim-app-dialog--confirm');

            modal.style.display = 'block';
            updateBodyScrollLock();
            attachKeyHandler();
            setTimeout(function () {
                btnOk.focus();
            }, 50);
        });
    };

    window.tazrimAlert = function (options) {
        options = options || {};
        ensureMounted();
        return new Promise(function (resolve) {
            var modal = document.getElementById(MODAL_ID);
            var titleEl = document.getElementById('tazrim-app-dialog-title');
            var messageEl = document.getElementById('tazrim-app-dialog-message');
            var btnOk = document.getElementById('tazrim-app-dialog-ok');
            var btnCancel = document.getElementById('tazrim-app-dialog-cancel');

            mode = 'alert';
            resolvePending = resolve;

            setDialogIcon('alert');

            titleEl.textContent = options.title || 'הודעה';
            messageEl.textContent = options.message || '';
            messageEl.style.whiteSpace = 'pre-line';

            btnOk.textContent = options.okText || options.buttonText || 'הבנתי';
            btnOk.classList.remove('tazrim-app-dialog__btn--danger');
            btnCancel.style.display = 'none';

            modal.classList.remove('tazrim-app-dialog--confirm');
            modal.classList.add('tazrim-app-dialog--alert');

            modal.style.display = 'block';
            updateBodyScrollLock();
            attachKeyHandler();
            setTimeout(function () {
                btnOk.focus();
            }, 50);
        });
    };
})();
