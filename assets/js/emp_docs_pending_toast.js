/**
 * Admin/HR toaster — document uploads + pending related documents (once per browser session)
 */
(function () {
    if (!window.toastr) {
        return;
    }
    var key = 'emp_rel_docs_toast_v1';
    try {
        if (sessionStorage.getItem(key) === '1') {
            return;
        }
        sessionStorage.setItem(key, '1');
    } catch (e) { /* ignore */ }

    toastr.options = toastr.options || {};
    toastr.options.closeButton = true;
    toastr.options.progressBar = true;
    toastr.options.positionClass = 'toast-top-right';
    toastr.options.timeOut = 8000;
    toastr.options.extendedTimeOut = 4000;
    toastr.options.newestOnTop = true;

    var delay = 0;
    var uploads = window.EMP_DOC_UPLOAD_TOASTS || [];
    uploads.forEach(function (msg) {
        setTimeout(function () {
            toastr.success(msg, 'Document Uploaded');
        }, delay);
        delay += 400;
    });

    var pending = window.EMP_DOC_PENDING_TOASTS || [];
    pending.forEach(function (msg) {
        setTimeout(function () {
            toastr.warning(msg, 'Pending Documents');
        }, delay);
        delay += 450;
    });
})();
