<?php

declare(strict_types=1);

namespace Eleload\LoadTesting;

use InvalidArgumentException;

/**
 * Models user think time between scenario steps.
 * Supports three distributions: fixed, random(min,max), and exponential(mean).
 */
final class ThinkTime
{
    public const DISTRIBUTION_FIXED       = 'fixed';
    public const DISTRIBUTION_RANDOM      = 'random';
    public const DISTRIBUTION_EXPONENTIAL = 'exponential';

    public const ALLOWED_DISTRIBUTIONS = [
        self::DISTRIBUTION_FIXED,
        self::DISTRIBUTION_RANDOM,
        self::DISTRIBUTION_EXPONENTIAL,
    ];

    public function __construct(
        /** @var 'fixed'|'random'|'exponential' */
        public readonly string $distribution,
        /** Fixed or exponential mean (ms) */
        public readonly float $valueMsA,
        /** Upper bound for random distribution (ms); unused for fixed/exponential */
        public readonly float $valueMsB = 0.0,
    ) {
        if (!in_array($distribution, self::ALLOWED_DISTRIBUTIONS, true)) {
            throw new InvalidArgumentException(
                "ThinkTime distribution '{$distribution}' is not allowed. Use: "
                . implode(', ', self::ALLOWED_DISTRIBUTIONS)
            );
        }

        if ($distribution === self::DISTRIBUTION_RANDOM && $valueMsB < $valueMsA) {
            throw new InvalidArgumentException(
                "ThinkTime random: max ({$valueMsB}) must be >= min ({$valueMsA})."
            );
        }

        if ($valueMsA < 0.0) {
            throw new InvalidArgumentException('ThinkTime value must be non-negative.');
        }
    }

    /**
     * Sample a concrete delay in milliseconds according to the distribution.
     */
    public function sampleMs(): float
    {
        return match ($this->distribution) {
            self::DISTRIBUTION_FIXED => $this->valueMsA,
            self::DISTRIBUTION_RANDOM => $this->valueMsA + mt_rand(0, PHP_INT_MAX) / PHP_INT_MAX * ($this->valueMsB - $this->valueMsA),
            self::DISTRIBUTION_EXPONENTIAL => $this->sampleExponential(),
        };
    }

    private function sampleExponential(): float
    {
        if ($this->valueMsA <= 0.0) {
            return 0.0;
        }
        // Inverse CDF: -mean * ln(U)
        $u = max(mt_rand(1, PHP_INT_MAX) / PHP_INT_MAX, 1e-12);
        return -$this->valueMsA * log($u);
    }
}
