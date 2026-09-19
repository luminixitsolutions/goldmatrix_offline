<?php
session_start();
require_once dirname(__DIR__) . '/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

auragold_ensure_branch_id_on_settings_tables($conn);

$invoice_print_column_keys = function_exists('getInvoicePrintSaleInvoiceColumnKeys')
    ? getInvoicePrintSaleInvoiceColumnKeys()
    : ['sr_no','item_name','design_no','huid','category','gross_weight','less_weight','net_weight','purity_karat','rate','making_charge','diamond_amount','stone_amount','discount','amount'];

$isFormData = !empty($_POST) || (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'multipart/form-data') !== false);

if ($isFormData && !empty($_POST)) {
    $input = $_POST;
    $columns = isset($input['sale_invoice_columns']) ? (is_array($input['sale_invoice_columns']) ? $input['sale_invoice_columns'] : @json_decode($input['sale_invoice_columns'], true)) : [];
} else {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $columns = isset($input['sale_invoice_columns']) && is_array($input['sale_invoice_columns']) ? $input['sale_invoice_columns'] : [];
}

$header_logo = isset($input['header_company_logo']) ? ($input['header_company_logo'] ? '1' : '0') : '1';
$header_name = isset($input['header_company_name']) ? ($input['header_company_name'] ? '1' : '0') : '1';
$header_gst = isset($input['header_gst_number']) ? ($input['header_gst_number'] ? '1' : '0') : '1';
$header_phone = isset($input['header_phone']) ? ($input['header_phone'] ? '1' : '0') : '1';
$header_title = isset($input['header_invoice_title']) ? ($input['header_invoice_title'] ? '1' : '0') : '1';
$footer_terms = isset($input['footer_terms_conditions']) ? ($input['footer_terms_conditions'] ? '1' : '0') : '1';
$footer_signature = isset($input['footer_authorized_signature']) ? ($input['footer_authorized_signature'] ? '1' : '0') : '1';
$footer_thankyou = isset($input['footer_thank_you_message']) ? ($input['footer_thank_you_message'] ? '1' : '0') : '1';
$layout_type = isset($input['layout_type']) ? normalizeInvoicePrintLayoutType($input['layout_type']) : 'A4';
$page_orientation = isset($input['page_orientation']) ? normalizeInvoicePrintPageOrientation($input['page_orientation']) : 'portrait';

$company_name = isset($input['company_name']) ? trim((string)$input['company_name']) : '';
$company_address = isset($input['company_address']) ? trim((string)$input['company_address']) : '';
$company_gst = isset($input['company_gst']) ? trim((string)$input['company_gst']) : '';
$company_pan = isset($input['company_pan']) ? trim((string)$input['company_pan']) : '';
if (strlen($company_pan) > 32) {
    $company_pan = substr($company_pan, 0, 32);
}
$company_phone = isset($input['company_phone']) ? trim((string)$input['company_phone']) : '';
$company_email = isset($input['company_email']) ? trim((string)$input['company_email']) : '';
$invoice_title = isset($input['invoice_title']) ? trim((string)$input['invoice_title']) : '';
$terms_conditions = isset($input['terms_conditions']) ? trim((string)$input['terms_conditions']) : '';
$authorized_signature = isset($input['authorized_signature']) ? trim((string)$input['authorized_signature']) : '';
$thank_you_message = isset($input['thank_you_message']) ? trim((string)$input['thank_you_message']) : '';
$invoice_secondary_language = isset($input['invoice_secondary_language']) ? trim((string)$input['invoice_secondary_language']) : '';
if (!in_array($invoice_secondary_language, ['', 'hi', 'mr', 'ar'], true)) $invoice_secondary_language = '';
$footer_show_banner = isset($input['footer_show_banner']) ? ($input['footer_show_banner'] ? '1' : '0') : '0';
$header_section_enabled = isset($input['header_section_enabled']) ? ($input['header_section_enabled'] ? '1' : '0') : '1';

$print_padding_top_mm = isset($input['print_padding_top_mm']) ? trim((string)$input['print_padding_top_mm']) : '0';
if ($print_padding_top_mm !== '' && is_numeric($print_padding_top_mm)) {
    $p = (float)$print_padding_top_mm;
    if ($p < 0) $p = 0;
    if ($p > 80) $p = 80;
    $print_padding_top_mm = (string)$p;
} else {
    $print_padding_top_mm = '0';
}

$column_header_labels = [];
if (!empty($input['column_header_labels'])) {
    $raw = is_string($input['column_header_labels']) ? @json_decode($input['column_header_labels'], true) : $input['column_header_labels'];
    if (is_array($raw)) {
        foreach ($raw as $k => $v) {
            if (!in_array($k, $invoice_print_column_keys, true)) continue;
            if (!is_string($v)) continue;
            $column_header_labels[$k] = trim($v);
        }
    }
}

$summary_label_overrides = [];
if (!empty($input['summary_label_overrides'])) {
    $raw = is_string($input['summary_label_overrides']) ? @json_decode($input['summary_label_overrides'], true) : $input['summary_label_overrides'];
    if (is_array($raw)) {
        $sum_keys = ['total', 'advance_amount', 'total_before_vat', 'vat_5_label', 'total_including_vat', 'less_scrap', 'balance_amount'];
        foreach ($sum_keys as $sk) {
            if (!isset($raw[$sk]) || !is_string($raw[$sk])) continue;
            $t = trim($raw[$sk]);
            if ($t !== '') {
                $summary_label_overrides[$sk] = $t;
            }
        }
    }
}

$summary_row_order = [];
$default_order = ['total', 'advance_amount', 'total_before_vat', 'vat_5_label', 'total_including_vat', 'less_scrap', 'balance_amount'];
if (!empty($input['summary_row_order'])) {
    $raw = is_string($input['summary_row_order']) ? @json_decode($input['summary_row_order'], true) : $input['summary_row_order'];
    if (is_array($raw)) {
        foreach ($raw as $k) {
            if (in_array($k, $default_order, true) && !in_array($k, $summary_row_order, true)) {
                $summary_row_order[] = $k;
            }
        }
    }
}
foreach ($default_order as $k) {
    if (!in_array($k, $summary_row_order, true)) {
        $summary_row_order[] = $k;
    }
}

$t6_show_vlines = isset($input['t6_show_item_vertical_lines']) ? ($input['t6_show_item_vertical_lines'] ? '1' : '0') : '0';
$t6_show_curr = isset($input['t6_show_currency_on_amounts']) ? ($input['t6_show_currency_on_amounts'] ? '1' : '0') : '0';
$t6_rates_banner_format = isset($input['t6_rates_banner_format']) ? trim((string)$input['t6_rates_banner_format']) : '';
if (strlen($t6_rates_banner_format) > 500) {
    $t6_rates_banner_format = substr($t6_rates_banner_format, 0, 500);
}
$t6_min_item_rows = isset($input['t6_min_item_rows']) ? trim((string)$input['t6_min_item_rows']) : '12';
if (!is_numeric($t6_min_item_rows)) {
    $t6_min_item_rows = '12';
} else {
    $mr = (int) $t6_min_item_rows;
    if ($mr < 1) {
        $mr = 1;
    }
    if ($mr > 40) {
        $mr = 40;
    }
    $t6_min_item_rows = (string) $mr;
}

$t6_label_trim = static function ($v, $max = 160) {
    $v = trim((string) $v);
    if (strlen($v) > $max) {
        return substr($v, 0, $max);
    }
    return $v;
};
$t6_label_gold_total = $t6_label_trim($input['t6_label_gold_total'] ?? '');
$t6_label_silver_total = $t6_label_trim($input['t6_label_silver_total'] ?? '');
$t6_label_total_before_gst = $t6_label_trim($input['t6_label_total_before_gst'] ?? '');
$t6_label_cgst = $t6_label_trim($input['t6_label_cgst'] ?? '');
$t6_label_sgst = $t6_label_trim($input['t6_label_sgst'] ?? '');
$t6_label_total_with_gst = $t6_label_trim($input['t6_label_total_with_gst'] ?? '');
$t6_label_bank_transfer = $t6_label_trim($input['t6_label_bank_transfer'] ?? '');
$t6_label_cash = $t6_label_trim($input['t6_label_cash'] ?? '');
$t6_label_last_balance = $t6_label_trim($input['t6_label_last_balance'] ?? '');
$t6_label_current_balance = $t6_label_trim($input['t6_label_current_balance'] ?? '');
$t6_balance_suffix = $t6_label_trim($input['t6_balance_suffix'] ?? '', 40);

$t6_column_labels = [];
$t6_col_keys = function_exists('getInvoicePrintTemplate6ColumnLabelKeys') ? getInvoicePrintTemplate6ColumnLabelKeys() : [];
if (!empty($input['t6_column_labels'])) {
    $raw = is_string($input['t6_column_labels']) ? @json_decode($input['t6_column_labels'], true) : $input['t6_column_labels'];
    if (is_array($raw)) {
        foreach ($t6_col_keys as $ck) {
            if (!isset($raw[$ck]) || !is_string($raw[$ck])) {
                continue;
            }
            $t = trim($raw[$ck]);
            if ($t !== '' && strlen($t) <= 40) {
                $t6_column_labels[$ck] = $t;
            }
        }
    }
}

$custom_print_css = isset($input['custom_print_css']) ? (string) $input['custom_print_css'] : '';
if (strlen($custom_print_css) > 65535) {
    $custom_print_css = substr($custom_print_css, 0, 65535);
}

$columns = is_array($columns)
    ? array_values(array_filter($columns, static function ($k) use ($invoice_print_column_keys) {
        return is_string($k) && in_array($k, $invoice_print_column_keys, true);
    }))
    : [];

$setting_type = isset($input['setting_type']) ? trim((string)$input['setting_type']) : 'default';
$allowed_types = getInvoicePrintSettingTypes();
if (!in_array($setting_type, $allowed_types, true)) {
    $setting_type = 'default';
}

$design_template = isset($input['design_template']) ? trim((string)$input['design_template']) : 'template_1';
$template_ids = array_column(getInvoicePrintDesignTemplates(), 'id');
if (!in_array($design_template, $template_ids, true)) {
    $design_template = 'template_1';
}

$invoice_template = isset($input['invoice_template']) ? trim((string)$input['invoice_template']) : 'template_classic';
$structure_templates = function_exists('getInvoicePrintStructureTemplates') ? array_keys(getInvoicePrintStructureTemplates()) : ['template_classic'];
if (!in_array($invoice_template, $structure_templates, true)) {
    $invoice_template = 'template_classic';
}

$upload_dir = dirname(__DIR__) . '/uploads/invoice_print/';
$company_logo_path = '';

if (!empty($_FILES['company_logo']['tmp_name']) && is_uploaded_file($_FILES['company_logo']['tmp_name'])) {
    if (!is_dir($upload_dir)) {
        @mkdir($upload_dir, 0755, true);
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($_FILES['company_logo']['tmp_name']);
    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (in_array($mime, $allowed, true)) {
        $ext = preg_match('/^image\/(jpeg|png|gif|webp)$/', $mime, $m) ? ($m[1] === 'jpeg' ? 'jpg' : $m[1]) : 'png';
        $dest = $upload_dir . 'logo.' . $ext;
        if (move_uploaded_file($_FILES['company_logo']['tmp_name'], $dest)) {
            $company_logo_path = 'uploads/invoice_print/logo.' . $ext;
        }
    }
} elseif ($isFormData && isset($input['company_logo_path'])) {
    $company_logo_path = trim((string)$input['company_logo_path']);
}

$advertise_banner_path = '';
if (!empty($_FILES['advertise_banner']['tmp_name']) && is_uploaded_file($_FILES['advertise_banner']['tmp_name'])) {
    if (!is_dir($upload_dir)) {
        @mkdir($upload_dir, 0755, true);
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($_FILES['advertise_banner']['tmp_name']);
    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (in_array($mime, $allowed, true)) {
        $ext = preg_match('/^image\/(jpeg|png|gif|webp)$/', $mime, $m) ? ($m[1] === 'jpeg' ? 'jpg' : $m[1]) : 'png';
        $dest = $upload_dir . 'advertise_banner.' . $ext;
        if (move_uploaded_file($_FILES['advertise_banner']['tmp_name'], $dest)) {
            $advertise_banner_path = 'uploads/invoice_print/advertise_banner.' . $ext;
        }
    }
} elseif ($isFormData && isset($input['advertise_banner_path'])) {
    $advertise_banner_path = trim((string)$input['advertise_banner_path']);
}

if (!saveInvoicePrintSetting('sale_invoice_columns', $columns, $setting_type)) {
    echo json_encode(['success' => false, 'message' => 'Could not save column configuration. Please try again.']);
    exit;
}
saveInvoicePrintSetting('header_company_logo', $header_logo, $setting_type);
saveInvoicePrintSetting('header_company_name', $header_name, $setting_type);
saveInvoicePrintSetting('header_gst_number', $header_gst, $setting_type);
saveInvoicePrintSetting('header_phone', $header_phone, $setting_type);
saveInvoicePrintSetting('header_invoice_title', $header_title, $setting_type);
saveInvoicePrintSetting('header_section_enabled', $header_section_enabled, $setting_type);
saveInvoicePrintSetting('print_padding_top_mm', $print_padding_top_mm, $setting_type);
saveInvoicePrintSetting('column_header_labels', $column_header_labels, $setting_type);
saveInvoicePrintSetting('summary_label_overrides', $summary_label_overrides, $setting_type);
saveInvoicePrintSetting('summary_row_order', $summary_row_order, $setting_type);
saveInvoicePrintSetting('footer_terms_conditions', $footer_terms, $setting_type);
saveInvoicePrintSetting('footer_authorized_signature', $footer_signature, $setting_type);
saveInvoicePrintSetting('footer_thank_you_message', $footer_thankyou, $setting_type);
saveInvoicePrintSetting('layout_type', $layout_type, $setting_type);
saveInvoicePrintSetting('page_orientation', $page_orientation, $setting_type);
saveInvoicePrintSetting('design_template', $design_template, $setting_type);
saveInvoicePrintSetting('invoice_template', $invoice_template, $setting_type);

if ($company_logo_path !== '' || $isFormData) {
    saveInvoicePrintSetting('company_logo_path', $company_logo_path, $setting_type);
}
saveInvoicePrintSetting('company_name', $company_name, $setting_type);
saveInvoicePrintSetting('company_address', $company_address, $setting_type);
saveInvoicePrintSetting('company_gst', $company_gst, $setting_type);
saveInvoicePrintSetting('company_pan', $company_pan, $setting_type);
saveInvoicePrintSetting('company_phone', $company_phone, $setting_type);
saveInvoicePrintSetting('company_email', $company_email, $setting_type);
saveInvoicePrintSetting('invoice_title', $invoice_title, $setting_type);
saveInvoicePrintSetting('terms_conditions', $terms_conditions, $setting_type);
saveInvoicePrintSetting('authorized_signature', $authorized_signature, $setting_type);
saveInvoicePrintSetting('thank_you_message', $thank_you_message, $setting_type);
saveInvoicePrintSetting('invoice_secondary_language', $invoice_secondary_language, $setting_type);
saveInvoicePrintSetting('footer_show_banner', $footer_show_banner, $setting_type);
if ($advertise_banner_path !== '' || $isFormData) {
    saveInvoicePrintSetting('advertise_banner_path', $advertise_banner_path, $setting_type);
}

saveInvoicePrintSetting('t6_show_item_vertical_lines', $t6_show_vlines, $setting_type);
saveInvoicePrintSetting('t6_show_currency_on_amounts', $t6_show_curr, $setting_type);
saveInvoicePrintSetting('t6_rates_banner_format', $t6_rates_banner_format, $setting_type);
saveInvoicePrintSetting('t6_min_item_rows', $t6_min_item_rows, $setting_type);
saveInvoicePrintSetting('t6_label_gold_total', $t6_label_gold_total, $setting_type);
saveInvoicePrintSetting('t6_label_silver_total', $t6_label_silver_total, $setting_type);
saveInvoicePrintSetting('t6_label_total_before_gst', $t6_label_total_before_gst, $setting_type);
saveInvoicePrintSetting('t6_label_cgst', $t6_label_cgst, $setting_type);
saveInvoicePrintSetting('t6_label_sgst', $t6_label_sgst, $setting_type);
saveInvoicePrintSetting('t6_label_total_with_gst', $t6_label_total_with_gst, $setting_type);
saveInvoicePrintSetting('t6_label_bank_transfer', $t6_label_bank_transfer, $setting_type);
saveInvoicePrintSetting('t6_label_cash', $t6_label_cash, $setting_type);
saveInvoicePrintSetting('t6_label_last_balance', $t6_label_last_balance, $setting_type);
saveInvoicePrintSetting('t6_label_current_balance', $t6_label_current_balance, $setting_type);
saveInvoicePrintSetting('t6_balance_suffix', $t6_balance_suffix, $setting_type);
saveInvoicePrintSetting('t6_column_labels', $t6_column_labels, $setting_type);

$t7_label_trim = static function ($v, $max = 160) {
    $t = trim((string) $v);
    if (strlen($t) > $max) {
        $t = substr($t, 0, $max);
    }
    return $t;
};
$t7_company_tagline = $t7_label_trim($input['t7_company_tagline'] ?? '', 120);
$t7_bank_name = $t7_label_trim($input['t7_bank_name'] ?? '', 150);
$t7_bank_account_name = $t7_label_trim($input['t7_bank_account_name'] ?? '', 150);
$t7_bank_account_no = $t7_label_trim($input['t7_bank_account_no'] ?? '', 64);
$t7_bank_ifsc = $t7_label_trim($input['t7_bank_ifsc'] ?? '', 20);
$t7_min_item_rows = isset($input['t7_min_item_rows']) ? trim((string) $input['t7_min_item_rows']) : '15';
if (!is_numeric($t7_min_item_rows)) {
    $t7_min_item_rows = '15';
} else {
    $mr7 = (int) $t7_min_item_rows;
    if ($mr7 < 1) {
        $mr7 = 1;
    }
    if ($mr7 > 40) {
        $mr7 = 40;
    }
    $t7_min_item_rows = (string) $mr7;
}
saveInvoicePrintSetting('t7_company_tagline', $t7_company_tagline, $setting_type);
saveInvoicePrintSetting('t7_min_item_rows', $t7_min_item_rows, $setting_type);
saveInvoicePrintSetting('t7_bank_name', $t7_bank_name, $setting_type);
saveInvoicePrintSetting('t7_bank_account_name', $t7_bank_account_name, $setting_type);
saveInvoicePrintSetting('t7_bank_account_no', $t7_bank_account_no, $setting_type);
saveInvoicePrintSetting('t7_bank_ifsc', $t7_bank_ifsc, $setting_type);

$t8_min_item_rows = isset($input['t8_min_item_rows']) ? trim((string) $input['t8_min_item_rows']) : '2';
if (!is_numeric($t8_min_item_rows)) {
    $t8_min_item_rows = '2';
} else {
    $mr8 = (int) $t8_min_item_rows;
    if ($mr8 < 1) {
        $mr8 = 1;
    }
    if ($mr8 > 40) {
        $mr8 = 40;
    }
    $t8_min_item_rows = (string) $mr8;
}
$t8_label_trim = static function ($v, $max = 160) {
    $v = trim((string) $v);
    if (strlen($v) > $max) {
        $v = substr($v, 0, $max);
    }
    return $v;
};
$t8_bank_name = $t8_label_trim($input['t8_bank_name'] ?? '', 150);
$t8_bank_account_no = $t8_label_trim($input['t8_bank_account_no'] ?? '', 64);
$t8_bank_ifsc = $t8_label_trim($input['t8_bank_ifsc'] ?? '', 20);
$t8_bank_branch = $t8_label_trim($input['t8_bank_branch'] ?? '', 120);
saveInvoicePrintSetting('t8_min_item_rows', $t8_min_item_rows, $setting_type);
saveInvoicePrintSetting('t8_bank_name', $t8_bank_name, $setting_type);
saveInvoicePrintSetting('t8_bank_account_no', $t8_bank_account_no, $setting_type);
saveInvoicePrintSetting('t8_bank_ifsc', $t8_bank_ifsc, $setting_type);
saveInvoicePrintSetting('t8_bank_branch', $t8_bank_branch, $setting_type);

$t9_label_trim = static function ($v, $max = 255) {
    $v = trim((string) $v);
    if (strlen($v) > $max) {
        $v = substr($v, 0, $max);
    }
    return $v;
};
$t9_arabic_company_name = $t9_label_trim($input['t9_arabic_company_name'] ?? '', 200);
$t9_qr_image_path = $t9_label_trim($input['t9_qr_image_path'] ?? '', 255);
$t9_amount_currency_label = $t9_label_trim($input['t9_amount_currency_label'] ?? 'Dhs.', 20);
if ($t9_amount_currency_label === '') {
    $t9_amount_currency_label = 'Dhs.';
}
$t9_terms_arabic = $t9_label_trim($input['t9_terms_arabic'] ?? '', 2000);
saveInvoicePrintSetting('t9_arabic_company_name', $t9_arabic_company_name, $setting_type);
saveInvoicePrintSetting('t9_qr_image_path', $t9_qr_image_path, $setting_type);
saveInvoicePrintSetting('t9_amount_currency_label', $t9_amount_currency_label, $setting_type);
saveInvoicePrintSetting('t9_terms_arabic', $t9_terms_arabic, $setting_type);

$t10_label_trim = static function ($v, $max = 255) {
    $v = trim((string) $v);
    if (strlen($v) > $max) {
        $v = substr($v, 0, $max);
    }
    return $v;
};
$t10_address_ar = $t10_label_trim($input['t10_address_ar'] ?? '', 200);
$t10_cr_no = $t10_label_trim($input['t10_cr_no'] ?? '', 64);
$t10_website = $t10_label_trim($input['t10_website'] ?? '', 120);
$t10_instagram = $t10_label_trim($input['t10_instagram'] ?? '', 120);
$t10_brand_sub = $t10_label_trim($input['t10_brand_sub'] ?? 'jewelry', 60);
$t10_amount_currency_label = $t10_label_trim($input['t10_amount_currency_label'] ?? 'QAR', 20);
if ($t10_amount_currency_label === '') {
    $t10_amount_currency_label = 'QAR';
}
$t10_gold_rate_karat = $t10_label_trim($input['t10_gold_rate_karat'] ?? '24K', 8);
if (!in_array($t10_gold_rate_karat, ['24K', '22K', '21K', '18K'], true)) {
    $t10_gold_rate_karat = '24K';
}
$t10_min_item_rows = isset($input['t10_min_item_rows']) ? (int) $input['t10_min_item_rows'] : 5;
if ($t10_min_item_rows < 1) {
    $t10_min_item_rows = 1;
} elseif ($t10_min_item_rows > 40) {
    $t10_min_item_rows = 40;
}
$t10_terms_arabic = $t10_label_trim($input['t10_terms_arabic'] ?? '', 4000);
saveInvoicePrintSetting('t10_address_ar', $t10_address_ar, $setting_type);
saveInvoicePrintSetting('t10_cr_no', $t10_cr_no, $setting_type);
saveInvoicePrintSetting('t10_website', $t10_website, $setting_type);
saveInvoicePrintSetting('t10_instagram', $t10_instagram, $setting_type);
saveInvoicePrintSetting('t10_brand_sub', $t10_brand_sub, $setting_type);
saveInvoicePrintSetting('t10_amount_currency_label', $t10_amount_currency_label, $setting_type);
saveInvoicePrintSetting('t10_gold_rate_karat', $t10_gold_rate_karat, $setting_type);
saveInvoicePrintSetting('t10_min_item_rows', (string) $t10_min_item_rows, $setting_type);
saveInvoicePrintSetting('t10_terms_arabic', $t10_terms_arabic, $setting_type);

saveInvoicePrintSetting('custom_print_css', $custom_print_css, $setting_type);

$email_message_subject = isset($input['email_message_subject']) ? trim((string) $input['email_message_subject']) : '';
if (strlen($email_message_subject) > 500) {
    $email_message_subject = substr($email_message_subject, 0, 500);
}
$email_message_body = isset($input['email_message_body']) ? (string) $input['email_message_body'] : '';
if (strlen($email_message_body) > 65535) {
    $email_message_body = substr($email_message_body, 0, 65535);
}
saveInvoicePrintSetting('email_message_subject', $email_message_subject, $setting_type);
saveInvoicePrintSetting('email_message_body', $email_message_body, $setting_type);

echo json_encode(['success' => true, 'message' => 'Settings saved successfully.', 'company_logo_path' => $company_logo_path, 'advertise_banner_path' => $advertise_banner_path]);
