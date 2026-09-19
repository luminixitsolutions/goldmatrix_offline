<?php
/**
 * Payment entries as cards (shared markup + assets).
 */
require_once __DIR__ . '/auragold_payment_cards_assets.php';
?>
                                    <div class="pos-payment-cards-area">
                                        <div class="pos-payment-cards-scroll">
                                        <div id="paymentTableBody" class="pos-payment-cards d-flex flex-wrap">
                                            <div class="no-payment-row pos-payment-empty w-100 text-center text-muted py-3">No payment entries</div>
                                        </div>
                                        </div>
                                        <div id="paymentTableFooter" class="pos-payment-cards-footer" style="display: none;">
                                            <span style="color: #64748b;">Total amount:</span>
                                            <span id="paymentTotalAmount" style="font-weight: 700;">0.00</span>
                                            <span style="color: #64748b;">Total qty:</span>
                                            <span id="paymentTotalQuantity" style="font-weight: 700;">0.00</span>
                                        </div>
                                    </div>
