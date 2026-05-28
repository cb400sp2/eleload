<?php

declare(strict_types=1);

namespace Eleload\Cli;

final class RunOptions
{
    /**
     * @param list<string> $headers
     */
    public function __construct(
        public readonly string $url,
        public readonly int $requests,
        public readonly int $concurrency,
        public readonly string $method,
        public readonly int $timeout,
        public readonly array $headers,
        public readonly ?string $body,
        public readonly ?string $reportJsonPath,
        public readonly ?string $reportHtmlPath,
        public readonly ?string $reportMdPath,
        public readonly ?string $outputDir,
        public readonly ?string $name,
        public readonly ?float $targetRps,
        public readonly ?float $targetTps
    ) {
    }
}
