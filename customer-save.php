<?php
session_start();
require_once "config.php";
require_once __DIR__ . '/includes/location-helpers.php';
require_once __DIR__ . '/includes/ensure_customer_ledger_branch_column.php';
require_once __DIR__ . '/includes/auragold_ledger_opening_metals.php';
require_once __DIR__ . '/includes/customer_nominee_schema.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Request']);
    exit;
}

mysqli_begin_transaction($conn);

try {
    auragold_bootstrap_location_data($conn);
    auragold_ensure_customer_ledger_location_columns($conn);
    auragold_ensure_customer_ledger_branch_column($conn);
    auragold_ensure_customer_nominee_column($conn);
    auragold_ledger_opening_ensure_metal_columns($conn);

    // Basic Information
    $name = esc($_POST['name'] ?? '');
    $alternate_name = esc($_POST['alternate_name'] ?? '');
    $first_name = esc($_POST['first_name'] ?? '');
    $last_name = esc($_POST['last_name'] ?? '');
    $mobile_country_code = esc($_POST['mobile_country_code'] ?? '971');
    $mobile_no = esc($_POST['mobile_no'] ?? '');
    $phone_country_code = esc($_POST['phone_country_code'] ?? '971');
    $phone_no = esc($_POST['phone_no'] ?? '');
    $mail_id = esc($_POST['mail_id'] ?? '');
    $identity_no = esc($_POST['identity_no'] ?? '');
    $national_id = esc($_POST['national_id'] ?? '');
    $trade_no = esc($_POST['trade_no'] ?? '');
    $identity_issue_date = esc($_POST['identity_issue_date'] ?? null);
    $identity_expiry_date = esc($_POST['identity_expiry_date'] ?? null);
    $special_day = esc($_POST['special_day'] ?? null);
    $customer_type_id = (int)($_POST['customer_type_id'] ?? 0);
    $registration_no = esc($_POST['registration_no'] ?? '');
    $registration_date = esc($_POST['registration_date'] ?? null);
    $gstin_raw = strtoupper(preg_replace('/\s+/', '', (string) ($_POST['gstin'] ?? '')));
    if (strlen($gstin_raw) > 15) {
        $gstin_raw = substr($gstin_raw, 0, 15);
    }
    $gstin = esc($gstin_raw);
    $nationality_id = (int)($_POST['nationality_id'] ?? 0);
    $country_id = (int)($_POST['country_id'] ?? 0);
    $ledger_state_id = (int)($_POST['ledger_state_id'] ?? 0);
    $ledger_city_id = (int)($_POST['ledger_city_id'] ?? 0);
    $date1 = esc($_POST['date1'] ?? null);
    $date2 = esc($_POST['date2'] ?? null);
    $group_id = (int)($_POST['group_id'] ?? 0);
    $sundry_debtors_id = (int)($_POST['sundry_debtors_id'] ?? 0);
    
    // Checkboxes
    $ledger_name_capital = isset($_POST['ledger_name_capital']) ? 1 : 0;
    $kyc = isset($_POST['kyc']) ? 1 : 0;
    $aml = isset($_POST['aml']) ? 1 : 0;
    $bill_to_bill = isset($_POST['bill_to_bill']) ? (int)$_POST['bill_to_bill'] : 0;
    
    // Address Information
    $billing_address1 = esc($_POST['billing_address1'] ?? '');
    $billing_address2 = esc($_POST['billing_address2'] ?? '');
    $billing_country = esc($_POST['billing_country'] ?? '');
    $billing_state = esc($_POST['billing_state'] ?? '');
    $billing_city = esc($_POST['billing_city'] ?? '');
    $billing_zip_code = esc($_POST['billing_zip_code'] ?? '');
    
    $shipping_address1 = esc($_POST['shipping_address1'] ?? '');
    $shipping_address2 = esc($_POST['shipping_address2'] ?? '');
    $shipping_country = esc($_POST['shipping_country'] ?? '');
    $shipping_state = esc($_POST['shipping_state'] ?? '');
    $shipping_city = esc($_POST['shipping_city'] ?? '');
    $shipping_zip_code = esc($_POST['shipping_zip_code'] ?? '');
    
    // Bank Details
    $bank_account_no = esc($_POST['bank_account_no'] ?? '');
    $bank_name = esc($_POST['bank_name'] ?? '');
    $bank_ifsc_code = esc($_POST['bank_ifsc_code'] ?? '');
    $bank_branch = esc($_POST['bank_branch'] ?? '');
    
    // Notes
    $notes = esc($_POST['notes'] ?? '');
    
    // Opening Balance
    $opening_balance = (float)($_POST['opening_balance'] ?? 0);
    $opening_type_raw = $_POST['opening_type'] ?? 'credit';
    $opening_crdr = (strtolower($opening_type_raw) === 'debit') ? 'Dr' : 'Cr';
    $opening_branch_id = (int) ($_POST['opening_branch_id'] ?? 0);
    $opening_metals_post = isset($_POST['opening_metal']) && is_array($_POST['opening_metal']) ? $_POST['opening_metal'] : [];
    $opening_metals_master = auragold_ledger_opening_metals_list($conn, $opening_branch_id > 0 ? $opening_branch_id : (function_exists('auragold_effective_branch_id') ? (int) auragold_effective_branch_id() : 0));
    $opening_metal_values = auragold_ledger_opening_metals_from_post($opening_metals_post, $opening_metals_master);
    $opening_metal_update_sql = auragold_ledger_opening_metal_update_sql($conn, $opening_metal_values);
    list($opening_metal_insert_cols, $opening_metal_insert_vals) = auragold_ledger_opening_metal_insert_cols($conn, $opening_metal_values);
    
    // Item Type Tax Data (encode first, then SQL-escape the JSON string)
    $item_tax_data = [];
    if (isset($_POST['item_tax']) && is_array($_POST['item_tax'])) {
        foreach ($_POST['item_tax'] as $item_name => $tax_data) {
            if (!is_array($tax_data)) {
                continue;
            }
            if (isset($tax_data['input_type']) && isset($tax_data['output_type'])) {
                $item_tax_data[(string) $item_name] = [
                    'input_type' => trim((string) $tax_data['input_type']),
                    'output_type' => trim((string) $tax_data['output_type']),
                ];
            }
        }
    }
    $item_tax_json = mysqli_real_escape_string($conn, json_encode($item_tax_data, JSON_UNESCAPED_UNICODE));
    
    // Share Holders Data
    $share_holders_data = [];
    if (isset($_POST['share_holders']) && is_array($_POST['share_holders'])) {
        foreach ($_POST['share_holders'] as $index => $holder) {
            if (!empty($holder['name'])) {
                $share_holders_data[] = [
                    'name' => esc($holder['name'] ?? ''),
                    'nationality_id' => (int)($holder['nationality_id'] ?? 0),
                    'share_percentage' => (float)($holder['share_percentage'] ?? 0)
                ];
            }
        }
    }
    $share_holders_json = json_encode($share_holders_data);

    // Nominees (JSON)
    $nominees_data = [];
    if (isset($_POST['nominees']) && is_array($_POST['nominees'])) {
        foreach ($_POST['nominees'] as $nominee) {
            if (!is_array($nominee)) {
                continue;
            }
            $n_name = trim((string) ($nominee['name'] ?? ''));
            if ($n_name === '') {
                continue;
            }
            $dob_raw = trim((string) ($nominee['dob'] ?? ''));
            $nominees_data[] = [
                'name'         => esc($n_name),
                'relationship' => esc(trim((string) ($nominee['relationship'] ?? ''))),
                'mobile'       => esc(trim((string) ($nominee['mobile'] ?? ''))),
                'dob'          => ($dob_raw !== '' ? esc($dob_raw) : null),
                'address'      => esc(trim((string) ($nominee['address'] ?? ''))),
            ];
        }
    }
    $nominees_json = json_encode($nominees_data);

    // Share holder documents: meta from POST rows + files in uploads/customers/share_holders
    $upload_dir_abs = __DIR__ . '/uploads/customers/share_holders/';
    $upload_dir_rel = 'uploads/customers/share_holders/';
    if (!is_dir($upload_dir_abs)) {
        @mkdir($upload_dir_abs, 0777, true);
    }

    $share_holder_documents = [];
    $types_req   = isset($_POST['share_holder_doc_type_id']) ? (array) $_POST['share_holder_doc_type_id'] : [];
    $expiries    = isset($_POST['share_holder_doc_expiry']) ? (array) $_POST['share_holder_doc_expiry'] : [];
    $exist_paths = isset($_POST['share_holder_doc_existing_path']) ? (array) $_POST['share_holder_doc_existing_path'] : [];
    $exist_names = isset($_POST['share_holder_doc_existing_name']) ? (array) $_POST['share_holder_doc_existing_name'] : [];

    $new_file_i = 0;
    $files_meta = (isset($_FILES['share_holder_documents']) && is_array($_FILES['share_holder_documents']['name']))
        ? $_FILES['share_holder_documents'] : null;

    $row_count = max(count($types_req), count($expiries), count($exist_paths));
    for ($i = 0; $i < $row_count; $i++) {
        $type_id = (int) ($types_req[$i] ?? 0);
        $exp_raw = trim((string) ($expiries[$i] ?? ''));
        $ex_path = trim((string) ($exist_paths[$i] ?? ''));
        $ex_name_guess = trim((string) ($exist_names[$i] ?? ''));

        if ($ex_path !== '') {
            if (!preg_match('#^uploads/customers/share_holders/[A-Za-z0-9_.-]+$#', $ex_path)) {
                continue;
            }
            $disk = __DIR__ . '/' . $ex_path;
            if (!is_file($disk)) {
                continue;
            }
            $share_holder_documents[] = [
                'name'             => esc($ex_name_guess !== '' ? $ex_name_guess : basename($ex_path)),
                'path'             => $ex_path,
                'document_type_id' => $type_id,
                'expiry_date'      => ($exp_raw !== '' ? esc($exp_raw) : null),
            ];
            continue;
        }

        if (!$files_meta || !isset($files_meta['error'][$new_file_i]) || $files_meta['error'][$new_file_i] !== UPLOAD_ERR_OK) {
            continue;
        }
        $orig = (string) ($files_meta['name'][$new_file_i] ?? 'file');
        $tmp  = $files_meta['tmp_name'][$new_file_i];
        $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        $allowed_ext = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
        if (!in_array($ext, $allowed_ext, true)) {
            $new_file_i++;
            continue;
        }
        $fname = 'shareholder_' . time() . '_' . $new_file_i . '_' . uniqid('', true) . '.' . $ext;
        $rel   = $upload_dir_rel . $fname;
        if (move_uploaded_file($tmp, $upload_dir_abs . $fname)) {
            $share_holder_documents[] = [
                'name'             => esc($orig),
                'path'             => $rel,
                'document_type_id' => $type_id,
                'expiry_date'      => ($exp_raw !== '' ? esc($exp_raw) : null),
            ];
        }
        $new_file_i++;
    }
    $share_holder_documents_json = mysqli_real_escape_string($conn, json_encode($share_holder_documents));
    // Check if update or insert (needed before validation checks)
    $customer_id = isset($_POST['customer_id']) ? (int)$_POST['customer_id'] : 0;
    $is_update = ($customer_id > 0);

    // Validation
    if ($name == '') {
        throw new Exception("Name is required");
    }
    if (!$is_update && $customer_type_id <= 0) {
        throw new Exception("Customer type is required");
    }
    
    // Check if mobile number already exists (only when provided, for new customers or if updating to a different mobile number)
    if (!empty(trim($mobile_no))) {
        if (!$is_update) {
            // For new customers, check if mobile number exists
            $check_mobile = "SELECT id, name FROM tbl_customers WHERE mobile_no = '$mobile_no' AND status = 1 LIMIT 1";
            $result = mysqli_query($conn, $check_mobile);
            if ($result && mysqli_num_rows($result) > 0) {
                $existing_customer = mysqli_fetch_assoc($result);
                throw new Exception("Mobile number already exists for customer: " . $existing_customer['name'] . ". Please use a different mobile number.");
            }
        } else {
            // For updates, check if mobile number exists for a different customer
            $check_mobile = "SELECT id, name FROM tbl_customers WHERE mobile_no = '$mobile_no' AND id != $customer_id AND status = 1 LIMIT 1";
            $result = mysqli_query($conn, $check_mobile);
            if ($result && mysqli_num_rows($result) > 0) {
                $existing_customer = mysqli_fetch_assoc($result);
                throw new Exception("Mobile number already exists for customer: " . $existing_customer['name'] . ". Please use a different mobile number.");
            }
        }
    }
    
    // Handle photo upload
    $ledger_photo = '';
    if (isset($_FILES['ledger_photo']) && $_FILES['ledger_photo']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/customers/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = pathinfo($_FILES['ledger_photo']['name'], PATHINFO_EXTENSION);
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array(strtolower($file_extension), $allowed_extensions)) {
            $file_name = 'customer_' . time() . '_' . uniqid() . '.' . $file_extension;
            $file_path = $upload_dir . $file_name;
            
            if (move_uploaded_file($_FILES['ledger_photo']['tmp_name'], $file_path)) {
                $ledger_photo = $file_path;
            }
        }
    }
    
    if ($is_update) {
        // Update existing customer
        $sql = "
            UPDATE tbl_customers 
            SET name = '$name',
                alternate_name = '$alternate_name',
                first_name = '$first_name',
                last_name = '$last_name',
                mobile_country_code = '$mobile_country_code',
                mobile_no = '$mobile_no',
                phone_country_code = '$phone_country_code',
                phone_no = '$phone_no',
                mail_id = '$mail_id',
                identity_no = '$identity_no',
                national_id = '$national_id',
                trade_no = '$trade_no',
                identity_issue_date = " . ($identity_issue_date ? "'$identity_issue_date'" : "NULL") . ",
                identity_expiry_date = " . ($identity_expiry_date ? "'$identity_expiry_date'" : "NULL") . ",
                special_day = " . ($special_day ? "'$special_day'" : "NULL") . ",
                customer_type_id = '$customer_type_id',
                registration_no = '$registration_no',
                registration_date = " . ($registration_date ? "'$registration_date'" : "NULL") . ",
                gstin = " . ($gstin_raw !== '' ? "'$gstin'" : 'NULL') . ",
                nationality_id = '$nationality_id',
                country_id = '$country_id',
                ledger_state_id = '$ledger_state_id',
                ledger_city_id = '$ledger_city_id',
                date1 = " . ($date1 ? "'$date1'" : "NULL") . ",
                date2 = " . ($date2 ? "'$date2'" : "NULL") . ",
                group_id = '$group_id',
                sundry_debtors_id = '$sundry_debtors_id',
                ledger_name_capital = '$ledger_name_capital',
                kyc = '$kyc',
                aml = '$aml',
                bill_to_bill = '$bill_to_bill',
                billing_address1 = '$billing_address1',
                billing_address2 = '$billing_address2',
                billing_country = '$billing_country',
                billing_state = '$billing_state',
                billing_city = '$billing_city',
                billing_zip_code = '$billing_zip_code',
                shipping_address1 = '$shipping_address1',
                shipping_address2 = '$shipping_address2',
                shipping_country = '$shipping_country',
                shipping_state = '$shipping_state',
                shipping_city = '$shipping_city',
                shipping_zip_code = '$shipping_zip_code',
                bank_account_no = '$bank_account_no',
                bank_name = '$bank_name',
                bank_ifsc_code = '$bank_ifsc_code',
                bank_branch = '$bank_branch',
                notes = '$notes',
                item_tax_data = '$item_tax_json',
                share_holders_data = '$share_holders_json',
                share_holder_documents = '$share_holder_documents_json',
                nominee_data = '$nominees_json',
                updated_at = NOW()
        ";
        
        if ($ledger_photo) {
            $sql .= ", ledger_photo = '$ledger_photo'";
        }
        
        $sql .= " WHERE id = $customer_id AND status = 1";
        
        if (!mysqli_query($conn, $sql)) {
            throw new Exception("Customer update failed: " . mysqli_error($conn));
        }
        
        // Update or insert opening entry (allow 0 opening balance so record is always saved)
        $debit_amount = $opening_balance > 0 && $opening_crdr === 'Dr' ? $opening_balance : 0;
        $credit_amount = $opening_balance > 0 && $opening_crdr === 'Cr' ? $opening_balance : 0;
        $balance_amount = $opening_balance > 0 ? ($opening_crdr === 'Dr' ? $opening_balance : -$opening_balance) : 0;
        $esc_name = mysqli_real_escape_string($conn, $name);
        // One opening row per branch: update/insert only the row for this branch (legacy NULL/0 = main office).
        if ($opening_branch_id > 0) {
            $opening_row = getRecord(
                "SELECT id FROM tbl_customer_ledger WHERE (customer_id = $customer_id OR customer_name = '$esc_name') AND transaction_type = 'opening' AND status = 1 AND COALESCE(branch_id, 0) = " . (int) $opening_branch_id . " ORDER BY id DESC LIMIT 1"
            );
        } else {
            $opening_row = getRecord(
                "SELECT id FROM tbl_customer_ledger WHERE (customer_id = $customer_id OR customer_name = '$esc_name') AND transaction_type = 'opening' AND status = 1 AND (branch_id IS NULL OR branch_id = 0) ORDER BY id DESC LIMIT 1"
            );
        }
        $ob_br_sql = $opening_branch_id > 0 ? (string) (int) $opening_branch_id : 'NULL';
        if ($opening_row) {
            $upd_sql = "UPDATE tbl_customer_ledger SET debit_amount = $debit_amount, credit_amount = $credit_amount, balance_amount = $balance_amount, branch_id = $ob_br_sql, $opening_metal_update_sql WHERE id = " . (int) $opening_row['id'];
            @mysqli_query($conn, $upd_sql);
        } else {
            $user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
            $today = date('Y-m-d');
            $ledger_sql = "INSERT INTO tbl_customer_ledger 
                (customer_id, customer_name, branch_id, transaction_type, transaction_id, transaction_no, transaction_date, 
                 debit_amount, credit_amount, $opening_metal_insert_cols,
                 balance_amount, description, reference_no, against_ledger, against_invoice_no, status, created_by, created_at)
                VALUES 
                ($customer_id, '$name', $ob_br_sql, 'opening', 0, 'OPENING', '$today',
                 $debit_amount, $credit_amount, $opening_metal_insert_vals,
                 $balance_amount, 'Opening balance', '', '', '', 1, $user_id, NOW())";
            @mysqli_query($conn, $ledger_sql);
        }
    } else {
        // Insert new customer
        $sql = "
            INSERT INTO tbl_customers 
            (name, alternate_name, first_name, last_name, mobile_country_code, mobile_no, phone_country_code, phone_no, mail_id, 
             identity_no, national_id, trade_no, identity_issue_date, identity_expiry_date, special_day, 
             customer_type_id, registration_no, registration_date, gstin, nationality_id, country_id, ledger_state_id, ledger_city_id, 
             date1, date2, group_id, sundry_debtors_id,
             ledger_name_capital, kyc, aml, bill_to_bill,
             billing_address1, billing_address2, billing_country, billing_state, billing_city, billing_zip_code,
             shipping_address1, shipping_address2, shipping_country, shipping_state, shipping_city, shipping_zip_code,
             bank_account_no, bank_name, bank_ifsc_code, bank_branch, notes, item_tax_data, share_holders_data, share_holder_documents, nominee_data, ledger_photo, created_at) 
            VALUES 
            ('$name', '$alternate_name', '$first_name', '$last_name', '$mobile_country_code', '$mobile_no', '$phone_country_code', '$phone_no', '$mail_id',
             '$identity_no', '$national_id', '$trade_no', 
             " . ($identity_issue_date ? "'$identity_issue_date'" : "NULL") . ", " . ($identity_expiry_date ? "'$identity_expiry_date'" : "NULL") . ", " . ($special_day ? "'$special_day'" : "NULL") . ",
             '$customer_type_id', '$registration_no', " . ($registration_date ? "'$registration_date'" : "NULL") . ", 
             " . ($gstin_raw !== '' ? "'$gstin'" : 'NULL') . ",
             '$nationality_id', '$country_id', '$ledger_state_id', '$ledger_city_id',
             " . ($date1 ? "'$date1'" : "NULL") . ", " . ($date2 ? "'$date2'" : "NULL") . ", 
             '$group_id', '$sundry_debtors_id',
             '$ledger_name_capital', '$kyc', '$aml', '$bill_to_bill',
             '$billing_address1', '$billing_address2', '$billing_country', '$billing_state', '$billing_city', '$billing_zip_code',
             '$shipping_address1', '$shipping_address2', '$shipping_country', '$shipping_state', '$shipping_city', '$shipping_zip_code',
             '$bank_account_no', '$bank_name', '$bank_ifsc_code', '$bank_branch', '$notes', '$item_tax_json', '$share_holders_json', '$share_holder_documents_json', '$nominees_json', '$ledger_photo', NOW())
        ";
        
        if (!mysqli_query($conn, $sql)) {
            throw new Exception("Customer insert failed: " . mysqli_error($conn));
        }
        
        $customer_id = mysqli_insert_id($conn);
        
        // Insert opening balance for new customer (always save opening record, including 0 balance)
        $debit_amount = $opening_balance > 0 && $opening_crdr === 'Dr' ? $opening_balance : 0;
        $credit_amount = $opening_balance > 0 && $opening_crdr === 'Cr' ? $opening_balance : 0;
        $balance_amount = $opening_balance > 0 ? ($opening_crdr === 'Dr' ? $opening_balance : -$opening_balance) : 0;
        $user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
        $today = date('Y-m-d');
        $ob_br_sql = $opening_branch_id > 0 ? (string) (int) $opening_branch_id : 'NULL';
        $ledger_sql = "INSERT INTO tbl_customer_ledger 
            (customer_id, customer_name, branch_id, transaction_type, transaction_id, transaction_no, transaction_date, 
             debit_amount, credit_amount, $opening_metal_insert_cols,
             balance_amount, description, reference_no, against_ledger, against_invoice_no, status, created_by, created_at)
            VALUES 
            ($customer_id, '$name', $ob_br_sql, 'opening', 0, 'OPENING', '$today',
             $debit_amount, $credit_amount, $opening_metal_insert_vals,
             $balance_amount, 'Opening balance', '', '', '', 1, $user_id, NOW())";
        @mysqli_query($conn, $ledger_sql);
    }
    
    mysqli_commit($conn);
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Customer saved successfully',
        'customer_id' => $customer_id,
        'customer_name' => $name,
        'gstin' => $gstin_raw,
    ]);
    
} catch (Exception $e) {
    mysqli_rollback($conn);
    
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

