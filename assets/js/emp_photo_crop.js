/**
 * Employee profile photo: pick → square crop → preview → save
 */
(function () {
    'use strict';

    var input = document.getElementById('empOwnPhotoInput');
    var preview = document.getElementById('empOwnPhotoPreview');
    var initials = document.getElementById('empOwnPhotoInitials');
    var saveBtn = document.getElementById('empOwnPhotoSave');
    var modal = document.getElementById('empPhotoCropModal');
    var cropImg = document.getElementById('empPhotoCropImage');
    var btnClose = document.getElementById('empPhotoCropClose');
    var btnCancel = document.getElementById('empPhotoCropCancel');
    var btnApply = document.getElementById('empPhotoCropApply');
    var btnRotate = document.getElementById('empPhotoCropRotate');
    var changeBtn = document.getElementById('empOwnPhotoChangeBtn');
    var wrap = document.getElementById('empOwnPhotoWrap');

    if (!input || !preview || !modal || !cropImg || typeof Cropper === 'undefined') {
        return;
    }

    if (changeBtn) {
        changeBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            input.click();
        });
    }
    // No photo yet: click avatar opens file picker
    if (wrap && (!wrap.getAttribute('data-photo-url'))) {
        wrap.addEventListener('click', function (e) {
            if (e.target.closest('.emp-avatar-change-btn')) return;
            input.click();
        });
        wrap.style.cursor = 'pointer';
    }

    var cropper = null;
    var objectUrl = null;

    function revokeUrl() {
        if (objectUrl) {
            URL.revokeObjectURL(objectUrl);
            objectUrl = null;
        }
    }

    function destroyCropper() {
        if (cropper) {
            cropper.destroy();
            cropper = null;
        }
    }

    function closeModal() {
        modal.hidden = true;
        document.body.classList.remove('emp-photo-crop-open');
        destroyCropper();
        revokeUrl();
        cropImg.removeAttribute('src');
        // Clear unfinished pick so same file can be re-selected
        if (!saveBtn || saveBtn.hidden) {
            input.value = '';
        }
    }

    function openModal(file) {
        revokeUrl();
        destroyCropper();
        objectUrl = URL.createObjectURL(file);
        cropImg.onload = function () {
            cropImg.onload = null;
            cropper = new Cropper(cropImg, {
                aspectRatio: 1,
                viewMode: 1,
                dragMode: 'move',
                autoCropArea: 0.9,
                responsive: true,
                background: false,
                guides: true,
                center: true,
                highlight: false,
                cropBoxMovable: true,
                cropBoxResizable: true,
                toggleDragModeOnDblclick: false
            });
        };
        cropImg.src = objectUrl;
        modal.hidden = false;
        document.body.classList.add('emp-photo-crop-open');
    }

    input.addEventListener('change', function () {
        var file = input.files && input.files[0];
        if (!file) return;
        if (!/^image\/(jpeg|png|webp)$/i.test(file.type) && !/\.(jpe?g|png|webp)$/i.test(file.name)) {
            alert('Photo must be JPG, PNG, or WEBP.');
            input.value = '';
            return;
        }
        if (file.size > 5 * 1024 * 1024) {
            alert('Photo must be 5MB or smaller.');
            input.value = '';
            return;
        }
        openModal(file);
    });

    function onCancel() {
        closeModal();
    }

    if (btnClose) btnClose.addEventListener('click', onCancel);
    if (btnCancel) btnCancel.addEventListener('click', onCancel);

    modal.addEventListener('click', function (e) {
        if (e.target === modal) onCancel();
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !modal.hidden) onCancel();
    });

    if (btnRotate) {
        btnRotate.addEventListener('click', function () {
            if (cropper) cropper.rotate(90);
        });
    }

    if (btnApply) {
        btnApply.addEventListener('click', function () {
            if (!cropper) return;
            btnApply.disabled = true;
            var canvas = cropper.getCroppedCanvas({
                width: 600,
                height: 600,
                imageSmoothingEnabled: true,
                imageSmoothingQuality: 'high'
            });
            if (!canvas) {
                btnApply.disabled = false;
                alert('Could not crop photo. Please try again.');
                return;
            }
            canvas.toBlob(function (blob) {
                btnApply.disabled = false;
                if (!blob) {
                    alert('Could not crop photo. Please try again.');
                    return;
                }
                var fileName = 'profile_photo.jpg';
                var file;
                try {
                    file = new File([blob], fileName, { type: 'image/jpeg', lastModified: Date.now() });
                } catch (err) {
                    file = blob;
                    file.name = fileName;
                }
                try {
                    var dt = new DataTransfer();
                    dt.items.add(file);
                    input.files = dt.files;
                } catch (err2) {
                    alert('Your browser could not prepare the cropped photo. Please try Chrome or Edge.');
                    return;
                }
                preview.src = URL.createObjectURL(blob);
                preview.hidden = false;
                if (initials) initials.hidden = true;
                if (saveBtn) saveBtn.hidden = false;
                if (wrap) {
                    wrap.setAttribute('data-photo-url', preview.src);
                    wrap.classList.add('emp-photo-view-btn');
                    wrap.title = 'Click to view · camera to change';
                }
                modal.hidden = true;
                document.body.classList.remove('emp-photo-crop-open');
                destroyCropper();
                revokeUrl();
            }, 'image/jpeg', 0.92);
        });
    }
})();
