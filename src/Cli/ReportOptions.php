<?php

declare(strict_types=1);

namespace Eleload\Cli;

/**
 * Parsed options for the `report` sub-command.
 */
final class ReportOptions
{
    /**
     * @param string $jsonPath Path to the source JSON report file.
     * @param string $htmlPath Output path for the generated HTML report.
     */
    public function __construct(
        public readonly string $jsonPath,
        public readonly string $htmlPath
    ) {
    }
}
