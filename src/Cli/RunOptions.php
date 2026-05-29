<?php

declare(strict_types=1);

namespace Eleload\Cli;

/**
 * Parsed options for the `run` sub-command.
 */
final class RunOptions
{
    /**
     * @param list<string> $headers
     * @param list<int>|null $successStatusCodes
     * @param list<int>|null $expectStatusCodes
     */
    public function __construct(
        public readonly string $url,
        public readonly int $requests,
        public readonly int $concurrency,
        public readonly string $method,
        public readonly int $timeout,
        public readonly ?int $connectTimeout,
        public readonly bool $silent,
        public readonly bool $verbose,
        public readonly bool $debug,
        public readonly bool $yes,
        public readonly bool $allowHighLoad,
        public readonly bool $followRedirects,
        public readonly array $headers,
        public readonly ?string $bearerToken,
        public readonly ?string $basicUser,
        public readonly ?string $basicPassword,
        public readonly ?string $cookie,
        public readonly ?string $body,
        public readonly ?string $reportJsonPath,
        public readonly ?string $reportHtmlPath,
        public readonly ?string $reportMdPath,
        public readonly ?string $reportCsvPath,
        public readonly ?string $outputDir,
        public readonly ?string $name,
        public readonly ?array $successStatusCodes,
        public readonly ?array $expectStatusCodes,
        public readonly ?string $expectBodyContains,
        public readonly ?float $durationSec,
        public readonly float $warmupSec,
        public readonly ?float $failOnP95,
        public readonly ?float $failOnP99,
        public readonly ?float $failOnErrorRate,
        public readonly ?float $failOnRpsBelow,
        public readonly ?float $failOnTpsBelow,
        public readonly ?float $targetRps,
        public readonly ?float $targetTps,
        public readonly float $rampUpSec
    ) {
    }
}
