/**
 * Sidebar toggle + accordion submenus + menu search
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
            // While searching, keep accordions open for matches
            if (document.body.classList.contains('sidebar-is-searching')) {
                return;
            }
            var open = !acc.classList.contains('is-open');
            acc.classList.toggle('is-open', open);
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            accState[name] = open;
            try {
                localStorage.setItem(ACC_KEY, JSON.stringify(accState));
            } catch (e) {}
        });
    });

    // Menu search
    var searchInput = document.getElementById('sidebarMenuSearch');
    var searchClear = document.getElementById('sidebarMenuSearchClear');
    var searchEmpty = document.getElementById('sidebarSearchEmpty');
    var sidebarScroll = document.getElementById('sidebarScroll');

    function linkLabel(el) {
        var t = (el.getAttribute('title') || '').trim();
        var spans = el.querySelectorAll('span');
        var texts = [];
        spans.forEach(function (s) {
            if (s.classList.contains('sidebar-dot')) return;
            var v = (s.textContent || '').trim();
            if (v) texts.push(v);
        });
        return (t + ' ' + texts.join(' ')).toLowerCase().replace(/\s+/g, ' ').trim();
    }

    function filterSidebarMenu(raw) {
        var q = (raw || '').trim().toLowerCase().replace(/\s+/g, ' ');
        var searching = q.length > 0;
        document.body.classList.toggle('sidebar-is-searching', searching);
        if (searchClear) searchClear.hidden = !searching;

        if (!sidebarScroll) return;

        var links = sidebarScroll.querySelectorAll('a.sidebar-link');
        var matchCount = 0;

        links.forEach(function (link) {
            if (!searching) {
                link.classList.remove('sidebar-search-hide');
                matchCount++;
                return;
            }
            var ok = linkLabel(link).indexOf(q) !== -1;
            link.classList.toggle('sidebar-search-hide', !ok);
            if (ok) matchCount++;
        });

        sidebarScroll.querySelectorAll('.sidebar-accordion').forEach(function (acc) {
            var btn = acc.querySelector('.sidebar-acc-btn');
            var visibleLinks = acc.querySelectorAll('a.sidebar-link:not(.sidebar-search-hide)');
            var hasMatch = visibleLinks.length > 0;
            if (searching) {
                if (btn) {
                    var btnText = ((btn.textContent || '') + ' ' + (btn.getAttribute('title') || '')).toLowerCase();
                    var btnMatch = btnText.indexOf(q) !== -1;
                    // If accordion title matches, show all its links
                    if (btnMatch) {
                        acc.querySelectorAll('a.sidebar-link').forEach(function (link) {
                            if (link.classList.contains('sidebar-search-hide')) {
                                link.classList.remove('sidebar-search-hide');
                                matchCount++;
                            }
                        });
                        hasMatch = true;
                    }
                    btn.classList.toggle('sidebar-search-hide', !hasMatch && !btnMatch);
                }
                acc.classList.toggle('is-open', hasMatch);
                if (btn) btn.setAttribute('aria-expanded', hasMatch ? 'true' : 'false');
            } else if (btn) {
                btn.classList.remove('sidebar-search-hide');
            }
        });

        sidebarScroll.querySelectorAll('.sidebar-section').forEach(function (section) {
            if (!searching) {
                section.classList.remove('sidebar-search-hide');
                var card = section.querySelector('.sidebar-dept-card');
                if (card) card.classList.remove('sidebar-search-hide');
                return;
            }
            var any = section.querySelectorAll('a.sidebar-link:not(.sidebar-search-hide)').length > 0;
            var card = section.querySelector('.sidebar-dept-card');
            if (card) {
                var cardText = (card.textContent || '').toLowerCase();
                var cardOk = cardText.indexOf(q) !== -1;
                card.classList.toggle('sidebar-search-hide', !cardOk);
                if (cardOk) any = true;
            }
            section.classList.toggle('sidebar-search-hide', !any);
        });

        if (!searching) {
            sidebarScroll.querySelectorAll('.sidebar-accordion').forEach(function (acc) {
                var name = acc.getAttribute('data-accordion') || '';
                var btn = acc.querySelector('.sidebar-acc-btn');
                var open = Object.prototype.hasOwnProperty.call(accState, name)
                    ? !!accState[name]
                    : (acc.getAttribute('data-default-open') === '1');
                acc.classList.toggle('is-open', open);
                if (btn) btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            });
        }

        if (searchEmpty) {
            var visible = sidebarScroll.querySelectorAll('a.sidebar-link:not(.sidebar-search-hide)').length;
            searchEmpty.hidden = !searching || visible > 0;
        }
    }

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            filterSidebarMenu(searchInput.value);
        });
        searchInput.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                searchInput.value = '';
                filterSidebarMenu('');
                searchInput.blur();
            }
        });
    }
    if (searchClear) {
        searchClear.addEventListener('click', function () {
            if (searchInput) {
                searchInput.value = '';
                filterSidebarMenu('');
                searchInput.focus();
            }
        });
    }
})();
