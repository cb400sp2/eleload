#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Build script that packages eleload as a self-contained PHAR.
 */

$rootDir = dirname(__DIR__);
$buildDir = $rootDir . '/build';
$pharPath = $buildDir . '/eleload.phar';

if (!is_dir($buildDir) && !mkdir($buildDir, 0775, true) && !is_dir($buildDir)) {
    fwrite(STDERR, "Failed to create build directory: {$buildDir}\n");
    exit(1);
}

if (file_exists($pharPath)) {
    unlink($pharPath);
}

if (ini_get('phar.readonly')) {
    fwrite(STDERR, "phar.readonly must be 0. Re-run with: php -d phar.readonly=0 bin/build-phar.php\n");
    exit(1);
}

$phar = new Phar($pharPath, 0, 'eleload.phar');
$phar->startBuffering();

$phar->buildFromDirectory($rootDir, '#^' . preg_quote($rootDir, '#') . '/(src|vendor|bin/eleload$|composer\.json$)#');

$stub = <<<'STUB'
#!/usr/bin/env php
<?php
Phar::mapPhar('eleload.phar');
require 'phar://eleload.phar/bin/eleload';
__HALT_COMPILER();
STUB;

$phar->setStub($stub);
$phar->stopBuffering();

chmod($pharPath, 0755);

echo "Built: {$pharPath} (" . number_format(filesize($pharPath)) . " bytes)\n";
