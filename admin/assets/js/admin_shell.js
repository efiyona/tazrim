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
