(function () {
    if (!window.jQuery) return;
    var $ = jQuery;
    function reload() {
        var dt = $('#contractorTable').DataTable();
        if (dt) dt.ajax.reload();
    }
    $('#btnApplyOpsFilter').on('click', reload);
    $('#filter_operation, #filter_employee, #filter_month, #filter_year').on('change', reload);
})();
