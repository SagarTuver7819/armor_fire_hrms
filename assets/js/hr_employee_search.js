/**
 * HR Dashboard — employee name/code search + view popup
 */
(function () {
    var root = document.getElementById('hrEmpSearch');
    if (!root) return;

    var input = document.getElementById('hrEmpSearchInput');
    var drop = document.getElementById('hrEmpSearchDrop');
    var modal = document.getElementById('hrEmpViewModal');
    var lookupUrl = root.getAttribute('data-lookup-url') || '';
    var timer = null;
    var lastQ = '';

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function dash(v) {
        v = String(v == null ? '' : v).trim();
        return v !== '' ? v : '—';
    }

    function initials(name) {
        name = String(name || '').trim();
        if (!name) return 'E';
        var parts = name.split(/\s+/);
        var a = parts[0].charAt(0);
        var b = parts.length > 1 ? parts[parts.length - 1].charAt(0) : '';
        return (a + b).toUpperCase();
    }

    function hideDrop() {
        if (!drop) return;
        drop.hidden = true;
        drop.innerHTML = '';
    }

    function showDrop(html) {
        if (!drop) return;
        drop.innerHTML = html;
        drop.hidden = false;
    }

    function renderResults(list) {
        if (!list || !list.length) {
            showDrop('<div class="hr-emp-search-empty">No employee found</div>');
            return;
        }
        var html = '<ul class="hr-emp-search-list" role="listbox">';
        list.forEach(function (r) {
            var photo = r.photo_url
                ? '<img src="' + esc(r.photo_url) + '" alt="">'
                : '<span class="hr-emp-search-av">' + esc(initials(r.employee_name)) + '</span>';
            html +=
                '<li>' +
                '<button type="button" class="hr-emp-search-item" data-id="' + esc(r.id) + '">' +
                '<span class="hr-emp-search-photo">' + photo + '</span>' +
                '<span class="hr-emp-search-meta">' +
                '<strong>' + esc(r.employee_name) + '</strong>' +
                '<small>' + esc(r.employee_code) +
                (r.designation ? ' · ' + esc(r.designation) : '') +
                (r.department ? ' · ' + esc(r.department) : '') +
                '</small>' +
                '</span>' +
                '<i class="fa-solid fa-chevron-right" aria-hidden="true"></i>' +
                '</button>' +
                '</li>';
        });
        html += '</ul>';
        showDrop(html);
    }

    function search(q) {
        q = String(q || '').trim();
        if (q.length < 1) {
            hideDrop();
            return;
        }
        if (q === lastQ && drop && !drop.hidden) return;
        lastQ = q;
        showDrop('<div class="hr-emp-search-empty">Searching…</div>');
        fetch(lookupUrl + '?action=search&q=' + encodeURIComponent(q), {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || !data.ok) {
                    showDrop('<div class="hr-emp-search-empty">Search failed</div>');
                    return;
                }
                renderResults(data.results || []);
            })
            .catch(function () {
                showDrop('<div class="hr-emp-search-empty">Search failed</div>');
            });
    }

    function openView(id) {
        if (!modal || !id) return;
        hideDrop();
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('hr-emp-view-open');
        var body = modal.querySelector('.hr-emp-view-body');
        if (body) {
            body.innerHTML = '<div class="hr-emp-view-loading"><i class="fa-solid fa-circle-notch fa-spin"></i> Loading…</div>';
        }
        fetch(lookupUrl + '?action=view&id=' + encodeURIComponent(id), {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || !data.ok || !data.employee) {
                    if (body) {
                        body.innerHTML = '<div class="hr-emp-view-loading">' + esc((data && data.error) || 'Not found') + '</div>';
                    }
                    return;
                }
                fillView(data.employee);
            })
            .catch(function () {
                if (body) {
                    body.innerHTML = '<div class="hr-emp-view-loading">Could not load employee</div>';
                }
            });
    }

    function fillView(e) {
        var body = modal.querySelector('.hr-emp-view-body');
        if (!body) return;
        var photoHtml = e.photo_url
            ? '<img src="' + esc(e.photo_url) + '" alt="' + esc(e.employee_name) + '">'
            : '<span class="hr-emp-view-av">' + esc(initials(e.employee_name)) + '</span>';

        var rows = [
            ['Emp Code', e.employee_code, ''],
            ['Employee Name', e.employee_name, ''],
            ['Designation', e.designation, ''],
            ['Department', e.department, ''],
            ['Official Mobile', e.office_mobile, ''],
            ['Official Mail ID', e.office_email, ''],
            ['Desk Number', e.desk_no, 'is-desk'],
        ];

        var info = '<dl class="hr-emp-view-grid">';
        rows.forEach(function (pair) {
            var cls = pair[2] ? ' hr-emp-view-row ' + pair[2] : 'hr-emp-view-row';
            info +=
                '<div class="' + cls.trim() + '">' +
                '<dt>' + esc(pair[0]) + '</dt>' +
                '<dd>' + esc(dash(pair[1])) + '</dd>' +
                '</div>';
        });
        info += '</dl>';

        body.innerHTML =
            '<div class="hr-emp-view-card">' +
            '<div class="hr-emp-view-photo">' + photoHtml + '</div>' +
            '<div class="hr-emp-view-head">' +
            '<h3>' + esc(dash(e.employee_name)) + '</h3>' +
            '<span class="hr-emp-view-code">' + esc(dash(e.employee_code)) + '</span>' +
            '</div>' +
            info +
            '</div>';
    }

    function closeView() {
        if (!modal) return;
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('hr-emp-view-open');
    }

    if (input) {
        input.addEventListener('input', function () {
            clearTimeout(timer);
            var q = input.value;
            timer = setTimeout(function () { search(q); }, 220);
        });
        input.addEventListener('focus', function () {
            if (String(input.value || '').trim().length >= 1) {
                search(input.value);
            }
        });
        input.addEventListener('keydown', function (ev) {
            if (ev.key === 'Escape') {
                hideDrop();
                input.blur();
            }
        });
    }

    if (drop) {
        drop.addEventListener('click', function (ev) {
            var btn = ev.target.closest('.hr-emp-search-item');
            if (!btn) return;
            openView(btn.getAttribute('data-id'));
        });
    }

    document.addEventListener('click', function (ev) {
        if (!root.contains(ev.target)) {
            hideDrop();
        }
    });

    if (modal) {
        modal.addEventListener('click', function (ev) {
            if (ev.target.matches('[data-hr-emp-close]') || ev.target.closest('[data-hr-emp-close]')) {
                closeView();
            }
        });
        document.addEventListener('keydown', function (ev) {
            if (ev.key === 'Escape' && !modal.hidden) {
                closeView();
            }
        });
    }
})();
