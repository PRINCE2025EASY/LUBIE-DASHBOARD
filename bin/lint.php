<?php

declare(strict_types=1);

// Syntax-check every PHP file in the project (excluding vendor). Exit code 1 on any error.
$root = dirname(__DIR__);
$bad = 0;
$n = 0;
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    $p = $f->getPathname();
    if ($f->getExtension() !== 'php' || preg_match('#[\\\\/](vendor|node_modules|\.git)[\\\\/]#', $p)) {
        continue;
    }
    $n++;
    $out = [];
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($p) . ' 2>&1', $out, $code);
    if ($code !== 0) {
        $bad++;
        echo implode(PHP_EOL, $out), PHP_EOL;
    }
}
echo "Checked {$n} PHP files, {$bad} with errors.", PHP_EOL;
exit($bad ? 1 : 0);
