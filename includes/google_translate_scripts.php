<?php
/**
 * Google Translate widget + banner suppression (single include).
 * Loaded from footer-script.php and dashboard_shell_bottom.php only.
 * Requires #google_translate_element in sidebar header.
 */
if (!empty($GLOBALS['auragold_google_translate_scripts_printed'])) {
    return;
}
$GLOBALS['auragold_google_translate_scripts_printed'] = true;

$auragold_gt_locale = 'en';
if (function_exists('auragold_get_locale')) {
    $auragold_gt_locale = trim((string) auragold_get_locale());
}
if ($auragold_gt_locale === '' || preg_match('/^en(\-[A-Za-z0-9]+)*$/i', $auragold_gt_locale)) {
    $auragold_gt_locale = 'en';
}
$auragold_gt_locale = preg_replace('/[^a-zA-Z0-9\-]/', '', $auragold_gt_locale);
if ($auragold_gt_locale === '') {
    $auragold_gt_locale = 'en';
}
$auragold_gt_allowed = 'en,hi,mr,gu,ta,te,kn,bn,pa,ar';
$auragold_gt_css = 'assets/css/auragold-google-translate.css?v=3';
$auragold_gt_js  = 'assets/js/auragold-google-translate.js?v=3';
if (function_exists('auragold_asset_url')) {
    $auragold_gt_css = htmlspecialchars(auragold_asset_url('assets/css/auragold-google-translate.css'), ENT_QUOTES, 'UTF-8') . '?v=3';
    $auragold_gt_js  = htmlspecialchars(auragold_asset_url('assets/js/auragold-google-translate.js'), ENT_QUOTES, 'UTF-8') . '?v=3';
}
?>
<link rel="stylesheet" href="<?php echo $auragold_gt_css; ?>">
<script>
window.AURAGOLD_GT = window.AURAGOLD_GT || {
    locale: <?php echo json_encode($auragold_gt_locale, JSON_UNESCAPED_UNICODE); ?>,
    allowed: <?php echo json_encode(explode(',', $auragold_gt_allowed), JSON_UNESCAPED_UNICODE); ?>,
    saveUrl: 'ajax/save-app-locale.php'
};
</script>
<script src="<?php echo $auragold_gt_js; ?>"></script>
<script src="https://translate.google.com/translate_a/element.js?cb=googleTranslateElementInit"></script>
