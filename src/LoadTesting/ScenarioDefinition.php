<?php

declare(strict_types=1);

namespace Eleload\LoadTesting;

final class ScenarioDefinition
{
    /**
     * @param list<ScenarioStep> $steps        Steps used when no variants are defined.
     * @param array<string, string> $variables Default variables for each VU.
     * @param list<ScenarioVariant> $variants  Optional weighted variants; overrides steps.
     */
    public function __construct(
        public readonly string $name,
        public readonly array $steps,
        public readonly array $variables = [],
        public readonly array $variants = [],
    ) {
    }
}
