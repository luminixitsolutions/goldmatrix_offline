<?php
$transcript = 'C:/Users/rajat/.cursor/projects/d-laragon-www-goldmatrix/agent-transcripts/1f692f20-29bc-47ed-8a98-8a0c65dd8c71/1f692f20-29bc-47ed-8a98-8a0c65dd8c71.jsonl';
$fh = fopen($transcript, 'r');
$idx = 0;
while (($line = fgets($fh)) !== false) {
    $o = json_decode($line, true);
    if (!$o) continue;
    foreach ($o['message']['content'] ?? [] as $block) {
        if (($block['name'] ?? '') !== 'StrReplace') continue;
        $inp = $block['input'] ?? [];
        if (!str_ends_with(str_replace('\\', '/', $inp['path'] ?? ''), 'config.php')) continue;
        if ($idx === 0) {
            file_put_contents(__DIR__ . '/tmp_config_op0_new.php', $inp['new_string']);
            file_put_contents(__DIR__ . '/tmp_config_op0_old.php', $inp['old_string']);
            echo "Wrote op0\n";
            exit(0);
        }
        $idx++;
    }
}
