(function () {
    var root = document.getElementById('admin-app');
    if (!root) return;
    var baseUrl = root.getAttribute('data-base-url') || '/';

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

    document.querySelectorAll('.admin-fk-lookup').forEach(function (wrap) {
        var entity = wrap.getAttribute('data-entity');
        var field = wrap.getAttribute('data-field');
        var optional = wrap.getAttribute('data-optional') === '1';
        var hidden = wrap.querySelector('.admin-fk-value');
        var search = wrap.querySelector('.admin-fk-search');
        var list = wrap.querySelector('.admin-fk-results');
        var clearBtn = wrap.querySelector('.admin-fk-clear');

        if (!entity || !field || !hidden || !search || !list) return;

        function hideList() {
            list.classList.add('hidden');
            list.innerHTML = '';
        }

        function setLocked(val) {
            search.setAttribute('data-locked-label', val);
        }

        function getLocked() {
            return search.getAttribute('data-locked-label') || '';
        }

        function selectItem(id, label) {
            hidden.value = String(id);
            search.value = label;
            setLocked(label);
            hideList();
        }

        function clearField() {
            hidden.value = '';
            search.value = '';
            setLocked('');
            hideList();
        }

        function runFetch() {
            var q = (search.value || '').trim();
            var url =
                apiUrl('admin/ajax/lookup.php') +
                '?t=' +
                encodeURIComponent(entity) +
                '&field=' +
                encodeURIComponent(field) +
                '&q=' +
                encodeURIComponent(q);
            fetch(url, { credentials: 'same-origin' })
                .then(function (r) {
                    return r.json();
                })
                .then(function (data) {
                    if (data.status !== 'ok' || !data.items) {
                        hideList();
                        return;
                    }
                    list.innerHTML = '';
                    data.items.forEach(function (item) {
                        var li = document.createElement('li');
                        var btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'admin-fk-item';
                        btn.textContent = item.label;
                        btn.addEventListener('click', function () {
                            selectItem(item.id, item.label);
                        });
                        li.appendChild(btn);
                        list.appendChild(li);
                    });
                    list.classList.toggle('hidden', data.items.length === 0);
                })
                .catch(function () {
                    hideList();
                });
        }

        var doFetch = debounce(runFetch, 280);

        search.addEventListener('input', function () {
            if (search.value !== getLocked()) {
                hidden.value = '';
            }
            doFetch();
        });

        search.addEventListener('focus', function () {
            runFetch();
        });

        if (clearBtn && optional) {
            clearBtn.addEventListener('click', function () {
                clearField();
            });
        }

        document.addEventListener('click', function (e) {
            if (!wrap.contains(e.target)) {
                hideList();
            }
        });
    });
})();
