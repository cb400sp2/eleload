<?php

declare(strict_types=1);

namespace Eleload\Compare;

use RuntimeException;

/**
 * Compares two eleload JSON reports and produces a structured comparison result.
 */
final class ReportComparator
{
    /**
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     * @return array<string, mixed>
     */
    public function compare(array $before, array $after): array
    {
        $metrics = [
            $this->compareMetric($before, $after, 'summary.throughput.rps', 'RPS', 'higher'),
            $this->compareMetric($before, $after, 'summary.throughput.tps', 'TPS', 'higher'),
            $this->compareMetric($before, $after, 'summary.latency.p95', 'p95 (ms)', 'lower'),
            $this->compareMetric($before, $after, 'summary.latency.p99', 'p99 (ms)', 'lower'),
            $this->compareMetric($before, $after, 'summary.requests.error_rate', 'Error Rate (%)', 'lower'),
        ];

        $counts = [
            'improved' => 0,
            'regressed' => 0,
            'unchanged' => 0,
        ];

        foreach ($metrics as $metric) {
            $counts[$metric['status']]++;
        }

        return [
            'meta' => [
                'tool' => 'eleload',
                'version' => '0.1.0',
            ],
            'before' => $this->buildInputSummary($before),
            'after' => $this->buildInputSummary($after),
            'metrics' => $metrics,
            'summary' => $counts,
        ];
    }

    /**
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     * @return array<string, mixed>
     */
    private function compareMetric(array $before, array $after, string $path, string $label, string $direction): array
    {
        $beforeValue = $this->extractFloat($before, $path);
        $afterValue = $this->extractFloat($after, $path);
        $delta = $afterValue - $beforeValue;
        $deltaRate = null;

        if ($beforeValue != 0.0) {
            $deltaRate = ($delta / $beforeValue) * 100.0;
        }

        $status = 'unchanged';
        if (abs($delta) > 0.000_001) {
            if ($direction === 'higher') {
                $status = $delta > 0.0 ? 'improved' : 'regressed';
            } else {
                $status = $delta < 0.0 ? 'improved' : 'regressed';
            }
        }

        return [
            'key' => $path,
            'label' => $label,
            'direction' => $direction,
            'before' => $this->round2($beforeValue),
            'after' => $this->round2($afterValue),
            'delta' => $this->round2($delta),
            'delta_rate' => $deltaRate === null ? null : $this->round2($deltaRate),
            'status' => $status,
        ];
    }

    /**
     * @param array<string, mixed> $report
     * @return array<string, mixed>
     */
    private function buildInputSummary(array $report): array
    {
        $target = $this->extractArray($report, 'target');
        $meta = $this->extractArray($report, 'meta');

        return [
            'url' => $this->extractString($target, 'url'),
            'method' => $this->extractString($target, 'method'),
            'test_name' => $this->extractOptionalString($meta, 'test_name'),
        ];
    }

    /**
     * @param array<string, mixed> $report
     */
    private function extractFloat(array $report, string $path): float
    {
        $value = $this->extractPath($report, $path);
        if (!is_int($value) && !is_float($value)) {
            throw new RuntimeException("Invalid report format at '{$path}': numeric value is required.");
        }

        return (float)$value;
    }

    /**
     * @param array<string, mixed> $report
     */
    private function extractPath(array $report, string $path): mixed
    {
        $segments = explode('.', $path);
        $current = $report;

        foreach ($segments as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                throw new RuntimeException("Invalid report format: missing '{$path}'.");
            }
            $current = $current[$segment];
        }

        return $current;
    }

    /**
     * @param array<string, mixed> $report
     * @return array<string, mixed>
     */
    private function extractArray(array $report, string $key): array
    {
        $value = $report[$key] ?? null;
        if (!is_array($value)) {
            throw new RuntimeException("Invalid report format: '{$key}' must be an object.");
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $object
     */
    private function extractString(array $object, string $key): string
    {
        $value = $object[$key] ?? null;
        if (!is_string($value) || $value === '') {
            throw new RuntimeException("Invalid report format: '{$key}' must be a non-empty string.");
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $object
     */
    private function extractOptionalString(array $object, string $key): ?string
    {
        if (!array_key_exists($key, $object) || $object[$key] === null) {
            return null;
        }

        if (!is_string($object[$key])) {
            throw new RuntimeException("Invalid report format: '{$key}' must be a string or null.");
        }

        return $object[$key];
    }

    /**
 * Rounds a value to 2 decimal places.
 */
private function round2(float $value): float
    {
        return round($value, 2);
    }
}

