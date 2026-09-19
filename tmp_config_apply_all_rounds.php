<?php
$transcript = 'C:/Users/rajat/.cursor/projects/d-laragon-www-goldmatrix/agent-transcripts/1f692f20-29bc-47ed-8a98-8a0c65dd8c71/1f692f20-29bc-47ed-8a98-8a0c65dd8c71.jsonl';
$content = file_get_contents(__DIR__ . '/config.php');

function normLf($s) { return str_replace("\r\n", "\n", $s); }
function applyPatch(&$content, $old, $new) {
    if ($old === '') return false;
    $oldN = normLf($old); $newN = normLf($new); $c = normLf($content);
    if (strpos($c, $oldN) === false) return false;
    $c = substr_replace($c, $newN, strpos($c, $oldN), strlen($oldN));
    $content = (strpos($content, "\r\n") !== false) ? str_replace("\n", "\r\n", $c) : $c;
    return true;
}

$ops = [];
$fh = fopen($transcript, 'r');
while (($line = fgets($fh)) !== false) {
    $o = json_decode($line, true);
    if (!$o) continue;
    foreach ($o['message']['content'] ?? [] as $block) {
        if (!is_array($block) || ($block['name'] ?? '') !== 'StrReplace') continue;
        $inp = $block['input'] ?? [];
        if (!str_ends_with(str_replace('\\', '/', $inp['path'] ?? ''), 'config.php')) continue;
        $ops[] = ['old' => $inp['old_string'] ?? '', 'new' => $inp['new_string'] ?? ''];
    }
}
fclose($fh);

$total = 0;
for ($pass = 1; $pass <= 25; $pass++) {
    $applied = 0;
    foreach ($ops as $i => $op) {
        if (applyPatch($content, $op['old'], $op['new'])) {
            $applied++;
            echo "pass $pass op #$i\n";
        }
    }
    $total += $applied;
    if ($applied === 0) break;
}
file_put_contents(__DIR__ . '/config.php', $content);
echo "total=$total has82=" . (strpos($content, 'auragold_82x38_2box_layout') !== false ? 'yes' : 'no') . "\n";
echo "hasLabel=" . (strpos($content, 'auragold_barcode_label_storage_preset') !== false ? 'yes' : 'no') . "\n";
