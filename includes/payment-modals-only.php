<?php
if (!isset($bank_accounts) || !is_array($bank_accounts)) {
    $bank_accounts = [];
}
if (!isset($credit_cards) || !is_array($credit_cards)) {
    $credit_cards = [];
    if (isset($conn) && $conn instanceof mysqli) {
        require_once __DIR__ . '/auragold_credit_card_schema.php';
        if (function_exists('auragold_ensure_branch_id_on_settings_tables')) {
            auragold_ensure_branch_id_on_settings_tables($conn);
        }
        $cc_branch_id = function_exists('auragold_settings_branch_id') ? (int) auragold_settings_branch_id() : 0;
        if ($cc_branch_id <= 0 && !empty($auragold_working_branch_id)) {
            $cc_branch_id = (int) $auragold_working_branch_id;
        }
        if (function_exists('auragold_get_credit_cards')) {
            $credit_cards = auragold_get_credit_cards($conn, $cc_branch_id);
        }
    }
}
?>
<!-- Payment Modals -->
<!-- Cash Payment Modal -->
<div class="modal fade" id="cashPaymentModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background: #11294b; color: #fff; border: none;">
                <h5 class="modal-title">Cash Payment</h5>
                <button type="button" class="close" data-dismiss="modal" style="color: #fff;">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Deposit Into</label>
                    <select class="form-control" id="cashDepositInto">
                        <option value="Cash">Cash</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Amount</label>
                    <input type="number" class="form-control" id="cashAmount" value="0.00" step="0.01">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" style="border: 1px solid #ec4899; color: #ec4899; background: #fff;" data-dismiss="modal">Clear</button>
                <button type="button" class="btn" style="background: #11294b; color: #fff; border: none;" onclick="savePayment('cash')">Save</button>
            </div>
        </div>
    </div>
</div>

<!-- Bank Payment Modal -->
<div class="modal fade" id="bankPaymentModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background: #11294b; color: #fff; border: none;">
                <h5 class="modal-title">Bank Payment</h5>
                <button type="button" class="close" data-dismiss="modal" style="color: #fff;">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Deposit Into</label>
                    <select class="form-control" id="bankDepositInto">
                        <option value="">Select Bank</option>
                        <?php foreach ($bank_accounts as $bank): ?>
                        <option value="<?php echo htmlspecialchars($bank['name']); ?>"><?php echo htmlspecialchars($bank['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Trans No.</label>
                    <input type="text" class="form-control" id="bankTransNo" placeholder="Transaction Number">
                </div>
                <div class="form-group">
                    <label>Amount</label>
                    <input type="number" class="form-control" id="bankAmount" value="0.00" step="0.01">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" style="border: 1px solid #ec4899; color: #ec4899; background: #fff;" data-dismiss="modal">Clear</button>
                <button type="button" class="btn" style="background: #11294b; color: #fff; border: none;" onclick="savePayment('bank')">Save</button>
            </div>
        </div>
    </div>
</div>

<!-- Cheque Payment Modal -->
<div class="modal fade" id="chequePaymentModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background: #11294b; color: #fff; border: none;">
                <h5 class="modal-title">Cheque Payment</h5>
                <button type="button" class="close" data-dismiss="modal" style="color: #fff;">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Deposit Into</label>
                    <select class="form-control" id="chequeDepositInto">
                        <option value="">Select Bank</option>
                        <?php foreach ($bank_accounts as $bank): ?>
                        <option value="<?php echo htmlspecialchars($bank['name']); ?>"><?php echo htmlspecialchars($bank['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Trans No.</label>
                    <input type="text" class="form-control" id="chequeTransNo" placeholder="Transaction Number">
                </div>
                <div class="form-group">
                    <label>Amount</label>
                    <input type="number" class="form-control" id="chequeAmount" value="0.00" step="0.01">
                </div>
                <div class="form-group">
                    <label>Cheque Dt.</label>
                    <div class="input-group">
                        <input type="date" class="form-control" id="chequeDate" value="<?php echo date('Y-m-d'); ?>">
                        <div class="input-group-append">
                            <button class="btn btn-sm" type="button" onclick="document.getElementById('chequeDate').value = '<?php echo date('Y-m-d'); ?>'" style="background: #f8fafc; border: 1px solid #e2e8f0;">
                                <i class="feather icon-refresh-cw"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" style="border: 1px solid #ec4899; color: #ec4899; background: #fff;" data-dismiss="modal">Clear</button>
                <button type="button" class="btn" style="background: #11294b; color: #fff; border: none;" onclick="savePayment('cheque')">Save</button>
            </div>
        </div>
    </div>
</div>

<!-- UPI Payment Modal -->
<div class="modal fade" id="upiPaymentModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background: #11294b; color: #fff; border: none;">
                <h5 class="modal-title">UPI Payment</h5>
                <button type="button" class="close" data-dismiss="modal" style="color: #fff;">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Deposit Into</label>
                    <select class="form-control" id="upiDepositInto">
                        <option value="">Select Bank</option>
                        <?php foreach ($bank_accounts as $bank): ?>
                        <option value="<?php echo htmlspecialchars($bank['name']); ?>"><?php echo htmlspecialchars($bank['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Trans No.</label>
                    <input type="text" class="form-control" id="upiTransNo" placeholder="Transaction Number">
                </div>
                <div class="form-group">
                    <label>Amount</label>
                    <input type="number" class="form-control" id="upiAmount" value="0.00" step="0.01">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" style="border: 1px solid #ec4899; color: #ec4899; background: #fff;" data-dismiss="modal">Clear</button>
                <button type="button" class="btn" style="background: #11294b; color: #fff; border: none;" onclick="savePayment('upi')">Save</button>
            </div>
        </div>
    </div>
</div>

<!-- Right Side Customer Creation Modal -->
<?php
if (!isset($countries_ledger)) {
    $countries_ledger = getList("SELECT id, name FROM tbl_countries WHERE status = 1 ORDER BY name ASC");
}
require_once __DIR__ . '/international-dial-codes.php';
require_once __DIR__ . '/ledger-modal-document-types-script.php';
?>
<div class="modal fade right" id="customerCreationModal" tabindex="-1" role="dialog" aria-labelledby="customerCreationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-right modal-xl" role="document" style="max-width: 90%; width: 90%; margin: 0; height: 100vh;">
        <div class="modal-content" style="height: 100vh; border-radius: 0; border: none;">
            <div class="modal-header" style="background: #11294b; color: #fff; border: none; padding: 1rem 1.5rem;">
                <h5 class="modal-title" id="customerCreationModalLabel">Ledger Details</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color: #fff; opacity: 1;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" style="padding: 0; height: calc(100vh - 60px); overflow-y: auto; background: #f8fafc;">
                <form id="customerCreationForm" method="post" style="height: 100%;" enctype="multipart/form-data">
                    <input type="hidden" id="ledgerCustomerId" name="customer_id" value="">
                    <div style="padding: 1.5rem; max-width: 1400px; margin: 0 auto;">
                        <!-- Top Action Buttons -->
                        <div class="d-flex justify-content-end mb-2">
                            <button type="button" class="btn btn-secondary btn-sm" onclick="clearCustomerForm()" style="margin-right: 0.5rem; padding: 0.4rem 0.75rem; font-size: 0.85rem;">Clear</button>
                            <button type="button" class="btn btn-primary btn-sm" id="customerModalSaveBtn" onclick="saveCustomer()" style="margin-right: 0.5rem; background: #11294b; border: none; padding: 0.4rem 0.75rem; font-size: 0.85rem;">Save</button>
                            <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal" style="padding: 0.4rem 0.75rem; font-size: 0.85rem;">Close</button>
                        </div>

                        <!-- Ledger Photo and Basic Info -->
                        <div class="row mb-3">
                            <div class="col-md-2">
                                <div style="text-align: center;">
                                    <div style="width: 120px; height: 120px; border-radius: 50%; background: #f1f5f9; border: 2px dashed #cbd5e1; display: flex; align-items: center; justify-content: center; margin: 0 auto; position: relative; cursor: pointer;" onclick="document.getElementById('ledgerPhotoInput').click();">
                                        <i class="feather icon-camera" style="font-size: 1.5rem; color: #94a3b8;"></i>
                                        <input type="file" id="ledgerPhotoInput" name="ledger_photo" accept="image/*" style="display: none;" onchange="previewLedgerPhoto(this);">
                                    </div>
                                    <div id="ledgerPhotoPreview" style="display: none; width: 120px; height: 120px; border-radius: 50%; margin: 0 auto; overflow: hidden; border: 2px solid #c5a864;">
                                        <img id="ledgerPhotoImg" src="" style="width: 100%; height: 100%; object-fit: cover;">
                                    </div>
                                    <div class="form-check mt-2" style="text-align: center;">
                                        <input class="form-check-input" type="checkbox" id="ledgerNameCapital" name="ledger_name_capital" style="width: 0.9rem; height: 0.9rem;">
                                        <label class="form-check-label" for="ledgerNameCapital" style="font-size: 0.75rem;">Ledger Name Capital</label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-9">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Name *</label>
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
                                            <label>Mobile No <span style="color: red;">*</span></label>
                                            <div class="input-group">
                                                <select class="form-control" id="mobileCountryCode" name="mobile_country_code" style="max-width: 96px; font-size: 0.85rem; padding: 0.4rem 0.5rem; height: 32px;">
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
                                                <select class="form-control" id="phoneCountryCode" name="phone_country_code" style="max-width: 96px; font-size: 0.85rem; padding: 0.4rem 0.5rem; height: 32px;">
                                                    <?php auragold_render_dial_code_select('971'); ?>
                                                </select>
                                                <input type="text" class="form-control" id="ledgerPhoneNo" name="phone_no" placeholder="Phone No" inputmode="numeric" autocomplete="tel">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Mail ID</label>
                                            <div class="input-group">
                                                <i class="feather icon-mail" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); z-index: 10; color: #94a3b8;"></i>
                                                <input type="email" class="form-control" id="ledgerMailId" name="mail_id" placeholder="Email" style="padding-left: 35px;">
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
                                            <div class="input-group">
                                                <i class="feather icon-credit-card" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); z-index: 10; color: #94a3b8;"></i>
                                                <input type="text" class="form-control" id="ledgerNationalId" name="national_id" style="padding-left: 35px;">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Trade No</label>
                                            <div class="input-group">
                                                <i class="feather icon-briefcase" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); z-index: 10; color: #94a3b8;"></i>
                                                <input type="text" class="form-control" id="ledgerTradeNo" name="trade_no" style="padding-left: 35px;">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Identity Issue Date</label>
                                            <div class="input-group">
                                                <input type="date" class="form-control" id="identityIssueDate" name="identity_issue_date">
                                                <div class="input-group-append">
                                                    <span class="input-group-text" style="padding: 0.4rem 0.5rem; height: 32px; border: 1px solid #e2e8f0; border-left: none; background: #f8fafc;"><i class="feather icon-calendar" style="font-size: 0.85rem;"></i></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Identity Expiry Date</label>
                                            <div class="input-group">
                                                <input type="date" class="form-control" id="identityExpiryDate" name="identity_expiry_date">
                                                <div class="input-group-append">
                                                    <span class="input-group-text" style="padding: 0.4rem 0.5rem; height: 32px; border: 1px solid #e2e8f0; border-left: none; background: #f8fafc;"><i class="feather icon-calendar" style="font-size: 0.85rem;"></i></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Special Day</label>
                                            <div class="input-group">
                                                <input type="date" class="form-control" id="specialDay" name="special_day">
                                                <div class="input-group-append">
                                                    <span class="input-group-text" style="padding: 0.4rem 0.5rem; height: 32px; border: 1px solid #e2e8f0; border-left: none; background: #f8fafc;"><i class="feather icon-calendar" style="font-size: 0.85rem;"></i></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Customer Type *</label>
                                            <div class="input-group">
                                                <i class="feather icon-users" style="position: absolute; left: 8px; top: 50%; transform: translateY(-50%); z-index: 10; color: #94a3b8; font-size: 0.9rem;"></i>
                                                <select class="form-control" id="customerType" name="customer_type_id">
                                                    <option value="">Select Customer Type</option>
                                                    <?php 
                                                    $customer_types = getList("SELECT id, name FROM tbl_customer_types WHERE status = 1 ORDER BY name ASC");
                                                    foreach($customer_types as $type) {
                                                        echo '<option value="'.$type['id'].'">'.htmlspecialchars($type['name']).'</option>';
                                                    }
                                                    ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Registration No</label>
                                            <input type="text" class="form-control" id="registrationNo" name="registration_no">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Registration Date</label>
                                            <div class="input-group">
                                                <input type="date" class="form-control" id="registrationDate" name="registration_date">
                                                <div class="input-group-append">
                                                    <span class="input-group-text" style="padding: 0.4rem 0.5rem; height: 32px; border: 1px solid #e2e8f0; border-left: none; background: #f8fafc;"><i class="feather icon-calendar" style="font-size: 0.85rem;"></i></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Nationality</label>
                                            <div class="input-group">
                                                <i class="feather icon-flag" style="position: absolute; left: 8px; top: 50%; transform: translateY(-50%); z-index: 10; color: #94a3b8; font-size: 0.9rem;"></i>
                                                <select class="form-control" id="nationality" name="nationality_id">
                                                    <option value="">Select Nationality</option>
                                                    <?php 
                                                    $nationalities = getList("SELECT id, name FROM tbl_nationalities WHERE status = 1 ORDER BY name ASC");
                                                    foreach($nationalities as $nationality) {
                                                        echo '<option value="'.$nationality['id'].'">'.htmlspecialchars($nationality['name']).'</option>';
                                                    }
                                                    ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Country</label>
                                            <div class="input-group">
                                                <i class="feather icon-flag" style="position: absolute; left: 8px; top: 50%; transform: translateY(-50%); z-index: 10; color: #94a3b8; font-size: 0.9rem;"></i>
                                                <select class="form-control" id="country" name="country_id" style="padding-left: 28px;">
                                                    <option value="">Select Country</option>
                                                    <?php 
                                                    $countries = getList("SELECT id, name FROM tbl_countries WHERE status = 1 ORDER BY name ASC");
                                                    foreach($countries as $country) {
                                                        echo '<option value="'.$country['id'].'">'.htmlspecialchars($country['name']).'</option>';
                                                    }
                                                    ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>State</label>
                                            <select class="form-control" id="ledgerState" name="ledger_state_id">
                                                <option value="">Select State</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>City</label>
                                            <select class="form-control" id="ledgerCity" name="ledger_city_id">
                                                <option value="">Select City</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Select Group</label>
                                            <div class="input-group">
                                                <i class="feather icon-users" style="position: absolute; left: 8px; top: 50%; transform: translateY(-50%); z-index: 10; color: #94a3b8; font-size: 0.9rem;"></i>
                                                <select class="form-control" id="ledgerGroup" name="group_id">
                                                    <option value="">Select Group</option>
                                                    <?php 
                                                    foreach($ledger_groups as $group) {
                                                        echo '<option value="'.$group['id'].'">'.htmlspecialchars($group['name']).'</option>';
                                                    }
                                                    ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Sundry Debtors *</label>
                                            <div class="input-group">
                                                <i class="feather icon-users" style="position: absolute; left: 8px; top: 50%; transform: translateY(-50%); z-index: 10; color: #94a3b8; font-size: 0.9rem;"></i>
                                                <select class="form-control" id="ledgerSundryDebtors" name="sundry_debtors_id" required>
                                                    <!-- <option value="">Select</option> -->
                                                    <?php 
                                                    foreach($sundry_options as $option) {
                                                        echo '<option value="'.$option['id'].'">'.htmlspecialchars($option['name']).'</option>';
                                                    }
                                                    ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="checkbox" id="ledgerKYC" name="kyc">
                                                <label class="form-check-label" for="ledgerKYC">KYC</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="checkbox" id="ledgerAML" name="aml">
                                                <label class="form-check-label" for="ledgerAML">AML</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <label class="form-check-label" style="margin-right: 10px;">Bill to Bill:</label>
                                                <input class="form-check-input" type="radio" id="billToBillYes" name="bill_to_bill" value="1">
                                                <label class="form-check-label" for="billToBillYes" style="margin-right: 15px;">Yes</label>
                                                <input class="form-check-input" type="radio" id="billToBillNo" name="bill_to_bill" value="0" checked>
                                                <label class="form-check-label" for="billToBillNo">No</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Address and Bank Details Tabs -->
                        <ul class="nav nav-tabs mb-3" id="ledgerTabs" role="tablist">
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
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Address 1</label>
                                            <input type="text" class="form-control" id="billingAddress1" name="billing_address1">
                                        </div>
                                        <div class="form-group">
                                            <label>Address 2</label>
                                            <input type="text" class="form-control" id="billingAddress2" name="billing_address2">
                                        </div>
                                        <div class="form-group">
                                            <label>Country *</label>
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
                                            <label>State *</label>
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
                                                <button type="button" class="btn btn-light border rounded-circle city-info-btn d-inline-flex align-items-center justify-content-center" style="width: 34px; height: 34px; flex-shrink: 0; padding: 0;" title="Cities load for the selected state. Use + to add a new city under this state." tabindex="-1">
                                                    <i class="feather icon-info" style="font-size: 1rem; color: #64748b;"></i>
                                                </button>
                                                <button type="button" class="btn btn-light border rounded-circle city-add-btn d-inline-flex align-items-center justify-content-center" style="width: 34px; height: 34px; flex-shrink: 0; padding: 0;" data-target="billing" title="Add city under selected state">
                                                    <i class="feather icon-plus" style="font-size: 1rem; color: #11294b;"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label>Zip Code</label>
                                            <input type="text" class="form-control" id="billingZipCode" name="billing_zip_code">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Bank Details</label>
                                        </div>
                                        <div class="form-group">
                                            <label>Acc.No.</label>
                                            <input type="text" class="form-control" id="bankAccountNo" name="bank_account_no" placeholder="Account No">
                                        </div>
                                        <div class="form-group">
                                            <label>Name</label>
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

                            <!-- Other Tabs (Placeholder) -->
                            <div class="tab-pane fade" id="shipping-address" role="tabpanel">
                                <div class="row">
                                    <div class="col-md-6">
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
                                                <button type="button" class="btn btn-light border rounded-circle city-info-btn d-inline-flex align-items-center justify-content-center" style="width: 34px; height: 34px; flex-shrink: 0; padding: 0;" title="Cities load for the selected state. Use + to add a new city under this state." tabindex="-1">
                                                    <i class="feather icon-info" style="font-size: 1rem; color: #64748b;"></i>
                                                </button>
                                                <button type="button" class="btn btn-light border rounded-circle city-add-btn d-inline-flex align-items-center justify-content-center" style="width: 34px; height: 34px; flex-shrink: 0; padding: 0;" data-target="shipping" title="Add city under selected state">
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

                            <div class="tab-pane fade" id="item-type-tax" role="tabpanel">
                                <div style="background: #fff; padding: 1.5rem; border-radius: 8px; border: 1px solid #e2e8f0;">
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
                                                <td>Diamond & Stones</td>
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
                            </div>

                            <div class="tab-pane fade" id="share-holders" role="tabpanel">
                                <div style="background: #fff; padding: 1.5rem; border-radius: 8px; border: 1px solid #e2e8f0;">
                                    <!-- Share Holders Table -->
                                    <div style="margin-bottom: 1.5rem;">
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                                            <h6 style="margin: 0; font-size: 0.95rem; font-weight: 600; color: #1e293b;">Share Holders</h6>
                                            <div style="display: flex; gap: 0.5rem;">
                                                <button type="button" class="btn btn-sm" id="addShareHolderBtn" style="background: #c5a864; color: #fff; border: none; padding: 0.4rem 0.75rem; border-radius: 4px; cursor: pointer;">
                                                    <i class="feather icon-plus" style="font-size: 0.85rem;"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm ccm-sh-settings-btn" title="Document type settings" aria-label="Document type settings" style="background: #f8fafc; color: #64748b; border: 1px solid #e2e8f0; padding: 0.4rem 0.75rem; border-radius: 4px; cursor: pointer;">
                                                    <i class="feather icon-settings" style="font-size: 0.85rem;"></i>
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
                                                    <!-- Rows will be added dynamically -->
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                    
                                    <!-- Upload Document Section -->
                                    <div style="margin-top: 1.5rem;">
                                        <?php require __DIR__ . '/customer-share-holder-documents-markup.php'; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="tab-pane fade" id="notes" role="tabpanel">
                                <div class="form-group">
                                    <label>Notes</label>
                                    <textarea class="form-control" id="ledgerNotes" name="notes" rows="5"></textarea>
                                </div>
                            </div>

                            <div class="tab-pane fade" id="nominee" role="tabpanel">
                                <?php require __DIR__ . '/customer-nominee-markup.php'; ?>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Category Creation Modal -->
<div class="modal fade" id="categoryCreationModal" tabindex="-1" role="dialog" aria-labelledby="categoryCreationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 600px;">
        <div class="modal-content">
            <div class="modal-header" style="background: #11294b; color: #fff; border: none;">
                <h5 class="modal-title" id="categoryCreationModalLabel">Add Category</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color: #fff; opacity: 1;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" style="padding: 1.5rem;">
                <form id="categoryCreationForm">
                    <div class="form-group">
                        <label>Name *</label>
                        <input type="text" class="form-control" id="categoryName" name="name" required>
                    </div>
                    <div class="form-group">
                        <label>Short Code</label>
                        <input type="text" class="form-control" id="categoryShortCode" name="short_code" maxlength="10">
                    </div>
                    <div class="form-group">
                        <label>Parent Category</label>
                        <select class="form-control" id="categoryParentId" name="parent_id">
                            <option value="0">None</option>
                            <?php
                            if (!empty($categories)) {
                                foreach ($categories as $cat) {
                                    echo '<option value="' . $cat['id'] . '">' . htmlspecialchars($cat['name']) . '</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Min. Qty.</label>
                                <input type="number" class="form-control" id="categoryMinQty" name="min_qty" step="0.01" value="0">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Max. Qty.</label>
                                <input type="number" class="form-control" id="categoryMaxQty" name="max_qty" step="0.01" value="0">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Min. Wt.</label>
                                <input type="number" class="form-control" id="categoryMinWt" name="min_wt" step="0.001" value="0">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Max. Wt.</label>
                                <input type="number" class="form-control" id="categoryMaxWt" name="max_wt" step="0.001" value="0">
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="categoryIsActive" name="is_active" checked>
                            <label class="form-check-label" for="categoryIsActive">Active</label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer" style="border-top: 1px solid #e2e8f0; padding: 1rem 1.5rem;">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary btn-sm" onclick="saveCategory()" style="background: #11294b; border: none;">Save</button>
            </div>
        </div>
    </div>
</div>

<!-- Card Payment Modal -->
<div class="modal fade" id="cardPaymentModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background: #11294b; color: #fff; border: none;">
                <h5 class="modal-title">Card Payment</h5>
                <button type="button" class="close" data-dismiss="modal" style="color: #fff;">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Deposit Into</label>
                    <select class="form-control" id="cardDepositInto">
                        <option value="">Select Account</option>
                        <?php foreach ($credit_cards as $card) :
                            $card_name = trim((string) ($card['name'] ?? ''));
                            if ($card_name === '') {
                                continue;
                            }
                            $is_default = !empty($card['is_default']);
                            ?>
                        <option value="<?php echo htmlspecialchars($card_name, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $is_default ? ' selected' : ''; ?>><?php echo htmlspecialchars($card_name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Trans No.</label>
                    <input type="text" class="form-control" id="cardTransNo" placeholder="Transaction Number">
                </div>
                <div class="form-group">
                    <label>Card No.</label>
                    <input type="text" class="form-control" id="cardNumber" placeholder="Card Number">
                </div>
                <div class="form-group">
                    <label>Amount</label>
                    <input type="number" class="form-control" id="cardAmount" value="0.00" step="0.01">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" style="border: 1px solid #ec4899; color: #ec4899; background: #fff;" data-dismiss="modal">Clear</button>
                <button type="button" class="btn" style="background: #11294b; color: #fff; border: none;" onclick="savePayment('card')">Save</button>
            </div>
        </div>
    </div>
</div>

<!-- Metal Exchange Payment Modal -->
<div class="modal fade" id="metalExchangeModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background: #11294b; color: #fff; border: none;">
                <h5 class="modal-title">M. Exch. Payment</h5>
                <button type="button" class="close" data-dismiss="modal" style="color: #fff;">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-6 col-lg-3">
                        <div class="form-group mb-2">
                            <label>Metal</label>
                            <select class="form-control" id="metalExchangeMetal">
                                <option value="">Select Metal</option>
                                <?php foreach($metals as $metal): ?>
                                <option value="<?php echo $metal['id']; ?>"><?php echo htmlspecialchars($metal['display_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="form-group mb-2" style="position: relative;">
                            <label>Product</label>
                            <input type="text" class="form-control" id="metalExchangeProductInput" placeholder="Search, then pick from list…" autocomplete="off" title="You must select a row from the list; typed text alone is not saved.">
                            <input type="hidden" id="metalExchangeProductId" value="">
                            <div id="metalExchangeProductList" style="display: none; position: absolute; left: 0; right: 0; top: 100%; z-index: 1055; max-height: 220px; overflow-y: auto; background: #fff; border: 1px solid #cbd5e1; border-radius: 6px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); margin-top: 2px;"></div>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="form-group mb-2">
                            <label>Gross Wt</label>
                            <input type="number" class="form-control" id="metalExchangeGrossWt" value="0" step="0.001">
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="form-group mb-2">
                            <label>Purity / Karat</label>
                            <input type="number" class="form-control" id="metalExchangePurity" value="1" step="0.01">
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="form-group mb-2">
                            <label>Purity Wt.</label>
                            <input type="number" class="form-control" id="metalExchangePurityWt" value="0" step="0.001" readonly style="background: #f8fafc;">
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="form-group mb-2">
                            <label>Quantity</label>
                            <input type="number" class="form-control" id="metalExchangeQty" value="1" step="0.01">
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="form-group mb-2">
                            <label>Rate</label>
                            <input type="number" class="form-control" id="metalExchangeRate" value="0" step="0.01">
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="form-group mb-2">
                            <label>Amount</label>
                            <input type="number" class="form-control" id="metalExchangeAmount" value="0.00" step="0.01">
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="form-group mb-2">
                            <label>Item Code</label>
                            <input type="text" class="form-control" id="metalExchangeItemCode" placeholder="Item Code">
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" style="border: 1px solid #ec4899; color: #ec4899; background: #fff;" data-dismiss="modal">Clear</button>
                <button type="button" class="btn" style="background: #11294b; color: #fff; border: none;" onclick="savePayment('metal-exchange')">Save</button>
            </div>
        </div>
    </div>
</div>

<!-- Scrap Payment Modal -->
<div class="modal fade" id="scrapPaymentModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background: #11294b; color: #fff; border: none;">
                <h5 class="modal-title">Scrap Payment</h5>
                <button type="button" class="close" data-dismiss="modal" style="color: #fff;">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Metal Type</label>
                            <select class="form-control" id="scrapMetal">
                                <option value="">Select Metal</option>
                                <?php if (!empty($metals) && is_array($metals)) { foreach ($metals as $m) { ?>
                                <option value="<?php echo (int)$m['id']; ?>"><?php echo htmlspecialchars($m['display_name'] ?? $m['system_name'] ?? ''); ?></option>
                                <?php } } ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Gross Wt</label>
                            <input type="number" class="form-control" id="scrapGrossWt" value="0" step="0.001">
                        </div>
                        <div class="form-group">
                            <label>Net Wt.</label>
                            <input type="number" class="form-control" id="scrapNetWt" value="0" step="0.001" readonly style="background: #f8fafc;">
                        </div>
                        <div class="form-group">
                            <label>Rate</label>
                            <input type="number" class="form-control" id="scrapRate" value="0" step="0.01">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group" style="position: relative;">
                            <label>Product</label>
                            <input type="text" class="form-control" id="scrapProductInput" placeholder="Search, then pick from list…" autocomplete="off" title="You must select a row from the list; typed text alone is not saved.">
                            <input type="hidden" id="scrapProductId" value="">
                            <div id="scrapProductList" style="display: none; position: absolute; left: 0; right: 0; top: 100%; z-index: 1000; max-height: 220px; overflow-y: auto; background: #fff; border: 1px solid #cbd5e1; border-radius: 6px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); margin-top: 2px;"></div>
                        </div>
                        <div class="form-group">
                            <label>Less Wt.</label>
                            <input type="number" class="form-control" id="scrapLessWt" value="0" step="0.001">
                        </div>
                        <div class="form-group">
                            <label>Purity / Karat</label>
                            <input type="number" class="form-control" id="scrapPurity" value="1" step="0.01" placeholder="From product when selected">
                        </div>
                        <div class="form-group">
                            <label>Amount</label>
                            <input type="number" class="form-control" id="scrapAmount" value="0.00" step="0.01">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Quantity</label>
                            <input type="number" class="form-control" id="scrapQty" value="1" step="0.01">
                        </div>
                        <div class="form-group">
                            <label>Stone Wt.</label>
                            <input type="number" class="form-control" id="scrapStoneWt" value="0" step="0.001" placeholder="Deduct from weight">
                        </div>
                        <div class="form-group">
                            <label>Purity Wt.</label>
                            <input type="number" class="form-control" id="scrapPurityWt" value="0" step="0.001" readonly style="background: #f8fafc;">
                        </div>
                        <div class="form-group">
                            <label>Item Code</label>
                            <input type="text" class="form-control" id="scrapItemCode" placeholder="Item Code">
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" style="border: 1px solid #ec4899; color: #ec4899; background: #fff;" data-dismiss="modal">CLEAR</button>
                <button type="button" class="btn" style="background: #11294b; color: #fff; border: none;" onclick="savePayment('scrap')">SAVE</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    if (window.__metalExchangeProductSearchInited) return;
    window.__metalExchangeProductSearchInited = true;
    var _meSync = false;
    function mePurityFactor(purity) {
        var p = parseFloat(purity) || 0;
        return (p > 0 && p <= 1) ? p : (p / 100);
    }
    /** Recompute purity wt from gross × purity only. */
    function meRefreshPurityWt() {
        var grossEl = document.getElementById('metalExchangeGrossWt');
        var purEl = document.getElementById('metalExchangePurity');
        var pwEl = document.getElementById('metalExchangePurityWt');
        if (!grossEl || !pwEl) return 0;
        var gross = parseFloat(grossEl.value) || 0;
        var purityWt = gross * mePurityFactor(purEl && purEl.value);
        pwEl.value = purityWt.toFixed(3);
        return purityWt;
    }
    /** Rate (or weight/qty) changed → Amount = purityWt × rate × qty */
    function meSyncAmountFromRate() {
        if (_meSync) return;
        _meSync = true;
        try {
            var pw = meRefreshPurityWt();
            var rateEl = document.getElementById('metalExchangeRate');
            var qtyEl = document.getElementById('metalExchangeQty');
            var amtEl = document.getElementById('metalExchangeAmount');
            if (!amtEl) return;
            var rate = parseFloat(rateEl && rateEl.value) || 0;
            var qty = parseFloat(qtyEl && qtyEl.value) || 0;
            var q = qty > 0 ? qty : 1;
            amtEl.value = (pw * rate * q).toFixed(2);
        } finally {
            _meSync = false;
        }
    }
    /** Amount changed → Rate = amount / (purityWt × qty) */
    function meSyncRateFromAmount() {
        if (_meSync) return;
        _meSync = true;
        try {
            var pw = meRefreshPurityWt();
            var qtyEl = document.getElementById('metalExchangeQty');
            var rateEl = document.getElementById('metalExchangeRate');
            var amtEl = document.getElementById('metalExchangeAmount');
            if (!rateEl || !amtEl) return;
            var amt = parseFloat(amtEl.value) || 0;
            var qty = parseFloat(qtyEl && qtyEl.value) || 0;
            var q = qty > 0 ? qty : 1;
            var base = pw * q;
            if (base > 0) {
                rateEl.value = (amt / base).toFixed(6);
            }
        } finally {
            _meSync = false;
        }
    }
    function updateMetalExchangeCalculations() {
        meSyncAmountFromRate();
    }
    window.updateMetalExchangeCalculations = updateMetalExchangeCalculations;
    function initMetalExchangeProductSearch() {
        var metalEl = document.getElementById('metalExchangeMetal');
        var inputEl = document.getElementById('metalExchangeProductInput');
        var idEl = document.getElementById('metalExchangeProductId');
        var listEl = document.getElementById('metalExchangeProductList');
        if (!metalEl || !inputEl || !idEl || !listEl) return;
        var tmr;
        var rateEl = document.getElementById('metalExchangeRate');
        var purEl = document.getElementById('metalExchangePurity');
        function showList(products) {
            listEl.innerHTML = '';
            listEl.style.display = 'block';
            if (!products || !products.length) {
                listEl.innerHTML = '<div class="p-2 text-muted small">No products found</div>';
                return;
            }
            products.forEach(function (p) {
                var div = document.createElement('div');
                div.className = 'p-2 border-bottom';
                div.style.cursor = 'pointer';
                div.style.fontSize = '0.9rem';
                div.onmouseover = function () { this.style.background = '#f1f5f9'; };
                div.onmouseout = function () { this.style.background = ''; };
                div.textContent = (p.name || '') + (p.metal_name ? ' (' + p.metal_name + ')' : '');
                div.addEventListener('click', function () {
                    inputEl.value = (p.name || '') + (p.metal_name ? ' (' + p.metal_name + ')' : '');
                    idEl.value = (p.characteristic_id || p.id) || '';
                    if (rateEl && p.rate != null && p.rate !== '') rateEl.value = p.rate;
                    if (purEl && p.opening_purity != null && p.opening_purity !== '') purEl.value = p.opening_purity;
                    var sku = (p.sku_code || p.barcode || '');
                    var ic = document.getElementById('metalExchangeItemCode');
                    if (ic && sku) ic.value = sku;
                    listEl.style.display = 'none';
                    listEl.innerHTML = '';
                    meSyncAmountFromRate();
                });
                listEl.appendChild(div);
            });
        }
        function search() {
            var mid = parseInt(metalEl.value, 10) || 0;
            var q = (inputEl.value || '').trim();
            if (!mid) {
                listEl.innerHTML = '<div class="p-2 text-muted small">Select metal first</div>';
                listEl.style.display = 'block';
                return;
            }
            listEl.innerHTML = '<div class="p-2 text-muted small">Loading...</div>';
            listEl.style.display = 'block';
            var url = 'ajax/get-products-by-metal.php?metal_id=' + encodeURIComponent(mid) + (q ? '&search=' + encodeURIComponent(q) : '');
            fetch(url)
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    showList(data.success && data.products ? data.products : []);
                })
                .catch(function () {
                    listEl.innerHTML = '<div class="p-2 text-danger small">Error loading products</div>';
                });
        }
        inputEl.addEventListener('input', function () {
            clearTimeout(tmr);
            idEl.value = '';
            tmr = setTimeout(search, 300);
        });
        inputEl.addEventListener('focus', function () {
            if (metalEl.value) search();
        });
        /** Free text without picking a DB row is invalid — clear on blur. */
        inputEl.addEventListener('blur', function () {
            if (!String(idEl.value || '').trim()) {
                inputEl.value = '';
            }
        });
        metalEl.addEventListener('change', function () {
            inputEl.value = '';
            idEl.value = '';
            listEl.style.display = 'none';
            listEl.innerHTML = '';
        });
        document.addEventListener('click', function (e) {
            if (listEl.style.display === 'block' && !listEl.contains(e.target) && e.target !== inputEl) {
                listEl.style.display = 'none';
            }
        });
        ['metalExchangeGrossWt', 'metalExchangePurity', 'metalExchangeRate', 'metalExchangeQty'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) {
                el.addEventListener('input', meSyncAmountFromRate);
                el.addEventListener('change', meSyncAmountFromRate);
            }
        });
        var amtIn = document.getElementById('metalExchangeAmount');
        if (amtIn) {
            amtIn.addEventListener('input', meSyncRateFromAmount);
            amtIn.addEventListener('change', meSyncRateFromAmount);
        }
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initMetalExchangeProductSearch);
    } else {
        initMetalExchangeProductSearch();
    }
})();
</script>
<script src="js/customer-ledger-address.js"></script>
