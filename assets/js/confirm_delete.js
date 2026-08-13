/**
 * Delete confirmation modal — all grid delete buttons (.btn-delete)
 */
(function () {
    var modal = document.getElementById('confirmDeleteModal');
    if (!modal) return;

    var msgEl = modal.querySelector('.confirm-modal-msg');
    var yesBtn = modal.querySelector('.confirm-yes');
    var cancelBtn = modal.querySelector('.confirm-cancel');
    var backdrop = modal.querySelector('.confirm-modal-backdrop');
    var pendingHref = null;

    function closeModal() {
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('confirm-modal-open');
        pendingHref = null;
    }

    function openModal(name, href) {
        pendingHref = href;
        if (msgEl) {
            msgEl.textContent = 'Are you sure you want to delete "' + name + '"? This action cannot be undone.';
        }
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('confirm-modal-open');
        if (yesBtn) yesBtn.focus();
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.btn-delete');
        if (!btn) return;

        e.preventDefault();
        e.stopPropagation();

        var name = btn.getAttribute('data-name') || 'this record';
        var href = btn.getAttribute('href');
        if (!href) return;

        openModal(name, href);
    }, true);

    if (yesBtn) {
        yesBtn.addEventListener('click', function () {
            if (pendingHref) {
                window.location.href = pendingHref;
            }
            closeModal();
        });
    }

    if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
    if (backdrop) backdrop.addEventListener('click', closeModal);

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !modal.hidden) {
            closeModal();
        }
    });
})();
