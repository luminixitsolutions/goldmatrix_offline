<?php 
session_start();
require_once 'config.php';

$edit_customer_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$ledger_opening_branch_from_url = array_key_exists('branch_id', $_GET) ? (int) $_GET['branch_id'] : null;

// Ledger Details modal data
$ledger_groups = [
    ['id' => 1, 'name' => 'Sundry Debtors'],
    ['id' => 2, 'name' => 'Sundry Creditors'],
    ['id' => 3, 'name' => 'Bank Accounts'],
    ['id' => 4, 'name' => 'Cash'],
    ['id' => 5, 'name' => 'Sales'],
    ['id' => 6, 'name' => 'Purchase'],
    ['id' => 7, 'name' => 'Expenses'],
    ['id' => 8, 'name' => 'Income'],
    ['id' => 9, 'name' => 'Capital'],
    ['id' => 10, 'name' => 'Loans & Advances'],
    ['id' => 11, 'name' => 'Fixed Assets'],
    ['id' => 12, 'name' => 'Current Assets'],
    ['id' => 13, 'name' => 'Current Liabilities'],
    ['id' => 14, 'name' => 'Investment'],
];
// Sundry Debtors dropdown options (ledger/account types from reference)
$sundry_options = [
    ['id' => 1,  'name' => 'Primary'],
    ['id' => 2,  'name' => 'Capital Account'],
    ['id' => 3,  'name' => 'Loans (Liability)'],
    ['id' => 4,  'name' => 'Current Liabilities'],
    ['id' => 5,  'name' => 'Fixed Assets'],
    ['id' => 6,  'name' => 'Investments'],
    ['id' => 7,  'name' => 'Current Assets'],
    ['id' => 8,  'name' => 'Branch /Divisions'],
    ['id' => 9,  'name' => 'Misc.Expenses (ASSET)'],
    ['id' => 10, 'name' => 'Suspense A/C'],
    ['id' => 11, 'name' => 'Sales Account'],
    ['id' => 12, 'name' => 'Purchase Account'],
    ['id' => 13, 'name' => 'Direct Income'],
    ['id' => 14, 'name' => 'Direct Expenses'],
    ['id' => 15, 'name' => 'Indirect Income'],
    ['id' => 16, 'name' => 'Indirect Expenses'],
    ['id' => 17, 'name' => 'Reserves & Surplus'],
    ['id' => 18, 'name' => 'Bank OD A/C'],
    ['id' => 19, 'name' => 'Secured Loans'],
    ['id' => 20, 'name' => 'UnSecured Loans'],
    ['id' => 21, 'name' => 'Duties & Taxes'],
    ['id' => 22, 'name' => 'Provisions'],
    ['id' => 23, 'name' => 'Sundry Creditors'],
    ['id' => 24, 'name' => 'Stock-in-Hand'],
    ['id' => 25, 'name' => 'Deposits(Assets)'],
    ['id' => 26, 'name' => 'Loans & Advances(Asset)'],
    ['id' => 27, 'name' => 'Sundry Debtors'],
    ['id' => 28, 'name' => 'Cash-in Hand'],
    ['id' => 29, 'name' => 'Bank Account'],
    ['id' => 30, 'name' => 'Service Account'],
];
$customer_types = getList("SELECT id, name FROM tbl_customer_types WHERE status = 1 ORDER BY name ASC");
$nationalities = getList("SELECT id, name FROM tbl_nationalities WHERE status = 1 ORDER BY name ASC");
$countries = getList("SELECT id, name FROM tbl_countries WHERE status = 1 ORDER BY name ASC");
$countries_ledger = $countries;
require_once __DIR__ . '/includes/international-dial-codes.php';
require_once __DIR__ . '/includes/auragold_ledger_opening_metals.php';

$ledger_opening_branch_for_metals = 0;
if ($ledger_opening_branch_from_url !== null && (int) $ledger_opening_branch_from_url > 0) {
    $ledger_opening_branch_for_metals = (int) $ledger_opening_branch_from_url;
} elseif (function_exists('auragold_effective_branch_id')) {
    $ledger_opening_branch_for_metals = (int) auragold_effective_branch_id();
}
$ledger_opening_metals = auragold_ledger_opening_metals_list($conn, $ledger_opening_branch_for_metals);

$ledger_opening_branches = function_exists('auragold_registry_active_branches_list')
    ? auragold_registry_active_branches_list()
    : [];
if (empty($ledger_opening_branches) && function_exists('getListMaster')) {
    $ledger_opening_branches = @getListMaster("SELECT id, name FROM tbl_branches WHERE status = 1 ORDER BY name ASC");
}
if (!is_array($ledger_opening_branches)) {
    $ledger_opening_branches = [];
}

$ledger_opening_default_branch_id = 0;
if ($ledger_opening_branch_from_url !== null && (int) $ledger_opening_branch_from_url > 0) {
    $ledger_opening_default_branch_id = (int) $ledger_opening_branch_from_url;
} elseif ($edit_customer_id <= 0 && function_exists('auragold_effective_branch_id')) {
    $ledger_opening_default_branch_id = (int) auragold_effective_branch_id();
}

if (!function_exists('auragold_ledger_opening_metal_ui_class')) {
    function auragold_ledger_opening_metal_ui_class(string $label): string
    {
        $n = strtolower(trim($label));
        if (strpos($n, 'silver') !== false) {
            return 'gm-metal-silver';
        }
        if (strpos($n, 'diamond') !== false || strpos($n, 'stone') !== false) {
            return 'gm-metal-diamond';
        }
        if (strpos($n, 'platinum') !== false) {
            return 'gm-metal-platinum';
        }
        if (strpos($n, 'watch') !== false || strpos($n, 'imitation') !== false) {
            return 'gm-metal-watch';
        }
        if (strpos($n, 'service') !== false) {
            return 'gm-metal-service';
        }
        if (strpos($n, 'gold') !== false) {
            return 'gm-metal-gold';
        }
        return 'gm-metal-other';
    }
}

if (!function_exists('auragold_ledger_opening_metal_icon')) {
    function auragold_ledger_opening_metal_icon(string $label): string
    {
        static $map = [
            'gm-metal-gold' => 'icon-layers',
            'gm-metal-silver' => 'icon-layers',
            'gm-metal-diamond' => 'icon-aperture',
            'gm-metal-platinum' => 'icon-disc',
            'gm-metal-watch' => 'icon-watch',
            'gm-metal-service' => 'icon-tag',
            'gm-metal-other' => 'icon-circle',
        ];
        $cls = auragold_ledger_opening_metal_ui_class($label);
        return $map[$cls] ?? 'icon-circle';
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="default-style">
<head>
    <title>Ledger Opening - <?php echo htmlspecialchars(auragold_app_name(), ENT_QUOTES, 'UTF-8'); ?> Software</title>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0">
    <link rel="icon" type="image/jpeg" href="favicon.jpeg">
<?php include 'header-script.php';?>
    <link rel="stylesheet" href="assets/css/ledger-opening-page.css">
    <style>.goldmatrix-ledger-opening-page .page-header-bar{display:none!important;}</style>
</head>

<body class="goldmatrix-ledger-opening-page">
<?php include 'sidebar.php'; ?>

<div class="layout-content">
<div class="container-fluid flex-grow-1" style="padding-top:0;padding-bottom:0;">

<!-- Page Header -->
<div class="page-header-bar">
    <span>Ledger Opening</span>
    <div class="page-header-actions"></div>
</div>

<!-- Ledger Details form (shown on page) -->
<header class="gm-page-header">
    <div class="gm-page-header-left">
        <div class="gm-page-header-icon"><i class="feather icon-book"></i></div>
        <div class="gm-page-header-text">
            <h1>Ledger Opening</h1>
            <p>Create or update ledger details, contact information and opening balances.</p>
        </div>
    </div>
    <div class="gm-page-header-actions">
        <button type="button" class="gm-btn-clear" onclick="clearCustomerForm()">Clear</button>
        <button type="button" class="gm-btn-save" id="saveCustomerBtn" onclick="saveCustomer(this)">
            <i class="feather icon-save"></i> Save Ledger
        </button>
    </div>
</header>

<div class="ledger-form-container">
    <form id="customerCreationForm" method="post" enctype="multipart/form-data" data-default-opening-branch="<?php echo (int) $ledger_opening_default_branch_id; ?>">
        <input type="hidden" name="customer_id" id="customerId" value="<?php echo $edit_customer_id; ?>">

        <div class="gm-page-shell">
            <div class="gm-main-column">

                <!-- Basic Information & Contact -->
                <section class="gm-card">
                    <div class="gm-card-header">
                        <span class="gm-section-icon"><i class="feather icon-user"></i></span>
                        <h2 class="gm-card-title">Basic Information &amp; Contact</h2>
                    </div>
                    <div class="gm-card-body">
                        <div class="gm-form-grid">
                            <div class="gm-photo-col gm-photo-upload">
                                <div class="gm-photo-circle" onclick="document.getElementById('ledgerPhotoInput').click();" title="Upload ledger photo">
                                    <i class="feather icon-camera"></i>
                                    <span>Upload Photo</span>
                                    <input type="file" id="ledgerPhotoInput" name="ledger_photo" accept="image/*" style="display: none;" onchange="previewLedgerPhoto(this);">
                                </div>
                                <div id="ledgerPhotoPreview" style="display: none;">
                                    <img id="ledgerPhotoImg" src="" alt="Ledger photo preview">
                                </div>
                                <div class="form-check mt-2 text-center">
                                    <input class="form-check-input" type="checkbox" id="ledgerNameCapital" name="ledger_name_capital">
                                    <label class="form-check-label" for="ledgerNameCapital" style="font-size: 0.75rem;">Ledger Name Capital</label>
                                </div>
                            </div>
                            <div class="gm-fields-col">
                                <div class="gm-form-grid">
                                    <div class="gm-field gm-col-4">
                                        <label for="ledgerName">Name <span class="req">*</span></label>
                                        <input type="text" class="form-control" id="ledgerName" name="name" required oninput="handleNameInput(this)">
                                    </div>
                                    <div class="gm-field gm-col-4">
                                        <label for="ledgerAlternateName">Alternate Name</label>
                                        <input type="text" class="form-control" id="ledgerAlternateName" name="alternate_name">
                                    </div>
                                    <div class="gm-field gm-col-4">
                                        <label for="ledgerFirstName">First Name</label>
                                        <input type="text" class="form-control" id="ledgerFirstName" name="first_name">
                                    </div>
                                    <div class="gm-field gm-col-4">
                                        <label for="ledgerLastName">Last Name</label>
                                        <input type="text" class="form-control" id="ledgerLastName" name="last_name">
                                    </div>
                                    <div class="gm-field gm-col-4">
                                        <label for="ledgerMobileNo">Mobile No</label>
                                        <div class="gm-mobile-group">
                                            <select id="mobileCountryCode" name="mobile_country_code">
                                                <?php auragold_render_dial_code_select('971'); ?>
                                            </select>
                                            <input type="text" id="ledgerMobileNo" name="mobile_no" placeholder="Enter mobile number">
                                        </div>
                                    </div>
                                    <div class="gm-field gm-col-4">
                                        <label for="ledgerPhoneNo">Phone No</label>
                                        <input type="text" class="form-control" id="ledgerPhoneNo" name="phone_no" placeholder="Phone No">
                                    </div>
                                    <div class="gm-field gm-col-4">
                                        <label for="ledgerMailId">Mail ID</label>
                                        <input type="email" class="form-control" id="ledgerMailId" name="mail_id" placeholder="Email">
                                    </div>
                                    <div class="gm-field gm-col-4">
                                        <label for="ledgerIdentityNo">Short Code / Identity No</label>
                                        <input type="text" class="form-control" id="ledgerIdentityNo" name="identity_no">
                                    </div>
                                    <div class="gm-field gm-col-4">
                                        <label for="ledgerNationalId">National Id</label>
                                        <input type="text" class="form-control" id="ledgerNationalId" name="national_id">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Business Details -->
                <section class="gm-card">
                    <div class="gm-card-header">
                        <span class="gm-section-icon"><i class="feather icon-briefcase"></i></span>
                        <h2 class="gm-card-title">Business Details</h2>
                    </div>
                    <div class="gm-card-body">
                        <div class="gm-form-grid">
                            <div class="gm-field gm-col-4">
                                <label for="ledgerTradeNo">Trade No</label>
                                <input type="text" class="form-control" id="ledgerTradeNo" name="trade_no">
                            </div>
                            <div class="gm-field gm-col-4">
                                <label for="identityIssueDate">Identity Issue Date</label>
                                <input type="date" class="form-control" id="identityIssueDate" name="identity_issue_date">
                            </div>
                            <div class="gm-field gm-col-4">
                                <label for="identityExpiryDate">Identity Expiry Date</label>
                                <input type="date" class="form-control" id="identityExpiryDate" name="identity_expiry_date">
                            </div>
                            <div class="gm-field gm-col-4">
                                <label for="specialDay">Special Day</label>
                                <input type="date" class="form-control" id="specialDay" name="special_day">
                            </div>
                            <div class="gm-field gm-col-4">
                                <label for="customerType">Customer Type <span class="req">*</span></label>
                                <select class="form-control" id="customerType" name="customer_type_id">
                                    <option value="">Select Customer Type</option>
                                    <?php foreach ($customer_types as $type) {
                                        echo '<option value="' . $type['id'] . '">' . htmlspecialchars($type['name']) . '</option>';
                                    } ?>
                                </select>
                            </div>
                            <div class="gm-field gm-col-4">
                                <label for="registrationNo">Registration No</label>
                                <input type="text" class="form-control" id="registrationNo" name="registration_no">
                            </div>
                            <div class="gm-field gm-col-4">
                                <label for="registrationDate">Registration Date</label>
                                <input type="date" class="form-control" id="registrationDate" name="registration_date">
                            </div>
                            <div class="gm-field gm-col-4">
                                <label for="ledgerGroup">Select Group</label>
                                <select class="form-control" id="ledgerGroup" name="group_id">
                                    <option value="">Select Group</option>
                                    <?php foreach ($ledger_groups as $group) {
                                        echo '<option value="' . $group['id'] . '">' . htmlspecialchars($group['name']) . '</option>';
                                    } ?>
                                </select>
                            </div>
                            <div class="gm-field gm-col-4">
                                <label for="ledgerSundryDebtors">Sundry Debtors <span class="req">*</span></label>
                                <select class="form-control" id="ledgerSundryDebtors" name="sundry_debtors_id" required>
                                    <option value="">Select Sundry Debtors</option>
                                    <?php foreach ($sundry_options as $option) {
                                        echo '<option value="' . $option['id'] . '">' . htmlspecialchars($option['name']) . '</option>';
                                    } ?>
                                </select>
                            </div>
                            <div class="gm-field gm-col-4">
                                <label for="nationality">Nationality</label>
                                <select class="form-control" id="nationality" name="nationality_id">
                                    <option value="">Select Nationality</option>
                                    <?php foreach ($nationalities as $nationality) {
                                        echo '<option value="' . $nationality['id'] . '">' . htmlspecialchars($nationality['name']) . '</option>';
                                    } ?>
                                </select>
                            </div>
                            <div class="gm-field gm-col-4">
                                <label for="country">Country</label>
                                <select class="form-control" id="country" name="country_id">
                                    <option value="">Select Country</option>
                                    <?php foreach ($countries as $country) {
                                        echo '<option value="' . $country['id'] . '">' . htmlspecialchars($country['name']) . '</option>';
                                    } ?>
                                </select>
                            </div>
                            <div class="gm-field gm-col-12">
                                <div class="gm-options-row">
                                    <div class="form-check form-check-inline mb-0">
                                        <input class="form-check-input" type="checkbox" id="ledgerKYC" name="kyc">
                                        <label class="form-check-label" for="ledgerKYC">KYC</label>
                                    </div>
                                    <div class="form-check form-check-inline mb-0">
                                        <input class="form-check-input" type="checkbox" id="ledgerAML" name="aml">
                                        <label class="form-check-label" for="ledgerAML">AML</label>
                                    </div>
                                    <div class="form-check form-check-inline mb-0">
                                        <label class="form-check-label" style="margin-right: 8px;">Bill to Bill</label>
                                        <input class="form-check-input" type="radio" id="billToBillYes" name="bill_to_bill" value="1">
                                        <label class="form-check-label" for="billToBillYes" style="margin-right: 12px;">Yes</label>
                                        <input class="form-check-input" type="radio" id="billToBillNo" name="bill_to_bill" value="0" checked>
                                        <label class="form-check-label" for="billToBillNo">No</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Address & Banking Summary -->
                <section class="gm-card">
                    <div class="gm-card-header">
                        <span class="gm-section-icon"><i class="feather icon-map-pin"></i></span>
                        <h2 class="gm-card-title">Address &amp; Banking Summary</h2>
                    </div>
                    <div class="gm-card-body">
                        <div class="gm-form-grid">
                            <div class="gm-col-6">
                                <h3 class="gm-subsection-title"><i class="feather icon-map"></i> Billing Location</h3>
                                <div class="gm-form-grid">
                                    <div class="gm-field gm-col-12">
                                        <label for="billingCountry">Country <span class="req">*</span></label>
                                        <select class="form-control" id="billingCountry" name="billing_country" required>
                                            <option value="">Select Country</option>
                                            <?php foreach ($countries_ledger as $co) {
                                                $nm = htmlspecialchars($co['name'], ENT_QUOTES, 'UTF-8');
                                                $cid = (int) $co['id'];
                                                echo '<option value="' . $nm . '" data-country-id="' . $cid . '">' . $nm . '</option>';
                                            } ?>
                                        </select>
                                    </div>
                                    <div class="gm-field gm-col-12">
                                        <label for="billingState">State <span class="req">*</span></label>
                                        <select class="form-control" id="billingState" name="billing_state" required>
                                            <option value="">Select State</option>
                                        </select>
                                    </div>
                                    <div class="gm-field gm-col-12">
                                        <label for="billingCity">City</label>
                                        <div class="gm-city-actions">
                                            <select class="form-control" id="billingCity" name="billing_city">
                                                <option value="">Select City</option>
                                            </select>
                                            <button type="button" class="gm-icon-btn city-info-btn" title="Cities load for the selected state. Use + to add a new city under this state." tabindex="-1">
                                                <i class="feather icon-info"></i>
                                            </button>
                                            <button type="button" class="gm-icon-btn city-add-btn" data-target="billing" title="Add city under selected state">
                                                <i class="feather icon-plus"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="gm-field gm-col-12">
                                        <label for="billingZipCode">Zip Code</label>
                                        <input type="text" class="form-control" id="billingZipCode" name="billing_zip_code">
                                    </div>
                                </div>
                            </div>
                            <div class="gm-col-6 gm-bank-block">
                                <i class="feather icon-credit-card gm-bank-watermark"></i>
                                <h3 class="gm-subsection-title"><i class="feather icon-home"></i> Bank Details</h3>
                                <div class="gm-form-grid">
                                    <div class="gm-field gm-col-12">
                                        <label for="bankAccountNo">Acc. No.</label>
                                        <input type="text" class="form-control" id="bankAccountNo" name="bank_account_no" placeholder="Account No">
                                    </div>
                                    <div class="gm-field gm-col-12">
                                        <label for="bankName">Name</label>
                                        <input type="text" class="form-control" id="bankName" name="bank_name" placeholder="Bank Name">
                                    </div>
                                    <div class="gm-field gm-col-12">
                                        <label for="bankIfscCode">IFSC Code</label>
                                        <input type="text" class="form-control" id="bankIfscCode" name="bank_ifsc_code" placeholder="IFSC Code">
                                    </div>
                                    <div class="gm-field gm-col-12">
                                        <label for="bankBranch">Branch</label>
                                        <input type="text" class="form-control" id="bankBranch" name="bank_branch">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Tabs -->
                <section class="gm-card gm-tab-card">
                    <ul class="nav nav-tabs" id="ledgerTabs" role="tablist">
                        <li class="nav-item"><a class="nav-link active" id="billing-tab" data-toggle="tab" href="#billing-address" role="tab">Billing Address</a></li>
                        <li class="nav-item"><a class="nav-link" id="shipping-tab" data-toggle="tab" href="#shipping-address" role="tab">Shipping Address</a></li>
                        <li class="nav-item"><a class="nav-link" id="item-type-tax-tab" data-toggle="tab" href="#item-type-tax" role="tab">Item Type Tax</a></li>
                        <li class="nav-item"><a class="nav-link" id="share-holders-tab" data-toggle="tab" href="#share-holders" role="tab">Share Holders</a></li>
                        <li class="nav-item"><a class="nav-link" id="notes-tab" data-toggle="tab" href="#notes" role="tab">Notes</a></li>
                        <li class="nav-item"><a class="nav-link" id="nominee-tab" data-toggle="tab" href="#nominee" role="tab">Nominee</a></li>
                    </ul>
                    <div class="tab-content" id="ledgerTabContent">
                        <div class="tab-pane fade show active" id="billing-address" role="tabpanel">
                            <div class="gm-form-grid">
                                <div class="gm-col-6">
                                    <h3 class="gm-subsection-title">Billing Address</h3>
                                    <div class="gm-field">
                                        <label for="billingAddress1">Address 1</label>
                                        <input type="text" class="form-control" id="billingAddress1" name="billing_address1">
                                    </div>
                                    <div class="gm-field">
                                        <label for="billingAddress2">Address 2</label>
                                        <input type="text" class="form-control" id="billingAddress2" name="billing_address2">
                                    </div>
                                    <p class="gm-notes-help">Country, state, city and zip are configured in Address &amp; Banking Summary above.</p>
                                </div>
                                <div class="gm-col-6 gm-bank-block">
                                    <i class="feather icon-credit-card gm-bank-watermark"></i>
                                    <h3 class="gm-subsection-title">Bank Details</h3>
                                    <p class="gm-notes-help">Bank account details are configured in Address &amp; Banking Summary above.</p>
                                </div>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="shipping-address" role="tabpanel">
                            <div class="gm-form-grid">
                                <div class="gm-col-6">
                                    <h3 class="gm-subsection-title">Shipping Address</h3>
                                    <div class="gm-field">
                                        <label for="shippingAddress1">Address 1</label>
                                        <input type="text" class="form-control" id="shippingAddress1" name="shipping_address1">
                                    </div>
                                    <div class="gm-field">
                                        <label for="shippingAddress2">Address 2</label>
                                        <input type="text" class="form-control" id="shippingAddress2" name="shipping_address2">
                                    </div>
                                    <div class="gm-field">
                                        <label for="shippingCountry">Country</label>
                                        <select class="form-control" id="shippingCountry" name="shipping_country">
                                            <option value="">Select Country</option>
                                            <?php foreach ($countries_ledger as $co) {
                                                $nm = htmlspecialchars($co['name'], ENT_QUOTES, 'UTF-8');
                                                $cid = (int) $co['id'];
                                                echo '<option value="' . $nm . '" data-country-id="' . $cid . '">' . $nm . '</option>';
                                            } ?>
                                        </select>
                                    </div>
                                    <div class="gm-field">
                                        <label for="shippingState">State</label>
                                        <select class="form-control" id="shippingState" name="shipping_state">
                                            <option value="">Select State</option>
                                        </select>
                                    </div>
                                    <div class="gm-field">
                                        <label for="shippingCity">City</label>
                                        <div class="gm-city-actions">
                                            <select class="form-control" id="shippingCity" name="shipping_city">
                                                <option value="">Select City</option>
                                            </select>
                                            <button type="button" class="gm-icon-btn city-info-btn" title="Cities load for the selected state. Use + to add a new city under this state." tabindex="-1">
                                                <i class="feather icon-info"></i>
                                            </button>
                                            <button type="button" class="gm-icon-btn city-add-btn" data-target="shipping" title="Add city under selected state">
                                                <i class="feather icon-plus"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="gm-field">
                                        <label for="shippingZipCode">Zip Code</label>
                                        <input type="text" class="form-control" id="shippingZipCode" name="shipping_zip_code">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="item-type-tax" role="tabpanel">
                            <table class="item-tax-table">
                                <thead>
                                    <tr>
                                        <th style="width: 40%;">Item Name</th>
                                        <th style="width: 30%;">Default Input Type</th>
                                        <th style="width: 30%;">Default Output Type</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr><td>AMOUNT</td><td><select name="item_tax[AMOUNT][input_type]" class="form-control"><option value="">...</option><option value="VAT">VAT</option><option value="TAX BAH">TAX BAH</option></select></td><td><select name="item_tax[AMOUNT][output_type]" class="form-control"><option value="">...</option><option value="VAT">VAT</option><option value="TAX BAH">TAX BAH</option></select></td></tr>
                                    <tr><td>Gold</td><td><select name="item_tax[Gold][input_type]" class="form-control"><option value="">...</option><option value="VAT">VAT</option><option value="TAX BAH">TAX BAH</option></select></td><td><select name="item_tax[Gold][output_type]" class="form-control"><option value="">...</option><option value="VAT">VAT</option><option value="TAX BAH">TAX BAH</option></select></td></tr>
                                    <tr><td>GOLD - MAKING</td><td><select name="item_tax[GOLD_MAKING][input_type]" class="form-control"><option value="">...</option><option value="VAT">VAT</option><option value="TAX BAH">TAX BAH</option></select></td><td><select name="item_tax[GOLD_MAKING][output_type]" class="form-control"><option value="">...</option><option value="VAT">VAT</option><option value="TAX BAH">TAX BAH</option></select></td></tr>
                                    <tr><td>Silver</td><td><select name="item_tax[Silver][input_type]" class="form-control"><option value="">...</option><option value="VAT">VAT</option><option value="TAX BAH">TAX BAH</option></select></td><td><select name="item_tax[Silver][output_type]" class="form-control"><option value="">...</option><option value="VAT">VAT</option><option value="TAX BAH">TAX BAH</option></select></td></tr>
                                    <tr><td>SILVER - MAKING</td><td><select name="item_tax[SILVER_MAKING][input_type]" class="form-control"><option value="">...</option><option value="VAT">VAT</option><option value="TAX BAH">TAX BAH</option></select></td><td><select name="item_tax[SILVER_MAKING][output_type]" class="form-control"><option value="">...</option><option value="VAT">VAT</option><option value="TAX BAH">TAX BAH</option></select></td></tr>
                                    <tr><td>Diamond &amp; Stones</td><td><select name="item_tax[Diamond_Stones][input_type]" class="form-control"><option value="">...</option><option value="VAT">VAT</option><option value="TAX BAH">TAX BAH</option></select></td><td><select name="item_tax[Diamond_Stones][output_type]" class="form-control"><option value="">...</option><option value="VAT">VAT</option><option value="TAX BAH">TAX BAH</option></select></td></tr>
                                    <tr><td>Imitation Or Watches</td><td><select name="item_tax[Imitation_Watches][input_type]" class="form-control"><option value="">...</option><option value="VAT">VAT</option><option value="TAX BAH">TAX BAH</option></select></td><td><select name="item_tax[Imitation_Watches][output_type]" class="form-control"><option value="">...</option><option value="VAT">VAT</option><option value="TAX BAH">TAX BAH</option></select></td></tr>
                                    <tr><td>LOOSE - DIAMOND</td><td><select name="item_tax[LOOSE_DIAMOND][input_type]" class="form-control"><option value="">...</option><option value="VAT">VAT</option><option value="TAX BAH">TAX BAH</option></select></td><td><select name="item_tax[LOOSE_DIAMOND][output_type]" class="form-control"><option value="">...</option><option value="VAT">VAT</option><option value="TAX BAH">TAX BAH</option></select></td></tr>
                                    <tr><td>CERTIFIED - DIAMOND</td><td><select name="item_tax[CERTIFIED_DIAMOND][input_type]" class="form-control"><option value="">...</option><option value="VAT">VAT</option><option value="TAX BAH">TAX BAH</option></select></td><td><select name="item_tax[CERTIFIED_DIAMOND][output_type]" class="form-control"><option value="">...</option><option value="VAT">VAT</option><option value="TAX BAH">TAX BAH</option></select></td></tr>
                                    <tr><td>Other Or Services</td><td><select name="item_tax[Other_Services][input_type]" class="form-control"><option value="">...</option><option value="VAT">VAT</option><option value="TAX BAH">TAX BAH</option></select></td><td><select name="item_tax[Other_Services][output_type]" class="form-control"><option value="">...</option><option value="VAT">VAT</option><option value="TAX BAH">TAX BAH</option></select></td></tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="tab-pane fade" id="share-holders" role="tabpanel">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h3 class="gm-subsection-title mb-0">Share Holders</h3>
                                <div class="d-flex" style="gap: 8px;">
                                    <button type="button" class="gm-btn-save" id="addShareHolderBtn" style="height: 34px; padding: 0 12px; font-size: 0.78rem;">
                                        <i class="feather icon-plus"></i> Add Shareholder
                                    </button>
                                </div>
                            </div>
                            <div style="overflow-x: auto;">
                                <table class="item-tax-table" id="shareHoldersTable">
                                    <thead>
                                        <tr>
                                            <th onclick="sortShareHoldersTable(0)" style="cursor:pointer;">Name <i class="feather icon-arrow-up" style="font-size: 0.7rem;"></i><i class="feather icon-arrow-down" style="font-size: 0.7rem;"></i></th>
                                            <th onclick="sortShareHoldersTable(1)" style="cursor:pointer;">Nationality <i class="feather icon-arrow-up" style="font-size: 0.7rem;"></i><i class="feather icon-arrow-down" style="font-size: 0.7rem;"></i></th>
                                            <th onclick="sortShareHoldersTable(2)" style="cursor:pointer;">Share Per. <i class="feather icon-arrow-up" style="font-size: 0.7rem;"></i><i class="feather icon-arrow-down" style="font-size: 0.7rem;"></i></th>
                                            <th style="width: 60px; text-align: center;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="shareHoldersTableBody"></tbody>
                                </table>
                            </div>
                            <div class="mt-4">
                                <h3 class="gm-subsection-title">Upload Document</h3>
                                <div id="shareHolderDocumentUpload" ondrop="handleShareHolderFileDrop(event)" ondragover="event.preventDefault(); this.style.borderColor = '#c5a864';" ondragleave="this.style.borderColor = '#cbd5e1';" onclick="document.getElementById('shareHolderFileInput').click();">
                                    <input type="file" id="shareHolderFileInput" name="share_holder_documents[]" multiple accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" style="display: none;" onchange="handleShareHolderFileSelect(this);">
                                    <i class="feather icon-upload-cloud" style="font-size: 2rem; color: #c5a864; margin-bottom: 0.5rem;"></i>
                                    <p style="margin: 0.5rem 0 0 0; color: #64748b; font-size: 0.85rem;">Drop files here or click to upload.</p>
                                </div>
                                <div id="shareHolderFileList" class="mt-2"></div>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="notes" role="tabpanel">
                            <div class="gm-field">
                                <label for="ledgerNotes">Internal Notes</label>
                                <p class="gm-notes-help">Add any additional information related to this ledger.</p>
                                <textarea class="form-control" id="ledgerNotes" name="notes" rows="5"></textarea>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="nominee" role="tabpanel">
                            <?php require __DIR__ . '/includes/customer-nominee-markup.php'; ?>
                        </div>
                    </div>
                </section>

            </div><!-- /.gm-main-column -->

            <aside class="gm-sidebar-column">
                <div class="gm-opening-panel ledger-opening-sidebar">
                    <div class="gm-opening-head">
                        <h6 class="opening-title">Opening Balances</h6>
                    </div>
                    <div class="gm-opening-body">
                        <div class="gm-opening-block">
                            <label for="openingBalance">Opening Balance</label>
                            <input type="number" class="form-control" id="openingBalance" name="opening_balance" value="0" step="0.01" min="0" placeholder="0.00">
                            <div class="gm-opening-cd opening-credit-debit">
                                <div class="form-check form-check-inline mb-0">
                                    <input class="form-check-input" type="radio" id="openingCredit" name="opening_type" value="credit" checked>
                                    <label class="form-check-label" for="openingCredit">Credit</label>
                                </div>
                                <div class="form-check form-check-inline mb-0">
                                    <input class="form-check-input" type="radio" id="openingDebit" name="opening_type" value="debit">
                                    <label class="form-check-label" for="openingDebit">Debit</label>
                                </div>
                            </div>
                        </div>
                        <div class="gm-opening-block">
                            <label for="openingBranchId">Branch (Opening Balance)</label>
                            <select class="form-control" id="openingBranchId" name="opening_branch_id" autocomplete="off" title="Choose which branch this opening balance applies to">
                                <option value="">â€” Not set â€”</option>
                                <?php foreach ($ledger_opening_branches as $lb):
                                    $lbid = (int) ($lb['id'] ?? 0);
                                    if ($lbid <= 0) continue;
                                    $is_def = ($ledger_opening_default_branch_id > 0 && $ledger_opening_default_branch_id === $lbid);
                                ?>
                                <option value="<?php echo $lbid; ?>"<?php echo $is_def ? ' selected' : ''; ?>><?php echo htmlspecialchars((string) ($lb['name'] ?? '')); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php if (!empty($ledger_opening_metals)): ?>
                        <div class="gm-opening-block">
                            <label>Metal Opening (gm)</label>
                            <div class="gm-metal-list opening-metal-section">
                                <?php foreach ($ledger_opening_metals as $lom):
                                    $lom_id = (int) ($lom['id'] ?? 0);
                                    if ($lom_id <= 0) continue;
                                    $lom_label = trim((string) ($lom['display_name'] ?? ''));
                                    $lom_ui = auragold_ledger_opening_metal_ui_class($lom_label);
                                    $lom_icon = auragold_ledger_opening_metal_icon($lom_label);
                                ?>
                                <div class="gm-metal-card opening-metal-row <?php echo htmlspecialchars($lom_ui, ENT_QUOTES, 'UTF-8'); ?>" data-metal-id="<?php echo $lom_id; ?>">
                                    <div class="gm-metal-card-head">
                                        <i class="feather <?php echo htmlspecialchars($lom_icon, ENT_QUOTES, 'UTF-8'); ?>"></i>
                                        <span><?php echo htmlspecialchars($lom_label); ?></span>
                                    </div>
                                    <input type="number"
                                           class="form-control opening-metal-weight"
                                           name="opening_metal[<?php echo $lom_id; ?>][weight]"
                                           id="openingMetalWeight_<?php echo $lom_id; ?>"
                                           value="0"
                                           step="0.001"
                                           min="0"
                                           placeholder="0.000">
                                    <div class="gm-opening-cd opening-credit-debit">
                                        <div class="form-check form-check-inline mb-0">
                                            <input class="form-check-input opening-metal-type"
                                                   type="radio"
                                                   name="opening_metal[<?php echo $lom_id; ?>][type]"
                                                   id="openingMetalCredit_<?php echo $lom_id; ?>"
                                                   value="credit"
                                                   checked>
                                            <label class="form-check-label" for="openingMetalCredit_<?php echo $lom_id; ?>">Credit</label>
                                        </div>
                                        <div class="form-check form-check-inline mb-0">
                                            <input class="form-check-input opening-metal-type"
                                                   type="radio"
                                                   name="opening_metal[<?php echo $lom_id; ?>][type]"
                                                   id="openingMetalDebit_<?php echo $lom_id; ?>"
                                                   value="debit">
                                            <label class="form-check-label" for="openingMetalDebit_<?php echo $lom_id; ?>">Debit</label>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </aside>

        </div><!-- /.gm-page-shell -->
    </form>
</div>

</div>
</div>

<!-- Filter Modal -->



<script>
(function() {
    const nationalities = <?php echo json_encode(isset($nationalities) && is_array($nationalities) ? $nationalities : []); ?>;
    let shareHolderRowIndex = 0;
    let shareHoldersData = [];
    let shareHolderFiles = [];

    function previewLedgerPhoto(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                var preview = document.getElementById('ledgerPhotoPreview');
                var circle = document.querySelector('.gm-photo-circle');
                if (preview) preview.style.display = 'block';
                if (circle) circle.style.display = 'none';
                document.getElementById('ledgerPhotoImg').src = e.target.result;
            };
            reader.readAsDataURL(input.files[0]);
        }
    }

    function handleNameInput(input) {
        const nameValue = input.value;
        const capitalCheckbox = document.getElementById('ledgerNameCapital');
        if (capitalCheckbox && capitalCheckbox.checked) {
            input.value = nameValue.toUpperCase();
        }
        const nameParts = nameValue.trim().split(/\s+/);
        const firstNameField = document.getElementById('ledgerFirstName');
        const lastNameField = document.getElementById('ledgerLastName');
        if (nameParts.length > 0) {
            if (firstNameField) firstNameField.value = nameParts[0];
            if (nameParts.length > 1 && lastNameField) {
                lastNameField.value = nameParts[nameParts.length - 1];
            } else if (nameParts.length === 1 && lastNameField) {
                lastNameField.value = '';
            }
        }
    }

    function addShareHolderRow() {
        shareHolderRowIndex++;
        const tbody = document.getElementById('shareHoldersTableBody');
        if (!tbody) return;
        let nationalityOptions = '<option value="">Select Nationality</option>';
        if (Array.isArray(nationalities)) {
            nationalities.forEach(function(n) {
                nationalityOptions += '<option value="' + n.id + '">' + (n.name || '') + '</option>';
            });
        }
        const row = document.createElement('tr');
        row.id = 'shareHolderRow_' + shareHolderRowIndex;
        row.setAttribute('data-row-index', shareHolderRowIndex);
        row.innerHTML = '<td><input type="text" class="form-control" name="share_holders[' + shareHolderRowIndex + '][name]" placeholder="Enter name" style="font-size: 0.85rem; padding: 0.4rem 0.6rem; height: 32px; border: 1px solid #e2e8f0;"></td>' +
            '<td><select class="form-control" name="share_holders[' + shareHolderRowIndex + '][nationality_id]" style="font-size: 0.85rem; padding: 0.4rem 0.6rem; height: 32px; border: 1px solid #e2e8f0;">' + nationalityOptions + '</select></td>' +
            '<td><input type="number" class="form-control" name="share_holders[' + shareHolderRowIndex + '][share_percentage]" placeholder="0.00" step="0.01" min="0" max="100" style="font-size: 0.85rem; padding: 0.4rem 0.6rem; height: 32px; border: 1px solid #e2e8f0; text-align: right;"></td>' +
            '<td style="text-align: center;"><button type="button" class="btn btn-sm delete-share-holder" onclick="window.deleteShareHolderRow(' + shareHolderRowIndex + ')" style="background: transparent; border: none; color: #ef4444; padding: 0.25rem; cursor: pointer;"><i class="feather icon-trash-2" style="font-size: 0.9rem;"></i></button></td>';
        tbody.appendChild(row);
        shareHoldersData.push({ row_index: shareHolderRowIndex, name: '', nationality_id: '', share_percentage: '' });
    }

    window.deleteShareHolderRow = function(rowIndex) {
        if (confirm('Are you sure you want to delete this share holder?')) {
            const row = document.getElementById('shareHolderRow_' + rowIndex);
            if (row) {
                row.remove();
                shareHoldersData = shareHoldersData.filter(function(item) { return item.row_index !== rowIndex; });
            }
        }
    };

    function sortShareHoldersTable(columnIndex) {
        const tbody = document.getElementById('shareHoldersTableBody');
        if (!tbody) return;
        const rows = Array.from(tbody.querySelectorAll('tr'));
        rows.sort(function(a, b) {
            var aVal, bVal;
            if (columnIndex === 0) {
                aVal = (a.querySelector('input[type="text"]') && a.querySelector('input[type="text"]').value) || '';
                bVal = (b.querySelector('input[type="text"]') && b.querySelector('input[type="text"]').value) || '';
            } else if (columnIndex === 1) {
                aVal = (a.querySelector('select') && a.querySelector('select').selectedOptions[0] && a.querySelector('select').selectedOptions[0].text) || '';
                bVal = (b.querySelector('select') && b.querySelector('select').selectedOptions[0] && b.querySelector('select').selectedOptions[0].text) || '';
            } else if (columnIndex === 2) {
                aVal = parseFloat((a.querySelector('input[type="number"]') && a.querySelector('input[type="number"]').value) || 0);
                bVal = parseFloat((b.querySelector('input[type="number"]') && b.querySelector('input[type="number"]').value) || 0);
            }
            if (typeof aVal === 'string') return aVal.localeCompare(bVal);
            return aVal - bVal;
        });
        rows.forEach(function(row) { tbody.appendChild(row); });
    }

    function handleShareHolderFiles(files) {
        const fileList = document.getElementById('shareHolderFileList');
        if (!fileList) return;
        Array.from(files).forEach(function(file) {
            shareHolderFiles.push(file);
            const fileItem = document.createElement('div');
            fileItem.className = 'share-holder-file-item';
            fileItem.style.cssText = 'display: flex; align-items: center; justify-content: space-between; padding: 0.5rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; margin-bottom: 0.5rem;';
            fileItem.innerHTML = '<div style="display: flex; align-items: center; gap: 0.5rem;"><i class="feather icon-file" style="color: #c5a864;"></i><span style="font-size: 0.85rem; color: #334155;">' + file.name + '</span><span style="font-size: 0.75rem; color: #94a3b8;">(' + (file.size / 1024).toFixed(2) + ' KB)</span></div><button type="button" onclick="window.removeShareHolderFile(this)" style="background: transparent; border: none; color: #ef4444; cursor: pointer; padding: 0.25rem;"><i class="feather icon-x" style="font-size: 0.9rem;"></i></button>';
            fileList.appendChild(fileItem);
        });
    }

    function handleShareHolderFileDrop(event) {
        event.preventDefault();
        var uploadArea = document.getElementById('shareHolderDocumentUpload');
        if (uploadArea) uploadArea.style.borderColor = '#cbd5e1';
        handleShareHolderFiles(event.dataTransfer.files);
    }

    function handleShareHolderFileSelect(input) {
        handleShareHolderFiles(input.files);
    }

    window.removeShareHolderFile = function(button) {
        var fileItem = button.closest('.share-holder-file-item');
        if (fileItem) {
            var fileName = fileItem.querySelector('span').textContent.trim();
            shareHolderFiles = shareHolderFiles.filter(function(f) { return f.name !== fileName; });
            fileItem.remove();
        }
    };

    function ensureOpeningBranchInteractable() {
        var el = document.getElementById('openingBranchId');
        if (!el) return;
        el.disabled = false;
        el.removeAttribute('disabled');
        el.removeAttribute('readonly');
        el.style.pointerEvents = 'auto';
        el.style.opacity = '1';
    }

    function clearCustomerForm() {
        var form = document.getElementById('customerCreationForm');
        if (form) form.reset();
        var defBr = form && form.getAttribute('data-default-opening-branch');
        var defBid = defBr !== null && defBr !== '' ? parseInt(defBr, 10) : 0;
        if (defBid > 0 && document.getElementById('openingBranchId')) {
            setVal('openingBranchId', String(defBid));
        }
        ensureOpeningBranchInteractable();
        var preview = document.getElementById('ledgerPhotoPreview');
        if (preview) preview.style.display = 'none';
        var circle = document.querySelector('.gm-photo-circle');
        if (circle) circle.style.display = '';
        var photoInput = document.getElementById('ledgerPhotoInput');
        if (photoInput) photoInput.value = '';
        var shareHoldersBody = document.getElementById('shareHoldersTableBody');
        if (shareHoldersBody) shareHoldersBody.innerHTML = '';
        shareHolderRowIndex = 0;
        shareHoldersData = [];
        var fileList = document.getElementById('shareHolderFileList');
        if (fileList) fileList.innerHTML = '';
        shareHolderFiles = [];
    }

    function saveCustomer(saveBtn) {
        var form = document.getElementById('customerCreationForm');
        if (!form || !form.checkValidity()) {
            if (form) form.reportValidity();
            return;
        }
        var ledgerIdEl = document.getElementById('ledgerCustomerId');
        var isNewCustomer = !ledgerIdEl || !String(ledgerIdEl.value || '').trim();
        var customerTypeEl = document.getElementById('customerType');
        if (isNewCustomer && customerTypeEl && !String(customerTypeEl.value || '').trim()) {
            alert('Customer type is required');
            customerTypeEl.focus();
            return;
        }
        var formData = new FormData(form);
        saveBtn = saveBtn || document.getElementById('saveCustomerBtn');
        var originalText = saveBtn.innerHTML;
        saveBtn.innerHTML = '<i class="feather icon-loader spin"></i> Saving...';
        saveBtn.disabled = true;
        fetch('customer-save.php', { method: 'POST', body: formData })
            .then(function(response) {
                if (!response.ok) throw new Error('Network response was not ok');
                return response.text().then(function(text) {
                    try { return JSON.parse(text); } catch (e) { throw new Error('Invalid JSON response'); }
                });
            })
            .then(function(data) {
                if (data.status === 'success' || data.success === true) {
                    alert(data.message || 'Customer created successfully!');
                    window.location.href = 'account-ledger.php';
                } else {
                    alert('Error: ' + (data.message || 'Failed to create customer'));
                }
            })
            .catch(function(error) {
                console.error('Error:', error);
                alert('Error saving customer: ' + error.message);
            })
            .finally(function() {
                saveBtn.innerHTML = originalText;
                saveBtn.disabled = false;
            });
    }

    window.previewLedgerPhoto = previewLedgerPhoto;
    window.handleNameInput = handleNameInput;
    window.clearCustomerForm = clearCustomerForm;
    window.saveCustomer = saveCustomer;
    window.sortShareHoldersTable = sortShareHoldersTable;
    window.handleShareHolderFileDrop = handleShareHolderFileDrop;
    window.handleShareHolderFileSelect = handleShareHolderFileSelect;

    function setVal(id, val) {
        var el = document.getElementById(id);
        if (el) el.value = val || '';
    }

    var ledgerOpeningBranchFromUrl = <?php echo json_encode($ledger_opening_branch_from_url); ?>;

    function getCustomerAjaxUrl(customerId, branchOverride) {
        var qs = 'ajax/get-customer.php?customer_id=' + encodeURIComponent(customerId);
        if (branchOverride !== undefined && branchOverride !== null) {
            qs += '&branch_id=' + encodeURIComponent(String(branchOverride));
        } else if (ledgerOpeningBranchFromUrl !== null) {
            qs += '&branch_id=' + encodeURIComponent(String(ledgerOpeningBranchFromUrl));
        }
        return qs;
    }

    function applyOpeningMetalOpenings(openingMetals) {
        document.querySelectorAll('.opening-metal-row').forEach(function(row) {
            var mid = parseInt(row.getAttribute('data-metal-id') || '0', 10);
            var wtEl = row.querySelector('.opening-metal-weight');
            var creditEl = row.querySelector('input.opening-metal-type[value="credit"]');
            var debitEl = row.querySelector('input.opening-metal-type[value="debit"]');
            var found = null;
            if (Array.isArray(openingMetals)) {
                for (var i = 0; i < openingMetals.length; i++) {
                    if (parseInt(openingMetals[i].metal_id, 10) === mid) {
                        found = openingMetals[i];
                        break;
                    }
                }
            }
            if (wtEl) {
                wtEl.value = found && found.weight != null ? found.weight : '0';
            }
            var isDebit = found && String(found.type || '').toLowerCase() === 'debit';
            if (creditEl) creditEl.checked = !isDebit;
            if (debitEl) debitEl.checked = !!isDebit;
        });
    }

    function applyOpeningSidebarFromCustomer(c) {
        setVal('openingBalance', c.opening_balance || '0');
        if (c.opening_type === 'Debit') {
            document.getElementById('openingDebit').checked = true;
        } else {
            document.getElementById('openingCredit').checked = true;
        }
        setVal('openingBranchId', c.opening_branch_id != null && c.opening_branch_id !== '' ? String(c.opening_branch_id) : '');
        applyOpeningMetalOpenings(c.opening_metals || []);
        ensureOpeningBranchInteractable();
    }

    function loadCustomerForEdit(customerId) {
        if (!customerId) return;
        fetch(getCustomerAjaxUrl(customerId))
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (res.status !== 'success' || !res.customer) return;
                var c = res.customer;
                setVal('customerId', c.id);
                setVal('ledgerName', c.name);
                setVal('ledgerAlternateName', c.alternate_name);
                setVal('ledgerFirstName', c.first_name);
                setVal('ledgerLastName', c.last_name);
                setVal('mobileCountryCode', c.mobile_country_code || '971');
                setVal('ledgerMobileNo', c.mobile_no || '');
                setVal('ledgerPhoneNo', c.phone_no || '');
                setVal('ledgerMailId', c.mail_id || '');
                setVal('ledgerIdentityNo', c.identity_no || '');
                setVal('ledgerNationalId', c.national_id || '');
                setVal('ledgerTradeNo', c.trade_no || '');
                setVal('identityIssueDate', c.identity_issue_date || '');
                setVal('identityExpiryDate', c.identity_expiry_date || '');
                setVal('specialDay', c.special_day || '');
                setVal('customerType', c.customer_type_id || '');
                setVal('registrationNo', c.registration_no || '');
                setVal('registrationDate', c.registration_date || '');
                setVal('ledgerGstin', c.gstin || '');
                setVal('nationality', c.nationality_id || '');
                setVal('country', c.country_id || '');
                setVal('ledgerGroup', c.group_id || '');
                setVal('ledgerSundryDebtors', c.sundry_debtors_id || '');
                document.getElementById('ledgerKYC').checked = !!c.kyc;
                document.getElementById('ledgerAML').checked = !!c.aml;
                if (c.bill_to_bill == 1) document.getElementById('billToBillYes').checked = true;
                else document.getElementById('billToBillNo').checked = true;
                document.getElementById('ledgerNameCapital').checked = !!c.ledger_name_capital;
                setVal('billingAddress1', c.billing_address1 || '');
                setVal('billingAddress2', c.billing_address2 || '');
                setVal('billingZipCode', c.billing_zip_code || '');
                setVal('shippingAddress1', c.shipping_address1 || '');
                setVal('shippingAddress2', c.shipping_address2 || '');
                setVal('shippingZipCode', c.shipping_zip_code || '');
                if (typeof window.prefillCustomerLedgerAddressesAsync === 'function') {
                    window.prefillCustomerLedgerAddressesAsync(c);
                }
                setVal('bankAccountNo', c.bank_account_no || '');
                setVal('bankName', c.bank_name || '');
                setVal('bankIfscCode', c.bank_ifsc_code || '');
                setVal('bankBranch', c.bank_branch || '');
                setVal('ledgerNotes', c.notes || '');
                if (typeof window.fillCustomerNomineesFromCustomer === 'function') {
                    window.fillCustomerNomineesFromCustomer(c);
                }
                if (c.ledger_photo) {
                    document.getElementById('ledgerPhotoPreview').style.display = 'block';
                    var circle = document.querySelector('.gm-photo-circle');
                    if (circle) circle.style.display = 'none';
                    document.getElementById('ledgerPhotoImg').src = c.ledger_photo;
                }
                applyOpeningSidebarFromCustomer(c);
            })
            .catch(function(err) { console.error('Load customer error:', err); });
    }

    document.addEventListener('DOMContentLoaded', function() {
        var addBtn = document.getElementById('addShareHolderBtn');
        if (addBtn) addBtn.addEventListener('click', addShareHolderRow);
        var editId = <?php echo json_encode($edit_customer_id); ?>;
        if (editId) loadCustomerForEdit(editId);
        ensureOpeningBranchInteractable();
        var openingBranchEl = document.getElementById('openingBranchId');
        if (openingBranchEl) {
            openingBranchEl.addEventListener('change', function() {
                var cidEl = document.getElementById('customerId');
                var cid = cidEl && cidEl.value ? parseInt(cidEl.value, 10) : 0;
                if (!cid) return;
                var v = this.value;
                var branchParam = v === '' ? 0 : parseInt(v, 10);
                fetch(getCustomerAjaxUrl(cid, branchParam))
                    .then(function(r) { return r.json(); })
                    .then(function(res) {
                        if (res.status !== 'success' || !res.customer) return;
                        applyOpeningSidebarFromCustomer(res.customer);
                    })
                    .catch(function(err) { console.error('Load opening for branch:', err); });
            });
        }
    });
})();
</script>

<script src="js/customer-ledger-address.js"></script>
<script src="assets/js/customer-nominee.js?v=<?php echo (int) @filemtime(__DIR__ . '/assets/js/customer-nominee.js'); ?>"></script>
<?php include 'footer-script.php'; ?>
</body>
</html>

