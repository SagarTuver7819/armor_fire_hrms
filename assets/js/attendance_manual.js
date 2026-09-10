/**
 * Manual Attendance — Excel monthly grid
 * Click day cell → modal with status, time pickers, leave box
 */
(function ($) {
    'use strict';

    var $activeCell = null;
    var tpIn = null;
    var tpOut = null;

    function shiftTimes($ctx) {
        var $row = null;
        if ($ctx && $ctx.length) {
            $row = $ctx.closest('tr.excel-emp-row');
        } else if ($activeCell && $activeCell.length) {
            $row = $activeCell.closest('tr.excel-emp-row');
        }
        if ($row && $row.length) {
            var rin = ($row.attr('data-shift-in') || '').toString().trim();
            var rout = ($row.attr('data-shift-out') || '').toString().trim();
            if (rin || rout) {
                return {
                    inn: rin || (window.ATT_DEFAULT_IN || '9:00 AM').toString(),
                    out: rout || (window.ATT_DEFAULT_OUT || '6:00 PM').toString()
                };
            }
        }
        var $opt = $('#shiftSelect option:selected');
        return {
            inn: ($opt.data('in') || window.ATT_DEFAULT_IN || '9:00 AM').toString(),
            out: ($opt.data('out') || window.ATT_DEFAULT_OUT || '6:00 PM').toString()
        };
    }

    function buildPreview(status, inn, out, leaveType, leaveHalf) {
        status = status || '';
        leaveType = (leaveType || '').trim();
        leaveHalf = (leaveHalf || '').toUpperCase();
        if (!status) return '-';
        if (status === 'Week Off') return 'week off';
        if (status === 'Holiday') return 'holiday';
        if (status === 'Absent') return 'absent';
        if (status === 'Leave' && (!leaveHalf || leaveHalf === 'FULL')) {
            return leaveType || 'Leave';
        }
        var timePart = '';
        if (inn || out) {
            timePart = ((inn || '') + ' | ' + (out || '')).replace(/^\s*\|\s*|\s*\|\s*$/g, '').trim();
        }
        var leavePart = '';
        if (leaveType && (status === 'Leave' || status === 'Half Day')) {
            leavePart = leaveType;
            if (leaveHalf === 'FHF' || leaveHalf === 'SHF') leavePart += ' ' + leaveHalf;
        }
        if (timePart && leavePart) return timePart + '\n' + leavePart;
        return timePart || leavePart || status || '-';
    }

    function cellCss(status) {
        return {
            Present: 'is-present',
            'Half Day': 'is-half',
            Leave: 'is-leave',
            'Week Off': 'is-weekoff',
            Holiday: 'is-holiday',
            Absent: 'is-absent'
        }[status] || '';
    }

    function writeCell($td, data) {
        var status = data.status || '';
        var inn = data.in || '';
        var out = data.out || '';
        var leaveType = data.leaveType || '';
        var leaveHalf = data.leaveHalf || '';
        var text = buildPreview(status, inn, out, leaveType, leaveHalf);

        $td.attr({
            'data-status': status,
            'data-in': inn,
            'data-out': out,
            'data-leave-type': leaveType,
            'data-leave-half': leaveHalf
        });
        $td.removeClass('is-present is-half is-leave is-weekoff is-holiday is-absent')
            .addClass(cellCss(status));

        var html = text && text !== '-'
            ? String(text).replace(/\n/g, '<br>')
            : '<span class="cell-empty">+</span>';
        $td.find('.cell-text').html(html);
        $td.find('.cell-status').val(status);
        $td.find('.cell-in').val(inn);
        $td.find('.cell-out').val(out);
        $td.find('.cell-leave-type').val(leaveType);
        $td.find('.cell-leave-half').val(leaveHalf);
        $td.find('.cell-touched').val('1');
        recalcRowTotals($td.closest('tr'));
    }

    function recalcRowTotals($tr) {
        var totals = { PL: 0, SL: 0, 'C-Off': 0, DL: 0, LWP: 0 };
        $tr.find('td.day-cell').each(function () {
            var status = $(this).attr('data-status') || '';
            var type = ($(this).attr('data-leave-type') || '').trim();
            var half = ($(this).attr('data-leave-half') || '').toUpperCase();
            if (!type && status === 'Leave') type = 'PL';
            if (!type || !totals.hasOwnProperty(type)) return;
            var inc = (status === 'Half Day' || half === 'FHF' || half === 'SHF') ? 0.5 : 1;
            if (status === 'Leave' || status === 'Half Day') {
                totals[type] += inc;
            }
        });
        function fmt(n) {
            if (!n) return '';
            return (Math.round(n * 10) / 10).toString();
        }
        $tr.find('.tot-pl').text(fmt(totals.PL));
        $tr.find('.tot-sl').text(fmt(totals.SL));
        $tr.find('.tot-coff').text(fmt(totals['C-Off']));
        $tr.find('.tot-dl').text(fmt(totals.DL));
        $tr.find('.tot-lwp').text(fmt(totals.LWP));
    }

    function destroyModalSelect2() {
        $('#attCellModal select').each(function () {
            var $el = $(this);
            if ($el.hasClass('select2-hidden-accessible') || $el.data('select2')) {
                try {
                    $el.select2('destroy');
                } catch (e) { /* ignore */ }
            }
            $el.addClass('no-select2');
        });
        // Remove orphan select2 containers left inside modal
        $('#attCellModal .select2-container').remove();
    }

    function destroyModalTimePickers() {
        ['#mIn', '#mOut'].forEach(function (sel) {
            var el = document.querySelector(sel);
            if (!el) return;
            if (el._flatpickr) {
                try { el._flatpickr.destroy(); } catch (e) { /* ignore */ }
            }
            if (typeof window.mdtimepicker === 'function') {
                try { window.mdtimepicker(sel, 'hide'); } catch (e1) { /* ignore */ }
                try { window.mdtimepicker(sel, 'destroy'); } catch (e2) { /* ignore */ }
            }
        });
        // leftover overlays
        document.querySelectorAll('.mdtp__wrapper').forEach(function (n) {
            if (!n.querySelector('.mdtp__clock')) return;
        });
        tpIn = null;
        tpOut = null;
    }

    function bindRoundTimePicker(selector) {
        var el = document.querySelector(selector);
        if (!el || typeof window.mdtimepicker !== 'function') {
            return null;
        }

        window.mdtimepicker(selector, {
            theme: 'orange',
            format: 'h:mm tt',
            is24hour: false,
            hourPadding: false,
            clearBtn: true,
            readOnly: true,
            events: {
                timeChanged: function () {
                    syncModalPreview();
                }
            }
        });

        el.addEventListener('change', syncModalPreview);
        return el;
    }

    function initModalTimePickers() {
        destroyModalTimePickers();
        tpIn = bindRoundTimePicker('#mIn');
        tpOut = bindRoundTimePicker('#mOut');
    }

    function setModalTime(selector, val) {
        var el = document.querySelector(selector);
        if (!el) return;
        var value = (val || '').toString().trim();

        if (typeof window.mdtimepicker === 'function') {
            if (value) {
                try {
                    window.mdtimepicker(selector, 'setValue', value);
                    syncModalPreview();
                    return;
                } catch (e) { /* fall through */ }
            } else {
                el.value = '';
                try { window.mdtimepicker(selector, 'hide'); } catch (e2) { /* ignore */ }
                syncModalPreview();
                return;
            }
        }

        el.value = value;
        $(el).trigger('change');
    }

    function syncModalPreview() {
        var status = $('#mStatus').val() || '';
        var inn = $('#mIn').val() || '';
        var out = $('#mOut').val() || '';
        var lt = $('#mLeaveType').val() || '';
        var lh = $('#mLeaveHalf').val() || '';
        var text = buildPreview(status, inn, out, lt, lh);
        $('#mPreview').html(String(text).replace(/\n/g, '<br>'));
        toggleLeaveFields();
    }

    function toggleLeaveFields() {
        var status = $('#mStatus').val() || '';
        var show = status === 'Leave' || status === 'Half Day';
        $('#mLeaveWrap').toggle(show);
        var showTime = status === 'Present' || status === 'Half Day';
        $('#mIn, #mOut').closest('.form-group').toggle(showTime || status === '');
    }

        function formatDateDisplay(ymd) {
            var s = String(ymd || '');
            var m = s.match(/^(\d{4})-(\d{2})-(\d{2})$/);
            if (m) return m[3] + '-' + m[2] + '-' + m[1];
            return s;
        }

        function openModal($td) {
            destroyModalSelect2();
            $activeCell = $td;
            var date = $td.data('date');
            var day = $td.data('day');
            var name = $td.closest('tr').find('.sticky-col-2 strong').text();
            $('#attCellTitle').text(name + ' · Day ' + day + ' (' + formatDateDisplay(date) + ')');

            var status = $td.attr('data-status') || '';
            var inn = $td.attr('data-in') || '';
            var out = $td.attr('data-out') || '';
            var lt = $td.attr('data-leave-type') || '';
            var lh = $td.attr('data-leave-half') || '';
            var t = shiftTimes($td);

            // Empty cell → default Present with employee shift times
            if (!status) {
                status = 'Present';
                if (!inn) inn = t.inn;
                if (!out) out = t.out;
            }

            $('#mStatus').val(status);
            $('#mLeaveType').val(lt);
            $('#mLeaveHalf').val(lh || (status === 'Leave' ? 'FULL' : 'SHF'));
            syncModalPreview();

            $('#attCellModal').prop('hidden', false);
            initModalTimePickers();
            setModalTime('#mIn', inn);
            setModalTime('#mOut', out);
            syncModalPreview();
        }

    function closeModal() {
        // Hide any open round clock overlay
        $('.mdtimepicker, .mdtp__wrapper, .mdtp--open').removeClass('mdtp--open').hide();
        $('#attCellModal').prop('hidden', true);
        $activeCell = null;
    }

    function applyModal() {
        if (!$activeCell) return;
        var status = $('#mStatus').val() || '';
        var lt = $('#mLeaveType').val() || '';
        var lh = $('#mLeaveHalf').val() || '';
        var inn = $('#mIn').val() || '';
        var out = $('#mOut').val() || '';
        var t = shiftTimes($activeCell);

        if (status === 'Present') {
            if (!inn) inn = t.inn;
            if (!out) out = t.out;
            lt = '';
            lh = '';
        } else if (status === 'Half Day') {
            if (!lt) lt = 'PL';
            if (!lh || lh === 'FULL') lh = 'SHF';
            if (lh === 'FHF') {
                if (!inn) inn = '1:00 PM';
                if (!out) out = t.out;
            } else {
                if (!inn) inn = t.inn;
                if (!out) out = '1:00 PM';
            }
        } else if (status === 'Leave') {
            if (!lt) lt = 'PL';
            lh = lh || 'FULL';
            if (lh === 'FHF' || lh === 'SHF') {
                status = 'Half Day';
                if (lh === 'FHF') {
                    inn = inn || '1:00 PM';
                    out = out || t.out;
                } else {
                    inn = inn || t.inn;
                    out = out || '1:00 PM';
                }
            } else {
                inn = '';
                out = '';
            }
        } else {
            inn = '';
            out = '';
            lt = '';
            lh = '';
        }

        writeCell($activeCell, {
            status: status,
            in: inn,
            out: out,
            leaveType: lt,
            leaveHalf: lh
        });
        closeModal();
    }

    $(function () {
        if (window.ATT_TOAST && typeof toastr !== 'undefined') {
            if (window.ATT_TOAST_TYPE === 'error') {
                toastr.error(window.ATT_TOAST);
            } else {
                toastr.success(window.ATT_TOAST);
            }
        }

        destroyModalSelect2();
        setTimeout(destroyModalSelect2, 50);
        setTimeout(destroyModalSelect2, 300);
        initModalTimePickers();

        $('#manualAttTable').on('click', 'td.day-cell', function (e) {
            if ($(e.target).is('input')) return;
            openModal($(this));
        });

        $('#mStatus, #mLeaveType, #mLeaveHalf').on('change', function () {
            var status = $('#mStatus').val();
            var t = shiftTimes($activeCell);
            var lh = $('#mLeaveHalf').val();
            if (status === 'Present') {
                if (!$('#mIn').val()) setModalTime('#mIn', t.inn);
                if (!$('#mOut').val()) setModalTime('#mOut', t.out);
            } else if (status === 'Half Day') {
                if (lh === 'FHF') {
                    setModalTime('#mIn', '1:00 PM');
                    setModalTime('#mOut', t.out);
                } else {
                    setModalTime('#mIn', t.inn);
                    setModalTime('#mOut', '1:00 PM');
                }
            } else if (status === 'Leave' || status === 'Week Off' || status === 'Holiday' || status === 'Absent') {
                setModalTime('#mIn', '');
                setModalTime('#mOut', '');
            }
            syncModalPreview();
        });

        $('#mApply').on('click', applyModal);
        $('#mCancel, .att-cell-modal-backdrop').on('click', closeModal);

        function weekdayName(dateStr) {
            var names = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
            var dt = new Date(String(dateStr || '') + 'T00:00:00');
            if (isNaN(dt.getTime())) return '';
            return names[dt.getDay()] || '';
        }

        function rowWeekOffDay($td) {
            var raw = ($td.closest('tr').attr('data-week-off') || 'Sunday').toString().trim();
            return raw || 'Sunday';
        }

        function isEmployeeWeekOffDay($td) {
            var off = rowWeekOffDay($td).toLowerCase();
            var day = weekdayName($td.data('date')).toLowerCase();
            return off !== '' && day !== '' && off === day;
        }

        $('#btnFillPresent').on('click', function () {
            var presentN = 0;
            var offN = 0;
            $('#manualAttTable td.day-cell').each(function () {
                var $td = $(this);
                if (($td.attr('data-status') || '') !== '') return;
                if (isEmployeeWeekOffDay($td)) {
                    writeCell($td, { status: 'Week Off', in: '', out: '', leaveType: '', leaveHalf: '' });
                    offN++;
                    return;
                }
                var t = shiftTimes($td);
                writeCell($td, { status: 'Present', in: t.inn, out: t.out, leaveType: '', leaveHalf: '' });
                presentN++;
            });
            if (typeof toastr !== 'undefined') {
                toastr.info('Filled Present (employee shift): ' + presentN + ' · Week Off: ' + offN);
            }
        });

        $('#btnFillWeekOff').on('click', function () {
            var n = 0;
            $('#manualAttTable td.day-cell').each(function () {
                var $td = $(this);
                if (!isEmployeeWeekOffDay($td)) return;
                // Only fill empty, or overwrite if already Week Off / Present from bulk fill
                var st = ($td.attr('data-status') || '');
                if (st !== '' && st !== 'Week Off' && st !== 'Present') return;
                writeCell($td, { status: 'Week Off', in: '', out: '', leaveType: '', leaveHalf: '' });
                n++;
            });
            if (typeof toastr !== 'undefined') {
                toastr.info(n + ' day(s) marked Week Off from each employee\'s week-off day.');
            }
        });

        $('#manualAttForm').on('submit', function (e) {
            var payload = {};
            var n = 0;

            $('#manualAttTable td.day-cell').each(function () {
                var $td = $(this);
                var touched = String($td.find('.cell-touched').val() || '0') === '1';
                if (!touched) {
                    $td.find('input').prop('disabled', true);
                    return;
                }
                n++;
                var emp = String($td.data('emp') || '');
                var date = String($td.data('date') || '');
                var row = {
                    status: $td.find('.cell-status').val() || '',
                    punch_in: $td.find('.cell-in').val() || '',
                    punch_out: $td.find('.cell-out').val() || '',
                    leave_type: $td.find('.cell-leave-type').val() || '',
                    leave_half: $td.find('.cell-leave-half').val() || '',
                    touched: '1'
                };
                // Do not POST thousands of hidden fields (PHP max_input_vars truncates)
                $td.find('input').prop('disabled', true);
                if (!emp || !date) return;
                if (!payload[emp]) payload[emp] = {};
                payload[emp][date] = row;
            });

            if (!n) {
                e.preventDefault();
                $('#manualAttTable td.day-cell input').prop('disabled', false);
                alert('No cells changed. Click a day cell to edit (or Fill Present / Week Off), then Save.');
                return false;
            }

            if (!confirm('Save ' + n + ' edited day cell(s)?')) {
                e.preventDefault();
                $('#manualAttTable td.day-cell input').prop('disabled', false);
                return false;
            }

            $('#cellsJson').val(JSON.stringify(payload));
            $('#manualAttForm .js-save-att').prop('disabled', true).each(function () {
                $(this).html('<i class="fa-solid fa-spinner fa-spin"></i> Saving...');
            });
            return true;
        });
    });
})(jQuery);
