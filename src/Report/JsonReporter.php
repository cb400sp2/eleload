<?php

declare(strict_types=1);

namespace Eleload\Report;

use RuntimeException;
use stdClass;

/**
 * Serialises a report array to a pretty-printed JSON file.
 */
final class JsonReporter implements ReportWriterInterface
{
    /**
     * @param array<string, mixed> $report
     */
    public function write(array $report, string $path): void
    {
        $this->ensureParentDirectory($path);
        $normalized = $this->normalizeReport($report);

        $json = json_encode(
            $normalized,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );

        if (file_put_contents($path, $json . PHP_EOL) === false) {
            throw new RuntimeException("Failed to write JSON report: {$path}");
        }
    }

    /**
     * Creates parent directories for $path if they do not already exist.
     *
     * @throws \RuntimeException
     */
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

    /**
     * @param array<string, mixed> $report
     * @return array<string, mixed>
     */
    private function normalizeReport(array $report): array
    {
        if (
            isset($report['summary']) &&
            is_array($report['summary']) &&
            isset($report['summary']['status_codes']) &&
            is_array($report['summary']['status_codes'])
        ) {
            $statusCodeObject = new stdClass();

            foreach ($report['summary']['status_codes'] as $code => $stats) {
                $statusCodeObject->{(string)$code} = $stats;
            }

            $report['summary']['status_codes'] = $statusCodeObject;
        }

        return $report;
    }
}
