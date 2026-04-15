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

    var state = { page: 1, total: 0, perPage: 20, columns: [] };

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

    function buildFormDataObject() {
        var data = {};
        if (!form) return data;
        var inputs = form.querySelectorAll('input, textarea, select');
        inputs.forEach(function (el) {
            var name = el.name;
            if (!name) return;
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
                editLink.className =
                    'inline-flex items-center rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-800 font-semibold text-sm py-1.5 px-3 no-underline';
                editLink.textContent = 'עריכה';
                tdAct.appendChild(editLink);
                tr.appendChild(tdAct);
            }
            tbody.appendChild(tr);
        });
        updateBulkUi();
    }

    function loadList() {
        var url =
            apiUrl('admin/ajax/list.php') +
            '?t=' +
            encodeURIComponent(tableKey) +
            '&page=' +
            encodeURIComponent(String(state.page));
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
            fetch(apiUrl('admin/ajax/save.php'), {
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
                        if (editId <= 0 && data.id) {
                            window.location.href =
                                apiUrl('admin/table.php?t=' + encodeURIComponent(tableKey) + '&id=' + encodeURIComponent(String(data.id)));
                        }
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
                fetch(apiUrl('admin/ajax/delete_bulk.php'), {
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

    if (delBtn && allowDelete && editId > 0) {
        delBtn.addEventListener('click', function () {
            confirmDelete('למחוק רשומה זו? פעולה בלתי הפיכה.', 'מחיקת רשומה').then(function (ok) {
                if (!ok) return;
                delBtn.disabled = true;
                fetch(apiUrl('admin/ajax/delete.php'), {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        csrf_token: csrf,
                        t: tableKey,
                        id: editId,
                    }),
                })
                    .then(function (r) {
                        return r.json();
                    })
                    .then(function (data) {
                        if (data.status === 'ok') {
                            window.location.href = apiUrl('admin/table.php?t=' + encodeURIComponent(tableKey));
                        } else {
                            showFlash(false, data.message || 'שגיאה במחיקה');
                        }
                    })
                    .catch(function () {
                        showFlash(false, 'שגיאת תקשורת');
                    })
                    .finally(function () {
                        delBtn.disabled = false;
                    });
            });
        });
    }

    loadList();
})();
