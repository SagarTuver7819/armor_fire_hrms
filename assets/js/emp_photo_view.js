/**
 * Click-to-view profile photo lightbox ([data-photo-url])
 */
(function () {
    'use strict';

    var overlay = null;

    function ensureOverlay() {
        if (overlay) return overlay;
        overlay = document.createElement('div');
        overlay.className = 'emp-photo-lightbox';
        overlay.hidden = true;
        overlay.innerHTML =
            '<div class="emp-photo-lightbox-inner" role="dialog" aria-modal="true" aria-label="Profile photo">' +
            '<button type="button" class="emp-photo-lightbox-close" aria-label="Close">&times;</button>' +
            '<img class="emp-photo-lightbox-img" alt="Profile photo">' +
            '</div>';
        document.body.appendChild(overlay);

        overlay.addEventListener('click', function (e) {
            if (e.target === overlay || e.target.classList.contains('emp-photo-lightbox-close')) {
                closeLightbox();
            }
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && overlay && !overlay.hidden) {
                closeLightbox();
            }
        });
        return overlay;
    }

    function openLightbox(url) {
        if (!url) return;
        var box = ensureOverlay();
        var img = box.querySelector('.emp-photo-lightbox-img');
        img.src = url;
        box.hidden = false;
        document.body.classList.add('emp-photo-lightbox-open');
    }

    function closeLightbox() {
        if (!overlay) return;
        overlay.hidden = true;
        document.body.classList.remove('emp-photo-lightbox-open');
        var img = overlay.querySelector('.emp-photo-lightbox-img');
        if (img) img.removeAttribute('src');
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-photo-url]');
        if (!btn) return;
        // Don't steal clicks from file inputs / crop camera
        if (e.target.closest('.emp-avatar-camera, .emp-avatar-change-btn, input[type="file"]')) {
            return;
        }
        var url = btn.getAttribute('data-photo-url') || '';
        if (url === '') return;
        e.preventDefault();
        e.stopPropagation();
        openLightbox(url);
    });

    window.EmpPhotoLightbox = { open: openLightbox, close: closeLightbox };
})();
