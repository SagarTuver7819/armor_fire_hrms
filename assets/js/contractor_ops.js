/**
 * Operations Rate List — daily qty grid
 * Amount formula matches Armor Steel contractor module.
 */
(function () {
    var grid = document.getElementById('opsGrid');
    if (!grid || !window.jQuery) return;

    var $ = window.jQuery;
    var repairOps = window.OPS_REPAIR || [];
    var otRepairOps = window.OPS_OT_REPAIR || [];
    var gradeOps = window.OPS_GRADE_OPS || ['CNC', 'BUFF', 'ARGON WELDING', 'GRINDING'];
    var products = [];
    var grades = [];

    function opVal() {
        return $('#operationSelect').val() || '';
    }
    function showsGrade(op) {
        op = (op || opVal() || '').toString();
        if (gradeOps.indexOf(op) !== -1) return true;
        return op.toUpperCase().indexOf('ARGON') === 0;
    }
    function daysInMonth(month, year) {
        return new Date(year, month, 0).getDate();
    }
    function applyDayEnable() {
        var month = parseInt($('#monthSelect').val(), 10) || 1;
        var year = parseInt($('#yearSelect').val(), 10) || new Date().getFullYear();
        var n = daysInMonth(month, year);
        var wds = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        $('[data-day]').each(function () {
            var d = parseInt($(this).attr('data-day'), 10);
            var off = d > n;
            $(this).toggleClass('is-off', off);
            $(this).find('input').prop('disabled', off);
            var $wd = $(this).find('.day-wd');
            if ($wd.length) {
                if (off) {
                    $wd.text('');
                } else {
                    $wd.text(wds[new Date(year, month - 1, d).getDay()]);
                }
            }
        });
    }
    function applyOpMode() {
        var op = opVal();
        var showR = repairOps.indexOf(op) !== -1;
        var showOt = otRepairOps.indexOf(op) !== -1;
        var showG = showsGrade(op);
        $('#opsGrid').toggleClass('show-r', showR);
        $('#opsGrid').toggleClass('show-ot', showOt);
        $('#opsGrid').toggleClass('show-grade', showG);
        $('#opsGrid').toggleClass('hide-grade', !showG);
        $('.r-field').prop('readonly', !showR);
        $('.ot-field').prop('readonly', !showOt);
        if (!showR) $('.r-field').val('');
        if (!showOt) $('.ot-field').val('');
        // Action + Sr + Process + (Grade?) + Rate
        $('#tf_grand_total_label').attr('colspan', showG ? 5 : 4);
    }
    function bindRowSelect2($sel, placeholder) {
        if (!$sel.length || !$.fn.select2) return;
        if ($sel.data('select2')) {
            $sel.off('change.select2ops');
            $sel.select2('destroy');
        }
        $sel.removeClass('no-select2');
        $sel.select2({
            width: '100%',
            placeholder: placeholder || 'Search & select',
            allowClear: true,
            minimumResultsForSearch: 0,
            dropdownParent: $(document.body),
            language: {
                noResults: function () { return 'No matching option'; },
                searching: function () { return 'Searching…'; }
            }
        });
    }
    function fillProductSelect($sel, selectedId) {
        if (!$sel.length) return;
        resetSelect2Artifacts($sel);
        var html = '<option value="">Select Process</option>';
        products.forEach(function (p) {
            html += '<option value="' + p.id + '" data-rate="' + p.rate + '" data-ot="' + p.ot_rate + '" data-rej="' + p.rejection_rate + '" data-process="' + (p.process || '') + '"'
                + (String(p.id) === String(selectedId) ? ' selected' : '') + '>'
                + $('<div/>').text(p.name).html() + '</option>';
        });
        $sel.html(html);
        bindRowSelect2($sel, 'Select Process');
    }
    function fillGradeSelect($sel, selectedId) {
        if (!$sel.length) return;
        resetSelect2Artifacts($sel);
        var html = '<option value="">Select Grade</option>';
        if (showsGrade()) {
            grades.forEach(function (g) {
                html += '<option value="' + g.id + '"'
                    + (String(g.id) === String(selectedId) ? ' selected' : '') + '>'
                    + $('<div/>').text(g.name).html() + '</option>';
            });
        }
        $sel.html(html);
        bindRowSelect2($sel, 'Select Grade');
        $sel.closest('tr').find('.row-grade-id').val($sel.val() || '0');
    }
    function loadProducts(cb) {
        var op = encodeURIComponent(opVal());
        $.getJSON(window.OPS_PRODUCTS_URL + '?operation=' + op)
            .done(function (data) {
                products = (data && data.products) ? data.products : (Array.isArray(data) ? data : []);
                grades = (data && data.grades) ? data.grades : [];
                applyOpMode();
                $('#opsRows .ops-row').each(function () {
                    var $row = $(this);
                    var sid = $row.find('.row-product-id').val() || $row.find('.product-select').val();
                    var gid = $row.find('.row-grade-id').val() || $row.find('.grade-select').val() || '';
                    fillProductSelect($row.find('.product-select'), sid);
                    fillGradeSelect($row.find('.grade-select'), showsGrade() ? gid : '');
                    var $opt = $row.find('.product-select option:selected');
                    if ($opt.val()) {
                        if (!$row.find('.rate').val() || parseFloat($row.find('.rate').val()) === 0) {
                            $row.find('.rate').val($opt.attr('data-rate') || 0);
                            $row.find('.row-ot-rate').val($opt.attr('data-ot') || 0);
                            $row.find('.row-rejection-rate').val($opt.attr('data-rej') || 0);
                        }
                        $row.find('.row-product-id').val($opt.val());
                    }
                });
                if (typeof cb === 'function') cb();
                recalcAll();
            })
            .fail(function () {
                products = [];
                grades = [];
                $('#opsRows .ops-row').each(function () {
                    fillProductSelect($(this).find('.product-select'), '');
                    fillGradeSelect($(this).find('.grade-select'), '');
                });
            });
    }
    function calculateRowTotals($row) {
        var op = opVal();
        var rate = parseFloat($row.find('.rate').val()) || 0;
        var otRate = parseFloat($row.find('.row-ot-rate').val()) || 0;
        var rejRate = parseFloat($row.find('.row-rejection-rate').val()) || 0;
        var normalQty = 0;
        var rQty = 0;
        var otQty = 0;
        $row.find('.day-qty').each(function () {
            if (!$(this).prop('disabled')) normalQty += parseFloat($(this).val()) || 0;
        });
        if (repairOps.indexOf(op) !== -1) {
            $row.find('.day-qty-r').each(function () {
                if (!$(this).prop('disabled')) rQty += parseFloat($(this).val()) || 0;
            });
        }
        if (otRepairOps.indexOf(op) !== -1) {
            $row.find('.day-qty-ot').each(function () {
                if (!$(this).prop('disabled')) otQty += parseFloat($(this).val()) || 0;
            });
        }
        var totalQty = normalQty + otQty;
        $row.find('.total-qty').val(parseFloat(totalQty.toFixed(2)));
        $row.find('.total-r').val(parseFloat(rQty.toFixed(2)));
        var totalAmt = 0;
        if (op === 'FOUNDRY') {
            totalAmt = (normalQty + rQty) * rate;
        } else if (repairOps.indexOf(op) !== -1) {
            totalAmt = (normalQty * rate) + (rQty * rejRate) + (otQty * otRate);
        } else {
            totalAmt = totalQty * rate;
        }
        $row.find('.total-amount').val(totalAmt.toFixed(2));
        updateGrandTotals();
    }
    function updateGrandTotals() {
        var gQty = 0, gR = 0, gAmt = 0;
        var dayQty = {};
        $('#opsRows .ops-row').each(function () {
            gQty += parseFloat($(this).find('.total-qty').val()) || 0;
            gR += parseFloat($(this).find('.total-r').val()) || 0;
            gAmt += parseFloat($(this).find('.total-amount').val()) || 0;
            $(this).find('.day-qty').each(function () {
                var d = $(this).closest('.day-cell').attr('data-day');
                dayQty[d] = (dayQty[d] || 0) + (parseFloat($(this).val()) || 0);
            });
        });
        $('#tf_total_qty').text(parseFloat(gQty.toFixed(2)));
        $('#tf_total_r').text(parseFloat(gR.toFixed(2)));
        $('#tf_amount').text(gAmt.toFixed(2));
        $('#sum_qty').text(parseFloat(gQty.toFixed(2)));
        $('#sum_r').text(parseFloat(gR.toFixed(2)));
        $('#sum_amt').text(gAmt.toFixed(2));
        $('.day-foot').each(function () {
            var d = $(this).attr('data-day');
            var v = dayQty[d] || 0;
            $(this).text(v ? parseFloat(v.toFixed(2)) : '');
        });
    }
    function reindex() {
        $('#opsRows .ops-row').each(function (i) {
            $(this).find('.ops-sr').text(i + 1);
            $(this).find('[name]').each(function () {
                var n = $(this).attr('name');
                if (!n) return;
                $(this).attr('name', n.replace(/items\[\d+]/, 'items[' + i + ']'));
            });
        });
    }
    function applyProductToRow($row) {
        var $opt = $row.find('.product-select option:selected');
        if (!$opt.val()) return;
        $row.find('.rate').val($opt.attr('data-rate') || 0);
        $row.find('.row-ot-rate').val($opt.attr('data-ot') || 0);
        $row.find('.row-rejection-rate').val($opt.attr('data-rej') || 0);
        $row.find('.row-product-id').val($opt.val());
        calculateRowTotals($row);
    }
    $('#opsRows').on('change.selectProduct change', '.product-select', function () {
        applyProductToRow($(this).closest('tr'));
    });
    $('#opsRows').on('change.selectGrade change', '.grade-select', function () {
        var $row = $(this).closest('tr');
        $row.find('.row-grade-id').val($(this).val() || '0');
    });
    $('#opsRows').on('input', '.day-qty, .day-qty-r, .day-qty-ot, .rate', function () {
        calculateRowTotals($(this).closest('tr'));
    });
    $('#opsRows').on('keydown', '.day-qty, .day-qty-r, .day-qty-ot', function (e) {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        var $cell = $(this).closest('.day-cell');
        var day = parseInt($cell.attr('data-day'), 10) || 1;
        var cls = this.className.indexOf('day-qty-r') !== -1 ? '.day-qty-r'
            : (this.className.indexOf('day-qty-ot') !== -1 ? '.day-qty-ot' : '.day-qty');
        var $row = $(this).closest('tr');
        var $next = $row.find('.day-cell[data-day="' + (day + 1) + '"]').find(cls).filter(':not(:disabled)');
        if ($next.length) {
            $next.focus().select();
        }
    });
    function destroyRowSelect2($row) {
        $row.find('.product-select, .grade-select').each(function () {
            resetSelect2Artifacts($(this));
        });
    }
    function doRemoveRow($row) {
        destroyRowSelect2($row);
        $row.remove();
        reindex();
        updateGrandTotals();
    }
    $('#opsRows').on('click', '.btn-remove-row', function () {
        if ($('#opsRows .ops-row').length <= 1) {
            if (window.ConfirmDelete) {
                window.ConfirmDelete.ask('At least one product row is required.', null, {
                    title: 'Cannot Delete',
                    yesLabel: 'OK'
                });
            }
            return;
        }
        var $row = $(this).closest('tr');
        var label = ($row.find('.product-select option:selected').text() || '').trim();
        if (!label || label === 'Select Process') {
            label = '';
        }
        var msg = 'You Want To Delete This Row?';
        if (label) {
            msg += '\n\n' + label;
        }
        function runDelete() {
            doRemoveRow($row);
        }
        if (window.ConfirmDelete && typeof window.ConfirmDelete.ask === 'function') {
            window.ConfirmDelete.ask(msg, runDelete, {
                title: 'Delete Row?',
                yesLabel: 'Yes, Delete'
            });
            return;
        }
        // Last-resort theme modal (never use browser alert)
        var box = document.createElement('div');
        box.className = 'confirm-modal';
        box.innerHTML =
            '<div class="confirm-modal-backdrop"></div>' +
            '<div class="confirm-modal-box">' +
            '<div class="confirm-modal-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>' +
            '<h3>Delete Row?</h3>' +
            '<p class="confirm-modal-msg" style="white-space:pre-line;"></p>' +
            '<div class="confirm-modal-actions">' +
            '<button type="button" class="btn-secondary js-tmp-cancel">Cancel</button>' +
            '<button type="button" class="btn-primary js-tmp-yes"><i class="fa-solid fa-trash-can"></i> Yes, Delete</button>' +
            '</div></div>';
        box.querySelector('.confirm-modal-msg').textContent = msg;
        document.body.appendChild(box);
        document.body.classList.add('confirm-modal-open');
        function closeTmp() {
            document.body.classList.remove('confirm-modal-open');
            box.remove();
        }
        box.querySelector('.js-tmp-cancel').onclick = closeTmp;
        box.querySelector('.confirm-modal-backdrop').onclick = closeTmp;
        box.querySelector('.js-tmp-yes').onclick = function () {
            closeTmp();
            runDelete();
        };
    });
    function resetSelect2Artifacts($sel) {
        if (!$sel || !$sel.length) return;
        if ($sel.data('select2')) {
            try { $sel.select2('destroy'); } catch (e) { /* ignore */ }
        }
        $sel.removeClass('select2-hidden-accessible')
            .removeAttr('data-select2-id')
            .removeAttr('tabindex')
            .removeAttr('aria-hidden')
            .removeAttr('aria-disabled')
            .removeData('select2');
        $sel.find('option').removeAttr('data-select2-id');
        $sel.closest('td').find('.select2-container').remove();
    }
    function clearRowInputs($row) {
        $row.find('input').each(function () {
            var $inp = $(this);
            if ($inp.hasClass('row-product-id') || $inp.hasClass('row-grade-id')) {
                $inp.val('0');
            } else {
                $inp.val('');
            }
        });
    }
    $('#btnAddProduct').on('click', function () {
        var $first = $('#opsRows .ops-row').first();
        if (!$first.length) return;

        var firstProductId = $first.find('.row-product-id').val() || $first.find('.product-select').val() || '';
        var firstGradeId = $first.find('.row-grade-id').val() || $first.find('.grade-select').val() || '';
        destroyRowSelect2($first);

        var $clone = $first.clone(false, false);
        $clone.find('.select2-container').remove();
        clearRowInputs($clone);
        destroyRowSelect2($clone);
        $clone.find('.product-select').empty().append('<option value="">Select Process</option>');
        $clone.find('.grade-select').empty().append('<option value="">Select Grade</option>');
        $clone.find('.btn-remove-row').attr('title', 'Delete row')
            .html('<i class="fa-solid fa-trash-can"></i>');

        $('#opsRows').append($clone);
        reindex();

        fillProductSelect($first.find('.product-select'), firstProductId);
        fillGradeSelect($first.find('.grade-select'), firstGradeId);
        fillProductSelect($clone.find('.product-select'), '');
        fillGradeSelect($clone.find('.grade-select'), '');

        applyDayEnable();
        applyOpMode();
        calculateRowTotals($clone);
        updateGrandTotals();

        var wrap = document.querySelector('.ops-grid-wrap');
        if (wrap) {
            wrap.scrollTop = wrap.scrollHeight;
        }
        setTimeout(function () {
            var $sel = $clone.find('.product-select');
            if ($sel.data('select2')) {
                $sel.select2('open');
            }
        }, 30);
    });
    $('#operationSelect').on('change', function () {
        applyOpMode();
        loadProducts();
    });
    $('#monthSelect, #yearSelect').on('change', function () {
        applyDayEnable();
        recalcAll();
    });
    function recalcAll() {
        applyDayEnable();
        applyOpMode();
        $('#opsRows .ops-row').each(function () {
            calculateRowTotals($(this));
        });
    }
    applyDayEnable();
    applyOpMode();
    loadProducts();
})();
