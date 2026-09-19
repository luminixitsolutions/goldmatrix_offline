<?php
$patchDir = __DIR__ . '/tmp_transcript_patches';
$baseFile = __DIR__ . '/set-software.php';
$outFile = __DIR__ . '/set-software.php';

$index = json_decode(file_get_contents($patchDir . '/index.json'), true);
$count = count($index);

$content = file_get_contents($baseFile);
$hadCrlf = strpos($content, "\r\n") !== false;

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
$failed = [];

for ($i = 0; $i < $count; $i++) {
    $op = json_decode(file_get_contents($patchDir . "/patch_{$i}.json"), true);
    $old = $op['old'] ?? '';
    $new = $op['new'] ?? '';
    if ($old === '') {
        $failed[] = "$i: empty old";
        continue;
    }
    // Skip if new already present and old gone (re-applied patch)
    if (strpos(normLf($content), normLf($old)) === false && strpos(normLf($content), normLf($new)) !== false) {
        $applied++;
        continue;
    }
    if (!applyPatch($content, $old, $new)) {
        $failed[] = "$i: not found (old_len=" . strlen($old) . ') ' . substr(str_replace("\n", ' ', $old), 0, 80);
    } else {
        $applied++;
    }
}

file_put_contents($outFile, $content);
echo "Applied $applied / $count patches\n";
echo 'Lines: ' . substr_count($content, "\n") . "\n";
if ($failed) {
    echo "\nFailed (" . count($failed) . "):\n";
    foreach ($failed as $f) {
        echo "  $f\n";
    }
}
