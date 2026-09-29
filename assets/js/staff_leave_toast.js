/**
 * Admin / HR / HR Head — leave applied toaster (once per browser session)
 * Rich card design; click opens leave queue and marks notification read.
 */
(function () {
    'use strict';

    var items = window.STAFF_LEAVE_APPLY_TOASTS;
    if (!window.toastr || !items || !items.length) {
        return;
    }

    var key = 'staff_leave_apply_toast_v1';
    try {
        if (sessionStorage.getItem(key) === '1') {
            return;
        }
        sessionStorage.setItem(key, '1');
    } catch (e) { /* ignore */ }

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    toastr.options = {
        closeButton: true,
        progressBar: true,
        positionClass: 'toast-top-right',
        timeOut: 10000,
        extendedTimeOut: 5000,
        newestOnTop: true,
        preventDuplicates: true,
        escapeHtml: false,
        tapToDismiss: false
    };

    items.forEach(function (item, i) {
        setTimeout(function () {
            var html =
                '<div class="hrms-leave-toast">' +
                    '<div class="hrms-leave-toast-top">' +
                        '<span class="hrms-leave-toast-ico"><i class="fa-solid fa-calendar-plus"></i></span>' +
                        '<div class="hrms-leave-toast-copy">' +
                            '<strong class="hrms-leave-toast-emp">' + esc(item.employee || 'Employee') + '</strong>' +
                            '<span class="hrms-leave-toast-type">' + esc(item.type || 'Leave') + '</span>' +
                        '</div>' +
                    '</div>' +
                    '<div class="hrms-leave-toast-meta">' +
                        '<span><i class="fa-solid fa-calendar-days"></i> ' + esc(item.dates || '') + '</span>' +
                        '<span><i class="fa-solid fa-clock"></i> ' + esc(item.days || '') + '</span>' +
                        (item.department
                            ? '<span><i class="fa-solid fa-building"></i> ' + esc(item.department) + '</span>'
                            : '') +
                    '</div>' +
                    (item.url
                        ? '<a class="hrms-leave-toast-link" href="' + esc(item.url) + '">' +
                            '<i class="fa-solid fa-arrow-right"></i> Review request' +
                          '</a>'
                        : '') +
                '</div>';

            toastr.info(html, esc(item.title || 'New Leave Application'), {
                toastClass: 'toast toast-info hrms-leave-toast-wrap',
                onclick: null
            });
        }, i * 500);
    });
})();
