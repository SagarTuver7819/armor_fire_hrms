/**
 * Department → Sub Department cascade for select boxes.
 */
(function () {
    if (!window.jQuery) return;
    var $ = window.jQuery;
    var deptSel = window.SUBDEPT_DEPT_SEL || 'select[name="department_id"]';
    var subSel = window.SUBDEPT_SUB_SEL || 'select[name="sub_department_id"]';
    var url = window.SUBDEPT_URL;
    if (!url || !document.querySelector(subSel)) return;

    function selectedId() {
        return window.SUBDEPT_SELECTED || $(subSel).attr('data-selected') || $(subSel).val() || '';
    }

    function fill(rows, keepId) {
        var html = '<option value="">Select Sub Department</option>';
        (rows || []).forEach(function (r) {
            html += '<option value="' + r.id + '"'
                + (String(r.id) === String(keepId) ? ' selected' : '') + '>'
                + $('<div/>').text(r.name).html() + '</option>';
        });
        var $sub = $(subSel);
        if ($sub.hasClass('select2-hidden-accessible') && $sub.data('select2')) {
            $sub.select2('destroy');
        }
        $sub.html(html);
        if (keepId) {
            $sub.val(String(keepId));
        }
        if ($.fn.select2) {
            $sub.select2({ width: '100%', placeholder: 'Select Sub Department', allowClear: true });
        }
        $sub.trigger('change');
    }

    function load(reset) {
        var deptId = $(deptSel).val() || 0;
        var keep = reset ? '' : selectedId();
        if (!deptId) {
            fill([], '');
            return;
        }
        $.getJSON(url, { department_id: deptId }).done(function (rows) {
            fill(rows, keep);
            window.SUBDEPT_SELECTED = '';
        });
    }

    $(deptSel).on('change', function () {
        load(true);
    });
    load(false);
})();
