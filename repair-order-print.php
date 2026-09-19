<?php
session_start();
require_once 'config.php';
require_once __DIR__ . '/includes/invoice-print-currency-symbol.php';

// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Get order ID from URL
$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($order_id <= 0) {
    die('Invalid order ID');
}

try {
    // Get order details from tbl_repair_orders
    $order = getRecord("SELECT * FROM tbl_repair_orders WHERE id = $order_id");
    
    if (!$order) {
        die('Order not found');
    }
} catch (Exception $e) {
    die('Error loading order: ' . $e->getMessage());
}

// Get order items
$items = getList("
    SELECT 
        roi.*,
        COALESCE(p.name, roi.product_name) as product_name,
        c.name as category_name
    FROM tbl_repair_order_items roi
    LEFT JOIN tbl_products p ON roi.product_id = p.id
    LEFT JOIN tbl_categories c ON p.category_id = c.id
    WHERE roi.order_id = $order_id 
    ORDER BY roi.id ASC
");

// Get order payments
$payments = getList("SELECT * FROM tbl_repair_order_payments WHERE order_id = $order_id ORDER BY id ASC");

// Calculate payment totals by type
$payment_totals = [
    'cash' => 0,
    'bank' => 0,
    'cheque' => 0,
    'upi' => 0,
    'card' => 0,
    'metal_exchange' => 0,
    'scrap' => 0
];

foreach ($payments as $payment) {
    $payment_type = strtolower($payment['payment_type'] ?? 'cash');
    $amount = (float)($payment['amount'] ?? 0);
    
    if (isset($payment_totals[$payment_type])) {
        $payment_totals[$payment_type] += $amount;
    } else {
        $payment_totals['cash'] += $amount;
    }
}

// Use $order for display (alias as $invoice for template compatibility)
$invoice = $order;

// Format currency
$currency = !empty($order['currency']) ? trim((string) $order['currency']) : 'AED';
$currency_symbol = invoice_print_currency_symbol(isset($conn) ? $conn : null, $currency);

// Format dates
$invoice_date = !empty($order['order_date']) ? date('d F Y', strtotime($order['order_date'])) : '';
$due_date = !empty($order['due_date']) ? date('d F Y', strtotime($order['due_date'])) : '';

// Company information (using config)
$company_name = isset($Proj_Title) ? $Proj_Title : 'Aura Gold';
$company_email = 'info@auragold.com';
$company_phone = '+971-XX-XXX-XXXX';
$company_address = 'Dubai, UAE';

// Calculate totals
$subtotal = (float)($order['subtotal'] ?? 0);
$discount_amt = (float)($order['discount_amt'] ?? 0);
$additional_amt = (float)($order['additional_amt'] ?? 0);
$tax_amount = 0;
$grand_total = (float)($order['grand_total'] ?? 0);
$paid_amt = (float)($order['paid_amt'] ?? 0);
$balance_amt = (float)($order['balance_amt'] ?? 0);
$round_off = (float)($order['round_off'] ?? 0);

foreach ($items as $item) {
    $tax_amount += (float)($item['tax_amount'] ?? 0);
}

$net_total = $subtotal - $discount_amt + $additional_amt + $tax_amount;
?>

<!DOCTYPE html>
<html class="no-js" lang="en">
<head>
  <meta charset="utf-8">
  <meta http-equiv="x-ua-compatible" content="ie=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="author" content="Aura Gold">
  <title>Repair Order - <?php echo htmlspecialchars($order['order_no']); ?></title>
  <link rel="stylesheet" href="https://html.laralink.com/invoma/assets/css/style.css">
  <style>
    @media print {
      .tm_invoice_btns { display: none !important; }
      body { margin: 0; padding: 0; }
      .tm_container { padding: 0; }
    }
    .tm_invoice { max-width: 210mm; margin: 0 auto; background: #fff; padding: 20px; }
    .tm_logo img { max-width: 150px; height: auto; }
    .tm_table { margin-top: 20px; }
    .tm_table table { width: 100%; border-collapse: collapse; }
    .tm_table th, .tm_table td { padding: 10px; text-align: left; border-bottom: 1px solid #e2e8f0; }
    .tm_table th { background: #f8fafc; font-weight: 600; }
    .text-right { text-align: right; }
    .text-center { text-align: center; }
    .tm_bold { font-weight: 600; }
    .payment-info { margin-top: 15px; padding: 10px; background: #f8fafc; border-radius: 4px; }
    .payment-info p { margin: 5px 0; font-size: 11px; }
  </style>
</head>

<body>
  <div class="tm_container">
    <div class="tm_invoice_wrap">
      <div class="tm_invoice tm_style2" id="tm_download_section">
        <div class="tm_invoice_in">
          <div class="tm_invoice_head tm_top_head tm_mb20">
            <div class="tm_invoice_left">
              <div class="tm_logo">
                <h2 style="margin: 0; color: #11294b; font-size: 28px;"><?php echo htmlspecialchars($company_name); ?></h2>
                <p style="margin: 5px 0 0 0; color: #64748b; font-size: 12px;">Repair Order</p>
              </div>
            </div>
            <div class="tm_invoice_right">
              <div class="tm_grid_row tm_col_3">
                <div><b class="tm_primary_color">Email</b><br><?php echo htmlspecialchars($company_email); ?></div>
                <div><b class="tm_primary_color">Phone</b><br><?php echo htmlspecialchars($company_phone); ?></div>
                <div><b class="tm_primary_color">Address</b><br><?php echo htmlspecialchars($company_address); ?></div>
              </div>
            </div>
          </div>

          <div class="tm_invoice_info tm_mb10">
            <div class="tm_invoice_info_left">
              <p class="tm_mb2"><b>Customer:</b></p>
              <p>
                <b class="tm_f16 tm_primary_color"><?php echo htmlspecialchars($order['customer_name']); ?></b><br>
                <?php if (!empty($order['ref_no'])): ?>Ref No: <?php echo htmlspecialchars($order['ref_no']); ?><br><?php endif; ?>
                <?php if (!empty($order['sales_person'])): ?>Sales Person: <?php echo htmlspecialchars($order['sales_person']); ?><br><?php endif; ?>
                <?php if (!empty($order['against_of'])): ?>Against: <?php echo htmlspecialchars($order['against_of']); ?><br><?php endif; ?>
              </p>
            </div>
            <div class="tm_invoice_info_right">
              <div class="tm_ternary_color tm_f50 tm_text_uppercase tm_text_center tm_invoice_title tm_mb15 tm_mobile_hide">Repair Order</div>
              <div class="tm_grid_row tm_col_3 tm_invoice_info_in tm_accent_bg">
                <div><span class="tm_white_color_60">Grand Total:</span><br><b class="tm_f16 tm_white_color"><?php echo $currency_symbol; ?> <?php echo number_format($grand_total, 2); ?></b></div>
                <div><span class="tm_white_color_60">Order Date:</span><br><b class="tm_f16 tm_white_color"><?php echo $invoice_date; ?></b></div>
                <div><span class="tm_white_color_60">Order No:</span><br><b class="tm_f16 tm_white_color">#<?php echo htmlspecialchars($order['order_no']); ?></b></div>
              </div>
              <?php if (!empty($due_date)): ?>
              <div class="tm_grid_row tm_col_1 tm_invoice_info_in" style="margin-top: 10px;">
                <div><span>Due Date: <b><?php echo $due_date; ?></b></span></div>
              </div>
              <?php endif; ?>
            </div>
          </div>

          <div class="tm_table tm_style1">
            <div class="tm_round_border">
              <div class="tm_table_responsive">
                <table>
                  <thead>
                    <tr>
                      <th class="tm_width_7 tm_semi_bold tm_accent_color">Item</th>
                      <th class="tm_width_1 tm_semi_bold tm_accent_color">Qty</th>
                      <th class="tm_width_2 tm_semi_bold tm_accent_color">Gross Wt</th>
                      <th class="tm_width_2 tm_semi_bold tm_accent_color">Final Wt</th>
                      <th class="tm_width_2 tm_semi_bold tm_accent_color">Rate</th>
                      <th class="tm_width_2 tm_semi_bold tm_accent_color tm_text_right">Amount</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php 
                    $row_count = 0;
                    foreach ($items as $item): 
                      $row_count++;
                      $bg_class = ($row_count % 2 == 0) ? 'tm_gray_bg' : '';
                      $product_name = !empty($item['product_name']) ? $item['product_name'] : ('Product #' . ($item['product_id'] ?? 'N/A'));
                      $category = !empty($item['category_name']) ? $item['category_name'] : '';
                      $barcode = !empty($item['barcode']) ? $item['barcode'] : '';
                      $design_no = !empty($item['design_no']) ? $item['design_no'] : '';
                      
                      $description_parts = array_filter([$category, $design_no, $barcode ? 'Barcode: ' . $barcode : '']);
                      $description = implode(' | ', $description_parts);
                      
                      $quantity = (float)($item['quantity'] ?? 1);
                      $gross_weight = (float)($item['gross_weight'] ?? 0);
                      $final_weight = (float)($item['final_weight'] ?? 0);
                      $rate = (float)($item['rate'] ?? 0);
                      $amount = (float)($item['amount'] ?? 0);
                      $making_amount = (float)($item['making_amount'] ?? 0);
                    ?>
                    <tr class="<?php echo $bg_class; ?>">
                      <td class="tm_width_7">
                        <p class="tm_m0 tm_f16 tm_primary_color"><?php echo htmlspecialchars($product_name); ?></p>
                        <?php if (!empty($description)): ?><?php echo htmlspecialchars($description); ?><?php endif; ?>
                        <?php if ($making_amount > 0): ?><br><small>Making: <?php echo $currency_symbol; ?> <?php echo number_format($making_amount, 2); ?></small><?php endif; ?>
                      </td>
                      <td class="tm_width_1"><?php echo number_format($quantity, 2); ?></td>
                      <td class="tm_width_2"><?php echo number_format($gross_weight, 3); ?></td>
                      <td class="tm_width_2"><?php echo number_format($final_weight, 3); ?></td>
                      <td class="tm_width_2"><?php echo $currency_symbol; ?> <?php echo number_format($rate, 2); ?></td>
                      <td class="tm_width_2 tm_text_right"><?php echo $currency_symbol; ?> <?php echo number_format($amount, 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <?php if (empty($items)): ?>
                    <tr><td colspan="6" class="text-center" style="padding: 20px; color: #94a3b8;">No items found</td></tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>

            <div class="tm_invoice_footer tm_mb15 tm_m0_md">
              <div class="tm_left_footer">
                <?php if (!empty($payments)): ?>
                <div class="payment-info">
                  <b>Payment Information:</b>
                  <?php 
                  $payment_details = [];
                  if ($payment_totals['cash'] > 0) $payment_details[] = 'Cash: ' . $currency_symbol . ' ' . number_format($payment_totals['cash'], 2);
                  if ($payment_totals['bank'] > 0) $payment_details[] = 'Bank: ' . $currency_symbol . ' ' . number_format($payment_totals['bank'], 2);
                  if ($payment_totals['cheque'] > 0) $payment_details[] = 'Cheque: ' . $currency_symbol . ' ' . number_format($payment_totals['cheque'], 2);
                  if ($payment_totals['upi'] > 0) $payment_details[] = 'UPI: ' . $currency_symbol . ' ' . number_format($payment_totals['upi'], 2);
                  if ($payment_totals['card'] > 0) $payment_details[] = 'Card: ' . $currency_symbol . ' ' . number_format($payment_totals['card'], 2);
                  if ($payment_totals['metal_exchange'] > 0) $payment_details[] = 'Metal Exchange: ' . $currency_symbol . ' ' . number_format($payment_totals['metal_exchange'], 2);
                  if ($payment_totals['scrap'] > 0) $payment_details[] = 'Scrap: ' . $currency_symbol . ' ' . number_format($payment_totals['scrap'], 2);
                  ?>
                  <p><?php echo implode(' | ', $payment_details); ?></p>
                  <p><b>Paid Amount:</b> <?php echo $currency_symbol; ?> <?php echo number_format($paid_amt, 2); ?></p>
                  <p><b>Balance Amount:</b> <?php echo $currency_symbol; ?> <?php echo number_format($balance_amt, 2); ?></p>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($order['comment'])): ?>
                <p class="tm_mb2" style="margin-top: 15px;"><b class="tm_primary_color">Note:</b></p>
                <p class="tm_m0"><?php echo nl2br(htmlspecialchars($order['comment'])); ?></p>
                <?php endif; ?>
                
                <?php if (!empty($order['previous_balance']) && $order['previous_balance'] > 0): ?>
                <p class="tm_mb2" style="margin-top: 15px;"><b class="tm_primary_color">Previous Balance:</b></p>
                <p class="tm_m0"><?php echo $currency_symbol; ?> <?php echo number_format($order['previous_balance'], 2); ?></p>
                <?php endif; ?>
              </div>
              <div class="tm_right_footer">
                <table class="tm_mb15">
                  <tbody>
                    <tr>
                      <td class="tm_width_3 tm_primary_color tm_border_none tm_bold">Subtotal</td>
                      <td class="tm_width_3 tm_primary_color tm_text_right tm_border_none tm_bold"><?php echo $currency_symbol; ?> <?php echo number_format($subtotal, 2); ?></td>
                    </tr>
                    <?php if ($discount_amt > 0): ?>
                    <tr>
                      <td class="tm_width_3 tm_danger_color tm_border_none tm_pt0">Discount</td>
                      <td class="tm_width_3 tm_danger_color tm_text_right tm_border_none tm_pt0">-<?php echo $currency_symbol; ?> <?php echo number_format($discount_amt, 2); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($additional_amt > 0): ?>
                    <tr>
                      <td class="tm_width_3 tm_primary_color tm_border_none tm_pt0">Additional Amount</td>
                      <td class="tm_width_3 tm_primary_color tm_text_right tm_border_none tm_pt0">+<?php echo $currency_symbol; ?> <?php echo number_format($additional_amt, 2); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($tax_amount > 0): ?>
                    <tr>
                      <td class="tm_width_3 tm_primary_color tm_border_none tm_pt0">Tax</td>
                      <td class="tm_width_3 tm_primary_color tm_text_right tm_border_none tm_pt0">+<?php echo $currency_symbol; ?> <?php echo number_format($tax_amount, 2); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($round_off != 0): ?>
                    <tr>
                      <td class="tm_width_3 tm_primary_color tm_border_none tm_pt0">Round Off</td>
                      <td class="tm_width_3 tm_primary_color tm_text_right tm_border_none tm_pt0"><?php echo $round_off > 0 ? '+' : ''; ?><?php echo $currency_symbol; ?> <?php echo number_format($round_off, 2); ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                      <td class="tm_width_3 tm_border_top_0 tm_bold tm_f16 tm_white_color tm_accent_bg tm_radius_6_0_0_6">Grand Total</td>
                      <td class="tm_width_3 tm_border_top_0 tm_bold tm_f16 tm_primary_color tm_text_right tm_white_color tm_accent_bg tm_radius_0_6_6_0"><?php echo $currency_symbol; ?> <?php echo number_format($grand_total, 2); ?></td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <div class="tm_invoice_footer tm_type1">
              <div class="tm_left_footer"></div>
              <div class="tm_right_footer">
                <div class="tm_sign tm_text_center">
                  <p class="tm_m0 tm_ternary_color">Authorized Signature</p>
                  <p class="tm_m0 tm_f16 tm_primary_color"><?php echo htmlspecialchars($company_name); ?></p>
                </div>
              </div>
            </div>

            <div class="tm_note tm_font_style_normal tm_text_center">
              <hr class="tm_mb15">
              <p class="tm_mb2"><b class="tm_primary_color">Terms & Conditions:</b></p>
              <p class="tm_m0">All claims relating to quantity or quality errors shall be waived by Buyer unless made in writing to Seller within thirty (30) days after delivery of goods.</p>
            </div>
          </div>
        </div>
      </div>
      
      <div class="tm_invoice_btns tm_hide_print">
        <a href="javascript:window.print()" class="tm_invoice_btn tm_color1">
          <span class="tm_btn_icon"><svg xmlns="http://www.w3.org/2000/svg" class="ionicon" viewBox="0 0 512 512"><path d="M384 368h24a40.12 40.12 0 0040-40V168a40.12 40.12 0 00-40-40H104a40.12 40.12 0 00-40 40v160a40.12 40.12 0 0040 40h24" fill="none" stroke="currentColor" stroke-linejoin="round" stroke-width="32"/><rect x="128" y="240" width="256" height="208" rx="24.32" ry="24.32" fill="none" stroke="currentColor" stroke-linejoin="round" stroke-width="32"/><path d="M384 128v-24a40.12 40.12 0 00-40-40H168a40.12 40.12 0 00-40 40v24" fill="none" stroke="currentColor" stroke-linejoin="round" stroke-width="32"/><circle cx="392" cy="184" r="24" fill='currentColor'/></svg></span>
          <span class="tm_btn_text">Print</span>
        </a>
        <button id="tm_download_btn" class="tm_invoice_btn tm_color2">
          <span class="tm_btn_icon"><svg xmlns="http://www.w3.org/2000/svg" class="ionicon" viewBox="0 0 512 512"><path d="M320 336h76c55 0 100-21.21 100-75.6s-53-73.47-96-75.6C391.11 99.74 329 48 256 48c-69 0-113.44 45.79-128 91.2-60 5.7-112 35.88-112 98.4S70 336 136 336h56M192 400.1l64 63.9 64-63.9M256 224v224.03" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="32"/></svg></span>
          <span class="tm_btn_text">Download</span>
        </button>
      </div>
    </div>
  </div>
  
  <script src="https://html.laralink.com/invoma/assets/js/jquery.min.js"></script>
  <script src="https://html.laralink.com/invoma/assets/js/jspdf.min.js"></script>
  <script src="https://html.laralink.com/invoma/assets/js/html2canvas.min.js"></script>
  <script src="https://html.laralink.com/invoma/assets/js/main.js"></script>
</body>
</html>
