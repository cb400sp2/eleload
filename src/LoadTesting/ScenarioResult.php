<?php

declare(strict_types=1);

namespace Eleload\LoadTesting;

final class ScenarioResult
{
    /**
     * @param list<ScenarioIterationResult> $iterationResults
     */
    public function __construct(
        public readonly ScenarioDefinition $definition,
        public readonly float $durationSec,
        public readonly float $warmupSec,
        public readonly array $iterationResults
    ) {
    }

    public function totalIterations(): int
    {
        return count($this->iterationResults);
    }

    public function successIterations(): int
    {
        $count = 0;
        foreach ($this->iterationResults as $r) {
            if ($r->success) {
                $count++;
            }
        }
        return $count;
    }

    public function errorRate(): float
    {
        $total = $this->totalIterations();
        if ($total === 0) {
            return 0.0;
        }

        return (($total - $this->successIterations()) / $total) * 100.0;
    }

    /**
     * Scenario completions (successful iterations) per second, excluding warmup.
     */
    public function scenarioTps(): float
    {
        $measuredDuration = max($this->durationSec - $this->warmupSec, 0.000_001);
        return $this->successIterations() / $measuredDuration;
    }

    /**
     * Returns per-step aggregate metrics (latency, rps, success rate).
     *
     * @return list<array{index: int, name: string, count: int, successCount: int, avgMs: float, minMs: float, p95Ms: float, maxMs: float, rps: float}>
     */
    public function perStepSummary(): array
    {
        $stepCount = count($this->definition->steps);
        $buckets = [];

        for ($i = 0; $i < $stepCount; $i++) {
            $step = $this->definition->steps[$i];
            $buckets[$i] = [
                'index' => $i,
                'name' => $step->name ?? 'Step ' . ($i + 1),
                'latencies' => [],
                'successCount' => 0,
            ];
        }

        foreach ($this->iterationResults as $iter) {
            foreach ($iter->stepResults as $sr) {
                if (isset($buckets[$sr->stepIndex])) {
                    $buckets[$sr->stepIndex]['latencies'][] = $sr->latencyMs;
                    if ($sr->success) {
                        $buckets[$sr->stepIndex]['successCount']++;
                    }
                }
            }
        }

        $measuredDuration = max($this->durationSec - $this->warmupSec, 0.000_001);

        $result = [];
        foreach ($buckets as $bucket) {
            $latencies = $bucket['latencies'];
            $count = count($latencies);
            if ($count === 0) {
                $result[] = [
                    'index' => $bucket['index'],
                    'name' => $bucket['name'],
                    'count' => 0,
                    'successCount' => 0,
                    'failedCount' => 0,
                    'successRate' => 0.0,
                    'errorRate' => 0.0,
                    'avgMs' => 0.0,
                    'minMs' => 0.0,
                    'p50Ms' => 0.0,
                    'p95Ms' => 0.0,
                    'p99Ms' => 0.0,
                    'maxMs' => 0.0,
                    'rps' => 0.0,
                ];
                continue;
            }

            sort($latencies);
            $avg = array_sum($latencies) / $count;
            $p50 = $latencies[(int) ceil($count * 0.50) - 1];
            $p95 = $latencies[(int) ceil($count * 0.95) - 1];
            $p99 = $latencies[(int) ceil($count * 0.99) - 1];
            $successCount = $bucket['successCount'];
            $failedCount = $count - $successCount;

            $result[] = [
                'index' => $bucket['index'],
                'name' => $bucket['name'],
                'count' => $count,
                'successCount' => $successCount,
                'failedCount' => $failedCount,
                'successRate' => round(($successCount / $count) * 100.0, 2),
                'errorRate' => round(($failedCount / $count) * 100.0, 2),
                'avgMs' => round($avg, 2),
                'minMs' => round($latencies[0], 2),
                'p50Ms' => round($p50, 2),
                'p95Ms' => round($p95, 2),
                'p99Ms' => round($p99, 2),
                'maxMs' => round($latencies[$count - 1], 2),
                'rps' => round($count / $measuredDuration, 2),
            ];
        }

        return $result;
    }
}
