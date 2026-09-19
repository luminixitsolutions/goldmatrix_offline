<?php
declare(strict_types=1);

$files = [
    'https://whitebooks.in/openapi/gst.json' => 'C:/laragon/www/goldmatrix/tmp_wb_gst.json',
    'https://whitebooks.in/openapi/einvoice.json' => 'C:/laragon/www/goldmatrix/tmp_wb_einvoice.json',
    'https://whitebooks.in/openapi/eway.json' => 'C:/laragon/www/goldmatrix/tmp_wb_eway.json',
];

foreach ($files as $url => $path) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);
    $data = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($data === false || $code !== 200) {
        echo "FAIL {$url} http={$code} err={$err}\n";
        continue;
    }
    file_put_contents($path, $data);
    json_decode($data);
    echo basename($path) . ' bytes=' . strlen($data) . ' json=' . json_last_error_msg() . PHP_EOL;
}
