<?php

declare(strict_types=1);

namespace Eleload\Metrics;

use Eleload\LoadTesting\RequestResult;
use Eleload\LoadTesting\RunResult;

/**
 * Computes descriptive statistics from a {@see RunResult} and produces the report data structure.
 */
final class StatisticsCalculator
{
    private const TOOL_VERSION = '0.1.0';

    private PercentileCalculator $percentile;

    /**
     * Initialises the percentile calculator dependency.
     */
    public function __construct()
    {
        $this->percentile = new PercentileCalculator();
    }

    /**
     * @return array<string, mixed>
     */
    public function summarize(RunResult $runResult): array
    {
        $executed = $runResult->countRequestResults();
        $warmup = 0;
        $total = 0;
        $success = 0;
        $statusCounts = [];
        $latencies = [];
        $streamingLatencySummary = $runResult->hasSpilledRequestResults() ? new StreamingLatencySummary() : null;
        $errors = [];
        $slowestRequests = [];
        $successStatusCodes = $runResult->options->successStatusCodes;
        $expectStatusCodes = $runResult->options->expectStatusCodes;
        $expectBodyContains = $runResult->options->expectBodyContains;
        /** @var array<int, array{count: int, success: int, latency_sum: float}> $buckets */
        $buckets = [];

        foreach ($runResult->iterateRequestResults() as $result) {
            if (!$result->includedInMetrics) {
                $warmup++;
                continue;
            }

            $total++;
            if ($streamingLatencySummary !== null) {
                $streamingLatencySummary->add($result->latencyMs);
            } else {
                $latencies[] = $result->latencyMs;
            }
            $statusKey = (string) $result->httpCode;
            $statusCounts[$statusKey] = ($statusCounts[$statusKey] ?? 0) + 1;

            $isSuccess = $this->isRequestSuccess(
                $result,
                $successStatusCodes,
                $expectStatusCodes,
                $expectBodyContains
            );

            if ($isSuccess) {
                $success++;
            } else {
                $errors[] = $this->formatRequestDetail($result, false);
            }

            $this->trackSlowestRequest($slowestRequests, $result, $isSuccess);

            $bucketIdx = max(0, (int) floor($result->elapsedSec - $runResult->options->warmupSec));
            if (!isset($buckets[$bucketIdx])) {
                $buckets[$bucketIdx] = ['count' => 0, 'success' => 0, 'latency_sum' => 0.0];
            }
            $buckets[$bucketIdx]['count']++;
            if ($isSuccess) {
                $buckets[$bucketIdx]['success']++;
            }
            $buckets[$bucketIdx]['latency_sum'] += $result->latencyMs;
        }

        ksort($statusCounts, SORT_NATURAL);

        ksort($buckets, SORT_NUMERIC);
        $timeBuckets = [];
        foreach ($buckets as $t => $bucket) {
            $count = $bucket['count'];
            $bucketSuccess = $bucket['success'];
            $timeBuckets[] = [
                't' => $t,
                'rps' => (float) $count,
                'tps' => (float) $bucketSuccess,
                'error_rate' => $count > 0 ? $this->round2((($count - $bucketSuccess) / $count) * 100.0) : 0.0,
                'avg_latency_ms' => $count > 0 ? $this->round2($bucket['latency_sum'] / $count) : 0.0,
            ];
        }

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

        $latencySummary = $streamingLatencySummary !== null
            ? $streamingLatencySummary->summarize()
            : [
                'min' => $this->min($latencies),
                'avg' => $this->avg($latencies),
                'p50' => $this->percentile->calculate($latencies, 50),
                'p95' => $this->percentile->calculate($latencies, 95),
                'p99' => $this->percentile->calculate($latencies, 99),
                'max' => $this->max($latencies),
            ];

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
                'expect_status' => $runResult->options->expectStatusCodes,
                'expect_body_contains' => $runResult->options->expectBodyContains,
                'duration' => $runResult->options->durationSec,
                'warmup' => $runResult->options->warmupSec,
                'ramp_up' => $runResult->options->rampUpSec > 0.0 ? $runResult->options->rampUpSec : null,
                'rate' => $runResult->options->rate,
                'target_rps' => $runResult->options->targetRps,
                'target_tps' => $runResult->options->targetTps,
            ],
            'summary' => [
                'duration_sec' => $this->round3($durationSec),
                'total_duration_sec' => $this->round3($runResult->durationSec),
                'requests' => [
                    'total' => $total,
                    'executed' => $executed,
                    'warmup' => $warmup,
                    'success' => $success,
                    'failed' => $failed,
                    'success_rate' => $this->round2($successRate),
                    'error_rate' => $this->round2($errorRate),
                ],
                'throughput' => $throughput,
                'latency' => [
                    'min' => $this->round2($latencySummary['min']),
                    'avg' => $this->round2($latencySummary['avg']),
                    'p50' => $this->round2($latencySummary['p50']),
                    'p95' => $this->round2($latencySummary['p95']),
                    'p99' => $this->round2($latencySummary['p99']),
                    'max' => $this->round2($latencySummary['max']),
                ],
                'status_codes' => $statusCodeStats,
                'slowest_requests' => array_map(
                    static fn (array $entry): array => $entry['detail'],
                    $slowestRequests
                ),
            ],
            'errors' => $errors,
            'time_buckets' => $timeBuckets,
            'meta' => [
                'tool' => 'eleload',
                'version' => self::TOOL_VERSION,
                'test_name' => $runResult->options->name,
            ],
        ];
    }

    /**
     * @param list<int>|null $successStatusCodes
     * @param list<int>|null $expectStatusCodes
     */
    private function isRequestSuccess(
        RequestResult $result,
        ?array $successStatusCodes,
        ?array $expectStatusCodes,
        ?string $expectBodyContains
    ): bool {
        $isSuccess = $result->isSuccess($successStatusCodes);
        if ($isSuccess && $expectStatusCodes !== null) {
            $isSuccess = in_array($result->httpCode, $expectStatusCodes, true);
        }
        if ($isSuccess && $expectBodyContains !== null) {
            $isSuccess = $result->bodyContainsExpected === true;
        }

        return $isSuccess;
    }

    /**
     * @return array<string, int|float|string|bool|null>
     */
    private function formatRequestDetail(RequestResult $result, bool $isSuccess): array
    {
        return [
            'request' => $result->requestNumber,
            'http_code' => $result->httpCode,
            'error_no' => $result->errorNo,
            'error' => $result->error,
            'latency_ms' => $this->round2($result->latencyMs),
            'download_bytes' => $this->round2($result->downloadBytes),
            'body_contains_expected' => $result->bodyContainsExpected,
            'success' => $isSuccess,
        ];
    }

    /**
     * @param list<array{latency_ms:float,detail:array<string, int|float|string|bool|null>}> $slowestRequests
     */
    private function trackSlowestRequest(array &$slowestRequests, RequestResult $result, bool $isSuccess): void
    {
        $slowestRequests[] = [
            'latency_ms' => $result->latencyMs,
            'detail' => $this->formatRequestDetail($result, $isSuccess),
        ];

        usort(
            $slowestRequests,
            static fn (array $a, array $b): int => $b['latency_ms'] <=> $a['latency_ms']
        );

        if (count($slowestRequests) > 5) {
            array_pop($slowestRequests);
        }
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

    /**
     * Rounds a value to 2 decimal places.
     */
    private function round2(float $value): float
    {
        return round($value, 2);
    }

    /**
     * Rounds a value to 3 decimal places.
     */
    private function round3(float $value): float
    {
        return round($value, 3);
    }
}
