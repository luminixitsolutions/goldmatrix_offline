<?php
session_start();
require_once 'config.php';
require_once __DIR__ . '/includes/auragold_require_login.php';

auragold_require_login_or_exit();
?><!DOCTYPE html>
<html lang="en" class="default-style customer-import-page">
<head>
    <title>Customer Details Import - <?php echo htmlspecialchars(auragold_app_name(), ENT_QUOTES, 'UTF-8'); ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0">
    <link rel="icon" type="image/jpeg" href="favicon.jpeg">
<?php include 'header-script.php'; ?>
<style>
html, body.customer-import-page { background: #f1f5f9; min-height: 100%; }
.customer-import-page .layout-content {
    padding: 0 !important;
    margin: 0 !important;
    width: 100% !important;
    max-width: 100% !important;
}
.cdi-shell {
    max-width: 920px;
    margin: 0 auto;
    padding: 16px 20px 40px;
}
.cdi-hero {
    background: linear-gradient(90deg, #11294b 0%, #1a3a5c 100%);
    color: #fff;
    border-radius: 10px;
    padding: 20px 24px;
    margin-bottom: 16px;
    box-shadow: 0 2px 10px rgba(17,41,75,.2);
}
.cdi-hero h1 { margin: 0 0 6px; font-size: 1.2rem; font-weight: 700; }
.cdi-hero p { margin: 0; font-size: .85rem; opacity: .9; }
.cdi-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 20px 24px;
    margin-bottom: 16px;
    box-shadow: 0 1px 3px rgba(15,23,42,.04);
}
.cdi-card h2 {
    margin: 0 0 12px;
    font-size: .95rem;
    font-weight: 700;
    color: #11294b;
}
.cdi-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 16px;
}
.cdi-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 9px 16px;
    border-radius: 6px;
    font-size: .85rem;
    font-weight: 600;
    border: none;
    cursor: pointer;
    text-decoration: none;
}
.cdi-btn-primary {
    background: #11294b;
    color: #fff;
}
.cdi-btn-primary:hover { background: #1a3a5c; color: #fff; text-decoration: none; }
.cdi-btn-outline {
    background: #fff;
    color: #11294b;
    border: 1px solid #cbd5e1;
}
.cdi-btn-outline:hover { background: #f8fafc; color: #11294b; text-decoration: none; }
.cdi-list {
    margin: 0;
    padding-left: 18px;
    color: #475569;
    font-size: .84rem;
    line-height: 1.6;
}
.cdi-list li { margin-bottom: 4px; }
.cdi-form-group { margin-bottom: 14px; }
.cdi-form-group label {
    display: block;
    font-size: .78rem;
    font-weight: 700;
    color: #334155;
    margin-bottom: 6px;
    text-transform: uppercase;
}
.cdi-form-group input[type="file"] {
    width: 100%;
    padding: 8px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    background: #fff;
}
.cdi-footer-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 8px;
}
.cdi-tag {
    display: inline-block;
    background: #eff6ff;
    color: #1d4ed8;
    font-size: .72rem;
    font-weight: 600;
    padding: 2px 8px;
    border-radius: 999px;
    margin: 2px 4px 2px 0;
}
</style>
</head>
<body class="customer-import-page">
<?php include 'sidebar.php'; ?>
<div class="layout-content">
<div class="cdi-shell">
                    <div class="cdi-hero">
                        <h1>Customer Details Import</h1>
                        <p>Download the sample Excel template, fill customer details using dropdown fields, then import to create or update customers.</p>
                    </div>

                    <div class="cdi-card">
                        <h2>Step 1 — Download sample Excel</h2>
                        <p style="font-size:.84rem;color:#64748b;margin:0 0 12px;">
                            The template includes dropdown lists for Customer Type, Nationality, Country, State, City, Group, Sundry Debtors, KYC, AML, Bill to Bill, and billing/shipping location fields.
                        </p>
                        <div class="cdi-actions">
                            <a href="ajax/download-customer-details-excel-sample.php" class="cdi-btn cdi-btn-primary">
                                <i class="feather icon-download"></i> Download Sample Excel
                            </a>
                            <a href="account-ledger.php" class="cdi-btn cdi-btn-outline">
                                <i class="feather icon-book"></i> Account Ledger
                            </a>
                        </div>
                        <div>
                            <span class="cdi-tag">Name *</span>
                            <span class="cdi-tag">Customer Type *</span>
                            <span class="cdi-tag">Mobile No</span>
                            <span class="cdi-tag">Addresses</span>
                            <span class="cdi-tag">Bank Details</span>
                            <span class="cdi-tag">GSTIN</span>
                        </div>
                    </div>

                    <div class="cdi-card">
                        <h2>Step 2 — Import Excel file</h2>
                        <ul class="cdi-list">
                            <li>Required: <strong>Name</strong> and <strong>Customer Type</strong> for new customers.</li>
                            <li>By default, the same mobile (or same name if mobile is blank) updates the existing customer.</li>
                            <li>Tick <strong>Always create new customers</strong> to insert new records even if the name already exists.</li>
                            <li>Dates should be in <strong>dd-mm-yyyy</strong> format.</li>
                            <li>Maximum 5000 rows per upload.</li>
                        </ul>
                        <form id="customerImportForm" enctype="multipart/form-data" style="margin-top:16px;">
                            <div class="cdi-form-group">
                                <label for="customerImportFile">Excel file (.xlsx / .xls)</label>
                                <input type="file" name="excel_file" id="customerImportFile" accept=".xlsx,.xls" required>
                            </div>
                            <div class="cdi-form-group">
                                <label for="customerImportCreateNew" style="text-transform:none;font-weight:600;display:flex;align-items:center;gap:8px;cursor:pointer;">
                                    <input type="checkbox" name="create_new" id="customerImportCreateNew" value="1" checked>
                                    Always create new customers (do not update existing)
                                </label>
                            </div>
                            <div class="cdi-footer-actions">
                                <button type="submit" class="cdi-btn cdi-btn-primary" id="customerImportSubmit">
                                    <i class="feather icon-upload"></i> Upload &amp; Import
                                </button>
                            </div>
                        </form>
                    </div>
</div>
</div>
<?php include 'footer-script.php'; ?>
<script>
document.getElementById('customerImportForm').addEventListener('submit', function(e) {
    e.preventDefault();
    var fileInput = document.getElementById('customerImportFile');
    if (!fileInput.files || !fileInput.files[0]) {
        alert('Please choose an Excel file.');
        return;
    }
    var btn = document.getElementById('customerImportSubmit');
    var original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = 'Importing...';
    var fd = new FormData(this);
    fetch('ajax/import-customer-details-excel.php', {
        method: 'POST',
        body: fd,
        credentials: 'same-origin'
    })
    .then(function(res) {
        return res.text().then(function(text) {
            if (!text || !String(text).trim()) {
                throw new Error('Server returned an empty response.');
            }
            try {
                return JSON.parse(text);
            } catch (err) {
                throw new Error('Invalid server response. ' + String(text).slice(0, 200));
            }
        });
    })
    .then(function(data) {
        if (data.status === 'success') {
            alert(data.message || 'Import completed.');
            fileInput.value = '';
        } else {
            var msg = data.message || 'Import failed.';
            if (data.errors && data.errors.length) {
                msg += '\n\n' + data.errors.slice(0, 5).join('\n');
            }
            alert(msg);
        }
    })
    .catch(function(err) {
        alert('Import failed: ' + (err && err.message ? err.message : String(err)));
    })
    .finally(function() {
        btn.disabled = false;
        btn.innerHTML = original;
    });
});
</script>
</body>
</html>
