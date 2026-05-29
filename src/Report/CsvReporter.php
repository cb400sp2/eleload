<?php

declare(strict_types=1);

namespace Eleload\Report;

use Eleload\LoadTesting\RequestResult;
use Eleload\LoadTesting\RunResult;
use RuntimeException;

final class CsvReporter
{
    public function write(RunResult $runResult, string $path): void
    {
        $this->ensureParentDirectory($path);

        $fp = fopen($path, 'wb');
        if ($fp === false) {
            throw new RuntimeException("Failed to open CSV report file: {$path}");
        }

        $ok = true;
        $ok = $ok && fputcsv($fp, [
            'request',
            'included_in_metrics',
            'success',
            'http_code',
            'error_no',
            'latency_ms',
            'download_bytes',
            'body_contains_expected',
            'error',
        ], ',', '"', '\\') !== false;

        foreach ($runResult->requestResults as $result) {
            $ok = $ok && fputcsv($fp, $this->toRow($runResult, $result), ',', '"', '\\') !== false;
        }

        fclose($fp);

        if (!$ok) {
            throw new RuntimeException("Failed to write CSV report: {$path}");
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

    /**
     * @return list<string|int|float>
     */
    private function toRow(RunResult $runResult, RequestResult $result): array
    {
        $isSuccess = $result->isSuccess($runResult->options->successStatusCodes);
        if ($isSuccess && $runResult->options->expectStatusCodes !== null) {
            $isSuccess = in_array($result->httpCode, $runResult->options->expectStatusCodes, true);
        }
        if ($isSuccess && $runResult->options->expectBodyContains !== null) {
            $isSuccess = $result->bodyContainsExpected === true;
        }

        return [
            $result->requestNumber,
            $result->includedInMetrics ? '1' : '0',
            $isSuccess ? '1' : '0',
            $result->httpCode,
            $result->errorNo,
            number_format($result->latencyMs, 2, '.', ''),
            number_format($result->downloadBytes, 0, '.', ''),
            $this->formatBodyContainsExpected($result->bodyContainsExpected),
            $result->error,
        ];
    }

    private function formatBodyContainsExpected(?bool $value): string
    {
        if ($value === null) {
            return '';
        }

        return $value ? '1' : '0';
    }
}
