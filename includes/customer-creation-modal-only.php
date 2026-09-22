<?php
if (!isset($countries_ledger)) {
    $countries_ledger = getList("SELECT id, name FROM tbl_countries WHERE status = 1 ORDER BY name ASC");
}
require_once __DIR__ . '/international-dial-codes.php';
require_once __DIR__ . '/ledger-modal-document-types-script.php';
$ccm_css_ver = (int) @filemtime(__DIR__ . '/../assets/css/customer-creation-modal.css');
?>
<link rel="stylesheet" href="assets/css/customer-creation-modal.css?v=<?php echo $ccm_css_ver; ?>">
<!-- Right Side Customer Creation Modal -->
<div class="modal fade right" id="customerCreationModal" tabindex="-1" role="dialog" aria-labelledby="customerCreationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-right modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="customerCreationModalLabel">Ledger Details</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="customerCreationForm" method="post" enctype="multipart/form-data">
                    <input type="hidden" id="ledgerCustomerId" name="customer_id" value="">

                    <div class="ccm-toolbar">
                        <button type="button" class="btn btn-ccm-clear" onclick="clearCustomerForm()">Clear</button>
                        <button type="button" class="btn btn-ccm-save" id="customerModalSaveBtn" onclick="saveCustomer(event)">Save</button>
                        <button type="button" class="btn btn-ccm-close" data-dismiss="modal">Close</button>
                    </div>

                    <div class="ccm-scroll">
                        <div class="ccm-inner">

                            <!-- Basic Details -->
                            <div class="ccm-card">
                                <div class="ccm-card-body">
                                    <h6 class="ccm-section-title">Basic Details</h6>
                                    <div class="row">
                                        <div class="col-md-2 col-sm-3">
                                            <div class="ccm-photo-col">
                                                <div class="ccm-photo-upload" onclick="document.getElementById('ledgerPhotoInput').click();" title="Upload photo">
                                                    <i class="feather icon-camera"></i>
                                                    <input type="file" id="ledgerPhotoInput" name="ledger_photo" accept="image/*" style="display: none;" onchange="previewLedgerPhoto(this);">
                                                </div>
                                                <div id="ledgerPhotoPreview" onclick="document.getElementById('ledgerPhotoInput').click();" title="Change photo">
                                                    <img id="ledgerPhotoImg" src="" alt="Ledger photo">
                                                </div>
                                                <div class="form-check ccm-photo-check">
                                                    <input class="form-check-input" type="checkbox" id="ledgerNameCapital" name="ledger_name_capital">
                                                    <label class="form-check-label" for="ledgerNameCapital">Ledger Name Capital</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-10 col-sm-9">
                                            <div class="row">
                                                <div class="col-md-4">
                                                    <div class="form-group">
                                                        <label>Name <span class="req">*</span></label>
                                                        <input type="text" class="form-control" id="ledgerName" name="name" required oninput="handleNameInput(this)">
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="form-group">
                                                        <label>Alternate Name</label>
                                                        <input type="text" class="form-control" id="ledgerAlternateName" name="alternate_name">
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="form-group">
                                                        <label>First Name</label>
                                                        <input type="text" class="form-control" id="ledgerFirstName" name="first_name">
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="form-group">
                                                        <label>Last Name</label>
                                                        <input type="text" class="form-control" id="ledgerLastName" name="last_name">
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="form-group">
                                                        <label>Mobile No <span class="req">*</span></label>
                                                        <div class="input-group">
                                                            <select class="form-control ccm-dial" id="mobileCountryCode" name="mobile_country_code">
                                                                <?php auragold_render_dial_code_select('971'); ?>
                                                            </select>
                                                            <input type="text" class="form-control" id="ledgerMobileNo" name="mobile_no" placeholder="Mobile No" inputmode="numeric" pattern="[0-9]*" autocomplete="tel" required>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="form-group">
                                                        <label>Phone No</label>
                                                        <div class="input-group">
                                                            <select class="form-control ccm-dial" id="phoneCountryCode" name="phone_country_code">
                                                                <?php auragold_render_dial_code_select('971'); ?>
                                                            </select>
                                                            <input type="text" class="form-control" id="ledgerPhoneNo" name="phone_no" placeholder="Phone No" inputmode="numeric" autocomplete="tel">
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="form-group">
                                                        <label>Mail ID</label>
                                                        <div class="input-group ccm-has-icon">
                                                            <i class="feather icon-mail ccm-field-icon"></i>
                                                            <input type="email" class="form-control" id="ledgerMailId" name="mail_id" placeholder="Email">
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="form-group">
                                                        <label>Short Code / Identity No</label>
                                                        <input type="text" class="form-control" id="ledgerIdentityNo" name="identity_no">
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="form-group">
                                                        <label>National Id</label>
                                                        <div class="input-group ccm-has-icon">
                                                            <i class="feather icon-credit-card ccm-field-icon"></i>
                                                            <input type="text" class="form-control" id="ledgerNationalId" name="national_id">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <h6 class="ccm-subsection">Identity &amp; Registration</h6>
                                    <div class="row">
                                        <div class="col-md-3 col-sm-6">
                                            <div class="form-group">
                                                <label>Trade No</label>
                                                <div class="input-group ccm-has-icon">
                                                    <i class="feather icon-briefcase ccm-field-icon"></i>
                                                    <input type="text" class="form-control" id="ledgerTradeNo" name="trade_no">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3 col-sm-6">
                                            <div class="form-group">
                                                <label>Identity Issue Date</label>
                                                <input type="date" class="form-control" id="identityIssueDate" name="identity_issue_date">
                                            </div>
                                        </div>
                                        <div class="col-md-3 col-sm-6">
                                            <div class="form-group">
                                                <label>Identity Expiry Date</label>
                                                <input type="date" class="form-control" id="identityExpiryDate" name="identity_expiry_date">
                                            </div>
                                        </div>
                                        <div class="col-md-3 col-sm-6">
                                            <div class="form-group">
                                                <label>Special Day</label>
                                                <input type="date" class="form-control" id="specialDay" name="special_day">
                                            </div>
                                        </div>
                                        <div class="col-md-3 col-sm-6">
                                            <div class="form-group">
                                                <label>Customer Type <span class="req">*</span></label>
                                                <div class="input-group ccm-has-icon">
                                                    <i class="feather icon-users ccm-field-icon"></i>
                                                    <select class="form-control" id="customerType" name="customer_type_id">
                                                        <option value="">Select Customer Type</option>
                                                        <?php
                                                        $customer_types = getList("SELECT id, name FROM tbl_customer_types WHERE status = 1 ORDER BY name ASC");
                                                        foreach ($customer_types as $type) {
                                                            echo '<option value="' . $type['id'] . '">' . htmlspecialchars($type['name']) . '</option>';
                                                        }
                                                        ?>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3 col-sm-6">
                                            <div class="form-group">
                                                <label>Registration No</label>
                                                <input type="text" class="form-control" id="registrationNo" name="registration_no">
                                            </div>
                                        </div>
                                        <div class="col-md-3 col-sm-6">
                                            <div class="form-group">
                                                <label>Registration Date</label>
                                                <input type="date" class="form-control" id="registrationDate" name="registration_date">
                                            </div>
                                        </div>
                                        <div class="col-md-3 col-sm-6">
                                            <div class="form-group">
                                                <label>GSTIN <span class="hint">(e-Way)</span></label>
                                                <input type="text" class="form-control" id="ledgerGstin" name="gstin" maxlength="15" placeholder="Buyer GSTIN (15 chars)" autocomplete="off" style="text-transform:uppercase;">
                                            </div>
                                        </div>
                                        <div class="col-md-3 col-sm-6">
                                            <div class="form-group">
                                                <label>Nationality</label>
                                                <div class="input-group ccm-has-icon">
                                                    <i class="feather icon-flag ccm-field-icon"></i>
                                                    <select class="form-control" id="nationality" name="nationality_id">
                                                        <option value="">Select Nationality</option>
                                                        <?php
                                                        $nationalities = getList("SELECT id, name FROM tbl_nationalities WHERE status = 1 ORDER BY name ASC");
                                                        foreach ($nationalities as $nationality) {
                                                            echo '<option value="' . $nationality['id'] . '">' . htmlspecialchars($nationality['name']) . '</option>';
                                                        }
                                                        ?>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <h6 class="ccm-subsection">Location &amp; Classification</h6>
                                    <div class="row">
                                        <div class="col-md-3 col-sm-6">
                                            <div class="form-group">
                                                <label>Country</label>
                                                <div class="input-group ccm-has-icon">
                                                    <i class="feather icon-flag ccm-field-icon"></i>
                                                    <select class="form-control" id="country" name="country_id">
                                                        <option value="">Select Country</option>
                                                        <?php
                                                        $countries = getList("SELECT id, name FROM tbl_countries WHERE status = 1 ORDER BY name ASC");
                                                        foreach ($countries as $country) {
                                                            echo '<option value="' . $country['id'] . '">' . htmlspecialchars($country['name']) . '</option>';
                                                        }
                                                        ?>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3 col-sm-6">
                                            <div class="form-group">
                                                <label>State</label>
                                                <select class="form-control" id="ledgerState" name="ledger_state_id">
                                                    <option value="">Select State</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-3 col-sm-6">
                                            <div class="form-group">
                                                <label>City</label>
                                                <select class="form-control" id="ledgerCity" name="ledger_city_id">
                                                    <option value="">Select City</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-3 col-sm-6">
                                            <div class="form-group">
                                                <label>Select Group</label>
                                                <div class="input-group ccm-has-icon">
                                                    <i class="feather icon-users ccm-field-icon"></i>
                                                    <select class="form-control" id="ledgerGroup" name="group_id">
                                                        <option value="">Select Group</option>
                                                        <?php
                                                        foreach ($ledger_groups as $group) {
                                                            echo '<option value="' . $group['id'] . '">' . htmlspecialchars($group['name']) . '</option>';
                                                        }
                                                        ?>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3 col-sm-6">
                                            <div class="form-group">
                                                <label>Sundry Debtors <span class="req">*</span></label>
                                                <div class="input-group ccm-has-icon">
                                                    <i class="feather icon-users ccm-field-icon"></i>
                                                    <select class="form-control" id="ledgerSundryDebtors" name="sundry_debtors_id" required>
                                                        <?php
                                                        foreach ($sundry_options as $option) {
                                                            echo '<option value="' . $option['id'] . '">' . htmlspecialchars($option['name']) . '</option>';
                                                        }
                                                        ?>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-9 col-sm-12">
                                            <div class="ccm-flags">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="ledgerKYC" name="kyc">
                                                    <label class="form-check-label" for="ledgerKYC">KYC</label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="ledgerAML" name="aml">
                                                    <label class="form-check-label" for="ledgerAML">AML</label>
                                                </div>
                                                <div class="ccm-radio-group">
                                                    <span>Bill to Bill:</span>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="radio" id="billToBillYes" name="bill_to_bill" value="1">
                                                        <label class="form-check-label" for="billToBillYes">Yes</label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="radio" id="billToBillNo" name="bill_to_bill" value="0" checked>
                                                        <label class="form-check-label" for="billToBillNo">No</label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Address / Extra Tabs -->
                            <div class="ccm-card">
                                <ul class="nav nav-tabs" id="ledgerTabs" role="tablist">
                                    <li class="nav-item">
                                        <a class="nav-link active" id="billing-tab" data-toggle="tab" href="#billing-address" role="tab">Billing Address</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" id="shipping-tab" data-toggle="tab" href="#shipping-address" role="tab">Shipping Address</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" id="item-type-tax-tab" data-toggle="tab" href="#item-type-tax" role="tab">Item Type Tax</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" id="share-holders-tab" data-toggle="tab" href="#share-holders" role="tab">Share Holders</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" id="notes-tab" data-toggle="tab" href="#notes" role="tab">Notes</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" id="nominee-tab" data-toggle="tab" href="#nominee" role="tab">Nominee</a>
                                    </li>
                                    <li class="nav-item">
                                    </li>
                                </ul>

                                <div class="tab-content" id="ledgerTabContent">
                                    <!-- Billing Address Tab -->
                                    <div class="tab-pane fade show active" id="billing-address" role="tabpanel">
                                        <div class="row">
                                            <div class="col-md-6 mb-3 mb-md-0">
                                                <div class="ccm-panel">
                                                    <h6 class="ccm-panel-title">Billing Address</h6>
                                                    <div class="form-group">
                                                        <label>Address 1</label>
                                                        <input type="text" class="form-control" id="billingAddress1" name="billing_address1">
                                                    </div>
                                                    <div class="form-group">
                                                        <label>Address 2</label>
                                                        <input type="text" class="form-control" id="billingAddress2" name="billing_address2">
                                                    </div>
                                                    <div class="form-group">
                                                        <label>Country <span class="req">*</span></label>
                                                        <select class="form-control" id="billingCountry" name="billing_country" required>
                                                            <option value="">Select Country</option>
                                                            <?php foreach ($countries_ledger as $co) {
                                                                $nm = htmlspecialchars($co['name'], ENT_QUOTES, 'UTF-8');
                                                                $cid = (int) $co['id'];
                                                                echo '<option value="' . $nm . '" data-country-id="' . $cid . '">' . $nm . '</option>';
                                                            } ?>
                                                        </select>
                                                    </div>
                                                    <div class="form-group">
                                                        <label>State <span class="req">*</span></label>
                                                        <select class="form-control" id="billingState" name="billing_state" required>
                                                            <option value="">Select State</option>
                                                        </select>
                                                    </div>
                                                    <div class="form-group">
                                                        <label>City</label>
                                                        <div class="d-flex align-items-center flex-wrap" style="gap: 6px;">
                                                            <select class="form-control" id="billingCity" name="billing_city" style="flex: 1; min-width: 0;">
                                                                <option value="">Select City</option>
                                                            </select>
                                                            <button type="button" class="btn btn-light border rounded-circle city-info-btn d-inline-flex align-items-center justify-content-center" title="Cities load for the selected state. Use + to add a new city under this state." tabindex="-1">
                                                                <i class="feather icon-info" style="font-size: 1rem; color: #64748b;"></i>
                                                            </button>
                                                            <button type="button" class="btn btn-light border rounded-circle city-add-btn d-inline-flex align-items-center justify-content-center" data-target="billing" title="Add city under selected state">
                                                                <i class="feather icon-plus" style="font-size: 1rem; color: #11294b;"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                    <div class="form-group">
                                                        <label>Zip Code</label>
                                                        <input type="text" class="form-control" id="billingZipCode" name="billing_zip_code">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="ccm-panel">
                                                    <h6 class="ccm-panel-title">Bank Details</h6>
                                                    <div class="form-group">
                                                        <label>Acc. No.</label>
                                                        <input type="text" class="form-control" id="bankAccountNo" name="bank_account_no" placeholder="Account No">
                                                    </div>
                                                    <div class="form-group">
                                                        <label>Bank Name</label>
                                                        <input type="text" class="form-control" id="bankName" name="bank_name" placeholder="Bank Name">
                                                    </div>
                                                    <div class="form-group">
                                                        <label>IFSC Code</label>
                                                        <input type="text" class="form-control" id="bankIfscCode" name="bank_ifsc_code" placeholder="IFSC Code">
                                                    </div>
                                                    <div class="form-group">
                                                        <label>Branch</label>
                                                        <input type="text" class="form-control" id="bankBranch" name="bank_branch">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Shipping Address Tab -->
                                    <div class="tab-pane fade" id="shipping-address" role="tabpanel">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="ccm-panel">
                                                    <h6 class="ccm-panel-title">Shipping Address</h6>
                                                    <div class="form-group">
                                                        <label>Address 1</label>
                                                        <input type="text" class="form-control" id="shippingAddress1" name="shipping_address1">
                                                    </div>
                                                    <div class="form-group">
                                                        <label>Address 2</label>
                                                        <input type="text" class="form-control" id="shippingAddress2" name="shipping_address2">
                                                    </div>
                                                    <div class="form-group">
                                                        <label>Country</label>
                                                        <select class="form-control" id="shippingCountry" name="shipping_country">
                                                            <option value="">Select Country</option>
                                                            <?php foreach ($countries_ledger as $co) {
                                                                $nm = htmlspecialchars($co['name'], ENT_QUOTES, 'UTF-8');
                                                                $cid = (int) $co['id'];
                                                                echo '<option value="' . $nm . '" data-country-id="' . $cid . '">' . $nm . '</option>';
                                                            } ?>
                                                        </select>
                                                    </div>
                                                    <div class="form-group">
                                                        <label>State</label>
                                                        <select class="form-control" id="shippingState" name="shipping_state">
                                                            <option value="">Select State</option>
                                                        </select>
                                                    </div>
                                                    <div class="form-group">
                                                        <label>City</label>
                                                        <div class="d-flex align-items-center flex-wrap" style="gap: 6px;">
                                                            <select class="form-control" id="shippingCity" name="shipping_city" style="flex: 1; min-width: 0;">
                                                                <option value="">Select City</option>
                                                            </select>
                                                            <button type="button" class="btn btn-light border rounded-circle city-info-btn d-inline-flex align-items-center justify-content-center" title="Cities load for the selected state. Use + to add a new city under this state." tabindex="-1">
                                                                <i class="feather icon-info" style="font-size: 1rem; color: #64748b;"></i>
                                                            </button>
                                                            <button type="button" class="btn btn-light border rounded-circle city-add-btn d-inline-flex align-items-center justify-content-center" data-target="shipping" title="Add city under selected state">
                                                                <i class="feather icon-plus" style="font-size: 1rem; color: #11294b;"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                    <div class="form-group">
                                                        <label>Zip Code</label>
                                                        <input type="text" class="form-control" id="shippingZipCode" name="shipping_zip_code">
                                                    </div>
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
                                                <tr>
                                                    <td>AMOUNT</td>
                                                    <td>
                                                        <select name="item_tax[AMOUNT][input_type]" class="form-control">
                                                            <option value="">...</option>
                                                            <option value="VAT">VAT</option>
                                                            <option value="TAX BAH">TAX BAH</option>
                                                        </select>
                                                    </td>
                                                    <td>
                                                        <select name="item_tax[AMOUNT][output_type]" class="form-control">
                                                            <option value="">...</option>
                                                            <option value="VAT">VAT</option>
                                                            <option value="TAX BAH">TAX BAH</option>
                                                        </select>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td>Gold</td>
                                                    <td>
                                                        <select name="item_tax[Gold][input_type]" class="form-control">
                                                            <option value="">...</option>
                                                            <option value="VAT">VAT</option>
                                                            <option value="TAX BAH">TAX BAH</option>
                                                        </select>
                                                    </td>
                                                    <td>
                                                        <select name="item_tax[Gold][output_type]" class="form-control">
                                                            <option value="">...</option>
                                                            <option value="VAT">VAT</option>
                                                            <option value="TAX BAH">TAX BAH</option>
                                                        </select>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td>GOLD - MAKING</td>
                                                    <td>
                                                        <select name="item_tax[GOLD_MAKING][input_type]" class="form-control">
                                                            <option value="">...</option>
                                                            <option value="VAT">VAT</option>
                                                            <option value="TAX BAH">TAX BAH</option>
                                                        </select>
                                                    </td>
                                                    <td>
                                                        <select name="item_tax[GOLD_MAKING][output_type]" class="form-control">
                                                            <option value="">...</option>
                                                            <option value="VAT">VAT</option>
                                                            <option value="TAX BAH">TAX BAH</option>
                                                        </select>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td>Silver</td>
                                                    <td>
                                                        <select name="item_tax[Silver][input_type]" class="form-control">
                                                            <option value="">...</option>
                                                            <option value="VAT">VAT</option>
                                                            <option value="TAX BAH">TAX BAH</option>
                                                        </select>
                                                    </td>
                                                    <td>
                                                        <select name="item_tax[Silver][output_type]" class="form-control">
                                                            <option value="">...</option>
                                                            <option value="VAT">VAT</option>
                                                            <option value="TAX BAH">TAX BAH</option>
                                                        </select>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td>SILVER - MAKING</td>
                                                    <td>
                                                        <select name="item_tax[SILVER_MAKING][input_type]" class="form-control">
                                                            <option value="">...</option>
                                                            <option value="VAT">VAT</option>
                                                            <option value="TAX BAH">TAX BAH</option>
                                                        </select>
                                                    </td>
                                                    <td>
                                                        <select name="item_tax[SILVER_MAKING][output_type]" class="form-control">
                                                            <option value="">...</option>
                                                            <option value="VAT">VAT</option>
                                                            <option value="TAX BAH">TAX BAH</option>
                                                        </select>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td>Diamond &amp; Stones</td>
                                                    <td>
                                                        <select name="item_tax[Diamond_Stones][input_type]" class="form-control">
                                                            <option value="">...</option>
                                                            <option value="VAT">VAT</option>
                                                            <option value="TAX BAH">TAX BAH</option>
                                                        </select>
                                                    </td>
                                                    <td>
                                                        <select name="item_tax[Diamond_Stones][output_type]" class="form-control">
                                                            <option value="">...</option>
                                                            <option value="VAT">VAT</option>
                                                            <option value="TAX BAH">TAX BAH</option>
                                                        </select>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td>Imitation Or Watches</td>
                                                    <td>
                                                        <select name="item_tax[Imitation_Watches][input_type]" class="form-control">
                                                            <option value="">...</option>
                                                            <option value="VAT">VAT</option>
                                                            <option value="TAX BAH">TAX BAH</option>
                                                        </select>
                                                    </td>
                                                    <td>
                                                        <select name="item_tax[Imitation_Watches][output_type]" class="form-control">
                                                            <option value="">...</option>
                                                            <option value="VAT">VAT</option>
                                                            <option value="TAX BAH">TAX BAH</option>
                                                        </select>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td>LOOSE - DIAMOND</td>
                                                    <td>
                                                        <select name="item_tax[LOOSE_DIAMOND][input_type]" class="form-control">
                                                            <option value="">...</option>
                                                            <option value="VAT">VAT</option>
                                                            <option value="TAX BAH">TAX BAH</option>
                                                        </select>
                                                    </td>
                                                    <td>
                                                        <select name="item_tax[LOOSE_DIAMOND][output_type]" class="form-control">
                                                            <option value="">...</option>
                                                            <option value="VAT">VAT</option>
                                                            <option value="TAX BAH">TAX BAH</option>
                                                        </select>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td>CERTIFIED - DIAMOND</td>
                                                    <td>
                                                        <select name="item_tax[CERTIFIED_DIAMOND][input_type]" class="form-control">
                                                            <option value="">...</option>
                                                            <option value="VAT">VAT</option>
                                                            <option value="TAX BAH">TAX BAH</option>
                                                        </select>
                                                    </td>
                                                    <td>
                                                        <select name="item_tax[CERTIFIED_DIAMOND][output_type]" class="form-control">
                                                            <option value="">...</option>
                                                            <option value="VAT">VAT</option>
                                                            <option value="TAX BAH">TAX BAH</option>
                                                        </select>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td>Other Or Services</td>
                                                    <td>
                                                        <select name="item_tax[Other_Services][input_type]" class="form-control">
                                                            <option value="">...</option>
                                                            <option value="VAT">VAT</option>
                                                            <option value="TAX BAH">TAX BAH</option>
                                                        </select>
                                                    </td>
                                                    <td>
                                                        <select name="item_tax[Other_Services][output_type]" class="form-control">
                                                            <option value="">...</option>
                                                            <option value="VAT">VAT</option>
                                                            <option value="TAX BAH">TAX BAH</option>
                                                        </select>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>

                                    <div class="tab-pane fade" id="share-holders" role="tabpanel">
                                        <div style="margin-bottom: 1.5rem;">
                                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                                                <h6 class="ccm-panel-title" style="margin: 0;">Share Holders</h6>
                                                <div style="display: flex; gap: 0.5rem;">
                                                    <button type="button" class="btn btn-sm ccm-sh-add-btn" id="addShareHolderBtn" title="Add share holder" aria-label="Add share holder">
                                                        <i class="feather icon-plus" aria-hidden="true"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm ccm-sh-settings-btn" id="shareHolderDocTypeSettingsBtn" title="Document type settings" aria-label="Document type settings">
                                                        <i class="feather icon-settings" aria-hidden="true"></i>
                                                    </button>
                                                </div>
                                            </div>

                                            <div style="overflow-x: auto;">
                                                <table class="table" id="shareHoldersTable" style="margin-bottom: 0; font-size: 0.85rem;">
                                                    <thead style="background: #11294b; color: #fff;">
                                                        <tr>
                                                            <th style="padding: 0.6rem 1rem; font-weight: 600; font-size: 0.85rem; border: none; cursor: pointer; user-select: none;" onclick="sortShareHoldersTable(0)">
                                                                Name
                                                                <i class="feather icon-arrow-up" style="font-size: 0.7rem; margin-left: 0.25rem;"></i>
                                                                <i class="feather icon-arrow-down" style="font-size: 0.7rem; margin-left: 0.1rem;"></i>
                                                            </th>
                                                            <th style="padding: 0.6rem 1rem; font-weight: 600; font-size: 0.85rem; border: none; cursor: pointer; user-select: none;" onclick="sortShareHoldersTable(1)">
                                                                Nationality
                                                                <i class="feather icon-arrow-up" style="font-size: 0.7rem; margin-left: 0.25rem;"></i>
                                                                <i class="feather icon-arrow-down" style="font-size: 0.7rem; margin-left: 0.1rem;"></i>
                                                            </th>
                                                            <th style="padding: 0.6rem 1rem; font-weight: 600; font-size: 0.85rem; border: none; cursor: pointer; user-select: none;" onclick="sortShareHoldersTable(2)">
                                                                Share Per.
                                                                <i class="feather icon-arrow-up" style="font-size: 0.7rem; margin-left: 0.25rem;"></i>
                                                                <i class="feather icon-arrow-down" style="font-size: 0.7rem; margin-left: 0.1rem;"></i>
                                                            </th>
                                                            <th style="padding: 0.6rem 1rem; font-weight: 600; font-size: 0.85rem; border: none; width: 60px; text-align: center;">Action</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="shareHoldersTableBody">
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>

                                        <div style="margin-top: 1.5rem;">
                                            <?php require __DIR__ . '/customer-share-holder-documents-markup.php'; ?>
                                        </div>
                                    </div>

                                    <div class="tab-pane fade" id="notes" role="tabpanel">
                                        <div class="ccm-panel">
                                            <div class="form-group mb-0">
                                                <label>Notes</label>
                                                <textarea class="form-control" id="ledgerNotes" name="notes" rows="5"></textarea>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="tab-pane fade" id="nominee" role="tabpanel">
                                        <?php require __DIR__ . '/customer-nominee-markup.php'; ?>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Document Type settings (Share Holders) — stacked over customer modal -->
<div class="modal fade" id="ledgerDocumentTypeSettingsModal" tabindex="-1" role="dialog" aria-labelledby="ledgerDocumentTypeSettingsLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm" role="document" style="max-width: 420px;">
        <div class="modal-content">
            <div class="modal-header" style="padding: 0.75rem 1rem;">
                <h6 class="modal-title" id="ledgerDocumentTypeSettingsLabel" style="margin:0;font-weight:600;">Document Types</h6>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body" style="padding: 0.75rem 1rem;">
                <div class="form-group mb-2">
                    <label style="font-size:0.8rem;margin-bottom:0.25rem;">Name <span style="color:#ef4444">*</span></label>
                    <div style="display:flex;gap:0.5rem;">
                        <input type="hidden" id="ledgerDtSettingsEditId" value="">
                        <input type="text" id="ledgerDtSettingsName" class="form-control form-control-sm" placeholder="e.g. PAN, Aadhar" maxlength="150">
                        <button type="button" class="btn btn-sm" id="ledgerDtSettingsSaveBtn" style="background:#11294b;color:#fff;white-space:nowrap;">Save</button>
                    </div>
                </div>
                <div style="max-height:260px;overflow:auto;border:1px solid #e2e8f0;border-radius:6px;">
                    <table class="table table-sm mb-0" style="font-size:0.8rem;">
                        <thead style="background:#f1f5f9;">
                            <tr>
                                <th style="padding:0.45rem 0.65rem;">Name</th>
                                <th style="width:72px;text-align:center;padding:0.45rem;">Action</th>
                            </tr>
                        </thead>
                        <tbody id="ledgerDtSettingsTableBody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="assets/js/customer-ledger-form-validate.js?v=<?php echo (int) @filemtime(__DIR__ . '/../assets/js/customer-ledger-form-validate.js'); ?>"></script>
<script src="assets/js/customer-share-holder-documents-prefill.js?v=<?php echo (int) @filemtime(__DIR__ . '/../assets/js/customer-share-holder-documents-prefill.js'); ?>"></script>
<script src="assets/js/ledger-document-type-settings.js?v=<?php echo (int) @filemtime(__DIR__ . '/../assets/js/ledger-document-type-settings.js'); ?>"></script>
<script src="assets/js/customer-nominee.js?v=<?php echo (int) @filemtime(__DIR__ . '/../assets/js/customer-nominee.js'); ?>"></script>
<script src="js/customer-ledger-address.js"></script>
