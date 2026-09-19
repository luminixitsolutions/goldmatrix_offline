<?php
// One-off bulk replace helper (already applied). Do not run again unless reverting strings.
$dir = dirname(__DIR__);
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
$from = 'getList("SELECT id, name, code FROM tbl_branches WHERE status = 1 ORDER BY name ASC")';
$to   = 'getListMaster("SELECT id, name, code FROM tbl_branches WHERE status = 1 ORDER BY name ASC")';
foreach ($it as $f) {
    if ($f->getExtension() !== 'php' || strpos($f->getPathname(), DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR) !== false) {
        continue;
    }
    $path = $f->getPathname();
    $c = file_get_contents($path);
    $n = str_replace($from, $to, $c);
    if ($n !== $c) {
        file_put_contents($path, $n);
        echo $path, "\n";
    }
}
echo "done\n";
