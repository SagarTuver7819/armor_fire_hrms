$(function () {
    if (!$('#attendanceTable').length || typeof $.fn.DataTable === 'undefined') {
        return;
    }
    $('#attendanceTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: { url: window.ATT_AJAX_URL, type: 'POST' },
        order: [[3, 'desc']],
        pageLength: 25,
        columns: [
            { data: 0, orderable: false },
            { data: 1 },
            { data: 2 },
            { data: 3 },
            { data: 4 },
            { data: 5 },
            { data: 6 },
            { data: 7 },
            { data: 8 }
        ]
    });
});
