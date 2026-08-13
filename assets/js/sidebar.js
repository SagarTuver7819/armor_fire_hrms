/**
 * Sidebar toggle + accordion submenus
 */
(function () {
    var shell = document.getElementById('appShell');
    if (!shell) return;

    var KEY = 'armor_sidebar_collapsed';
    var ACC_KEY = 'armor_sidebar_acc';
    var toggleBtn = document.getElementById('sidebarToggle');
    var fabBtn = document.getElementById('sidebarFab');
    var headerBtn = document.getElementById('headerSidebarBtn');

    function isCollapsed() {
        return shell.classList.contains('sidebar-collapsed');
    }

    function setCollapsed(collapsed) {
        shell.classList.toggle('sidebar-collapsed', !!collapsed);
        document.body.classList.toggle('sidebar-is-collapsed', !!collapsed);
        try {
            localStorage.setItem(KEY, collapsed ? '1' : '0');
        } catch (e) {}

        var icon = toggleBtn ? toggleBtn.querySelector('i') : null;
        if (icon) {
            icon.className = collapsed ? 'fa-solid fa-angles-right' : 'fa-solid fa-angles-left';
        }
        if (toggleBtn) {
            toggleBtn.title = collapsed ? 'Show sidebar' : 'Hide sidebar';
        }
    }

    function toggle() {
        setCollapsed(!isCollapsed());
    }

    try {
        if (localStorage.getItem(KEY) === '1') {
            setCollapsed(true);
        }
    } catch (e) {}

    if (toggleBtn) toggleBtn.addEventListener('click', toggle);
    if (fabBtn) fabBtn.addEventListener('click', function () { setCollapsed(false); });
    if (headerBtn) headerBtn.addEventListener('click', toggle);

    shell.addEventListener('click', function (e) {
        if (e.target === shell && !isCollapsed() && window.matchMedia('(max-width: 900px)').matches) {
            setCollapsed(true);
        }
    });

    try {
        if (localStorage.getItem(KEY) === null && window.matchMedia('(max-width: 900px)').matches) {
            setCollapsed(true);
        }
    } catch (e) {}

    // Accordion submenus
    var accState = {};
    try {
        accState = JSON.parse(localStorage.getItem(ACC_KEY) || '{}') || {};
    } catch (e) {
        accState = {};
    }

    document.querySelectorAll('.sidebar-accordion').forEach(function (acc) {
        var name = acc.getAttribute('data-accordion') || '';
        var btn = acc.querySelector('.sidebar-acc-btn');
        if (!btn) return;

        // Keep page-forced open menus; only restore when not already open for context
        var forcedOpen = acc.classList.contains('is-open');
        if (!forcedOpen && Object.prototype.hasOwnProperty.call(accState, name)) {
            acc.classList.toggle('is-open', !!accState[name]);
            btn.setAttribute('aria-expanded', acc.classList.contains('is-open') ? 'true' : 'false');
        }

        btn.addEventListener('click', function () {
            var open = !acc.classList.contains('is-open');
            acc.classList.toggle('is-open', open);
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            accState[name] = open;
            try {
                localStorage.setItem(ACC_KEY, JSON.stringify(accState));
            } catch (e) {}
        });
    });
})();
