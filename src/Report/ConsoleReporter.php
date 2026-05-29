<?php

declare(strict_types=1);

namespace Eleload\Report;

use Eleload\Cli\ConsoleOutput;

final class ConsoleReporter
{
    /**
     * @param array<string, mixed> $report
     */
    public function render(array $report, ConsoleOutput $output, bool $verbose = false): void
    {
        $summary = $report['summary'];
        $requests = $summary['requests'];
        $throughput = $summary['throughput'];
        $latency = $summary['latency'];

        $output->writeln();
        $output->writeln('HTTP Load Test Result');
        $output->writeln();
        $output->writeln('Target');
        if (!empty($report['meta']['test_name']) && is_string($report['meta']['test_name'])) {
            $output->writeln('  Test Name            : ' . $report['meta']['test_name']);
        }
        $output->writeln('  URL                  : ' . $report['target']['url']);
        $output->writeln('  Method               : ' . $report['target']['method']);
        $output->writeln('  Success Status       : ' . $this->formatSuccessStatus($report['config']['success_status'] ?? null));
        $output->writeln('  Requests             : ' . $requests['total']);
        $output->writeln('  Concurrency          : ' . $report['config']['concurrency']);
        $output->writeln('  Duration             : ' . number_format((float)$summary['duration_sec'], 3) . ' sec');
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
                    '  RPS Achievement      : ' . $this->formatPercent((float)$throughput['rps_achievement_rate'])
                );
            }
            if ($hasTargetTps) {
                $output->writeln(
                    '  Target TPS           : ' . $this->formatRate((float)$throughput['target_tps'], 'tx/sec')
                );
                $output->writeln(
                    '  TPS Achievement      : ' . $this->formatPercent((float)$throughput['tps_achievement_rate'])
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
        foreach ($summary['status_codes'] as $code => $item) {
            $output->writeln(
                '  ' . str_pad((string)$code, 20, ' ', STR_PAD_RIGHT) .
                ': ' . $item['count'] . ' (' . $this->formatPercent((float)$item['rate']) . ')'
            );
        }

        if (!empty($report['thresholds']['checks'])) {
            $output->writeln();
            $output->writeln('Thresholds');
            foreach ($report['thresholds']['checks'] as $check) {
                $output->writeln(
                    '  ' . str_pad((string)$check['name'], 20, ' ', STR_PAD_RIGHT) .
                    ': actual ' . $check['actual'] . ' ' . $check['operator'] . ' ' . $check['threshold'] .
                    ' [' . ($check['passed'] ? 'PASS' : 'FAIL') . ']'
                );
            }
        }

        if (!empty($report['errors'])) {
            $output->writeln();
            $output->writeln($verbose ? 'Errors (detailed)' : 'Errors');
            $errorRows = $verbose ? $report['errors'] : array_slice($report['errors'], 0, 10);

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

            if (!$verbose && count($report['errors']) > 10) {
                $output->writeln('  ... and ' . (count($report['errors']) - 10) . ' more');
            }
        }

        if ($verbose && !empty($summary['slowest_requests'])) {
            $output->writeln();
            $output->writeln('Slowest Requests');
            foreach ($summary['slowest_requests'] as $request) {
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

    private function formatPercent(float $value): string
    {
        return number_format($value, 2) . '%';
    }

    private function formatRate(float $value, string $unit): string
    {
        return number_format($value, 2) . ' ' . $unit;
    }

    private function formatMs(float $value): string
    {
        return number_format($value, 2) . ' ms';
    }

    private function formatBoolFlag(mixed $value): string
    {
        if ($value === null) {
            return 'n/a';
        }

        return $value ? 'yes' : 'no';
    }

    private function formatSuccessStatus(mixed $value): string
    {
        if (!is_array($value) || $value === []) {
            return '2xx,3xx (default)';
        }

        return implode(',', array_map(static fn (mixed $code): string => (string)$code, $value));
    }
}
