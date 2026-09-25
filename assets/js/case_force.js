/**
 * Force detail text UPPERCASE / email lowercase (Add + Edit + Login fields).
 * Skips password, numbers, dates, selects, files.
 */
(function () {
    'use strict';

    function isEmailField(el) {
        if (!el) return false;
        var t = String(el.type || '').toLowerCase();
        var n = String(el.name || '');
        var id = String(el.id || '');
        if (t === 'email') return true;
        return /email/i.test(n) || /email/i.test(id);
    }

    function shouldSkip(el) {
        if (!el || el.disabled) return true;
        var tag = String(el.tagName || '').toUpperCase();
        if (tag === 'SELECT' || tag === 'BUTTON') return true;
        var t = String(el.type || '').toLowerCase();
        if (['password', 'number', 'hidden', 'file', 'checkbox', 'radio', 'date', 'time', 'range', 'color', 'submit', 'button'].indexOf(t) !== -1) {
            return true;
        }
        if (el.classList && (el.classList.contains('js-date') || el.classList.contains('no-case-force'))) {
            return true;
        }
        var n = String(el.name || '');
        // Keep passwords / salary revise numeric remarks? remarks → uppercase OK
        if (/password/i.test(n)) return true;
        return false;
    }

    function applyCase(el) {
        if (shouldSkip(el)) return;
        var v = el.value;
        if (typeof v !== 'string' || v === '') return;
        var next = isEmailField(el) ? v.toLowerCase() : v.toUpperCase();
        if (next !== v) {
            var start = el.selectionStart;
            var end = el.selectionEnd;
            el.value = next;
            // Restore caret for text-like inputs
            if (typeof start === 'number' && typeof end === 'number' && el.setSelectionRange && el.type !== 'email') {
                try {
                    el.setSelectionRange(start, end);
                } catch (e) { /* ignore */ }
            }
        }
    }

    function bindRoot(root) {
        if (!root) return;
        root.querySelectorAll('input, textarea').forEach(function (el) {
            if (shouldSkip(el)) return;
            if (isEmailField(el)) {
                el.classList.add('force-lower');
                el.classList.remove('force-upper');
            } else {
                el.classList.add('force-upper');
                el.classList.remove('force-lower');
            }
            applyCase(el);
            el.addEventListener('input', function () { applyCase(el); });
            el.addEventListener('blur', function () { applyCase(el); });
            el.addEventListener('change', function () { applyCase(el); });
        });

        var form = root.tagName === 'FORM' ? root : root.querySelector('form');
        if (form && !form.dataset.caseForceBound) {
            form.dataset.caseForceBound = '1';
            form.addEventListener('submit', function () {
                form.querySelectorAll('input, textarea').forEach(applyCase);
            });
        }
    }

    function boot() {
        document.querySelectorAll(
            'form.employee-form, #roleAssignForm, #staffUserForm'
        ).forEach(bindRoot);

        // Login page: do NOT force uppercase / lowercase
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

    // Family members re-rendered dynamically
    document.addEventListener('focusin', function (e) {
        var el = e.target;
        if (!el || shouldSkip(el)) return;
        if (!(el.closest && (el.closest('form.employee-form') || el.closest('#roleAssignForm') || el.closest('#staffUserForm')))) {
            return;
        }
        if (!el.classList.contains('force-upper') && !el.classList.contains('force-lower')) {
            if (isEmailField(el)) {
                el.classList.add('force-lower');
            } else {
                el.classList.add('force-upper');
            }
            el.addEventListener('input', function () { applyCase(el); });
            el.addEventListener('blur', function () { applyCase(el); });
        }
        applyCase(el);
    });

    window.ArmorCaseForce = { apply: applyCase, bind: bindRoot };
})();
