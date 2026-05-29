<?php

declare(strict_types=1);

namespace Eleload\Cli;

/**
 * Parsed options for the `compare` sub-command.
 */
final class CompareOptions
{
    /**
     * @param string $beforeJsonPath Path to the baseline JSON report.
     * @param string $afterJsonPath  Path to the new JSON report to compare against.
     * @param string|null $htmlPath  Output path for the HTML comparison report.
     * @param string|null $markdownPath Output path for the Markdown comparison report.
     */
    public function __construct(
        public readonly string $beforeJsonPath,
        public readonly string $afterJsonPath,
        public readonly ?string $htmlPath,
        public readonly ?string $markdownPath
    ) {
    }
}
