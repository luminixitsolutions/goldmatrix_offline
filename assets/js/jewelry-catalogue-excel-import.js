/**
 * Jewellery Catalogue page — bulk Excel import.
 */
(function () {
    'use strict';

    function setBusy(on) {
        var btns = document.querySelectorAll(
            '.js-jcat-excel-import-trigger, .js-jcat-excel-sample-download, #jcatImportBtn'
        );
        btns.forEach(function (btn) {
            btn.disabled = !!on;
        });
        var loader = document.getElementById('jcatExcelImportLoader');
        if (loader) {
            loader.classList.toggle('is-visible', !!on);
            loader.setAttribute('aria-hidden', on ? 'false' : 'true');
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

    function bindJewelryCatalogueExcelImport() {
        var fileInput = document.getElementById('jcatExcelImportFile');
        if (!fileInput || fileInput.getAttribute('data-jcat-excel-bound') === '1') {
            return;
        }
        fileInput.setAttribute('data-jcat-excel-bound', '1');

        document.addEventListener('click', function (e) {
            var sample = e.target.closest('.js-jcat-excel-sample-download');
            if (sample) {
                e.preventDefault();
                startFileDownload(sample.getAttribute('href') || sample.href);
                return;
            }
            var trigger = e.target.closest('.js-jcat-excel-import-trigger');
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

            fetch('ajax/import-jewelry-catalogue-excel.php', {
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
                    if (data.success) {
                        var msg = data.message || ('Imported ' + (data.imported || 0) + ' design(s).');
                        if (data.errors && data.errors.length) {
                            msg += '\n\nDetails:\n' + data.errors.slice(0, 8).join('\n');
                            if (data.errors.length > 8) {
                                msg += '\n... and ' + (data.errors.length - 8) + ' more.';
                            }
                        }
                        alert(msg);
                        if (typeof window.jcatReloadCatalog === 'function') {
                            window.jcatReloadCatalog();
                        }
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
        document.addEventListener('DOMContentLoaded', bindJewelryCatalogueExcelImport);
    } else {
        bindJewelryCatalogueExcelImport();
    }
})();
