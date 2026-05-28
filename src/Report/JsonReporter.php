<?php

declare(strict_types=1);

namespace Eleload\Report;

use RuntimeException;

final class JsonReporter
{
    /**
     * @param array<string, mixed> $report
     */
    public function write(array $report, string $path): void
    {
        $this->ensureParentDirectory($path);

        $json = json_encode(
            $report,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );

        if (file_put_contents($path, $json . PHP_EOL) === false) {
            throw new RuntimeException("Failed to write JSON report: {$path}");
        }
    }

    private function ensureParentDirectory(string $path): void
    {
        $dir = dirname($path);
        if ($dir === '.' || $dir === '') {
            return;
        }

        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException("Failed to create directory: {$dir}");
        }
    }
}

