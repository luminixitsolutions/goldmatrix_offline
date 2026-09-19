<?php
$t = 'C:/Users/rajat/.cursor/projects/d-laragon-www-goldmatrix/agent-transcripts/1f692f20-29bc-47ed-8a98-8a0c65dd8c71/1f692f20-29bc-47ed-8a98-8a0c65dd8c71.jsonl';
$fh = fopen($t, 'r');
$n = 0;
while (($line = fgets($fh)) !== false) {
    $n++;
    $o = json_decode($line, true);
    if (!$o) continue;
    foreach ($o['message']['content'] ?? [] as $b) {
        if (($b['name'] ?? '') !== 'StrReplace') continue;
        $p = str_replace('\\', '/', $b['input']['path'] ?? '');
        if (!str_ends_with($p, 'config.php')) continue;
        $new = $b['input']['new_string'] ?? '';
        if (strpos($new, 'auragold_82x38') !== false || strpos($new, '82x38_2box') !== false) {
            echo "L$n old_len=" . strlen($b['input']['old_string'] ?? '') . " new_len=" . strlen($new) . "\n";
            file_put_contents(__DIR__ . "/tmp_config_patch_L{$n}.txt", $new);
        }
    }
}
fclose($fh);
