<?php

declare(strict_types=1);

namespace Eleload\Metrics;

/**
 * Calculates exact percentile values from a sorted list of numbers.
 */
final class PercentileCalculator
{
    /**
     * @param list<float> $values
     */
    public function calculate(array $values, float $percentile): float
    {
        if ($values === []) {
            return 0.0;
        }

        sort($values, SORT_NUMERIC);
        $index = (int)ceil(($percentile / 100.0) * count($values)) - 1;
        $index = max(0, min($index, count($values) - 1));

        return $values[$index];
    }
}
