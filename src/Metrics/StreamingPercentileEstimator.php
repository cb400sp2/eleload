<?php

declare(strict_types=1);

namespace Eleload\Metrics;

use InvalidArgumentException;

final class StreamingPercentileEstimator
{
    /**
     * @var list<float>
     */
    private array $initialValues = [];

    /**
     * @var list<float>|null
     */
    private ?array $markerHeights = null;

    /**
     * @var list<int>
     */
    private array $markerPositions = [1, 2, 3, 4, 5];

    /**
     * @var list<float>
     */
    private array $desiredMarkerPositions = [];

    /**
     * @var list<float>
     */
    private array $positionIncrements = [];

    public function __construct(private readonly float $quantile)
    {
        if ($quantile <= 0.0 || $quantile >= 1.0) {
            throw new InvalidArgumentException('quantile must be between 0 and 1.');
        }
    }

    public function add(float $value): void
    {
        if ($this->markerHeights === null) {
            $this->initialValues[] = $value;
            if (count($this->initialValues) < 5) {
                return;
            }

            sort($this->initialValues, SORT_NUMERIC);
            $this->markerHeights = array_values($this->initialValues);
            $this->desiredMarkerPositions = [
                1.0,
                1.0 + (2.0 * $this->quantile),
                1.0 + (4.0 * $this->quantile),
                3.0 + (2.0 * $this->quantile),
                5.0,
            ];
            $this->positionIncrements = [
                0.0,
                $this->quantile / 2.0,
                $this->quantile,
                (1.0 + $this->quantile) / 2.0,
                1.0,
            ];

            return;
        }

        $markerHeights = $this->markerHeights;

        if ($value < $markerHeights[0]) {
            $markerHeights[0] = $value;
            $cell = 0;
        } elseif ($value < $markerHeights[1]) {
            $cell = 0;
        } elseif ($value < $markerHeights[2]) {
            $cell = 1;
        } elseif ($value < $markerHeights[3]) {
            $cell = 2;
        } elseif ($value <= $markerHeights[4]) {
            $cell = 3;
        } else {
            $markerHeights[4] = $value;
            $cell = 3;
        }

        for ($index = $cell + 1; $index < 5; $index++) {
            $this->markerPositions[$index]++;
        }

        for ($index = 0; $index < 5; $index++) {
            $this->desiredMarkerPositions[$index] += $this->positionIncrements[$index];
        }

        for ($index = 1; $index < 4; $index++) {
            $delta = $this->desiredMarkerPositions[$index] - $this->markerPositions[$index];
            $canMoveForward = $delta >= 1.0 && ($this->markerPositions[$index + 1] - $this->markerPositions[$index]) > 1;
            $canMoveBackward = $delta <= -1.0 && ($this->markerPositions[$index - 1] - $this->markerPositions[$index]) < -1;

            if (!$canMoveForward && !$canMoveBackward) {
                continue;
            }

            $direction = $delta > 0 ? 1 : -1;
            $candidate = $this->parabolic($markerHeights, $index, $direction);

            if ($candidate > $markerHeights[$index - 1] && $candidate < $markerHeights[$index + 1]) {
                $markerHeights[$index] = $candidate;
            } else {
                $markerHeights[$index] = $this->linear($markerHeights, $index, $direction);
            }

            $this->markerPositions[$index] += $direction;
        }

        $this->markerHeights = $markerHeights;
    }

    public function estimate(): float
    {
        if ($this->markerHeights === null) {
            return $this->calculateExactQuantile($this->initialValues);
        }

        return $this->markerHeights[2];
    }

    /**
     * @param list<float> $markerHeights
     */
    private function parabolic(array $markerHeights, int $index, int $direction): float
    {
        $leftSpan = $this->markerPositions[$index] - $this->markerPositions[$index - 1];
        $rightSpan = $this->markerPositions[$index + 1] - $this->markerPositions[$index];
        $totalSpan = $this->markerPositions[$index + 1] - $this->markerPositions[$index - 1];

        return $markerHeights[$index] + ($direction / $totalSpan) * (
            (($leftSpan + $direction) * ($markerHeights[$index + 1] - $markerHeights[$index]) / $rightSpan)
            + (($rightSpan - $direction) * ($markerHeights[$index] - $markerHeights[$index - 1]) / $leftSpan)
        );
    }

    /**
     * @param list<float> $markerHeights
     */
    private function linear(array $markerHeights, int $index, int $direction): float
    {
        $targetIndex = $index + $direction;

        return $markerHeights[$index] + ($direction * (
            $markerHeights[$targetIndex] - $markerHeights[$index]
        ) / (
            $this->markerPositions[$targetIndex] - $this->markerPositions[$index]
        ));
    }

    /**
     * @param list<float> $values
     */
    private function calculateExactQuantile(array $values): float
    {
        if ($values === []) {
            return 0.0;
        }

        sort($values, SORT_NUMERIC);
        $index = (int) ceil($this->quantile * count($values)) - 1;
        $index = max(0, min($index, count($values) - 1));

        return $values[$index];
    }
}