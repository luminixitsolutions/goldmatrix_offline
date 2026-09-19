<?php
$config = file_get_contents(__DIR__ . '/config.php');
$block = file_get_contents(__DIR__ . '/includes/config_82x38_recovered.php');
$marker = "/**\n * True when barcode print uses two tags on one 120×50 mm sticker.\n */";
if (strpos($config, 'function auragold_82x38_2box_layout') !== false) {
    echo "Already has 82x38 block\n";
    exit(0);
}
if (strpos($config, $marker) === false) {
    die("Marker not found\n");
}
$config = str_replace($marker, $block . "\n" . $marker, $config);
file_put_contents(__DIR__ . '/config.php', $config);
echo "Inserted 82x38 block\n";
