<?php
$transcript = 'C:/Users/rajat/.cursor/projects/d-laragon-www-goldmatrix/agent-transcripts/1f692f20-29bc-47ed-8a98-8a0c65dd8c71/1f692f20-29bc-47ed-8a98-8a0c65dd8c71.jsonl';
$files = ['config.php', 'set-software.php', 'barcode-print.php', 'ajax/save-barcode-settings.php'];

function normLf($s) { return str_replace("\r\n", "\n", $s); }

function applyPatch(&$content, $old, $new) {
    if ($old === '') return 0;
    $oldN = normLf($old);
    $newN = normLf($new);
    $normContent = normLf($content);
    if (strpos($normContent, $oldN) === false) {
        return (strpos($normContent, $newN) !== false) ? 2 : 0;
    }
    $normContent = substr_replace($normContent, $newN, strpos($normContent, $oldN), strlen($oldN));
    $content = (strpos($content, "\r\n") !== false) ? str_replace("\n", "\r\n", $normContent) : $normContent;
    return 1;
}

$opsByFile = array_fill_keys($files, []);
$fh = fopen($transcript, 'r');
while (($line = fgets($fh)) !== false) {
    $o = json_decode($line, true);
    if (!$o) continue;
    foreach ($o['message']['content'] ?? [] as $block) {
        if (!is_array($block) || ($block['type'] ?? '') !== 'tool_use' || ($block['name'] ?? '') !== 'StrReplace') continue;
        $inp = $block['input'] ?? [];
        $path = str_replace('\\', '/', $inp['path'] ?? '');
        foreach ($files as $rel) {
            if (str_ends_with($path, $rel)) {
                $opsByFile[$rel][] = ['old' => $inp['old_string'] ?? '', 'new' => $inp['new_string'] ?? ''];
            }
        }
    }
}
fclose($fh);

foreach ($files as $rel) {
    $path = __DIR__ . '/' . $rel;
    if (!is_file($path)) continue;
    $content = file_get_contents($path);
    $changed = 0;
    for ($pass = 1; $pass <= 30; $pass++) {
        $applied = 0;
        foreach ($opsByFile[$rel] as $op) {
            $r = applyPatch($content, $op['old'], $op['new']);
            if ($r === 1) $applied++;
        }
        $changed += $applied;
        if ($applied === 0) break;
    }
    file_put_contents($path, $content);
    $has82 = strpos($content, '82x38') !== false ? 'yes' : 'no';
    echo "$rel: passes changed=$changed, 82x38=$has82\n";
}
