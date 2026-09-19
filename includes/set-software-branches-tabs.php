<?php
/**
 * Horizontal sub-menu tabs for Branches-related Set Software pages.
 * Include near the top of set-software-main on branches.php, set-software.php,
 * barcode-prefix-settings.php, menu-settings.php.
 */
$ss_branches_tabs_page = basename((string) ($_SERVER['PHP_SELF'] ?? ''));
$ss_t = static function (string $key, string $fallback): string {
    if (function_exists('auragold_t')) {
        $s = (string) auragold_t($key);
        if ($s !== '' && $s !== $key) {
            return $s;
        }
    }
    return $fallback;
};
$ss_branches_tabs = [
    ['href' => 'branches.php', 'label' => $ss_t('set_software.branches', 'Branches'), 'pages' => ['branches.php']],
    ['href' => 'set-software.php', 'label' => $ss_t('set_software.barcode_setting', 'Barcode Setting'), 'pages' => ['set-software.php']],
    ['href' => 'barcode-prefix-settings.php', 'label' => $ss_t('set_software.barcode_prefix_setting', 'Barcode Prefix Setting'), 'pages' => ['barcode-prefix-settings.php']],
    ['href' => 'menu-settings.php', 'label' => $ss_t('set_software.menu_setting', 'Menu Setting'), 'pages' => ['menu-settings.php']],
];
if (!isset($ss_sub_tabs_styles_printed)) {
    $ss_sub_tabs_styles_printed = true;
    ?>
<style>
.ss-sub-tabs {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px 4px;
    padding: 12px 20px 14px;
    margin: 0;
    border-bottom: 1px solid #e2e8f0;
    background: #fff;
}
.ss-sub-tabs a {
    display: inline-block;
    padding: 8px 16px;
    font-size: 13px;
    font-weight: 500;
    line-height: 1.3;
    color: #11294b;
    text-decoration: none;
    border-radius: 8px;
    transition: background 0.15s ease, color 0.15s ease;
    white-space: nowrap;
}
.ss-sub-tabs a:hover {
    color: #11294b;
    background: rgba(197, 168, 100, 0.18);
    text-decoration: none;
}
.ss-sub-tabs a.active {
    color: #11294b;
    background: #c5a864;
    font-weight: 600;
}
.ss-sub-tabs a.active:hover {
    color: #11294b;
    background: #d4b87a;
}
</style>
    <?php
}
?>
<nav class="ss-sub-tabs" aria-label="Branches sections">
    <?php foreach ($ss_branches_tabs as $tab) {
        $isActive = in_array($ss_branches_tabs_page, $tab['pages'], true);
        ?>
        <a href="<?php echo htmlspecialchars($tab['href'], ENT_QUOTES, 'UTF-8'); ?>"
           class="<?php echo $isActive ? 'active' : ''; ?>"
           <?php echo $isActive ? 'aria-current="page"' : ''; ?>>
            <?php echo htmlspecialchars($tab['label'], ENT_QUOTES, 'UTF-8'); ?>
        </a>
    <?php } ?>
</nav>
