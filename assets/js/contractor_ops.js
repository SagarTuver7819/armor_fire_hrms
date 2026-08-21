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
    var products = [];

    function opVal() {
        return $('#operationSelect').val() || '';
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
        $('#opsGrid').toggleClass('show-r', showR);
        $('#opsGrid').toggleClass('show-ot', showOt);
        $('#opsGrid').addClass('hide-grade');
        $('.r-field').prop('readonly', !showR);
        $('.ot-field').prop('readonly', !showOt);
        if (!showR) $('.r-field').val('');
        if (!showOt) $('.ot-field').val('');
        $('#tf_grand_total_label').attr('colspan', 3);
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
        if ($sel.data('select2')) {
            $sel.select2('destroy');
        }
        var html = '<option value="">Select Product</option>';
        products.forEach(function (p) {
            html += '<option value="' + p.id + '" data-rate="' + p.rate + '" data-ot="' + p.ot_rate + '" data-rej="' + p.rejection_rate + '" data-process="' + (p.process || '') + '"'
                + (String(p.id) === String(selectedId) ? ' selected' : '') + '>'
                + $('<div/>').text(p.name).html() + '</option>';
        });
        $sel.html(html);
        bindRowSelect2($sel, 'Select Product');
    }
    function loadProducts(cb) {
        var op = encodeURIComponent(opVal());
        $.getJSON(window.OPS_PRODUCTS_URL + '?operation=' + op).done(function (data) {
            products = (data && data.products) ? data.products : (Array.isArray(data) ? data : []);
            $('#opsRows .ops-row').each(function () {
                var $row = $(this);
                var sid = $row.find('.row-product-id').val() || $row.find('.product-select').val();
                fillProductSelect($row.find('.product-select'), sid);
                $row.find('.row-grade-id').val('0');
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
    $('#opsRows').on('click', '.btn-remove-row', function () {
        if ($('#opsRows .ops-row').length <= 1) return;
        var $row = $(this).closest('tr');
        $row.find('.product-select').each(function () {
            if ($(this).data('select2')) {
                $(this).select2('destroy');
            }
        });
        $row.remove();
        reindex();
        updateGrandTotals();
    });
    $('#btnAddProduct').on('click', function () {
        var $first = $('#opsRows .ops-row').first();
        $first.find('.product-select').each(function () {
            if ($(this).data('select2')) {
                $(this).select2('destroy');
            }
        });
        var $clone = $first.clone();
        $clone.find('.select2-container').remove();
        $clone.find('input').val('');
        $clone.find('.product-select').val('').removeAttr('data-select2-id').removeClass('select2-hidden-accessible');
        $clone.find('.product-select').find('option').removeAttr('data-select2-id');
        $clone.find('.row-product-id, .row-grade-id').val('0');
        $clone.find('.row-grade-id').val('0');
        fillProductSelect($clone.find('.product-select'), '');
        bindRowSelect2($first.find('.product-select'), 'Select Product');
        $('#opsRows').append($clone);
        reindex();
        applyDayEnable();
        applyOpMode();
        var wrap = document.querySelector('.ops-grid-wrap');
        if (wrap) {
            wrap.scrollTop = wrap.scrollHeight;
        }
        $clone.find('.product-select').select2('open');
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
