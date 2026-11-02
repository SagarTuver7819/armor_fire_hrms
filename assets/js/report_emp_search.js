/**
 * Live employee search box for Attendance Report / Salary Register tables.
 * Input: [data-report-emp-search=".selector for rows"]
 */
(function () {
    function rowHaystack(tr) {
        var attr = tr.getAttribute('data-emp-search');
        if (attr) {
            return String(attr).toLowerCase();
        }
        var parts = [];
        var cells = tr.querySelectorAll('td.sticky-col, td.sticky-col-2, td.sr-sticky-1, td.sr-sticky-2');
        if (!cells.length) {
            cells = tr.querySelectorAll('td');
        }
        for (var i = 0; i < Math.min(cells.length, 2); i++) {
            parts.push((cells[i].textContent || '').replace(/\s+/g, ' ').trim());
        }
        return parts.join(' ').toLowerCase();
    }

    function applyFilter(input) {
        var q = String(input.value || '').trim().toLowerCase();
        var rowSel = input.getAttribute('data-report-emp-search') || '';
        if (!rowSel) {
            return;
        }
        var rows = document.querySelectorAll(rowSel);
        var shown = 0;
        rows.forEach(function (tr) {
            if (tr.querySelector('.empty-cell')) {
                tr.style.display = q ? 'none' : '';
                return;
            }
            var ok = !q || rowHaystack(tr).indexOf(q) !== -1;
            tr.style.display = ok ? '' : 'none';
            if (ok) {
                shown++;
            }
        });

        document.querySelectorAll('.salary-register-sheet').forEach(function (sheet) {
            if (!sheet.querySelector(rowSel.split(',')[0] || 'tbody tr')) {
                return;
            }
            var visible = 0;
            sheet.querySelectorAll('tbody tr').forEach(function (tr) {
                if (tr.style.display !== 'none' && !tr.querySelector('.empty-cell')) {
                    visible++;
                }
            });
            sheet.style.display = (!q || visible > 0) ? '' : 'none';
        });

        var countEl = document.querySelector(input.getAttribute('data-report-emp-count') || '');
        if (countEl) {
            countEl.textContent = q
                ? (shown + ' match' + (shown === 1 ? '' : 'es'))
                : '';
        }
    }

    function bind(input) {
        if (!input || input._reportEmpSearchBound) {
            return;
        }
        input._reportEmpSearchBound = true;
        var clearBtn = input.parentElement
            ? input.parentElement.querySelector('[data-report-emp-clear]')
            : null;
        input.addEventListener('input', function () {
            applyFilter(input);
        });
        input.addEventListener('keydown', function (ev) {
            if (ev.key === 'Escape') {
                input.value = '';
                applyFilter(input);
                input.blur();
            }
        });
        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                input.value = '';
                applyFilter(input);
                input.focus();
            });
        }
        if (input.value) {
            applyFilter(input);
        }
    }

    document.querySelectorAll('[data-report-emp-search]').forEach(bind);
})();
