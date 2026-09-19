/**
 * Product Opening page — bulk Excel import (product master + characteristics).
 */
(function () {
    'use strict';

    function setBusy(on) {
        var loader = document.getElementById('ajaxLoader');
        var btns = document.querySelectorAll('.js-po-excel-import-trigger, .js-po-excel-sample-download, .js-po-excel-export-all, #poExcelImportBtn, #poExcelExportBtn');
        btns.forEach(function (btn) {
            btn.disabled = !!on;
        });
        if (loader) {
            loader.style.display = on ? 'block' : 'none';
        }
    }

    function startFileDownload(url) {
        if (!url) {
            return;
        }
        setBusy(true);
        window.location.assign(url);
        window.setTimeout(function () {
            setBusy(false);
        }, 2500);
    }

    function bindProductOpeningExcelImport() {
        var fileInput = document.getElementById('poExcelImportFile');
        if (!fileInput || fileInput.getAttribute('data-po-excel-bound') === '1') {
            return;
        }
        fileInput.setAttribute('data-po-excel-bound', '1');

        document.addEventListener('click', function (e) {
            var exportLink = e.target.closest('.js-po-excel-export-all');
            if (exportLink) {
                e.preventDefault();
                startFileDownload(exportLink.getAttribute('href') || exportLink.href);
                return;
            }
            var sample = e.target.closest('.js-po-excel-sample-download');
            if (sample) {
                e.preventDefault();
                startFileDownload(sample.getAttribute('href') || sample.href);
                return;
            }
            var trigger = e.target.closest('.js-po-excel-import-trigger');
            if (!trigger) {
                return;
            }
            e.preventDefault();
            fileInput.value = '';
            fileInput.click();
        });

        fileInput.addEventListener('change', function () {
            if (!fileInput.files || !fileInput.files.length) {
                return;
            }

            var fd = new FormData();
            fd.append('excel_file', fileInput.files[0]);

            setBusy(true);

            fetch('ajax/import-product-opening-excel.php', {
                method: 'POST',
                body: fd,
                credentials: 'same-origin'
            })
                .then(function (r) {
                    return r.text().then(function (text) {
                        var data = null;
                        try {
                            data = text ? JSON.parse(text) : null;
                        } catch (err) {
                            var s = (text || '').replace(/\s+/g, ' ').trim().slice(0, 300);
                            throw new Error(s || 'Server did not return JSON');
                        }
                        if (!data) {
                            throw new Error('Empty response from server');
                        }
                        return data;
                    });
                })
                .then(function (data) {
                    if (data.status === 'success' || data.status === true) {
                        var msg = data.message || ('Imported ' + (data.imported || 0) + ' product(s).');
                        if (data.errors && data.errors.length) {
                            var preview = data.errors.slice(0, 8).join('\n');
                            msg += '\n\nDetails:\n' + preview;
                            if (data.errors.length > 8) {
                                msg += '\n... and ' + (data.errors.length - 8) + ' more.';
                            }
                        }
                        alert(msg);
                        window.location.href = 'product-opening.php';
                        return;
                    }
                    var errMsg = data.message || 'Import failed';
                    if (data.errors && data.errors.length) {
                        errMsg += '\n\n' + data.errors.slice(0, 10).join('\n');
                    }
                    alert(errMsg);
                })
                .catch(function (err) {
                    alert(err && err.message ? err.message : 'Import request failed');
                })
                .finally(function () {
                    setBusy(false);
                    fileInput.value = '';
                });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bindProductOpeningExcelImport);
    } else {
        bindProductOpeningExcelImport();
    }
})();
