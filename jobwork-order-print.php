<?php
session_start();
require_once 'config.php';

error_reporting(E_ALL);
ini_set('display_errors', 0);

$jwo_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$rjwo_id = isset($_GET['rjwo_id']) ? (int)$_GET['rjwo_id'] : 0;

if ($rjwo_id > 0) {
    $jwo = getRecord("SELECT * FROM tbl_repair_jobwork_orders WHERE id = $rjwo_id");
    if (!$jwo) {
        die('Repair Job Work Order not found');
    }
    $items = getList("SELECT * FROM tbl_repair_jobwork_order_items WHERE repair_jobwork_order_id = $rjwo_id ORDER BY id ASC");
} else {
    if ($jwo_id <= 0) {
        die('Invalid Job Work Order ID');
    }
    $jwo = getRecord("SELECT * FROM tbl_jobwork_orders WHERE id = $jwo_id");
    if (!$jwo) {
        die('Job Work Order not found');
    }
    $items = getList("SELECT * FROM tbl_jobwork_order_items WHERE jobwork_order_id = $jwo_id ORDER BY id ASC");
}

$currency_symbol = 'AED';
$invoice_date = !empty($jwo['order_date']) ? date('d F Y', strtotime($jwo['order_date'])) : '';
$due_date = !empty($jwo['due_date']) ? date('d F Y', strtotime($jwo['due_date'])) : '';
$grand_total = (float)($jwo['grand_total'] ?? 0);

$company_name = isset($Proj_Title) ? $Proj_Title : 'Aura Gold';
$company_email = 'info@auragold.com';
$company_phone = '+971-XX-XXX-XXXX';
$company_address = 'Dubai, UAE';
?>
<!DOCTYPE html>
<html class="no-js" lang="en">
<head>
  <meta charset="utf-8">
  <meta http-equiv="x-ua-compatible" content="ie=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="author" content="Aura Gold">
  <title><?php echo $rjwo_id > 0 ? 'Repair ' : ''; ?>Job Work Order - <?php echo htmlspecialchars($jwo['jobwork_no']); ?></title>
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
                <p style="margin: 5px 0 0 0; color: #64748b; font-size: 12px;">Job Work Order</p>
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
                <b class="tm_f16 tm_primary_color"><?php echo htmlspecialchars($jwo['customer_name'] ?? ''); ?></b><br>
                <span><?php echo $rjwo_id > 0 ? 'Repair Order' : 'Sale Order'; ?>: #<?php echo htmlspecialchars($rjwo_id > 0 ? ($jwo['repair_order_no'] ?? '') : ($jwo['sale_order_no'] ?? '')); ?></span>
              </p>
            </div>
            <div class="tm_invoice_info_right">
              <div class="tm_ternary_color tm_f50 tm_text_uppercase tm_text_center tm_invoice_title tm_mb15 tm_mobile_hide">Job Work Order</div>
              <div class="tm_grid_row tm_col_3 tm_invoice_info_in tm_accent_bg">
                <div><span class="tm_white_color_60">Grand Total:</span><br><b class="tm_f16 tm_white_color"><?php echo $currency_symbol; ?> <?php echo number_format($grand_total, 2); ?></b></div>
                <div><span class="tm_white_color_60">Order Date:</span><br><b class="tm_f16 tm_white_color"><?php echo $invoice_date; ?></b></div>
                <div><span class="tm_white_color_60">JWO No:</span><br><b class="tm_f16 tm_white_color"><?php echo htmlspecialchars($jwo['jobwork_no']); ?></b></div>
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
                      $design_no = !empty($item['design_no']) ? $item['design_no'] : '';
                      $barcode = !empty($item['barcode']) ? $item['barcode'] : '';
                      $description_parts = array_filter([$design_no, $barcode ? 'Barcode: ' . $barcode : '']);
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
              <div class="tm_left_footer"></div>
              <div class="tm_right_footer">
                <table class="tm_mb15">
                  <tbody>
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
        <a href="sale-order-process.php" class="tm_invoice_btn tm_color2"><span class="tm_btn_text">Back to Order List</span></a>
      </div>
    </div>
  </div>

  <script src="https://html.laralink.com/invoma/assets/js/jquery.min.js"></script>
  <script>
    (function() {
      if (window.attachEvent) {
        window.attachEvent('onload', function() { window.print(); });
      } else {
        window.addEventListener('load', function() { window.print(); });
      }
    })();
  </script>
</body>
</html>
