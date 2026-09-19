<?php
/**
 * Recover file contents by replaying StrReplace ops from agent transcript (local only).
 */
$transcript = 'C:/Users/rajat/.cursor/projects/d-laragon-www-goldmatrix/agent-transcripts/1f692f20-29bc-47ed-8a98-8a0c65dd8c71/1f692f20-29bc-47ed-8a98-8a0c65dd8c71.jsonl';
$root = __DIR__;
$targetFiles = [
    'set-software.php',
    'config.php',
    'barcode-print.php',
    'ajax/save-barcode-settings.php',
];

function normLf($s) {
    return str_replace("\r\n", "\n", $s);
}

function applyPatch(&$content, $old, $new, &$failReason) {
    if ($old === '') {
        $failReason = 'empty old_string';
        return false;
    }
    $old = normLf($old);
    $new = normLf($new);
    $normContent = normLf($content);
    if (strpos($normContent, $old) !== false) {
        $normContent = substr_replace($normContent, $new, strpos($normContent, $old), strlen($old));
        $content = (strpos($content, "\r\n") !== false)
            ? str_replace("\n", "\r\n", $normContent)
            : $normContent;
        return true;
    }
    // Already applied?
    if (strpos($normContent, $new) !== false) {
        return true;
    }
    $failReason = 'old_string not found (len ' . strlen($old) . ')';
    return false;
}

$opsByFile = [];
foreach ($targetFiles as $f) {
    $opsByFile[$f] = [];
}

$fh = fopen($transcript, 'r');
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
        if (($block['type'] ?? '') !== 'tool_use' || ($block['name'] ?? '') !== 'StrReplace') {
            continue;
        }
        $inp = $block['input'] ?? [];
        $path = str_replace('\\', '/', $inp['path'] ?? '');
        $rel = null;
        foreach ($targetFiles as $tf) {
            if (str_ends_with($path, $tf)) {
                $rel = $tf;
                break;
            }
        }
        if ($rel === null) {
            continue;
        }
        $opsByFile[$rel][] = [
            'line' => $lineNo,
            'old' => $inp['old_string'] ?? '',
            'new' => $inp['new_string'] ?? '',
        ];
    }
}
fclose($fh);

foreach ($targetFiles as $rel) {
    $fullPath = $root . '/' . $rel;
    if (!is_file($fullPath)) {
        echo "SKIP missing: $rel\n";
        continue;
    }
    $content = file_get_contents($fullPath);
    $ops = $opsByFile[$rel];
    $applied = 0;
    $skipped = 0;
    $failed = [];
    foreach ($ops as $i => $op) {
        $reason = '';
        if (applyPatch($content, $op['old'], $op['new'], $reason)) {
            if ($reason === '') {
                $applied++;
            } else {
                $skipped++;
            }
        } else {
            if (normLf($op['new']) !== '' && strpos(normLf($content), normLf($op['new'])) !== false) {
                $skipped++;
            } else {
                $failed[] = "#$i@L{$op['line']}: $reason";
            }
        }
    }
    file_put_contents($fullPath, $content);
    echo "$rel: " . count($ops) . " ops, applied~$applied, skipped~$skipped, failed=" . count($failed) . "\n";
    foreach (array_slice($failed, 0, 15) as $f) {
        echo "  FAIL $f\n";
    }
    if (count($failed) > 15) {
        echo '  ... and ' . (count($failed) - 15) . " more\n";
    }
}
