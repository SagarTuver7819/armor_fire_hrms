/**
 * Global DD-MM-YYYY date inputs (flatpickr)
 * Targets: .js-date and legacy input[type=date]
 */
(function () {
    'use strict';

    function parseUserDate(datestr) {
        var s = String(datestr || '').trim();
        if (!s) {
            return undefined;
        }
        // DD-MM-YYYY / DD/MM/YYYY / DD.MM.YYYY
        var m = s.match(/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})$/);
        if (m) {
            var d = parseInt(m[1], 10);
            var mo = parseInt(m[2], 10);
            var y = parseInt(m[3], 10);
            if (mo < 1 || mo > 12 || d < 1 || d > 31) {
                return undefined;
            }
            var dt = new Date(y, mo - 1, d);
            if (dt.getFullYear() === y && dt.getMonth() === mo - 1 && dt.getDate() === d) {
                return dt;
            }
            return undefined;
        }
        // YYYY-MM-DD
        m = s.match(/^(\d{4})-(\d{2})-(\d{2})$/);
        if (m) {
            var y2 = parseInt(m[1], 10);
            var mo2 = parseInt(m[2], 10);
            var d2 = parseInt(m[3], 10);
            var dt2 = new Date(y2, mo2 - 1, d2);
            if (dt2.getFullYear() === y2 && dt2.getMonth() === mo2 - 1 && dt2.getDate() === d2) {
                return dt2;
            }
        }
        return undefined;
    }

    function formatUserDate(date) {
        if (!(date instanceof Date) || isNaN(date.getTime())) {
            return '';
        }
        var d = date.getDate();
        var m = date.getMonth() + 1;
        var y = date.getFullYear();
        return (d < 10 ? '0' : '') + d + '-' + (m < 10 ? '0' : '') + m + '-' + y;
    }

    function stripSelect2($sel) {
        if (!$sel || !$sel.length) {
            return;
        }
        $sel.addClass('no-select2');
        if ($sel.data('select2')) {
            try {
                $sel.select2('destroy');
            } catch (e) { /* ignore */ }
        }
        $sel.removeClass('select2-hidden-accessible')
            .removeAttr('data-select2-id')
            .removeAttr('aria-hidden')
            .removeAttr('tabindex')
            .css({ display: '', width: '', height: '', position: '', opacity: '', visibility: '' });
        var $wrap = $sel.next('.select2-container');
        if ($wrap.length) {
            $wrap.remove();
        }
        $sel.parent().find('> .select2-container').remove();
    }

    function polishCalendar(instance) {
        if (!instance || !instance.calendarContainer) {
            return;
        }
        instance.calendarContainer.classList.add('armor-fp');

        var yearInput = instance.currentYearElement;
        if (yearInput) {
            yearInput.removeAttribute('readonly');
            yearInput.setAttribute('inputmode', 'numeric');
            yearInput.setAttribute('title', 'Year');
        }

        var monthSelect = instance.monthsDropdownContainer;
        if (monthSelect) {
            monthSelect.classList.add('no-select2');
            monthSelect.setAttribute('title', 'Select month');
            monthSelect.style.pointerEvents = 'auto';
            // Undo Select2 if global init already wrapped this control
            if (window.jQuery) {
                stripSelect2(window.jQuery(monthSelect));
            }
        }
    }

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
                polishCalendar(el._flatpickr);
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
                clickOpens: true,
                // Keep calendar outside overflow:hidden cards so month/year selects work
                appendTo: document.body,
                position: 'auto',
                monthSelectorType: 'dropdown',
                shorthandCurrentMonth: false,
                parseDate: function (datestr) {
                    return parseUserDate(datestr);
                },
                formatDate: function (date) {
                    return formatUserDate(date);
                },
                onReady: function (selectedDates, dateStr, instance) {
                    polishCalendar(instance);
                },
                onOpen: function (selectedDates, dateStr, instance) {
                    polishCalendar(instance);
                },
                onMonthChange: function (selectedDates, dateStr, instance) {
                    polishCalendar(instance);
                },
                onClose: function (selectedDates, dateStr, instance) {
                    // Commit typed DD-MM-YYYY when calendar closes
                    var raw = (instance.input && instance.input.value) ? String(instance.input.value).trim() : '';
                    if (!raw) {
                        instance.clear(false);
                        return;
                    }
                    var parsed = parseUserDate(raw);
                    if (parsed) {
                        instance.setDate(parsed, true);
                    } else if (selectedDates && selectedDates[0]) {
                        instance.setDate(selectedDates[0], true);
                    }
                }
            });
        });
    }

    function boot() {
        initDateInputs(document);
        // Select2 runs after us — strip any wrap it applied to month dropdowns
        setTimeout(function () {
            if (!window.jQuery) {
                return;
            }
            window.jQuery('.flatpickr-monthDropdown-months, .flatpickr-calendar select').each(function () {
                stripSelect2(window.jQuery(this));
            });
        }, 0);
        setTimeout(function () {
            if (!window.jQuery) {
                return;
            }
            window.jQuery('.flatpickr-monthDropdown-months, .flatpickr-calendar select').each(function () {
                stripSelect2(window.jQuery(this));
            });
        }, 300);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

    window.ArmorInitDates = initDateInputs;
})();
