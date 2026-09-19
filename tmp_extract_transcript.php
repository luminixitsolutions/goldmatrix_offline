<?php
$path = 'C:/Users/rajat/.cursor/projects/d-laragon-www-goldmatrix/agent-transcripts/1f692f20-29bc-47ed-8a98-8a0c65dd8c71/1f692f20-29bc-47ed-8a98-8a0c65dd8c71.jsonl';
$baseFile = __DIR__ . '/set-software.php';
$outFile = __DIR__ . '/set-software.recovered.php';

$ops = [];
$fh = fopen($path, 'r');
while (($line = fgets($fh)) !== false) {
    $obj = json_decode($line, true);
    if (!$obj) {
        continue;
    }
    $content = $obj['message']['content'] ?? [];
    if (!is_array($content)) {
        continue;
    }
    foreach ($content as $block) {
        if (!is_array($block)) {
            continue;
        }
        if (($block['type'] ?? '') === 'tool_use' && ($block['name'] ?? '') === 'StrReplace') {
            $inp = $block['input'] ?? [];
            $p = str_replace('\\', '/', $inp['path'] ?? '');
            if (str_ends_with($p, 'set-software.php')) {
                $ops[] = [
                    'old' => $inp['old_string'] ?? '',
                    'new' => $inp['new_string'] ?? '',
                ];
            }
        }
    }
}
fclose($fh);

echo 'Found ' . count($ops) . " StrReplace ops\n";

$content = file_get_contents($baseFile);
$applied = 0;
$failed = [];

foreach ($ops as $i => $op) {
    if ($op['old'] === '') {
        $failed[] = $i . ': empty old_string';
        continue;
    }
    $pos = strpos($content, $op['old']);
    if ($pos === false) {
        $failed[] = $i . ': old_string not found (len=' . strlen($op['old']) . ') preview=' . substr(str_replace("\n", '\\n', $op['old']), 0, 80);
        continue;
    }
    $content = substr_replace($content, $op['new'], $pos, strlen($op['old']));
    $applied++;
}

file_put_contents($outFile, $content);
echo "Applied $applied / " . count($ops) . "\n";
echo "Output: $outFile\n";
echo "Lines: " . substr_count($content, "\n") . "\n";

if ($failed) {
    echo "\nFailed ops:\n";
    foreach (array_slice($failed, 0, 20) as $f) {
        echo "  $f\n";
    }
    if (count($failed) > 20) {
        echo '  ... and ' . (count($failed) - 20) . " more\n";
    }
}
