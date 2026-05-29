<?php

declare(strict_types=1);

namespace Eleload\Metrics;

use Eleload\LoadTesting\RequestResult;
use Eleload\LoadTesting\RunResult;

final class StatisticsCalculator
{
    private PercentileCalculator $percentile;

    public function __construct()
    {
        $this->percentile = new PercentileCalculator();
    }

    /**
     * @return array<string, mixed>
     */
    public function summarize(RunResult $runResult): array
    {
        $metricResults = array_values(array_filter(
            $runResult->requestResults,
            static fn (RequestResult $result): bool => $result->includedInMetrics
        ));
        $total = count($metricResults);
        $success = 0;
        $statusCounts = [];
        $latencies = [];
        $errors = [];
        $successStatusCodes = $runResult->options->successStatusCodes;

        foreach ($metricResults as $result) {
            $latencies[] = $result->latencyMs;
            $statusKey = (string)$result->httpCode;
            $statusCounts[$statusKey] = ($statusCounts[$statusKey] ?? 0) + 1;

            if ($result->isSuccess($successStatusCodes)) {
                $success++;
            } else {
                $errors[] = $this->formatError($result);
            }
        }

        ksort($statusCounts, SORT_NATURAL);

        $failed = $total - $success;
        $durationSec = max($runResult->durationSec - min($runResult->options->warmupSec, $runResult->durationSec), 0.000_001);
        $successRate = $total > 0 ? ($success / $total) * 100.0 : 0.0;
        $errorRate = $total > 0 ? ($failed / $total) * 100.0 : 0.0;
        $rps = $total / $durationSec;
        $tps = $success / $durationSec;
        $tpsRpsRate = $rps > 0.0 ? ($tps / $rps) * 100.0 : 0.0;

        $statusCodeStats = [];
        foreach ($statusCounts as $code => $count) {
            $statusCodeStats[$code] = [
                'count' => $count,
                'rate' => $total > 0 ? $this->round2(($count / $total) * 100.0) : 0.0,
            ];
        }

        $throughput = [
            'rps' => $this->round2($rps),
            'tps' => $this->round2($tps),
            'tps_rps_rate' => $this->round2($tpsRpsRate),
        ];

        if ($runResult->options->targetRps !== null) {
            $throughput['target_rps'] = $this->round2($runResult->options->targetRps);
            $throughput['rps_achievement_rate'] = $this->round2(
                ($rps / $runResult->options->targetRps) * 100.0
            );
        }

        if ($runResult->options->targetTps !== null) {
            $throughput['target_tps'] = $this->round2($runResult->options->targetTps);
            $throughput['tps_achievement_rate'] = $this->round2(
                ($tps / $runResult->options->targetTps) * 100.0
            );
        }

        return [
            'target' => [
                'url' => $runResult->options->url,
                'method' => $runResult->options->method,
            ],
            'config' => [
                'requests' => $runResult->options->requests,
                'concurrency' => $runResult->options->concurrency,
                'timeout' => $runResult->options->timeout,
                'follow_redirects' => $runResult->options->followRedirects,
                'name' => $runResult->options->name,
                'success_status' => $runResult->options->successStatusCodes,
                'duration' => $runResult->options->durationSec,
                'warmup' => $runResult->options->warmupSec,
                'target_rps' => $runResult->options->targetRps,
                'target_tps' => $runResult->options->targetTps,
            ],
            'summary' => [
                'duration_sec' => $this->round3($durationSec),
                'total_duration_sec' => $this->round3($runResult->durationSec),
                'requests' => [
                    'total' => $total,
                    'executed' => count($runResult->requestResults),
                    'warmup' => count($runResult->requestResults) - $total,
                    'success' => $success,
                    'failed' => $failed,
                    'success_rate' => $this->round2($successRate),
                    'error_rate' => $this->round2($errorRate),
                ],
                'throughput' => $throughput,
                'latency' => [
                    'min' => $this->round2($this->min($latencies)),
                    'avg' => $this->round2($this->avg($latencies)),
                    'p50' => $this->round2($this->percentile->calculate($latencies, 50)),
                    'p95' => $this->round2($this->percentile->calculate($latencies, 95)),
                    'p99' => $this->round2($this->percentile->calculate($latencies, 99)),
                    'max' => $this->round2($this->max($latencies)),
                ],
                'status_codes' => $statusCodeStats,
            ],
            'errors' => $errors,
            'meta' => [
                'tool' => 'eleload',
                'version' => '0.1.0',
                'test_name' => $runResult->options->name,
            ],
        ];
    }

    /**
     * @return array<string, int|float|string>
     */
    private function formatError(RequestResult $result): array
    {
        return [
            'request' => $result->requestNumber,
            'http_code' => $result->httpCode,
            'error_no' => $result->errorNo,
            'error' => $result->error,
            'latency_ms' => $this->round2($result->latencyMs),
        ];
    }

    /**
     * @param list<float> $values
     */
    private function min(array $values): float
    {
        return $values === [] ? 0.0 : (float)min($values);
    }

    /**
     * @param list<float> $values
     */
    private function max(array $values): float
    {
        return $values === [] ? 0.0 : (float)max($values);
    }

    /**
     * @param list<float> $values
     */
    private function avg(array $values): float
    {
        if ($values === []) {
            return 0.0;
        }

        return array_sum($values) / count($values);
    }

    private function round2(float $value): float
    {
        return round($value, 2);
    }

    private function round3(float $value): float
    {
        return round($value, 3);
    }
}
