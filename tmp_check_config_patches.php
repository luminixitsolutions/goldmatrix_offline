<?php
$transcript = 'C:/Users/rajat/.cursor/projects/d-laragon-www-goldmatrix/agent-transcripts/1f692f20-29bc-47ed-8a98-8a0c65dd8c71/1f692f20-29bc-47ed-8a98-8a0c65dd8c71.jsonl';
$head = shell_exec('git -C ' . __DIR__ . ' show HEAD:config.php');
$idx = 0;
$fh = fopen($transcript, 'r');
while (($line = fgets($fh)) !== false) {
    $o = json_decode($line, true);
    if (!$o) continue;
    foreach ($o['message']['content'] ?? [] as $block) {
        if (($block['name'] ?? '') !== 'StrReplace') continue;
        $inp = $block['input'] ?? [];
        if (!str_ends_with(str_replace('\\', '/', $inp['path'] ?? ''), 'config.php')) continue;
        $old = str_replace("\r\n", "\n", $inp['old_string'] ?? '');
        $found = strpos(str_replace("\r\n", "\n", $head), $old) !== false;
        echo "#$idx found=" . ($found ? 'Y' : 'N') . " old_len=" . strlen($old) . " new_len=" . strlen($inp['new_string'] ?? '') . "\n";
        if (!$found && $idx <= 12) {
            file_put_contents(__DIR__ . "/tmp_failed_config_{$idx}_old.txt", $inp['old_string'] ?? '');
        }
        $idx++;
    }
}
fclose($fh);
