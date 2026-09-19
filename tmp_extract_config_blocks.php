<?php
$transcript = 'C:/Users/rajat/.cursor/projects/d-laragon-www-goldmatrix/agent-transcripts/1f692f20-29bc-47ed-8a98-8a0c65dd8c71/1f692f20-29bc-47ed-8a98-8a0c65dd8c71.jsonl';
$fh = fopen($transcript, 'r');
$lineNo = 0;
while (($line = fgets($fh)) !== false) {
    $lineNo++;
    $o = json_decode($line, true);
    if (!$o) continue;
    foreach ($o['message']['content'] ?? [] as $block) {
        if (($block['name'] ?? '') !== 'StrReplace') continue;
        $inp = $block['input'] ?? [];
        if (!str_ends_with(str_replace('\\', '/', $inp['path'] ?? ''), 'config.php')) continue;
        $new = $inp['new_string'] ?? '';
        if (strpos($new, 'function auragold_barcode_label_storage_preset') !== false) {
            file_put_contents(__DIR__ . '/tmp_config_label_funcs.txt', $new);
            echo "Found at line $lineNo len=" . strlen($new) . "\n";
        }
        if (strpos($new, 'function getBarcodeSettingsForPrint') !== false) {
            file_put_contents(__DIR__ . '/tmp_config_get_for_print.txt', $new);
            echo "getBarcodeSettingsForPrint at line $lineNo\n";
        }
    }
}
fclose($fh);
