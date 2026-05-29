<?php

declare(strict_types=1);

namespace Eleload\Metrics;

/**
 * Accumulates latency samples one-by-one and can produce a summary without storing all values.
 *
 * Uses {@see StreamingPercentileEstimator} (P² algorithm) for p50/p95/p99 estimation.
 */
final class StreamingLatencySummary
{
    private int $count = 0;
    private float $sum = 0.0;
    private float $min = 0.0;
    private float $max = 0.0;
    private StreamingPercentileEstimator $p50;
    private StreamingPercentileEstimator $p95;
    private StreamingPercentileEstimator $p99;

    /**
     * Initialises the three internal P² estimators for p50, p95, and p99.
     */
    public function __construct()
    {
        $this->p50 = new StreamingPercentileEstimator(0.50);
        $this->p95 = new StreamingPercentileEstimator(0.95);
        $this->p99 = new StreamingPercentileEstimator(0.99);
    }

    /**
     * Adds a latency sample to the running statistics.
     */
    public function add(float $latencyMs): void
    {
        if ($this->count === 0) {
            $this->min = $latencyMs;
            $this->max = $latencyMs;
        } else {
            $this->min = min($this->min, $latencyMs);
            $this->max = max($this->max, $latencyMs);
        }

        $this->count++;
        $this->sum += $latencyMs;
        $this->p50->add($latencyMs);
        $this->p95->add($latencyMs);
        $this->p99->add($latencyMs);
    }

    /**
     * @return array{min:float,avg:float,p50:float,p95:float,p99:float,max:float}
     */
    public function summarize(): array
    {
        if ($this->count === 0) {
            return [
                'min' => 0.0,
                'avg' => 0.0,
                'p50' => 0.0,
                'p95' => 0.0,
                'p99' => 0.0,
                'max' => 0.0,
            ];
        }

        return [
            'min' => $this->min,
            'avg' => $this->sum / $this->count,
            'p50' => $this->p50->estimate(),
            'p95' => $this->p95->estimate(),
            'p99' => $this->p99->estimate(),
            'max' => $this->max,
        ];
    }
}