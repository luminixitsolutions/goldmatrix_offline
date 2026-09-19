<?php
/**
 * One-shot toast after redirect (e.g. branch DB missing on login). Include once per page; consumes session.
 */
if (session_status() !== PHP_SESSION_ACTIVE || empty($_SESSION['auragold_toast']) || !is_array($_SESSION['auragold_toast'])) {
    return;
}
$__auragold_toast = $_SESSION['auragold_toast'];
unset($_SESSION['auragold_toast']);
$__tmsg  = isset($__auragold_toast['message']) ? trim((string) $__auragold_toast['message']) : '';
$__ttype = isset($__auragold_toast['type']) ? strtolower(trim((string) $__auragold_toast['type'])) : 'danger';
if ($__tmsg === '') {
    return;
}
if ($__ttype !== 'warning' && $__ttype !== 'success' && $__ttype !== 'info') {
    $__ttype = 'danger';
}
$__tmsg_js = json_encode($__tmsg, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$__ttype_js = json_encode($__ttype, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>
<style>
.auragold-toast-host { position: fixed; top: 72px; right: 16px; z-index: 999999; max-width: min(420px, calc(100vw - 32px)); pointer-events: none; }
.auragold-toast {
    pointer-events: auto;
    padding: 14px 18px;
    border-radius: 10px;
    font-size: 13px;
    line-height: 1.45;
    font-weight: 500;
    box-shadow: 0 8px 28px rgba(0,0,0,0.18);
    border: 1px solid transparent;
    opacity: 0;
    transform: translateX(12px);
    transition: opacity 0.28s ease, transform 0.28s ease;
}
.auragold-toast--show { opacity: 1; transform: translateX(0); }
.auragold-toast--danger { background: #fef2f2; color: #991b1b; border-color: #fecaca; }
.auragold-toast--warning { background: #fffbeb; color: #92400e; border-color: #fde68a; }
.auragold-toast--success { background: #ecfdf5; color: #065f46; border-color: #a7f3d0; }
.auragold-toast--info { background: #eff6ff; color: #1e40af; border-color: #bfdbfe; }
</style>
<div class="auragold-toast-host" aria-live="polite"><div id="auragoldToastEl" class="auragold-toast auragold-toast--<?php echo htmlspecialchars($__ttype); ?>"></div></div>
<script>
(function () {
    var msg = <?php echo $__tmsg_js; ?>;
    var type = <?php echo $__ttype_js; ?>;
    var el = document.getElementById('auragoldToastEl');
    if (!el || !msg) return;
    el.textContent = msg;
    el.className = 'auragold-toast auragold-toast--' + type;
    requestAnimationFrame(function () { el.classList.add('auragold-toast--show'); });
    setTimeout(function () {
        el.classList.remove('auragold-toast--show');
        setTimeout(function () {
            var host = el.closest('.auragold-toast-host');
            if (host) host.remove();
        }, 320);
    }, 7000);
})();
</script>
