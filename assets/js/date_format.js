/**
 * Global DD-MM-YYYY date inputs (flatpickr)
 * Targets: .js-date and legacy input[type=date]
 */
(function () {
    'use strict';

    function initDateInputs(root) {
        if (typeof flatpickr === 'undefined') {
            return;
        }
        var scope = root && root.querySelectorAll ? root : document;
        var nodes = scope.querySelectorAll('input.js-date, input[type="date"]');
        if (!nodes.length) {
            return;
        }

        nodes.forEach(function (el) {
            if (el._flatpickr) {
                return;
            }
            // Force text so browser never shows locale MM/DD/YYYY native picker
            if (el.getAttribute('type') === 'date') {
                el.setAttribute('type', 'text');
            }
            el.setAttribute('placeholder', el.getAttribute('placeholder') || 'DD-MM-YYYY');
            el.setAttribute('autocomplete', 'off');
            el.setAttribute('inputmode', 'numeric');
            el.classList.add('js-date');

            flatpickr(el, {
                dateFormat: 'd-m-Y',
                allowInput: true,
                disableMobile: true,
                altInput: false,
                clickOpens: true
            });
        });
    }

    function boot() {
        initDateInputs(document);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

    window.ArmorInitDates = initDateInputs;
})();
