<?php

declare(strict_types=1);

namespace Eleload\LoadTesting;

use InvalidArgumentException;

/**
 * A named group of steps with a relative weight used for VU assignment.
 */
final class ScenarioVariant
{
    /**
     * @param list<ScenarioStep> $steps
     */
    public function __construct(
        public readonly string $name,
        public readonly float $weight,
        public readonly array $steps,
    ) {
        if ($weight <= 0.0) {
            throw new InvalidArgumentException("Variant '{$name}': weight must be > 0.");
        }
        if (count($steps) === 0) {
            throw new InvalidArgumentException("Variant '{$name}': steps must not be empty.");
        }
    }
}
