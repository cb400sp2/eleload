<?php

declare(strict_types=1);

namespace Eleload\Report;

use RuntimeException;

final class HtmlReporter
{
    public function __construct(private readonly string $templatePath)
    {
    }

    /**
     * @param array<string, mixed> $report
     */
    public function write(array $report, string $path): void
    {
        if (!is_file($this->templatePath)) {
            throw new RuntimeException("HTML template not found: {$this->templatePath}");
        }

        $this->ensureParentDirectory($path);

        $reportData = $report;
        ob_start();
        $report = $reportData;
        include $this->templatePath;
        $html = ob_get_clean();

        if (!is_string($html) || file_put_contents($path, $html) === false) {
            throw new RuntimeException("Failed to write HTML report: {$path}");
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

