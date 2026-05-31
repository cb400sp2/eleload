<?php

declare(strict_types=1);

namespace Eleload\LoadTesting;

final class ScenarioIterationResult
{
    /**
     * @param list<ScenarioStepResult> $stepResults
     */
    public function __construct(
        public readonly int $vuId,
        public readonly int $iterationNumber,
        public readonly float $totalMs,
        public readonly float $elapsedAtEndSec,
        public readonly array $stepResults,
        public readonly bool $success,
        public readonly ?string $variantName = null,
    ) {
    }
}
