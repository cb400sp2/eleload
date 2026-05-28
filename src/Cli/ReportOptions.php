<?php

declare(strict_types=1);

namespace Eleload\Cli;

final class ReportOptions
{
    public function __construct(
        public readonly string $jsonPath,
        public readonly string $htmlPath
    ) {
    }
}

