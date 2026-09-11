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
        var isFoundry = op === 'FOUNDRY';
        var showR = repairOps.indexOf(op) !== -1;
        var showOt = otRepairOps.indexOf(op) !== -1;
        var showG = showsGrade(op);
        $('#opsGrid').toggleClass('show-r', showR);
        $('#opsGrid').toggleClass('show-ot', showOt);
        $('#opsGrid').toggleClass('show-foundry', isFoundry);
        $('#opsGrid').toggleClass('show-grade', showG);
        $('#opsGrid').toggleClass('hide-grade', !showG);
        $('.r-field').prop('readonly', !showR);
        $('.ot-field').prop('readonly', !showOt);
        if (!showR) $('.r-field').val('');
        if (!showOt) $('.ot-field').val('');

        // Foundry: D / N with separate Day Qty Total & Night Qty Total (not combined)
        $('.q-lab').text(isFoundry ? 'D' : 'Q');
        $('.r-lab').text(isFoundry ? 'N' : 'R');
        $('#th_total_qty').text(isFoundry ? 'Day Qty Total' : 'TOTAL QTY');
        $('#th_total_r').text(isFoundry ? 'Night Qty Total' : 'TOTAL R');
        $('#sum_qty_lab').text(isFoundry ? 'Day Qty Total' : 'Qty');
        $('#sum_r_lab').text(isFoundry ? 'Night Qty Total' : 'R');

        // Action + Sr + Process + (Grade?) + Rate
        $('#tf_grand_total_label').attr('colspan', showG ? 5 : 4);
        // Cleanup Select2 leftovers / custom combo when Grade column hidden
        if (!showG) {
            $('#opsRows .grade-select').each(function () {
                teardownOpsCombo($(this));
            });
            $('#opsRows .ops-row').each(function () {
                $(this).children('.select2-container').remove();
            });
        }
        updateGrandTotals();
    }

    /* ========== Custom searchable combo (NO Select2 in sticky grid) ========== */
    var $opsComboPanel = null;
    var opsComboActiveSel = null;

    function ensureOpsComboPanel() {
        if ($opsComboPanel && $opsComboPanel.length) return $opsComboPanel;
        $opsComboPanel = $(
            '<div id="opsComboPanel" class="ops-combo-panel" hidden>' +
            '<div class="ops-combo-search-wrap">' +
            '<input type="search" class="ops-combo-search" placeholder="Type to search…" autocomplete="off">' +
            '</div>' +
            '<ul class="ops-combo-list" role="listbox"></ul>' +
            '</div>'
        );
        $(document.body).append($opsComboPanel);

        $opsComboPanel.on('input', '.ops-combo-search', function () {
            filterOpsComboList($(this).val());
        });
        $opsComboPanel.on('keydown', '.ops-combo-search', function (e) {
            if (e.key === 'Escape') {
                e.preventDefault();
                closeOpsCombo();
            } else if (e.key === 'Enter') {
                e.preventDefault();
                var $hi = $opsComboPanel.find('.ops-combo-item.is-active:visible').first();
                if (!$hi.length) $hi = $opsComboPanel.find('.ops-combo-item:visible').first();
                if ($hi.length) $hi.trigger('click');
            } else if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                var $items = $opsComboPanel.find('.ops-combo-item:visible');
                if (!$items.length) return;
                var idx = $items.index($items.filter('.is-active'));
                $items.removeClass('is-active');
                if (e.key === 'ArrowDown') idx = idx < $items.length - 1 ? idx + 1 : 0;
                else idx = idx > 0 ? idx - 1 : $items.length - 1;
                var $next = $items.eq(idx).addClass('is-active');
                if ($next[0] && $next[0].scrollIntoView) $next[0].scrollIntoView({ block: 'nearest' });
            }
        });
        $opsComboPanel.on('mousedown', '.ops-combo-item', function (e) {
            e.preventDefault();
            pickOpsCombo($(this));
        });
        $(document).on('mousedown.opsCombo', function (e) {
            if (!$opsComboPanel || !$opsComboPanel.length || $opsComboPanel[0].hasAttribute('hidden')) return;
            if ($(e.target).closest('#opsComboPanel, .ops-combo-trigger').length) return;
            closeOpsCombo();
        });
        $(window).on('resize.opsCombo', function () {
            if (opsComboActiveSel) positionOpsComboPanel();
        });
        return $opsComboPanel;
    }

    function filterOpsComboList(q) {
        q = (q || '').toString().toLowerCase().trim();
        var $list = $opsComboPanel.find('.ops-combo-list');
        $list.find('.ops-combo-empty').remove();
        var $items = $list.find('.ops-combo-item');
        var visible = 0;
        $items.each(function () {
            var text = ($(this).text() || '').toLowerCase();
            var show = !q || text.indexOf(q) !== -1;
            this.style.display = show ? '' : 'none';
            if (show) visible += 1;
        });
        $items.removeClass('is-active');
        $items.filter(function () { return this.style.display !== 'none'; }).first().addClass('is-active');
        if (!visible) {
            $list.append('<li class="ops-combo-empty">No matching option</li>');
        }
    }

    function positionOpsComboPanel() {
        if (!opsComboActiveSel || !$opsComboPanel) return;
        var $td = opsComboActiveSel.closest('td');
        var $trig = $td.find('.ops-combo-trigger');
        if (!$trig.length) return;
        // Inline under trigger inside sticky cell — cannot drift to Action/Sr
        if ($opsComboPanel.parent()[0] !== $td[0]) {
            $td.append($opsComboPanel);
        }
        $td.addClass('ops-combo-open-cell');
        $opsComboPanel.addClass('ops-combo-inline').css({
            position: 'absolute',
            left: '4px',
            right: '4px',
            top: '100%',
            width: 'auto',
            zIndex: 60
        }).removeAttr('hidden');
        $opsComboPanel.find('.ops-combo-list').css('max-height', '260px');
    }

    function openOpsCombo($sel) {
        ensureOpsComboPanel();
        closeOpsCombo();
        opsComboActiveSel = $sel;
        var html = '';
        $sel.find('option').each(function () {
            var val = $(this).attr('value');
            var text = $(this).text();
            if (val === '' || val === undefined) {
                html += '<li class="ops-combo-item ops-combo-clear" data-value="">' + $('<div/>').text(text || '— Clear —').html() + '</li>';
            } else {
                var sel = String($sel.val()) === String(val) ? ' is-selected' : '';
                html += '<li class="ops-combo-item' + sel + '" data-value="' + String(val).replace(/"/g, '&quot;') + '">'
                    + $('<div/>').text(text).html() + '</li>';
            }
        });
        $opsComboPanel.find('.ops-combo-list').html(html);
        $opsComboPanel.find('.ops-combo-search').val('');
        filterOpsComboList('');
        $sel.closest('td').find('.ops-combo-trigger').addClass('is-open');
        positionOpsComboPanel();
        setTimeout(function () {
            var inp = $opsComboPanel.find('.ops-combo-search')[0];
            if (inp) {
                try { inp.focus({ preventScroll: true }); } catch (e) { /* ignore */ }
            }
            positionOpsComboPanel();
        }, 0);
    }

    function closeOpsCombo() {
        $('.ops-combo-open-cell').removeClass('ops-combo-open-cell');
        if ($opsComboPanel) {
            $opsComboPanel.attr('hidden', 'hidden').removeClass('ops-combo-inline');
            $opsComboPanel.find('.ops-combo-search').val('');
            if ($opsComboPanel.parent()[0] !== document.body) {
                $(document.body).append($opsComboPanel);
            }
        }
        $('.ops-combo-trigger.is-open').removeClass('is-open');
        opsComboActiveSel = null;
    }

    function pickOpsCombo($item) {
        if (!opsComboActiveSel) return;
        var $sel = opsComboActiveSel;
        var val = $item.attr('data-value');
        $sel.val(val === undefined ? '' : val).trigger('change');
        syncOpsComboTrigger($sel);
        closeOpsCombo();
    }

    function syncOpsComboTrigger($sel) {
        var $trig = $sel.closest('td').find('.ops-combo-trigger');
        if (!$trig.length) return;
        var $opt = $sel.find('option:selected');
        var text = ($opt.text() || '').trim();
        var empty = !$opt.val();
        $trig.toggleClass('is-placeholder', empty);
        $trig.find('.ops-combo-label').text(empty ? ($sel.data('placeholder') || 'Select') : text);
    }

    function teardownOpsCombo($sel) {
        if (!$sel || !$sel.length) return;
        if (opsComboActiveSel && opsComboActiveSel[0] === $sel[0]) closeOpsCombo();
        $sel.closest('td').find('.ops-combo-trigger').remove();
        $sel.removeClass('ops-combo-native');
        resetSelect2Artifacts($sel);
    }

    function bindOpsCombo($sel, placeholder) {
        if (!$sel.length) return;
        teardownOpsCombo($sel);
        $sel.addClass('no-select2 ops-combo-native')
            .attr('tabindex', '-1')
            .data('placeholder', placeholder || 'Select');

        var $td = $sel.closest('td');
        $td.find('.ops-combo-trigger, .select2-container').remove();
        var $trig = $(
            '<button type="button" class="ops-combo-trigger">' +
            '<span class="ops-combo-label"></span>' +
            '<span class="ops-combo-caret"><i class="fa-solid fa-chevron-down"></i></span>' +
            '</button>'
        );
        $sel.after($trig);
        syncOpsComboTrigger($sel);

        $trig.on('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            applyCrosshair($sel);
            if (opsComboActiveSel && opsComboActiveSel[0] === $sel[0] && $opsComboPanel && !$opsComboPanel.is('[hidden]')) {
                closeOpsCombo();
                return;
            }
            openOpsCombo($sel);
        });
    }

    function fillProductSelect($sel, selectedId) {
        if (!$sel.length) return;
        var prevLabel = '';
        if (selectedId) {
            prevLabel = ($sel.find('option:selected').text() || '').trim();
            if (!prevLabel) {
                prevLabel = ($sel.closest('td').find('.ops-combo-label').text() || '').trim();
            }
        }
        var html = '<option value="">Select Process</option>';
        var hasSelected = false;
        products.forEach(function (p) {
            var selAttr = String(p.id) === String(selectedId) ? ' selected' : '';
            if (selAttr) hasSelected = true;
            html += '<option value="' + p.id + '" data-rate="' + p.rate + '" data-ot="' + p.ot_rate + '" data-rej="' + p.rejection_rate + '" data-process="' + (p.process || '') + '"'
                + selAttr + '>'
                + $('<div/>').text(p.name).html() + '</option>';
        });
        // Old duplicate product id → map to unique label match
        if (selectedId && !hasSelected && prevLabel) {
            products.forEach(function (p) {
                if (hasSelected) return;
                if (String(p.name).toLowerCase() === String(prevLabel).toLowerCase()) {
                    selectedId = p.id;
                    hasSelected = true;
                }
            });
            if (hasSelected) {
                html = '<option value="">Select Process</option>';
                products.forEach(function (p) {
                    html += '<option value="' + p.id + '" data-rate="' + p.rate + '" data-ot="' + p.ot_rate + '" data-rej="' + p.rejection_rate + '" data-process="' + (p.process || '') + '"'
                        + (String(p.id) === String(selectedId) ? ' selected' : '') + '>'
                        + $('<div/>').text(p.name).html() + '</option>';
                });
            }
        }
        $sel.html(html);
        if (selectedId) $sel.val(String(selectedId));
        bindOpsCombo($sel, 'Select Process');
    }
    function fillGradeSelect($sel, selectedId) {
        if (!$sel.length) return;
        teardownOpsCombo($sel);

        if (!showsGrade()) {
            $sel.html('<option value="">Select Grade</option>');
            $sel.val('');
            $sel.closest('tr').find('.row-grade-id').val('0');
            return;
        }

        var html = '<option value="">Select Grade</option>';
        grades.forEach(function (g) {
            html += '<option value="' + g.id + '"'
                + (String(g.id) === String(selectedId) ? ' selected' : '') + '>'
                + $('<div/>').text(g.name).html() + '</option>';
        });
        $sel.html(html);
        if (selectedId) $sel.val(String(selectedId));
        bindOpsCombo($sel, 'Select Grade');
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
        // Foundry: show Day & Night separately (not combined as one Qty)
        if (op === 'FOUNDRY') {
            totalQty = normalQty;
        }
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
    function fmtFoot(v) {
        v = parseFloat(v) || 0;
        return v ? parseFloat(v.toFixed(2)) : '';
    }
    function updateGrandTotals() {
        var op = opVal();
        var showR = repairOps.indexOf(op) !== -1;
        var showOt = otRepairOps.indexOf(op) !== -1;
        var gQty = 0, gR = 0, gAmt = 0;
        var dayQ = {};
        var dayR = {};
        var dayOt = {};
        $('#opsRows .ops-row').each(function () {
            gQty += parseFloat($(this).find('.total-qty').val()) || 0;
            gR += parseFloat($(this).find('.total-r').val()) || 0;
            gAmt += parseFloat($(this).find('.total-amount').val()) || 0;
            $(this).find('.day-qty').each(function () {
                var d = $(this).closest('.day-cell').attr('data-day');
                dayQ[d] = (dayQ[d] || 0) + (parseFloat($(this).val()) || 0);
            });
            if (showR) {
                $(this).find('.day-qty-r').each(function () {
                    var d = $(this).closest('.day-cell').attr('data-day');
                    dayR[d] = (dayR[d] || 0) + (parseFloat($(this).val()) || 0);
                });
            }
            if (showOt) {
                $(this).find('.day-qty-ot').each(function () {
                    var d = $(this).closest('.day-cell').attr('data-day');
                    dayOt[d] = (dayOt[d] || 0) + (parseFloat($(this).val()) || 0);
                });
            }
        });
        $('#tf_total_qty').text(parseFloat(gQty.toFixed(2)));
        $('#tf_total_r').text(parseFloat(gR.toFixed(2)));
        $('#tf_amount').text(gAmt.toFixed(2));
        $('#sum_qty').text(parseFloat(gQty.toFixed(2)));
        $('#sum_r').text(parseFloat(gR.toFixed(2)));
        $('#sum_amt').text(gAmt.toFixed(2));
        $('.day-foot').each(function () {
            var d = $(this).attr('data-day');
            var $q = $(this).find('.foot-q');
            var $r = $(this).find('.foot-r');
            var $ot = $(this).find('.foot-ot');
            if (!$q.length) {
                // fallback for old markup
                var onlyQ = dayQ[d] || 0;
                $(this).text(onlyQ ? parseFloat(onlyQ.toFixed(2)) : '');
                return;
            }
            $q.text(fmtFoot(dayQ[d]));
            $r.text(showR ? fmtFoot(dayR[d]) : '');
            $ot.text(showOt ? fmtFoot(dayOt[d]) : '');
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
            teardownOpsCombo($(this));
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
        $clone.find('.select2-container, .ops-combo-trigger').remove();
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
            if ($sel.length) openOpsCombo($sel);
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
    function clearCrosshair() {
        $('#opsGrid')
            .find('.cross-row, .cross-col, .cross-cell')
            .removeClass('cross-row cross-col cross-cell');
    }

    function applyCrosshair($el) {
        clearCrosshair();
        var $td = $el.closest('td');
        var $tr = $el.closest('tr.ops-row');
        if (!$tr.length || !$td.length) {
            return;
        }
        $tr.addClass('cross-row');
        $td.addClass('cross-cell');

        var day = $td.attr('data-day');
        if (day) {
            $('#opsGrid').find('th[data-day="' + day + '"], td[data-day="' + day + '"]').addClass('cross-col');
        } else if ($td.hasClass('sticky-name')) {
            $('#opsGrid thead th.sticky-name, #opsGrid tbody td.sticky-name').addClass('cross-col');
        } else if ($td.hasClass('sticky-grade') || $td.hasClass('td-grade')) {
            $('#opsGrid thead th.sticky-grade, #opsGrid tbody td.sticky-grade, #opsGrid tbody td.td-grade').addClass('cross-col');
        } else if ($td.hasClass('sticky-rate')) {
            $('#opsGrid thead th.sticky-rate, #opsGrid tbody td.sticky-rate').addClass('cross-col');
        }
    }

    // Crosshair: full row + full column when cell focused (theme highlight)
    $('#opsGrid').on('focusin', 'input, .ops-combo-trigger', function () {
        applyCrosshair($(this));
    });
    $('#opsGrid').on('click', 'td.day-cell, td.sticky-name, td.sticky-grade, td.sticky-rate, td.td-grade', function (e) {
        if ($(e.target).closest('.ops-combo-trigger, .ops-combo-panel').length) {
            return;
        }
        if ($(e.target).is('input, button, select')) {
            return;
        }
        var $td = $(this);
        var $trig = $td.find('.ops-combo-trigger').first();
        if ($trig.length) {
            $trig.trigger('click');
            return;
        }
        var $input = $td.find('input:not([disabled]):not([readonly])').filter(':visible').first();
        if ($input.length) {
            $input.trigger('focus');
        } else {
            applyCrosshair($td);
        }
    });

    // Recover leftover Select2 / body styles from older buggy builds
    try {
        document.body.style.removeProperty('position');
        document.body.style.removeProperty('top');
        document.body.style.removeProperty('left');
        document.body.style.removeProperty('width');
        document.body.style.removeProperty('right');
        document.body.style.removeProperty('transform');
        document.body.classList.remove('ops-select2-fixed', 'ops-dd-fixed');
        var oldPortal = document.getElementById('opsSelect2Portal');
        if (oldPortal) oldPortal.remove();
        $('.select2-container.ops-dd-fixed').remove();
        closeOpsCombo();
    } catch (e) { /* ignore */ }

    applyDayEnable();
    applyOpMode();
    loadProducts();
})();
