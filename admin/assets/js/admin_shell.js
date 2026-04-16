(function () {
    var root = document.getElementById('admin-profile-menu-root');
    var btn = document.getElementById('admin-profile-menu-btn');
    var panel = document.getElementById('admin-profile-menu-panel');
    if (!root || !btn || !panel) return;

    function closeProfileMenu() {
        if (panel.classList.contains('hidden')) return;
        panel.classList.add('hidden');
        panel.setAttribute('aria-hidden', 'true');
        btn.setAttribute('aria-expanded', 'false');
    }

    function openProfileMenu() {
        panel.classList.remove('hidden');
        panel.setAttribute('aria-hidden', 'false');
        btn.setAttribute('aria-expanded', 'true');
    }

    btn.addEventListener('click', function (e) {
        e.stopPropagation();
        if (panel.classList.contains('hidden')) {
            openProfileMenu();
        } else {
            closeProfileMenu();
        }
    });

    // בשלב בועה (לא capture): כדי שלא נרוץ לפני Alpine על overlay/קישורי התפריט הצידי במובייל
    document.addEventListener('click', function (e) {
        if (!e.target) return;
        if (root.contains(e.target)) return;
        closeProfileMenu();
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeProfileMenu();
    });
})();

(function () {
    var search = document.getElementById('admin-topbar-search');
    if (!search || search.getAttribute('data-context') === 'table') return;

    var queryNav = function () {
        var q = (search.value || '').trim().toLowerCase();
        document.querySelectorAll('.admin-nav-link').forEach(function (link) {
            var label = (link.getAttribute('data-nav-label') || link.textContent || '').trim().toLowerCase();
            var li = link.closest('li');
            if (!li) return;
            li.style.display = q === '' || label.indexOf(q) !== -1 ? '' : 'none';
        });

        document.querySelectorAll('.admin-nav-group').forEach(function (group) {
            var visibleChildren = group.querySelectorAll('.admin-nav-leaf:not([style*="display: none"])').length;
            var toggle = group.querySelector('.admin-nav-toggle');
            var matchesGroup = toggle && ((toggle.getAttribute('data-nav-label') || '').toLowerCase().indexOf(q) !== -1);
            group.style.display = q === '' || matchesGroup || visibleChildren > 0 ? '' : 'none';
        });
    };

    search.addEventListener('input', queryNav);
})();
