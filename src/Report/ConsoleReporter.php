<?php

declare(strict_types=1);

namespace Eleload\Report;

use Eleload\Cli\ConsoleOutput;

/**
 * Renders a load-test report to the console in a human-readable format.
 */
final class ConsoleReporter
{
    /**
     * @param array<string, mixed> $report
     */
    public function render(array $report, ConsoleOutput $output, bool $verbose = false): void
    {
        /** @var array<string, mixed> $summary */
        $summary = $report['summary'];
        /** @var array{total: int, success: int, failed: int, success_rate: float, error_rate: float} $requests */
        $requests = $summary['requests'];
        /** @var array{rps: float, tps: float, tps_rps_rate: float, target_rps?: float, rps_achievement_rate?: float, target_tps?: float, tps_achievement_rate?: float} $throughput */
        $throughput = $summary['throughput'];
        /** @var array{p50: float, p95: float, p99: float, min: float, avg: float, max: float} $latency */
        $latency = $summary['latency'];
        /** @var float $durationSec */
        $durationSec = $summary['duration_sec'];
        /** @var array<string, array{count: int, rate: float}> $statusCodes */
        $statusCodes = is_array($summary['status_codes'] ?? null) ? $summary['status_codes'] : [];
        /** @var list<array{request: int, success?: bool, http_code: int, error_no: int, latency_ms: float, download_bytes?: float, body_contains_expected?: bool, error: string}> $slowestRequests */
        $slowestRequests = is_array($summary['slowest_requests'] ?? null) ? array_values($summary['slowest_requests']) : [];
        /** @var array{url: string, method: string} $reportTarget */
        $reportTarget = $report['target'];
        /** @var array{concurrency: int, success_status?: mixed} $reportConfig */
        $reportConfig = $report['config'];
        /** @var array{test_name?: string} $reportMeta */
        $reportMeta = is_array($report['meta'] ?? null) ? $report['meta'] : [];
        /** @var array{checks?: list<array{name: string, actual: float, threshold: float, operator: string, passed: bool}>} $reportThresholds */
        $reportThresholds = is_array($report['thresholds'] ?? null) ? $report['thresholds'] : [];
        /** @var list<array{request: int, success?: bool, http_code: int, error_no: int, latency_ms: float, download_bytes?: float, body_contains_expected?: bool, error: string}> $reportErrors */
        $reportErrors = is_array($report['errors'] ?? null) ? array_values($report['errors']) : [];

        $output->writeln();
        $output->writeln('HTTP Load Test Result');
        $output->writeln();
        $output->writeln('Target');
        if (!empty($reportMeta['test_name'])) {
            $output->writeln('  Test Name            : ' . $reportMeta['test_name']);
        }
        $output->writeln('  URL                  : ' . $reportTarget['url']);
        $output->writeln('  Method               : ' . $reportTarget['method']);
        $output->writeln('  Success Status       : ' . $this->formatSuccessStatus($reportConfig['success_status'] ?? null));
        $output->writeln('  Requests             : ' . $requests['total']);
        $output->writeln('  Concurrency          : ' . $reportConfig['concurrency']);
        $output->writeln('  Duration             : ' . number_format($durationSec, 3) . ' sec');
        $output->writeln();
        $output->writeln('Throughput');
        $output->writeln('  RPS                  : ' . $this->formatRate((float)$throughput['rps'], 'req/sec'));
        $output->writeln('  TPS                  : ' . $this->formatRate((float)$throughput['tps'], 'tx/sec'));
        $output->writeln('  TPS / RPS Rate       : ' . $this->formatPercent((float)$throughput['tps_rps_rate']));

        $hasTargetRps = array_key_exists('target_rps', $throughput);
        $hasTargetTps = array_key_exists('target_tps', $throughput);
        if ($hasTargetRps || $hasTargetTps) {
            $output->writeln();
            $output->writeln('Target Achievement');
            if ($hasTargetRps) {
                $output->writeln(
                    '  Target RPS           : ' . $this->formatRate((float)$throughput['target_rps'], 'req/sec')
                );
                $output->writeln(
                    '  RPS Achievement      : ' . $this->formatPercent($throughput['rps_achievement_rate'] ?? 0.0)
                );
            }
            if ($hasTargetTps) {
                $output->writeln(
                    '  Target TPS           : ' . $this->formatRate((float)$throughput['target_tps'], 'tx/sec')
                );
                $output->writeln(
                    '  TPS Achievement      : ' . $this->formatPercent($throughput['tps_achievement_rate'] ?? 0.0)
                );
            }
        }

        $output->writeln();
        $output->writeln('Result');
        $output->writeln(
            '  Success              : ' . $requests['success'] .
            ' / ' . $requests['total'] .
            ' (' . $this->formatPercent((float)$requests['success_rate']) . ')'
        );
        $output->writeln(
            '  Failed               : ' . $requests['failed'] .
            ' / ' . $requests['total'] .
            ' (' . $this->formatPercent((float)$requests['error_rate']) . ')'
        );
        $output->writeln('  Error Rate           : ' . $this->formatPercent((float)$requests['error_rate']));

        $output->writeln();
        $output->writeln('Latency');
        $output->writeln('  min                  : ' . $this->formatMs((float)$latency['min']));
        $output->writeln('  avg                  : ' . $this->formatMs((float)$latency['avg']));
        $output->writeln('  p50                  : ' . $this->formatMs((float)$latency['p50']));
        $output->writeln('  p95                  : ' . $this->formatMs((float)$latency['p95']));
        $output->writeln('  p99                  : ' . $this->formatMs((float)$latency['p99']));
        $output->writeln('  max                  : ' . $this->formatMs((float)$latency['max']));

        $output->writeln();
        $output->writeln('Status Codes');
        foreach ($statusCodes as $code => $item) {
            $output->writeln(
                '  ' . str_pad((string)$code, 20, ' ', STR_PAD_RIGHT) .
                ': ' . $item['count'] . ' (' . $this->formatPercent((float)$item['rate']) . ')'
            );
        }

        if (!empty($reportThresholds['checks'])) {
            $output->writeln();
            $output->writeln('Thresholds');
            foreach ($reportThresholds['checks'] as $check) {
                $output->writeln(
                    '  ' . str_pad((string)$check['name'], 20, ' ', STR_PAD_RIGHT) .
                    ': actual ' . $check['actual'] . ' ' . $check['operator'] . ' ' . $check['threshold'] .
                    ' [' . ($check['passed'] ? 'PASS' : 'FAIL') . ']'
                );
            }
        }

        if ($reportErrors !== []) {
            $output->writeln();
            $output->writeln($verbose ? 'Errors (detailed)' : 'Errors');
            $errorRows = $verbose ? $reportErrors : array_slice($reportErrors, 0, 10);

            foreach ($errorRows as $error) {
                if ($verbose) {
                    $output->writeln(
                        sprintf(
                            '  #%d success=%s code=%d errno=%d latency=%sms bytes=%s body_match=%s message=%s',
                            $error['request'],
                            $this->formatBoolFlag($error['success'] ?? null),
                            $error['http_code'],
                            $error['error_no'],
                            number_format((float)$error['latency_ms'], 2),
                            number_format((float)($error['download_bytes'] ?? 0.0), 0),
                            $this->formatBoolFlag($error['body_contains_expected'] ?? null),
                            $error['error'] !== '' ? $error['error'] : '(no message)'
                        )
                    );
                    continue;
                }

                $output->writeln(
                    sprintf(
                        '  #%d code=%d errno=%d latency=%sms message=%s',
                        $error['request'],
                        $error['http_code'],
                        $error['error_no'],
                        number_format((float)$error['latency_ms'], 2),
                        $error['error'] !== '' ? $error['error'] : '(no message)'
                    )
                );
            }

            if (!$verbose && count($reportErrors) > 10) {
                $output->writeln('  ... and ' . (count($reportErrors) - 10) . ' more');
            }
        }

        if ($verbose && $slowestRequests !== []) {
            $output->writeln();
            $output->writeln('Slowest Requests');
            foreach ($slowestRequests as $request) {
                $output->writeln(
                    sprintf(
                        '  #%d success=%s code=%d errno=%d latency=%sms bytes=%s body_match=%s message=%s',
                        $request['request'],
                        $this->formatBoolFlag($request['success'] ?? null),
                        $request['http_code'],
                        $request['error_no'],
                        number_format((float)$request['latency_ms'], 2),
                        number_format((float)($request['download_bytes'] ?? 0.0), 0),
                        $this->formatBoolFlag($request['body_contains_expected'] ?? null),
                        $request['error'] !== '' ? $request['error'] : '(no message)'
                    )
                );
            }
        }
    }

    /**
     * Formats a float as a percentage string (e.g. "12.34%").
     */
    private function formatPercent(float $value): string
    {
        return number_format($value, 2) . '%';
    }

    /**
     * Formats a throughput rate with its unit (e.g. "10.00 req/sec").
     */
    private function formatRate(float $value, string $unit): string
    {
        return number_format($value, 2) . ' ' . $unit;
    }

    /**
     * Formats a latency value in milliseconds (e.g. "12.34 ms").
     */
    private function formatMs(float $value): string
    {
        return number_format($value, 2) . ' ms';
    }

    /**
     * Converts a nullable bool to "yes" / "no" / "n/a".
     */
    private function formatBoolFlag(mixed $value): string
    {
        if ($value === null) {
            return 'n/a';
        }

        return $value ? 'yes' : 'no';
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
