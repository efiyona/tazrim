(function () {
    var overlay = document.getElementById('admin-mobile-nav-overlay');
    var drawer = document.getElementById('admin-mobile-nav-drawer');
    var openBtn = document.getElementById('admin-mobile-nav-open-btn');
    var closeBtn = document.getElementById('admin-mobile-nav-close-btn');
    if (!overlay || !drawer || !openBtn || !closeBtn) return;

    function closeProfileMenuQuick() {
        var panel = document.getElementById('admin-profile-menu-panel');
        var b = document.getElementById('admin-profile-menu-btn');
        if (panel && !panel.classList.contains('hidden')) {
            panel.classList.add('hidden');
            panel.setAttribute('aria-hidden', 'true');
            if (b) b.setAttribute('aria-expanded', 'false');
        }
    }

    function mobileNavIsOpen() {
        return drawer.classList.contains('translate-x-0');
    }

    function setMobileNavOpen(open) {
        if (open) {
            closeProfileMenuQuick();
            overlay.classList.remove('opacity-0', 'pointer-events-none');
            overlay.classList.add('opacity-75', 'pointer-events-auto');
            drawer.classList.remove('translate-x-full');
            drawer.classList.add('translate-x-0');
            overlay.setAttribute('aria-hidden', 'false');
            drawer.setAttribute('aria-hidden', 'false');
        } else {
            overlay.classList.add('opacity-0', 'pointer-events-none');
            overlay.classList.remove('opacity-75', 'pointer-events-auto');
            drawer.classList.add('translate-x-full');
            drawer.classList.remove('translate-x-0');
            overlay.setAttribute('aria-hidden', 'true');
            drawer.setAttribute('aria-hidden', 'true');
        }
        openBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    openBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        setMobileNavOpen(!mobileNavIsOpen());
    });
    closeBtn.addEventListener('click', function () {
        setMobileNavOpen(false);
    });
    overlay.addEventListener('click', function () {
        setMobileNavOpen(false);
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') setMobileNavOpen(false);
    });
    document.querySelectorAll('.admin-mobile-nav-close-link').forEach(function (a) {
        a.addEventListener('click', function () {
            setMobileNavOpen(false);
        });
    });
})();

(function () {
    document.querySelectorAll('.admin-nav-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var group = btn.closest('.admin-nav-group');
            if (!group) return;
            var sub = group.querySelector('.admin-nav-group-children');
            if (!sub) return;
            var icon = btn.querySelector('.fa-chevron-down');
            sub.classList.toggle('hidden');
            var isOpen = !sub.classList.contains('hidden');
            btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            if (icon) icon.classList.toggle('rotate-180', isOpen);
        });
    });
})();

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
