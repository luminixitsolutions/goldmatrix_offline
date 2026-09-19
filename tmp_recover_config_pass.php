<?php
$transcript = 'C:/Users/rajat/.cursor/projects/d-laragon-www-goldmatrix/agent-transcripts/1f692f20-29bc-47ed-8a98-8a0c65dd8c71/1f692f20-29bc-47ed-8a98-8a0c65dd8c71.jsonl';
$fullPath = __DIR__ . '/config.php';

function normLf($s) { return str_replace("\r\n", "\n", $s); }

function applyPatch(&$content, $old, $new) {
    if ($old === '') return false;
    $old = normLf($old);
    $new = normLf($new);
    $normContent = normLf($content);
    $pos = strpos($normContent, $old);
    if ($pos === false) {
        if (strpos($normContent, $new) !== false) return true;
        return false;
    }
    $normContent = substr_replace($normContent, $new, $pos, strlen($old));
    $content = (strpos($content, "\r\n") !== false) ? str_replace("\n", "\r\n", $normContent) : $normContent;
    return true;
}

$ops = [];
$fh = fopen($transcript, 'r');
while (($line = fgets($fh)) !== false) {
    $obj = json_decode($line, true);
    if (!$obj) continue;
    foreach ($obj['message']['content'] ?? [] as $block) {
        if (!is_array($block) || ($block['type'] ?? '') !== 'tool_use' || ($block['name'] ?? '') !== 'StrReplace') continue;
        $inp = $block['input'] ?? [];
        $path = str_replace('\\', '/', $inp['path'] ?? '');
        if (!str_ends_with($path, 'config.php')) continue;
        $ops[] = ['old' => $inp['old_string'] ?? '', 'new' => $inp['new_string'] ?? ''];
    }
}
fclose($fh);

$content = file_get_contents($fullPath);
$totalApplied = 0;
for ($pass = 1; $pass <= 20; $pass++) {
    $applied = 0;
    foreach ($ops as $op) {
        if (applyPatch($content, $op['old'], $op['new'])) {
            if (normLf($op['old']) !== '' && strpos(normLf($content), normLf($op['old'])) === false) {
                $applied++;
            }
        }
    }
    $totalApplied += $applied;
    echo "Pass $pass: newly applied $applied\n";
    if ($applied === 0) break;
}
file_put_contents($fullPath, $content);
echo "Total newly applied: $totalApplied\n";
echo (strpos($content, 'auragold_82x38_2box_layout') !== false ? "HAS 82x38 layout\n" : "MISSING 82x38 layout\n");
