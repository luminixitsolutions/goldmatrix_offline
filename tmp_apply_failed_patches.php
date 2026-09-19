<?php
$patchDir = __DIR__ . '/tmp_transcript_patches';
$failedIdx = [4, 6, 7, 9, 13, 15, 20, 21, 28, 35, 36, 41, 73, 75, 85, 86, 87, 89, 96, 101, 102, 108, 118, 137];
$content = file_get_contents(__DIR__ . '/set-software.php');

function normLf($s) {
    return str_replace("\r\n", "\n", $s);
}

function applyPatch(&$content, $old, $new) {
    $old = normLf($old);
    $new = normLf($new);
    $normContent = normLf($content);
    $pos = strpos($normContent, $old);
    if ($pos === false) {
        return false;
    }
    $normContent = substr_replace($normContent, $new, $pos, strlen($old));
    $content = (strpos($content, "\r\n") !== false)
        ? str_replace("\n", "\r\n", $normContent)
        : $normContent;
    return true;
}

$applied = 0;
foreach ($failedIdx as $i) {
    $op = json_decode(file_get_contents($patchDir . "/patch_{$i}.json"), true);
    if (applyPatch($content, $op['old'], $op['new'])) {
        echo "Applied patch $i\n";
        $applied++;
    } else {
        echo "Still failed $i\n";
    }
}

file_put_contents(__DIR__ . '/set-software.php', $content);
echo "Done: $applied applied\n";
