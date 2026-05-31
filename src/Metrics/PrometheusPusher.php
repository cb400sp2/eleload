<?php

declare(strict_types=1);

namespace Eleload\Metrics;

/**
 * Pushes load-test metrics to a Prometheus Pushgateway using the text exposition format.
 *
 * Export errors are silently swallowed so they never affect load-test exit codes.
 *
 * @see https://github.com/prometheus/pushgateway
 * @see https://prometheus.io/docs/instrumenting/exposition_formats/
 */
final class PrometheusPusher
{
    public function __construct(private readonly string $gatewayUrl)
    {
    }

    /**
     * Push metrics derived from a StatisticsCalculator::summarize() report array.
     *
     * @param array<string, mixed> $report
     * @param string               $jobName Prometheus job label (default: eleload)
     */
    public function push(array $report, string $jobName = 'eleload'): void
    {
        $body = $this->buildBody($report, $jobName);
        $url  = rtrim($this->gatewayUrl, '/') . '/metrics/job/' . rawurlencode($jobName);

        $ctx = stream_context_create([
            'http' => [
                'method'        => 'PUT',
                'header'        => "Content-Type: text/plain; version=0.0.4\r\nContent-Length: " . strlen($body) . "\r\n",
                'content'       => $body,
                'timeout'       => 5,
                'ignore_errors' => true,
            ],
        ]);

        @file_get_contents($url, false, $ctx);
    }

    /**
     * Build the Prometheus text exposition body without sending it.
     * Exposed separately to allow unit-testing the output format.
     *
     * @param array<string, mixed> $report
     * @param string               $jobName
     */
    public function buildBody(array $report, string $jobName = 'eleload'): string
    {
        /** @var array<string, mixed> $summary */
        $summary    = is_array($report['summary'] ?? null) ? $report['summary'] : [];
        /** @var array{total?: int, success?: int, error_rate?: float} $requests */
        $requests   = is_array($summary['requests'] ?? null) ? $summary['requests'] : [];
        /** @var array{rps?: float, tps?: float} $throughput */
        $throughput = is_array($summary['throughput'] ?? null) ? $summary['throughput'] : [];
        /** @var array{p50?: float, p95?: float, p99?: float} $latency */
        $latency    = is_array($summary['latency'] ?? null) ? $summary['latency'] : [];
        /** @var float $durationSec */
        $durationSec  = $summary['duration_sec'] ?? 0.0;
        /** @var array{test_name?: string} $reportMeta */
        $reportMeta   = is_array($report['meta'] ?? null) ? $report['meta'] : [];
        /** @var array{url?: string} $reportTarget */
        $reportTarget = is_array($report['target'] ?? null) ? $report['target'] : [];
        $testName   = $reportMeta['test_name'] ?? '';
        $url        = $reportTarget['url'] ?? '';

        $labels = $this->buildLabels([
            'job'       => $jobName,
            'test_name' => $testName,
            'url'       => $url,
        ]);

        $lines = [];

        $this->addMetric(
            $lines,
            'eleload_requests_total',
            'gauge',
            'Total number of HTTP requests sent.',
            $labels,
            (int) ($requests['total'] ?? 0)
        );

        $this->addMetric(
            $lines,
            'eleload_requests_success_total',
            'gauge',
            'Total number of successful requests.',
            $labels,
            (int) ($requests['success'] ?? 0)
        );

        $this->addMetric(
            $lines,
            'eleload_error_rate_percent',
            'gauge',
            'Error rate as a percentage (0–100).',
            $labels,
            (float) ($requests['error_rate'] ?? 0.0)
        );

        $this->addMetric(
            $lines,
            'eleload_rps',
            'gauge',
            'Requests per second (overall average).',
            $labels,
            (float) ($throughput['rps'] ?? 0.0)
        );

        $this->addMetric(
            $lines,
            'eleload_tps',
            'gauge',
            'Successful transactions per second.',
            $labels,
            (float) ($throughput['tps'] ?? 0.0)
        );

        $this->addMetric(
            $lines,
            'eleload_latency_p50_ms',
            'gauge',
            'p50 (median) request latency in milliseconds.',
            $labels,
            (float) ($latency['p50'] ?? 0.0)
        );

        $this->addMetric(
            $lines,
            'eleload_latency_p95_ms',
            'gauge',
            'p95 request latency in milliseconds.',
            $labels,
            (float) ($latency['p95'] ?? 0.0)
        );

        $this->addMetric(
            $lines,
            'eleload_latency_p99_ms',
            'gauge',
            'p99 request latency in milliseconds.',
            $labels,
            (float) ($latency['p99'] ?? 0.0)
        );

        $this->addMetric(
            $lines,
            'eleload_duration_seconds',
            'gauge',
            'Total load-test duration in seconds.',
            $labels,
            $durationSec
        );

        $lines[] = '';

        return implode("\n", $lines);
    }

    /**
     * Build a Prometheus label set string from a key-value map.
     * Label values are escaped per the Prometheus exposition format spec.
     *
     * @param array<string, string> $labels
     */
    public function buildLabels(array $labels): string
    {
        if ($labels === []) {
            return '';
        }

        $parts = [];
        foreach ($labels as $key => $value) {
            $escaped = str_replace(['\\', '"', "\n"], ['\\\\', '\\"', '\\n'], $value);
            $parts[] = $key . '="' . $escaped . '"';
        }

        return '{' . implode(',', $parts) . '}';
    }

    /**
     * Append HELP, TYPE, and value lines for one metric.
     *
     * @param list<string> $lines
     */
    private function addMetric(
        array &$lines,
        string $name,
        string $type,
        string $help,
        string $labels,
        int|float $value,
    ): void {
        $lines[] = "# HELP {$name} {$help}";
        $lines[] = "# TYPE {$name} {$type}";
        $lines[] = "{$name}{$labels} {$value}";
    }
}
