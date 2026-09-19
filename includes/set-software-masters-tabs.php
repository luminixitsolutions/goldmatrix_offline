<?php
/**
 * Horizontal sub-menu tabs for Masters-related Set Software pages.
 * Include near the top of set-software-main on masters.php, metal-rates-url.php, set-sale-percentage.php.
 */
$ss_masters_tabs_page = basename((string) ($_SERVER['PHP_SELF'] ?? ''));
$ss_masters_tabs = [
    ['href' => 'masters.php', 'label' => function_exists('auragold_t') ? (string) auragold_t('set_software.masters') : 'Masters', 'pages' => ['masters.php']],
    ['href' => 'metal-rates-url.php', 'label' => 'Metal Rates Url', 'pages' => ['metal-rates-url.php']],
    ['href' => 'set-sale-percentage.php', 'label' => 'Set Sale Percentage', 'pages' => ['set-sale-percentage.php']],
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
<nav class="ss-sub-tabs" aria-label="Masters sections">
    <?php foreach ($ss_masters_tabs as $tab) {
        $isActive = in_array($ss_masters_tabs_page, $tab['pages'], true);
        $label = $tab['label'];
        if ($label === '' || $label === 'set_software.masters') {
            $label = 'Masters';
        }
        ?>
        <a href="<?php echo htmlspecialchars($tab['href'], ENT_QUOTES, 'UTF-8'); ?>"
           class="<?php echo $isActive ? 'active' : ''; ?>"
           <?php echo $isActive ? 'aria-current="page"' : ''; ?>>
            <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
        </a>
    <?php } ?>
</nav>
