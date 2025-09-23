<?php

$base = __DIR__.'/../storage/app/tmp/playwright';

function rrmdir(string $dir): void
{
    if (! is_dir($dir)) {
        return;
    }

    $items = scandir($dir);
    if ($items === false) {
        return;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $path = $dir.DIRECTORY_SEPARATOR.$item;
        if (is_dir($path)) {
            rrmdir($path);
        } else {
            @unlink($path);
        }
    }

    @rmdir($dir);
}

rrmdir($base);
if (! is_dir($base)) {
    mkdir($base, 0777, true);
}

echo "Playwright artefacts cleared at {$base}.".PHP_EOL;
