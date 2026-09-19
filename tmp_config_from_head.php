<?php
/**
 * Build config.php by replaying transcript patches from git HEAD baseline.
 */
$transcript = 'C:/Users/rajat/.cursor/projects/d-laragon-www-goldmatrix/agent-transcripts/1f692f20-29bc-47ed-8a98-8a0c65dd8c71/1f692f20-29bc-47ed-8a98-8a0c65dd8c71.jsonl';
$root = __DIR__;

function normLf($s) { return str_replace("\r\n", "\n", $s); }
function applyPatch(&$content, $old, $new) {
    if ($old === '') return 0;
    $oldN = normLf($old);
    $newN = normLf($new);
    $c = normLf($content);
    if (strpos($c, $oldN) === false) {
        return (strpos($c, $newN) !== false) ? 2 : 0;
    }
    $c = substr_replace($c, $newN, strpos($c, $oldN), strlen($oldN));
    $content = (strpos($content, "\r\n") !== false) ? str_replace("\n", "\r\n", $c) : $c;
    return 1;
}

// Start from committed config.php
$content = shell_exec('git -C ' . escapeshellarg($root) . ' show HEAD:config.php');
if (!is_string($content) || $content === '') {
    die("Could not read HEAD config.php\n");
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

$applied = 0; $already = 0; $failed = [];
foreach ($ops as $i => $op) {
    $r = applyPatch($content, $op['old'], $op['new']);
    if ($r === 1) $applied++;
    elseif ($r === 2) $already++;
    else $failed[] = $i;
}

file_put_contents($root . '/config.php', $content);
echo "From HEAD: applied=$applied already=$already failed=" . count($failed) . "\n";
echo "has getBarcodeSettingsForPrint=" . (strpos($content, 'function getBarcodeSettingsForPrint') !== false ? 'yes' : 'no') . "\n";
echo "has auragold_82x38_2box_layout=" . (strpos($content, 'function auragold_82x38_2box_layout') !== false ? 'yes' : 'no') . "\n";
if ($failed) {
    echo "Failed indices: " . implode(',', array_slice($failed, 0, 40)) . "\n";
}
