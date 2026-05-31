<?php

declare(strict_types=1);

namespace Eleload\Report;

use RuntimeException;

/**
 * Renders a load-test report to a Markdown file.
 */
final class MarkdownReporter implements ReportWriterInterface
{
    /**
     * @param array<string, mixed> $report
     */
    public function write(array $report, string $path): void
    {
        $this->ensureParentDirectory($path);

        if (file_put_contents($path, $this->render($report)) === false) {
            throw new RuntimeException("Failed to write Markdown report: {$path}");
        }
    }

    /**
     * @param array<string, mixed> $report
     */
    public function render(array $report): string
    {
        /** @var array<string, mixed> $summary */
        $summary = $report['summary'];
        /** @var array{total: int, success: int, failed: int, success_rate: float, error_rate: float} $requests */
        $requests = $summary['requests'];
        /** @var array{rps: float, tps: float, tps_rps_rate: float} $throughput */
        $throughput = $summary['throughput'];
        /** @var array{p50: float, p95: float, p99: float, min: float, avg: float, max: float} $latency */
        $latency = $summary['latency'];
        /** @var float $durationSec */
        $durationSec = $summary['duration_sec'];
        /** @var array<string, array{count: int, rate: float}> $statusCodes */
        $statusCodes = is_array($summary['status_codes'] ?? null) ? $summary['status_codes'] : [];
        /** @var array{url: string, method: string} $reportTarget */
        $reportTarget = $report['target'];
        /** @var array{concurrency: int, success_status?: mixed} $reportConfig */
        $reportConfig = $report['config'];
        /** @var array{test_name?: string} $reportMeta */
        $reportMeta = is_array($report['meta'] ?? null) ? $report['meta'] : [];
        /** @var array{checks?: list<array{name: string, actual: float, threshold: float, operator: string, passed: bool}>} $reportThresholds */
        $reportThresholds = is_array($report['thresholds'] ?? null) ? $report['thresholds'] : [];
        /** @var list<array{request: int, http_code: int, error_no: int, latency_ms: float, error: string}> $reportErrors */
        $reportErrors = is_array($report['errors'] ?? null) ? array_values($report['errors']) : [];
        $testName = $reportMeta['test_name'] ?? null;
        $successStatusLabel = $this->formatSuccessStatus($reportConfig['success_status'] ?? null);

        $lines = [
            '# Eleload Report',
            '',
        ];

        if (is_string($testName) && $testName !== '') {
            $lines[] = '**Test Name:** ' . $this->escape($testName);
            $lines[] = '';
        }

        $lines = array_merge($lines, [
            '## Target',
            '',
            '| Field | Value |',
            '|---|---:|',
            '| URL | ' . $this->escape($reportTarget['url']) . ' |',
            '| Method | ' . $this->escape($reportTarget['method']) . ' |',
            '| Success Status | ' . $this->escape($successStatusLabel) . ' |',
            '| Requests | ' . $requests['total'] . ' |',
            '| Concurrency | ' . $reportConfig['concurrency'] . ' |',
            '| Duration | ' . number_format($durationSec, 3) . ' sec |',
            '',
            '## Summary',
            '',
            '| Metric | Value |',
            '|---|---:|',
            '| Success | ' . $requests['success'] . ' / ' . $requests['total'] . ' (' . $this->percent((float)$requests['success_rate']) . ') |',
            '| Failed | ' . $requests['failed'] . ' / ' . $requests['total'] . ' (' . $this->percent((float)$requests['error_rate']) . ') |',
            '| Error Rate | ' . $this->percent((float)$requests['error_rate']) . ' |',
            '| RPS | ' . $this->number((float)$throughput['rps']) . ' req/sec |',
            '| TPS | ' . $this->number((float)$throughput['tps']) . ' tx/sec |',
            '| TPS / RPS Rate | ' . $this->percent((float)$throughput['tps_rps_rate']) . ' |',
            '| p95 | ' . $this->number((float)$latency['p95']) . ' ms |',
            '| p99 | ' . $this->number((float)$latency['p99']) . ' ms |',
            '',
            '## Latency',
            '',
            '| Metric | Value |',
            '|---|---:|',
            '| min | ' . $this->number((float)$latency['min']) . ' ms |',
            '| avg | ' . $this->number((float)$latency['avg']) . ' ms |',
            '| p50 | ' . $this->number((float)$latency['p50']) . ' ms |',
            '| p95 | ' . $this->number((float)$latency['p95']) . ' ms |',
            '| p99 | ' . $this->number((float)$latency['p99']) . ' ms |',
            '| max | ' . $this->number((float)$latency['max']) . ' ms |',
            '',
            '## Status Codes',
            '',
            '| Code | Count | Rate |',
            '|---|---:|---:|',
        ]);

        foreach ($statusCodes as $code => $item) {
            $lines[] = '| ' . $this->escape((string)$code) . ' | ' . $item['count'] . ' | ' . $this->percent((float)$item['rate']) . ' |';
        }

        if (!empty($reportThresholds['checks'])) {
            $lines = array_merge($lines, [
                '',
                '## Thresholds',
                '',
                '| Check | Actual | Rule | Result |',
                '|---|---:|---:|---|',
            ]);

            foreach ($reportThresholds['checks'] as $check) {
                $lines[] = '| ' . $this->escape($check['name']) .
                    ' | ' . $this->number($check['actual']) .
                    ' | ' . $check['operator'] . ' ' . $this->number($check['threshold']) .
                    ' | ' . ($check['passed'] ? 'PASS' : 'FAIL') . ' |';
            }
        }

        if ($reportErrors !== []) {
            $lines = array_merge($lines, [
                '',
                '## Errors',
                '',
                '| Request | HTTP Code | cURL errno | Latency | Error |',
                '|---:|---:|---:|---:|---|',
            ]);

            foreach (array_slice($reportErrors, 0, 10) as $error) {
                $lines[] = '| ' . $error['request'] .
                    ' | ' . $error['http_code'] .
                    ' | ' . $error['error_no'] .
                    ' | ' . $this->number((float)$error['latency_ms']) . ' ms' .
                    ' | ' . $this->escape($error['error'] !== '' ? $error['error'] : '(no message)') . ' |';
            }
        }

        return implode(PHP_EOL, $lines) . PHP_EOL;
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
     * Escapes pipe characters in Markdown table cells.
     */
    private function escape(string $value): string
    {
        return str_replace('|', '\\|', $value);
    }

    /**
     * Formats a float as a fixed-precision decimal string.
     */
    private function number(float $value): string
    {
        return number_format($value, 2);
    }

    /**
     * Formats a float as a percentage string (e.g. "12.34%").
     */
    private function percent(float $value): string
    {
        return number_format($value, 2) . '%';
    }

    /**
     * Returns a human-readable label for the configured success status codes.
     */
    private function formatSuccessStatus(mixed $value): string
    {
        if (!is_array($value) || $value === []) {
            return '2xx,3xx (default)';
        }

        return implode(',', array_map(static fn (mixed $code): string => is_scalar($code) ? (string)$code : '', $value));
    }
}
