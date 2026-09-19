<?php
$transcript = 'C:/Users/rajat/.cursor/projects/d-laragon-www-goldmatrix/agent-transcripts/1f692f20-29bc-47ed-8a98-8a0c65dd8c71/1f692f20-29bc-47ed-8a98-8a0c65dd8c71.jsonl';
$fullPath = __DIR__ . '/config.php';

function normLf($s) { return str_replace("\r\n", "\n", $s); }
function applyPatch(&$content, $old, $new) {
    if ($old === '') return false;
    $oldN = normLf($old);
    $newN = normLf($new);
    $normContent = normLf($content);
    $pos = strpos($normContent, $oldN);
    if ($pos === false) return false;
    $normContent = substr_replace($normContent, $newN, $pos, strlen($oldN));
    $content = (strpos($content, "\r\n") !== false) ? str_replace("\n", "\r\n", $normContent) : $normContent;
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
        $path = str_replace('\\', '/', $inp['path'] ?? '');
        if (!str_ends_with($path, 'config.php')) continue;
        $ops[] = ['old' => $inp['old_string'] ?? '', 'new' => $inp['new_string'] ?? ''];
    }
}
fclose($fh);

$content = file_get_contents($fullPath);
$round = 0;
do {
    $round++;
    $applied = 0;
    foreach ($ops as $i => $op) {
        if (applyPatch($content, $op['old'], $op['new'])) {
            $applied++;
            echo "Applied op #$i round $round\n";
        }
    }
} while ($applied > 0 && $round < 15);

file_put_contents($fullPath, $content);
echo "Done rounds=$round has82=" . (strpos($content, 'auragold_82x38_2box_layout') !== false ? 'yes' : 'no') . "\n";
