<?php

declare(strict_types=1);

namespace Eleload\Report;

use RuntimeException;

final class MarkdownReporter
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
        $summary = $report['summary'];
        $requests = $summary['requests'];
        $throughput = $summary['throughput'];
        $latency = $summary['latency'];
        $testName = $report['meta']['test_name'] ?? null;

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
            '| URL | ' . $this->escape((string)$report['target']['url']) . ' |',
            '| Method | ' . $this->escape((string)$report['target']['method']) . ' |',
            '| Requests | ' . $requests['total'] . ' |',
            '| Concurrency | ' . $report['config']['concurrency'] . ' |',
            '| Duration | ' . number_format((float)$summary['duration_sec'], 3) . ' sec |',
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

        foreach ($summary['status_codes'] as $code => $item) {
            $lines[] = '| ' . $this->escape((string)$code) . ' | ' . $item['count'] . ' | ' . $this->percent((float)$item['rate']) . ' |';
        }

        if (!empty($report['thresholds']['checks'])) {
            $lines = array_merge($lines, [
                '',
                '## Thresholds',
                '',
                '| Check | Actual | Rule | Result |',
                '|---|---:|---:|---|',
            ]);

            foreach ($report['thresholds']['checks'] as $check) {
                $lines[] = '| ' . $this->escape((string)$check['name']) .
                    ' | ' . $this->number((float)$check['actual']) .
                    ' | ' . $check['operator'] . ' ' . $this->number((float)$check['threshold']) .
                    ' | ' . ($check['passed'] ? 'PASS' : 'FAIL') . ' |';
            }
        }

        if (!empty($report['errors'])) {
            $lines = array_merge($lines, [
                '',
                '## Errors',
                '',
                '| Request | HTTP Code | cURL errno | Latency | Error |',
                '|---:|---:|---:|---:|---|',
            ]);

            foreach (array_slice($report['errors'], 0, 10) as $error) {
                $lines[] = '| ' . $error['request'] .
                    ' | ' . $error['http_code'] .
                    ' | ' . $error['error_no'] .
                    ' | ' . $this->number((float)$error['latency_ms']) . ' ms' .
                    ' | ' . $this->escape((string)($error['error'] !== '' ? $error['error'] : '(no message)')) . ' |';
            }
        }

        return implode(PHP_EOL, $lines) . PHP_EOL;
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

    private function escape(string $value): string
    {
        return str_replace('|', '\\|', $value);
    }

    private function number(float $value): string
    {
        return number_format($value, 2);
    }

    private function percent(float $value): string
    {
        return number_format($value, 2) . '%';
    }
}
