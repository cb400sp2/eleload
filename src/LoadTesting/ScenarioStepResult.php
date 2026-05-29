<?php

declare(strict_types=1);

namespace Eleload\LoadTesting;

final class ScenarioStepResult
{
    public function __construct(
        public readonly int $stepIndex,
        public readonly string $stepName,
        public readonly float $latencyMs,
        public readonly int $httpCode,
        public readonly int $errorNo,
        public readonly string $error,
        public readonly bool $success
    ) {
    }
}
