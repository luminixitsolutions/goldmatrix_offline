<?php
$j = json_decode(file_get_contents(__DIR__ . '/tmp_transcript_patches/patch_0.json'), true);
$c = file_get_contents(__DIR__ . '/set-software.php');
echo strpos($c, $j['old']) !== false ? "FOUND\n" : "NOT FOUND\n";
echo 'old has crlf: ' . (strpos($j['old'], "\r\n") !== false ? 'yes' : 'no') . "\n";
echo 'file has crlf: ' . (strpos($c, "\r\n") !== false ? 'yes' : 'no') . "\n";
