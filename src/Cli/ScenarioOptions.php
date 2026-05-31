<?php

declare(strict_types=1);

namespace Eleload\Cli;

final class ScenarioOptions
{
    public function __construct(
        public readonly string $scenarioPath,
        public readonly int $concurrency = 10,
        public readonly ?float $durationSec = null,
        public readonly int $iterations = 100,
        public readonly float $warmupSec = 0.0,
        public readonly bool $silent = false,
        public readonly bool $verbose = false,
        public readonly bool $debug = false,
        public readonly bool $yes = false,
        public readonly bool $allowHighLoad = false,
        public readonly ?string $reportJsonPath = null,
        public readonly ?string $outputDir = null,
        public readonly ?string $name = null,
        public readonly int $agents = 1,
    ) {
    }
}
