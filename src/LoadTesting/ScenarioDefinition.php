<?php

declare(strict_types=1);

namespace Eleload\LoadTesting;

final class ScenarioDefinition
{
    /**
     * @param list<ScenarioStep> $steps
     * @param array<string, string> $variables
     */
    public function __construct(
        public readonly string $name,
        public readonly array $steps,
        public readonly array $variables = []
    ) {
    }
}
