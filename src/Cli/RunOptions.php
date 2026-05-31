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
        public readonly ?string $reportHeatmapPath,
        public readonly ?string $reportJunitPath,
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
        public readonly ?float $rate,
        public readonly ?float $targetRps,
        public readonly ?float $targetTps,
        public readonly float $rampUpSec,
        public readonly int $memoryBufferSize = 10_000,
        public readonly bool $blockPrivateNetworks = false,
        public readonly string $httpVersion = '2.0',
        public readonly int $dnsCacheTtl = -1,
        public readonly string $acceptEncoding = 'gzip',
        public readonly bool $noDecompress = false,
        public readonly int $maxConnections = 0,
        public readonly int $tcpKeepaliveSec = 60,
        /** Compare this run against a previously saved JSON baseline. */
        public readonly ?string $baselinePath = null,
        /** Save this run's JSON report as the new baseline at this path. */
        public readonly ?string $saveBaselinePath = null,
        /** Minimum log level: debug|info|warn|error */
        public readonly string $logLevel = 'warn',
        /** Optional file path for structured JSON-Lines log output. */
        public readonly ?string $logFile = null,
        public readonly ?string $grpcMethod = null,
        /** OTLP HTTP endpoint URL (e.g. http://localhost:4318). Null = no-op tracing. */
        public readonly ?string $otelEndpoint = null,
        /** Prometheus Pushgateway URL (e.g. http://localhost:9091). Null = disabled. */
        public readonly ?string $prometheusUrl = null,
        /** Enable real-time TUI progress dashboard (requires TTY). */
        public readonly bool $tui = false,
    ) {
    }
}
