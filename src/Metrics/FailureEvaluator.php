<?php

declare(strict_types=1);

namespace Eleload\Metrics;

use Eleload\Cli\RunOptions;

/**
 * Evaluates configured failure thresholds against a report and returns pass/fail results.
 */
final class FailureEvaluator
{
    /**
     * @param array<string, mixed> $report
     * @return array{checks:list<array{name:string, actual:float, threshold:float, operator:string, passed:bool}>, failed:bool}
     */
    public function evaluate(array $report, RunOptions $options): array
    {
        $checks = [];
        /** @var array<string, mixed> $summary */
        $summary = $report['summary'];
        /** @var array{p95: float, p99: float} $latency */
        $latency = $summary['latency'];
        /** @var array{error_rate: float} $requests */
        $requests = $summary['requests'];
        /** @var array{rps: float, tps: float} $throughput */
        $throughput = $summary['throughput'];

        if ($options->failOnP95 !== null) {
            $checks[] = $this->maxCheck('p95', $latency['p95'], $options->failOnP95);
        }

        if ($options->failOnP99 !== null) {
            $checks[] = $this->maxCheck('p99', $latency['p99'], $options->failOnP99);
        }

        if ($options->failOnErrorRate !== null) {
            $checks[] = $this->maxCheck('error_rate', $requests['error_rate'], $options->failOnErrorRate);
        }

        if ($options->failOnRpsBelow !== null) {
            $checks[] = $this->minCheck('rps', $throughput['rps'], $options->failOnRpsBelow);
        }

        if ($options->failOnTpsBelow !== null) {
            $checks[] = $this->minCheck('tps', $throughput['tps'], $options->failOnTpsBelow);
        }

        return [
            'checks' => $checks,
            'failed' => count(array_filter($checks, static fn (array $check): bool => !$check['passed'])) > 0,
        ];
    }

    /**
     * @return array{name:string, actual:float, threshold:float, operator:string, passed:bool}
     */
    private function maxCheck(string $name, float $actual, float $threshold): array
    {
        return [
            'name' => $name,
            'actual' => round($actual, 2),
            'threshold' => round($threshold, 2),
            'operator' => '<=',
            'passed' => $actual <= $threshold,
        ];
    }

    /**
     * @return array{name:string, actual:float, threshold:float, operator:string, passed:bool}
     */
    private function minCheck(string $name, float $actual, float $threshold): array
    {
        return [
            'name' => $name,
            'actual' => round($actual, 2),
            'threshold' => round($threshold, 2),
            'operator' => '>=',
            'passed' => $actual >= $threshold,
        ];
    }
}
