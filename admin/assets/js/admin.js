(function () {
    var root = document.getElementById('admin-app');
    if (!root) return;

    var tableKey = root.getAttribute('data-table-key');
    var csrf = root.getAttribute('data-csrf');
    var baseUrl = root.getAttribute('data-base-url') || '/';
    var allowDelete = root.getAttribute('data-allow-delete') === '1';
    var listOnly = root.getAttribute('data-list-only') === '1';
    var bulkDelete = root.getAttribute('data-bulk-delete') === '1';
    var editId = root.getAttribute('data-edit-id');
    editId = editId ? parseInt(editId, 10) : 0;

    var flashEl = document.getElementById('admin-flash');
    var tbody = document.getElementById('admin-tbody');
    var theadRow = document.getElementById('admin-thead-row');
    var pagTop = document.getElementById('admin-pagination-top');
    var pagBottom = document.getElementById('admin-pagination-bottom');
    var form = document.getElementById('admin-entity-form');
    var saveBtn = document.getElementById('admin-save-btn');
    var delBtn = document.getElementById('admin-delete-btn');
    var bulkBar = document.getElementById('admin-bulk-bar');
    var bulkBtn = document.getElementById('admin-bulk-delete-btn');
    var bulkCountEl = document.getElementById('admin-bulk-count');
    var topbarSearch = document.getElementById('admin-topbar-search');
    var modalEl = document.getElementById('admin-entity-modal');
    var formFields = document.getElementById('admin-form-fields');
    var modalTitle = document.getElementById('admin-entity-modal-title');
    var modalCloseBtn = document.getElementById('admin-modal-close-btn');
    var cancelBtn = document.getElementById('admin-cancel-btn');
    var modalShouldOpen = root.getAttribute('data-modal-open') === '1';

    var state = { page: 1, total: 0, perPage: 20, columns: [], q: '' };

    function confirmDelete(message, title) {
        if (typeof window.tazrimConfirm === 'function') {
            return window.tazrimConfirm({
                title: title || 'אישור מחיקה',
                message: message,
                danger: true,
                confirmText: 'מחק',
                cancelText: 'ביטול',
            });
        }
        return Promise.resolve(window.confirm(message));
    }

    function showFlash(ok, msg) {
        if (!flashEl) return;
        flashEl.textContent = msg;
        flashEl.setAttribute('role', 'status');
        flashEl.classList.remove('hidden');
        flashEl.className =
            'mb-4 px-4 py-3 rounded-lg text-sm font-semibold border ' +
            (ok
                ? 'bg-green-100 text-green-800 border-green-200'
                : 'bg-red-100 text-red-800 border-red-200');
    }

    function apiUrl(path) {
        return baseUrl.replace(/\/?$/, '/') + path.replace(/^\//, '');
    }

    function debounce(fn, ms) {
        var t = null;
        return function () {
            var args = arguments;
            clearTimeout(t);
            t = setTimeout(function () {
                fn.apply(null, args);
            }, ms);
        };
    }

    function closeModal() {
        if (!modalEl) return;
        modalEl.classList.add('hidden');
        modalEl.setAttribute('aria-hidden', 'true');
        if (formFields) formFields.innerHTML = '';
        if (modalTitle) modalTitle.textContent = '';
        if (delBtn) delBtn.classList.add('hidden');
        editId = 0;
        window.history.replaceState({}, '', apiUrl('admin/table.php?t=' + encodeURIComponent(tableKey)));
    }

    function openModal() {
        if (!modalEl) return;
        modalEl.classList.remove('hidden');
        modalEl.setAttribute('aria-hidden', 'false');
    }

    function loadForm(mode, id) {
        if (!modalEl || !formFields) return Promise.resolve();
        openModal();
        formFields.innerHTML = '<div class="admin-form-loading">טוען טופס...</div>';
        if (modalTitle) {
            modalTitle.textContent = mode === 'update' ? 'טוען עריכה...' : 'טוען הוספה...';
        }
        var url = apiUrl('admin/ajax/crud/form.php?t=' + encodeURIComponent(tableKey));
        if (mode === 'update' && id) {
            url += '&id=' + encodeURIComponent(String(id));
        }
        return fetch(url, { credentials: 'same-origin' })
            .then(function (r) {
                return r.json();
            })
            .then(function (data) {
                if (data.status !== 'ok') {
                    showFlash(false, data.message || 'שגיאה בטעינת הטופס');
                    closeModal();
                    return;
                }
                editId = data.mode === 'update' ? parseInt(data.id, 10) || 0 : 0;
                if (modalTitle) modalTitle.textContent = data.title || '';
                formFields.innerHTML = data.fields_html || '';
                if (delBtn) delBtn.classList.toggle('hidden', !data.allow_delete);
                if (typeof window.tazrimAdminInitFkLookups === 'function') {
                    window.tazrimAdminInitFkLookups(formFields);
                }
                var newUrl = apiUrl(
                    'admin/table.php?t=' +
                        encodeURIComponent(tableKey) +
                        (editId > 0 ? '&id=' + encodeURIComponent(String(editId)) : '&create=1')
                );
                window.history.replaceState({}, '', newUrl);
            })
            .catch(function () {
                showFlash(false, 'שגיאת תקשורת');
                closeModal();
            });
    }

    function deleteOne(id, options) {
        options = options || {};
        return confirmDelete('למחוק רשומה זו? פעולה בלתי הפיכה.', 'מחיקת רשומה').then(function (ok) {
            if (!ok) return false;
            return fetch(apiUrl('admin/ajax/crud/delete.php'), {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    csrf_token: csrf,
                    t: tableKey,
                    id: id,
                }),
            })
                .then(function (r) {
                    return r.json();
                })
                .then(function (data) {
                    if (data.status === 'ok') {
                        showFlash(true, 'הרשומה נמחקה.');
                        loadList();
                        closeModal();
                        return true;
                    }
                    showFlash(false, data.message || 'שגיאה במחיקה');
                    return false;
                })
                .catch(function () {
                    showFlash(false, 'שגיאת תקשורת');
                    return false;
                });
        });
    }

    function buildFormDataObject() {
        var data = {};
        if (!form) return data;
        var inputs = form.querySelectorAll('input, textarea, select');
        inputs.forEach(function (el) {
            var name = el.name;
            if (!name) return;
            if (el.type === 'radio') {
                if (el.checked) {
                    data[name] = el.value;
                }
                return;
            }
            if (el.type === 'checkbox') {
                data[name] = el.checked ? '1' : '0';
            } else {
                data[name] = el.value;
            }
        });
        return data;
    }

    function renderPagination(container) {
        if (!container) return;
        var totalPages = Math.max(1, Math.ceil(state.total / state.perPage));
        container.innerHTML = '';
        var prev = document.createElement('button');
        prev.type = 'button';
        prev.className =
            'min-w-[40px] min-h-[40px] rounded-lg border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed';
        prev.innerHTML = '<i class="fa-solid fa-chevron-right"></i>';
        prev.disabled = state.page <= 1;
        prev.addEventListener('click', function () {
            if (state.page > 1) {
                state.page--;
                loadList();
            }
        });
        var label = document.createElement('span');
        label.className = 'text-sm text-gray-600';
        label.textContent = 'עמוד ' + state.page + ' מתוך ' + totalPages + ' (' + state.total + ' רשומות)';
        var next = document.createElement('button');
        next.type = 'button';
        next.className =
            'min-w-[40px] min-h-[40px] rounded-lg border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed';
        next.innerHTML = '<i class="fa-solid fa-chevron-left"></i>';
        next.disabled = state.page >= totalPages;
        next.addEventListener('click', function () {
            if (state.page < totalPages) {
                state.page++;
                loadList();
            }
        });
        container.appendChild(prev);
        container.appendChild(label);
        container.appendChild(next);
    }

    function updateBulkUi() {
        if (!bulkBar || !bulkBtn || !bulkCountEl) return;
        var n = tbody.querySelectorAll('.admin-row-check:checked').length;
        bulkCountEl.textContent = String(n);
        bulkBar.classList.toggle('hidden', n === 0);
        bulkBtn.disabled = n === 0;
        var all = tbody.querySelectorAll('.admin-row-check');
        var headCb = theadRow.querySelector('.admin-select-all');
        if (headCb && all.length > 0) {
            headCb.checked = n === all.length;
            headCb.indeterminate = n > 0 && n < all.length;
        }
    }

    function renderTable(rows, columns) {
        theadRow.innerHTML = '';
        if (!listOnly && bulkDelete) {
            var thCh = document.createElement('th');
            thCh.className = 'admin-th-check';
            thCh.scope = 'col';
            var selAll = document.createElement('input');
            selAll.type = 'checkbox';
            selAll.className = 'admin-select-all';
            selAll.title = 'בחר הכל בעמוד';
            selAll.setAttribute('aria-label', 'בחר הכל בעמוד');
            selAll.addEventListener('change', function () {
                var on = selAll.checked;
                tbody.querySelectorAll('.admin-row-check').forEach(function (cb) {
                    cb.checked = on;
                });
                updateBulkUi();
            });
            thCh.appendChild(selAll);
            theadRow.appendChild(thCh);
        }
        columns.forEach(function (c) {
            var th = document.createElement('th');
            th.textContent = c;
            th.scope = 'col';
            theadRow.appendChild(th);
        });
        if (!listOnly) {
            var actionTh = document.createElement('th');
            actionTh.textContent = 'פעולות';
            actionTh.scope = 'col';
            theadRow.appendChild(actionTh);
        }

        tbody.innerHTML = '';
        rows.forEach(function (row) {
            var tr = document.createElement('tr');
            if (!listOnly && bulkDelete) {
                var tdCh = document.createElement('td');
                tdCh.className = 'admin-td-check';
                var cb = document.createElement('input');
                cb.type = 'checkbox';
                cb.className = 'admin-row-check';
                cb.dataset.id = String(row.id);
                cb.setAttribute('aria-label', 'בחר רשומה');
                cb.addEventListener('change', updateBulkUi);
                tdCh.appendChild(cb);
                tr.appendChild(tdCh);
            }
            columns.forEach(function (c) {
                var td = document.createElement('td');
                var v = row[c];
                if (v === null || v === undefined) v = '';
                td.textContent = typeof v === 'object' ? JSON.stringify(v) : String(v);
                tr.appendChild(td);
            });
            if (!listOnly) {
                var tdAct = document.createElement('td');
                tdAct.className = 'admin-row-actions';
                var editLink = document.createElement('a');
                editLink.href = apiUrl('admin/table.php?t=' + encodeURIComponent(tableKey) + '&id=' + encodeURIComponent(row.id));
                editLink.className = 'admin-action-btn admin-action-btn--edit';
                editLink.innerHTML = '<i class="fa-solid fa-pen-to-square" aria-hidden="true"></i><span>עריכה</span>';
                editLink.addEventListener('click', function (e) {
                    e.preventDefault();
                    loadForm('update', parseInt(row.id, 10));
                });
                tdAct.appendChild(editLink);
                if (allowDelete) {
                    var rowDeleteBtn = document.createElement('button');
                    rowDeleteBtn.type = 'button';
                    rowDeleteBtn.className = 'admin-action-btn admin-action-btn--delete';
                    rowDeleteBtn.innerHTML = '<i class="fa-solid fa-trash" aria-hidden="true"></i><span>מחיקה</span>';
                    rowDeleteBtn.addEventListener('click', function () {
                        deleteOne(parseInt(row.id, 10), { redirect: false });
                    });
                    tdAct.appendChild(rowDeleteBtn);
                }
                tr.appendChild(tdAct);
            }
            tbody.appendChild(tr);
        });
        updateBulkUi();
    }

    function loadList() {
        var url =
            apiUrl('admin/ajax/crud/list.php') +
            '?t=' +
            encodeURIComponent(tableKey) +
            '&page=' +
            encodeURIComponent(String(state.page)) +
            '&q=' +
            encodeURIComponent(state.q || '');
        fetch(url, { credentials: 'same-origin' })
            .then(function (r) {
                return r.json();
            })
            .then(function (data) {
                if (data.status !== 'ok') {
                    showFlash(false, data.message || 'שגיאה בטעינת הרשימה');
                    return;
                }
                state.total = data.total;
                state.perPage = data.per_page;
                state.columns = data.columns || [];
                renderTable(data.rows || [], state.columns);
                renderPagination(pagTop);
                renderPagination(pagBottom);
            })
            .catch(function () {
                showFlash(false, 'שגיאת תקשורת');
            });
    }

    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var payload = {
                csrf_token: csrf,
                t: tableKey,
                action: editId > 0 ? 'update' : 'create',
                data: buildFormDataObject(),
            };
            if (editId > 0) payload.id = editId;
            if (saveBtn) {
                saveBtn.disabled = true;
            }
            fetch(apiUrl('admin/ajax/crud/save.php'), {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            })
                .then(function (r) {
                    return r.json();
                })
                .then(function (data) {
                    if (data.status === 'ok') {
                        showFlash(true, 'נשמר בהצלחה.');
                        loadList();
                        closeModal();
                    } else {
                        showFlash(false, data.message || 'שגיאה בשמירה');
                    }
                })
                .catch(function () {
                    showFlash(false, 'שגיאת תקשורת');
                })
                .finally(function () {
                    if (saveBtn) saveBtn.disabled = false;
                });
        });
    }

    if (bulkBtn && bulkDelete) {
        bulkBtn.addEventListener('click', function () {
            var ids = [];
            tbody.querySelectorAll('.admin-row-check:checked').forEach(function (cb) {
                ids.push(parseInt(cb.dataset.id, 10));
            });
            if (ids.length === 0) return;
            confirmDelete('למחוק ' + ids.length + ' רשומות? פעולה בלתי הפיכה.', 'מחיקה מרובת').then(function (ok) {
                if (!ok) return;
                bulkBtn.disabled = true;
                fetch(apiUrl('admin/ajax/crud/delete_bulk.php'), {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        csrf_token: csrf,
                        t: tableKey,
                        ids: ids,
                    }),
                })
                    .then(function (r) {
                        return r.json();
                    })
                    .then(function (data) {
                        if (data.status === 'ok') {
                            showFlash(true, 'נמחקו ' + (data.deleted || ids.length) + ' רשומות.');
                            loadList();
                        } else {
                            showFlash(false, data.message || 'שגיאה במחיקה');
                        }
                    })
                    .catch(function () {
                        showFlash(false, 'שגיאת תקשורת');
                    })
                    .finally(function () {
                        updateBulkUi();
                    });
            });
        });
    }

    if (delBtn && allowDelete) {
        delBtn.addEventListener('click', function () {
            if (editId <= 0) return;
            delBtn.disabled = true;
            deleteOne(editId, { redirect: false }).finally(function () {
                delBtn.disabled = false;
            });
        });
    }

    if (modalCloseBtn) {
        modalCloseBtn.addEventListener('click', closeModal);
    }

    if (cancelBtn) {
        cancelBtn.addEventListener('click', closeModal);
    }

    if (modalEl) {
        modalEl.addEventListener('click', function (e) {
            if (e.target && e.target.classList.contains('admin-modal__backdrop')) {
                closeModal();
            }
        });
    }

    var createLink = root.querySelector('a[href*="create=1"]');
    if (createLink) {
        createLink.addEventListener('click', function (e) {
            e.preventDefault();
            loadForm('create', 0);
        });
    }

    if (topbarSearch) {
        topbarSearch.addEventListener(
            'input',
            debounce(function () {
                state.q = topbarSearch.value.trim();
                state.page = 1;
                loadList();
            }, 250)
        );
    }

    if (modalShouldOpen && !listOnly) {
        loadForm(editId > 0 ? 'update' : 'create', editId);
    }

    loadList();
})();
