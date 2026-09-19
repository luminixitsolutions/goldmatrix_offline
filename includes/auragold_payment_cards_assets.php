<?php
/** Shared payment card UI (CSS + JS). Cache-bust JS when file changes. */
$__auragold_pc_js_ver = (int) @filemtime(__DIR__ . '/../assets/js/auragold-payment-cards.js');
if ($__auragold_pc_js_ver <= 0) {
    $__auragold_pc_js_ver = time();
}
?>
<link rel="stylesheet" href="assets/css/auragold-payment-cards.css">
<script src="assets/js/auragold-payment-cards.js?v=<?php echo $__auragold_pc_js_ver; ?>"></script>
