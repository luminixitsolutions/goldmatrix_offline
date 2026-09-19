<?php
$path = 'C:/Users/rajat/.cursor/projects/d-laragon-www-goldmatrix/agent-transcripts/1f692f20-29bc-47ed-8a98-8a0c65dd8c71/1f692f20-29bc-47ed-8a98-8a0c65dd8c71.jsonl';
$outDir = __DIR__ . '/tmp_transcript_patches';
if (!is_dir($outDir)) {
    mkdir($outDir, 0777, true);
}

$ops = [];
$fh = fopen($path, 'r');
$lineNo = 0;
while (($line = fgets($fh)) !== false) {
    $lineNo++;
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
                    'line' => $lineNo,
                    'old' => $inp['old_string'] ?? '',
                    'new' => $inp['new_string'] ?? '',
                ];
            }
        }
    }
}
fclose($fh);

file_put_contents($outDir . '/index.json', json_encode(array_map(function ($op, $i) {
    return [
        'idx' => $i,
        'line' => $op['line'],
        'old_len' => strlen($op['old']),
        'new_len' => strlen($op['new']),
        'old_preview' => substr(str_replace("\n", ' ', $op['old']), 0, 100),
    ];
}, $ops, array_keys($ops)), JSON_PRETTY_PRINT));

foreach ($ops as $i => $op) {
    file_put_contents($outDir . "/patch_{$i}.json", json_encode($op, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

echo 'Wrote ' . count($ops) . " patches to $outDir\n";
