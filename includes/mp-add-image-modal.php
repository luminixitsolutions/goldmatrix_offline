<?php
/**
 * Multi-image upload modal (same structure as includes/common-modal.php — sale invoice flow).
 * Used by manufacturing-process.php for job card + Jobwork Queue images.
 */
?>
<style>
/* Stack above Jobwork Queue overlay (.jwq-modal-overlay z-index: 1500) */
#addImageModal.modal {
    z-index: 10050 !important;
}
body > .modal-backdrop {
    z-index: 10040 !important;
}
</style>
<div class="modal fade" id="addImageModal" tabindex="-1" role="dialog" aria-labelledby="addImageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 480px;">
        <div class="modal-content">
            <div class="modal-header" style="border-bottom: 1px solid #e2e8f0;">
                <h5 class="modal-title" id="addImageModalLabel">Add Image</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close" id="addImageModalClose">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" style="padding: 1.25rem;">
                <div class="d-flex align-items-stretch" style="gap: 0.75rem;">
                    <div id="addImagePreviewWrap" style="flex: 1; min-height: 180px; border: 1px dashed #cbd5e1; border-radius: 8px; display: flex; align-items: center; justify-content: center; background: #f8fafc; overflow: hidden;">
                        <div id="addImagePreviewPlaceholder" class="text-center text-muted" style="padding: 1rem;">
                            <i class="feather icon-image" style="font-size: 2rem; display: block; margin-bottom: 0.5rem;"></i>
                            <span style="font-size: 0.8rem;">NO PREVIEW AVAILABLE</span>
                        </div>
                        <img id="addImagePreviewImg" src="" alt="Primary" style="max-width: 100%; max-height: 200px; object-fit: contain; display: none; border-radius: 6px; cursor: default;">
                    </div>
                    <div class="d-flex flex-column" style="gap: 0.5rem;">
                        <div id="addImageThumbnailsWrap" class="d-flex flex-wrap" style="gap: 0.5rem; max-width: 120px;">
                            <div id="addImageUploadZone" style="width: 70px; height: 70px; border: 2px dashed #94a3b8; border-radius: 8px; display: flex; align-items: center; justify-content: center; background: #f1f5f9; cursor: pointer; transition: background 0.2s; flex-shrink: 0;">
                                <input type="file" id="addImageModalFileInput" accept="image/*" multiple style="display: none;">
                                <i class="feather icon-upload" style="font-size: 1.5rem; color: #64748b;"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <p class="text-muted small mt-2 mb-0">Click the upload area or use the camera below to add images. Click a thumbnail to set as primary.</p>
            </div>
            <div class="modal-footer" style="border-top: 1px solid #e2e8f0; padding: 0.75rem 1.25rem;">
                <button type="button" class="btn btn-outline-secondary btn-sm" id="addImageModalCameraBtn" title="Select image(s)">
                    <i class="feather icon-camera" style="font-size: 1.1rem;"></i>
                </button>
                <div class="ml-auto">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-purple btn-sm" id="addImageModalSaveBtn">Save</button>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    var __jwqImgState = {
        items: [],
        primaryIndex: 0,
        jwoId: 0,
        bound: false
    };

    function jwqImgPrimarySrc() {
        var it = __jwqImgState.items[__jwqImgState.primaryIndex];
        if (!it) return '';
        return it.src || it.data || '';
    }

    function jwqImgRenderModalPreview() {
        var placeholder = document.getElementById('addImagePreviewPlaceholder');
        var previewImg = document.getElementById('addImagePreviewImg');
        var primaryUrl = jwqImgPrimarySrc();
        if (placeholder) placeholder.style.display = primaryUrl ? 'none' : '';
        if (previewImg) {
            if (primaryUrl) {
                previewImg.src = primaryUrl;
                previewImg.style.display = 'block';
            } else {
                previewImg.style.display = 'none';
                previewImg.src = '';
            }
        }
        var wrap = document.getElementById('addImageThumbnailsWrap');
        if (!wrap) return;
        var uploadZone = document.getElementById('addImageUploadZone');
        var existingAddZone = wrap.querySelector('#addImageUploadZone');
        var thumbContainer = wrap.querySelector('.addImage-thumb-list');
        if (thumbContainer) thumbContainer.remove();
        var list = document.createElement('div');
        list.className = 'addImage-thumb-list d-flex flex-wrap';
        list.style.gap = '0.5rem';
        __jwqImgState.items.forEach(function (it, idx) {
            var src = it.src || it.data || '';
            var box = document.createElement('div');
            box.style.cssText = 'width: 70px; height: 70px; border-radius: 8px; overflow: hidden; position: relative; border: 2px solid ' + (idx === __jwqImgState.primaryIndex ? '#11294b' : '#e2e8f0') + '; cursor: pointer; flex-shrink: 0;';
            var img = document.createElement('img');
            img.src = src;
            img.alt = 'Image ' + (idx + 1);
            img.style.cssText = 'width: 100%; height: 100%; object-fit: cover;';
            box.appendChild(img);
            var x = document.createElement('span');
            x.setAttribute('aria-label', 'Remove');
            x.style.cssText = 'position: absolute; top: 2px; right: 2px; width: 20px; height: 20px; background: rgba(0,0,0,0.6); color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px; line-height: 1; cursor: pointer;';
            x.textContent = '\u00d7';
            (function (rmIdx) {
                x.onclick = function (ev) {
                    ev.stopPropagation();
                    jwqImgRemoveAt(rmIdx);
                };
            })(idx);
            box.appendChild(x);
            (function (pi) {
                box.onclick = function (ev) {
                    if (ev.target === x) return;
                    __jwqImgState.primaryIndex = pi;
                    jwqImgRenderModalPreview();
                };
            })(idx);
            list.appendChild(box);
        });
        if (existingAddZone && existingAddZone.parentNode) {
            existingAddZone.parentNode.insertBefore(list, existingAddZone.nextSibling);
        } else {
            wrap.appendChild(list);
        }
    }

    function jwqImgAddFiles(files) {
        if (!files || !files.length) return;
        var added = 0;
        function readNext(i) {
            if (i >= files.length) {
                if (added) jwqImgRenderModalPreview();
                return;
            }
            var file = files[i];
            if (!file.type || !file.type.match(/^image\//)) {
                readNext(i + 1);
                return;
            }
            var reader = new FileReader();
            reader.onload = function (e) {
                var dataUrl = e.target.result;
                __jwqImgState.items.push({ kind: 'data', data: dataUrl, src: dataUrl });
                if (__jwqImgState.items.length === 1) __jwqImgState.primaryIndex = 0;
                added++;
                readNext(i + 1);
            };
            reader.readAsDataURL(file);
        }
        readNext(0);
    }

    function jwqImgRemoveAt(idx) {
        __jwqImgState.items.splice(idx, 1);
        if (__jwqImgState.primaryIndex >= __jwqImgState.items.length) {
            __jwqImgState.primaryIndex = Math.max(0, __jwqImgState.items.length - 1);
        }
        if (__jwqImgState.primaryIndex > idx) __jwqImgState.primaryIndex--;
        jwqImgRenderModalPreview();
    }

    function jwqImgCloseModal() {
        var modal = document.getElementById('addImageModal');
        if (modal && typeof window.jQuery !== 'undefined' && window.jQuery.fn.modal) {
            window.jQuery('#addImageModal').modal('hide');
        } else if (modal) {
            modal.classList.remove('show');
            modal.style.display = 'none';
        }
    }

    function jwqImgShowModal() {
        jwqImgRenderModalPreview();
        var modal = document.getElementById('addImageModal');
        if (modal && typeof window.jQuery !== 'undefined' && window.jQuery.fn.modal) {
            window.jQuery('#addImageModal').modal({ backdrop: true, keyboard: true, show: true });
        } else if (modal) {
            modal.style.display = 'block';
            modal.classList.add('show');
        }
    }

    window.mpJwqOpenAddImageModal = function (jwoId) {
        var id = parseInt(jwoId, 10) || 0;
        if (id < 1) {
            alert('Invalid job work order.');
            return;
        }
        __jwqImgState.jwoId = id;
        __jwqImgState.items = [];
        __jwqImgState.primaryIndex = 0;

        fetch('ajax/mp-get-jobwork-queue-images.php?id=' + encodeURIComponent(String(id)), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data && data.ok && data.items && data.items.length && data.images) {
                    data.items.forEach(function (it, i) {
                        var src = data.images[i] || '';
                        if (it.kind === 'path' && it.path) {
                            __jwqImgState.items.push({ kind: 'path', path: it.path, src: src });
                        }
                    });
                    var pri = data.primary || '';
                    if (pri && data.images && data.images.length) {
                        var ix = data.images.indexOf(pri);
                        if (ix >= 0) __jwqImgState.primaryIndex = ix;
                    }
                }
                jwqImgShowModal();
            })
            .catch(function () {
                jwqImgShowModal();
            });
    };

    window.initMpJwqAddImageModal = function () {
        if (__jwqImgState.bound) return;
        __jwqImgState.bound = true;

        var addImageModal = document.getElementById('addImageModal');
        var addImageModalFileInput = document.getElementById('addImageModalFileInput');
        var addImageUploadZone = document.getElementById('addImageUploadZone');
        var addImageModalSaveBtn = document.getElementById('addImageModalSaveBtn');
        var addImageModalCameraBtn = document.getElementById('addImageModalCameraBtn');
        var addImageModalClose = document.getElementById('addImageModalClose');

        if (addImageUploadZone && addImageModalFileInput) {
            addImageUploadZone.addEventListener('click', function () { addImageModalFileInput.click(); });
            addImageUploadZone.addEventListener('dragover', function (e) { e.preventDefault(); addImageUploadZone.style.background = '#e2e8f0'; });
            addImageUploadZone.addEventListener('dragleave', function () { addImageUploadZone.style.background = '#f1f5f9'; });
            addImageUploadZone.addEventListener('drop', function (e) {
                e.preventDefault();
                addImageUploadZone.style.background = '#f1f5f9';
                var files = e.dataTransfer && e.dataTransfer.files;
                if (files && files.length) jwqImgAddFiles(Array.prototype.slice.call(files));
            });
        }
        if (addImageModalFileInput) {
            addImageModalFileInput.addEventListener('change', function () {
                var files = this.files;
                if (files && files.length) jwqImgAddFiles(Array.prototype.slice.call(files));
                this.value = '';
            });
        }
        if (addImageModalCameraBtn && addImageModalFileInput) {
            addImageModalCameraBtn.addEventListener('click', function () { addImageModalFileInput.click(); });
        }
        if (addImageModalSaveBtn) {
            addImageModalSaveBtn.addEventListener('click', function () {
                var jid = __jwqImgState.jwoId;
                if (jid < 1) {
                    jwqImgCloseModal();
                    return;
                }
                var payload = { primary: __jwqImgState.primaryIndex, items: [] };
                __jwqImgState.items.forEach(function (it) {
                    if (it.kind === 'path' && it.path) {
                        payload.items.push({ kind: 'path', path: it.path });
                    } else if (it.kind === 'data' && it.data) {
                        payload.items.push({ kind: 'data', data: it.data });
                    }
                });
                var fd = new FormData();
                fd.append('jobwork_order_id', String(jid));
                fd.append('images_payload', JSON.stringify(payload));
                fetch('ajax/mp-save-jobwork-queue-images.php', { method: 'POST', body: fd, credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data && data.ok) {
                            jwqImgCloseModal();
                        } else {
                            alert((data && data.message) || 'Could not save images');
                        }
                    })
                    .catch(function () {
                        alert('Could not save images');
                    });
            });
        }
        if (addImageModalClose) {
            addImageModalClose.addEventListener('click', function () { jwqImgCloseModal(); });
        }
        if (addImageModal) {
            addImageModal.addEventListener('click', function (e) {
                if (e.target === addImageModal) jwqImgCloseModal();
            });
        }
    };
})();
</script>
