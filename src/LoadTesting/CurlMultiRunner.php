<?php

declare(strict_types=1);

namespace Eleload\LoadTesting;

use CurlHandle;
use CurlMultiHandle;
use InvalidArgumentException;
use RuntimeException;

/**
 * Executes HTTP load tests using curl_multi and collects per-request results.
 *
 * Supports both request-count mode and duration mode, optional rate limiting,
 * and spills results to a temporary file when the in-memory limit is exceeded.
 */
final class CurlMultiRunner
{
    private const DEFAULT_MAX_IN_MEMORY_REQUEST_RESULTS = 10_000;
    private const MULTI_SELECT_TIMEOUT_SEC = 1.0;
    private const IDLE_SLEEP_USEC = 5_000;
    private const NANOSECONDS_PER_SECOND = 1_000_000_000;
    private const NANOSECONDS_PER_MICROSECOND = 1_000;
    private const MEMORY_MONITOR_INTERVAL_NS = 250_000_000;
    private const MEMORY_PRESSURE_THRESHOLD_RATIO = 0.90;

    /**
     * @param int $maxInMemoryRequestResults Maximum number of results to keep in memory before spilling to disk.
     */
    public function __construct(
        private readonly int $maxInMemoryRequestResults = self::DEFAULT_MAX_IN_MEMORY_REQUEST_RESULTS,
        private readonly ?int $memorySoftLimitBytes = null
    ) {
        if ($this->maxInMemoryRequestResults < 1) {
            throw new InvalidArgumentException('maxInMemoryRequestResults must be >= 1.');
        }
    }

    /**
     * Executes the load test described by $options and returns aggregated results.
     *
     * The optional $onProgress callback is invoked approximately once per second with
     * (int $completed, int $errors, float $elapsedSec, int $total): void
     * where $total is the configured request count (0 in duration mode).
     *
     * The optional $shouldStop callback returns a non-empty reason string when
     * the run should stop early (for example SIGINT/SIGTERM).
     *
     * @throws \RuntimeException
     */
    public function run(
        RequestOptions $options,
        ?\Closure $onProgress = null,
        ?\Closure $shouldStop = null
    ): RunResult
    {
        $multi = curl_multi_init();
        // @phpstan-ignore-next-line (curl_multi_init always returns CurlMultiHandle in PHP 8+)
        if (!$multi instanceof CurlMultiHandle) {
            throw new RuntimeException('Failed to initialize curl multi handle.');
        }

        $nextRequest = 1;
        $completed = 0;
        $errors = 0;
        $running = 0;
        $inFlight = [];
        $results = [];
        $resultsPath = null;
        $resultsHandle = null;
        $requestResultCount = 0;
        $startedAt = hrtime(true);
        $lastProgressAt = $startedAt;
        $durationMode = $options->durationSec !== null;
        $keepSpilledResultsFile = false;
        $stopReason = null;
        $memoryStopThresholdBytes = $this->resolveMemoryStopThresholdBytes();
        $nextMemoryCheckAt = $startedAt;

        try {
            while (true) {
                if ($stopReason === null && $shouldStop !== null) {
                    $requestedStopReason = $shouldStop();
                    if (is_string($requestedStopReason) && $requestedStopReason !== '') {
                        $stopReason = $requestedStopReason;
                    }
                }

                $now = hrtime(true);
                if (
                    $stopReason === null &&
                    $memoryStopThresholdBytes !== null &&
                    $now >= $nextMemoryCheckAt
                ) {
                    $nextMemoryCheckAt = $now + self::MEMORY_MONITOR_INTERVAL_NS;

                    if (memory_get_peak_usage(true) >= $memoryStopThresholdBytes) {
                        $stopReason = 'memory_pressure';
                    }
                }

                while (
                    $stopReason === null &&
                    $this->canStartRequest($options, $startedAt, $nextRequest, $durationMode) &&
                    count($inFlight) < $this->effectiveConcurrency($options, $startedAt)
                ) {
                    $rateLimitSleepUsec = $this->getRateLimitSleepUsec($options, $startedAt, $nextRequest);
                    if ($rateLimitSleepUsec > 0) {
                        usleep($rateLimitSleepUsec);
                        continue;
                    }

                    $handle = $this->createHandle($options);
                    $handleId = spl_object_id($handle);

                    $inFlight[$handleId] = [
                        'request_number' => $nextRequest,
                        'started_at' => hrtime(true),
                        'handle' => $handle,
                    ];

                    curl_multi_add_handle($multi, $handle);
                    $nextRequest++;
                }

                do {
                    $status = curl_multi_exec($multi, $running);
                } while ($status === CURLM_CALL_MULTI_PERFORM);

                while ($info = curl_multi_info_read($multi)) {
                    $handle = $info['handle'];
                    if (!$handle instanceof CurlHandle) {
                        continue;
                    }

                    $handleId = spl_object_id($handle);
                    $meta = $inFlight[$handleId] ?? null;
                    if ($meta === null) {
                        curl_multi_remove_handle($multi, $handle);
                        continue;
                    }

                    $endedAt = hrtime(true);
                    $latencyMs = ($endedAt - $meta['started_at']) / 1_000_000;
                    $elapsedSec = ($endedAt - $startedAt) / 1_000_000_000;
                    $httpCode = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
                    $downloadBytes = (float) curl_getinfo($handle, CURLINFO_SIZE_DOWNLOAD);
                    $errorNo = curl_errno($handle);
                    $error = curl_error($handle);
                    $bodyContainsExpected = null;
                    if ($options->expectBodyContains !== null) {
                        $responseBody = (string) curl_multi_getcontent($handle);
                        $bodyContainsExpected = str_contains($responseBody, $options->expectBodyContains);
                    }

                    $requestResult = new RequestResult(
                        requestNumber: (int) $meta['request_number'],
                        latencyMs: $latencyMs,
                        httpCode: $httpCode,
                        downloadBytes: $downloadBytes,
                        errorNo: $errorNo,
                        error: $error,
                        includedInMetrics: $elapsedSec >= $options->warmupSec,
                        bodyContainsExpected: $bodyContainsExpected,
                        elapsedSec: $elapsedSec
                    );

                    $requestResultCount++;
                    if ($resultsHandle !== null || $requestResultCount > $this->maxInMemoryRequestResults) {
                        [$resultsPath, $resultsHandle] = $this->storeRequestResultToDisk(
                            $results,
                            $requestResult,
                            $resultsPath,
                            $resultsHandle
                        );
                    } else {
                        $results[] = $requestResult;
                    }

                    unset($inFlight[$handleId]);
                    curl_multi_remove_handle($multi, $handle);
                    $completed++;
                    if (!$requestResult->isSuccess($options->successStatusCodes)) {
                        $errors++;
                    }
                    if ($onProgress !== null) {
                        $now = hrtime(true);
                        if ($now - $lastProgressAt >= self::NANOSECONDS_PER_SECOND) {
                            $onProgress($completed, $errors, ($now - $startedAt) / 1_000_000_000, $options->requests);
                            $lastProgressAt = $now;
                        }
                    }
                }

                if ($stopReason !== null && count($inFlight) === 0) {
                    break;
                }

                if ($this->isComplete($options, $startedAt, $completed, count($inFlight), $durationMode)) {
                    break;
                }

                if ($running > 0) {
                    $selected = curl_multi_select($multi, self::MULTI_SELECT_TIMEOUT_SEC);
                    if ($selected === -1) {
                        usleep(self::IDLE_SLEEP_USEC);
                    }
                } else {
                    usleep(self::IDLE_SLEEP_USEC);
                }
            }

            $durationSec = (hrtime(true) - $startedAt) / 1_000_000_000;
            if ($resultsHandle !== null) {
                fclose($resultsHandle);
                $resultsHandle = null;
            }

            $keepSpilledResultsFile = true;

            return new RunResult(
                options: $options,
                durationSec: max($durationSec, 0.000_001),
                requestResults: $results,
                requestResultsPath: $resultsPath,
                requestResultCount: $requestResultCount,
                partial: $stopReason !== null,
                terminationReason: $stopReason
            );
        } finally {
            if ($resultsHandle !== null) {
                fclose($resultsHandle);
            }

            curl_multi_close($multi);

            if (!$keepSpilledResultsFile && $resultsPath !== null && is_file($resultsPath)) {
                @unlink($resultsPath);
            }
        }
    }

    /**
     * Flushes in-memory results to a temporary file and appends the new result.
     *
     * @param list<RequestResult> $inMemoryResults
     * @return array{0:string,1:resource}
     */
    private function storeRequestResultToDisk(
        array &$inMemoryResults,
        RequestResult $requestResult,
        ?string $resultsPath,
        mixed $resultsHandle
    ): array {
        if ($resultsPath === null || $resultsHandle === null) {
            [$resultsPath, $resultsHandle] = $this->openRequestResultsFile();

            foreach ($inMemoryResults as $storedResult) {
                $this->writeRequestResult($resultsHandle, $storedResult);
            }

            $inMemoryResults = [];
        }

        assert(is_resource($resultsHandle));
        $this->writeRequestResult($resultsHandle, $requestResult);

        return [$resultsPath, $resultsHandle];
    }

    /**
     * Opens a new temporary file for spilling request results.
     *
     * @return array{0:string,1:resource}
     */
    private function openRequestResultsFile(): array
    {
        $path = tempnam(sys_get_temp_dir(), 'eleload-results-');
        if (!is_string($path)) {
            throw new RuntimeException('Failed to create temporary request results file.');
        }

        $handle = fopen($path, 'wb');
        if ($handle === false) {
            @unlink($path);
            throw new RuntimeException('Failed to open temporary request results file.');
        }

        return [$path, $handle];
    }

    /**
     * Serialises a single RequestResult as JSON and writes it to the spill file.
     *
     * @param resource $resultsHandle
     */
    private function writeRequestResult($resultsHandle, RequestResult $requestResult): void
    {
        $encoded = json_encode(
            $requestResult->toArray(),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        if (fwrite($resultsHandle, $encoded . PHP_EOL) === false) {
            throw new RuntimeException('Failed to write spilled request result.');
        }
    }

    /**
     * Returns the soft memory threshold for early stop, or null when disabled.
     */
    private function resolveMemoryStopThresholdBytes(): ?int
    {
        if ($this->memorySoftLimitBytes !== null) {
            return $this->memorySoftLimitBytes > 0 ? $this->memorySoftLimitBytes : null;
        }

        $memoryLimit = ini_get('memory_limit');
        if ($memoryLimit === '' || $memoryLimit === '-1') {
            return null;
        }

        $bytes = $this->parseIniMemorySizeToBytes($memoryLimit);
        if ($bytes === null || $bytes <= 0) {
            return null;
        }

        return (int) floor($bytes * self::MEMORY_PRESSURE_THRESHOLD_RATIO);
    }

    /**
     * Parses memory values like 128M, 1G, 512K into bytes.
     */
    private function parseIniMemorySizeToBytes(string $value): ?int
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        $unit = strtolower(substr($trimmed, -1));
        $numberPart = $trimmed;
        $multiplier = 1;

        if ($unit === 'g' || $unit === 'm' || $unit === 'k') {
            $numberPart = substr($trimmed, 0, -1);
            if ($unit === 'g') {
                $multiplier = 1024 * 1024 * 1024;
            } elseif ($unit === 'm') {
                $multiplier = 1024 * 1024;
            } elseif ($unit === 'k') {
                $multiplier = 1024;
            }
        }

        if (!is_numeric($numberPart)) {
            return null;
        }

        return (int) floor((float) $numberPart * $multiplier);
    }

    /**
     * Returns the effective concurrency level, applying a ramp-up curve when configured.
     */
    private function effectiveConcurrency(RequestOptions $options, int $startedAt): int
    {
        if ($options->rampUpSec <= 0.0) {
            return $options->concurrency;
        }

        $elapsedSec = (hrtime(true) - $startedAt) / 1_000_000_000;
        if ($elapsedSec >= $options->rampUpSec) {
            return $options->concurrency;
        }

        $fraction = $elapsedSec / $options->rampUpSec;
        return max(1, (int) ceil($fraction * $options->concurrency));
    }

    /**
     * Returns true when another request can be started given the current elapsed time / count.
     */
    private function canStartRequest(
        RequestOptions $options,
        int $startedAt,
        int $nextRequest,
        bool $durationMode
    ): bool {
        if ($durationMode) {
            $elapsedSec = (hrtime(true) - $startedAt) / 1_000_000_000;
            return $elapsedSec < (float)$options->durationSec;
        }

        return $nextRequest <= $options->requests;
    }

    /**
     * Returns microseconds to sleep before dispatching the next request to honour the target rate.
     * Returns 0 when no rate limit is configured or the request is already overdue.
     */
    private function getRateLimitSleepUsec(RequestOptions $options, int $startedAt, int $nextRequest): int
    {
        $targetRate = $this->resolveTargetRate($options);
        if ($targetRate === null) {
            return 0;
        }

        $scheduledOffsetNs = (int) ceil((($nextRequest - 1) / $targetRate) * self::NANOSECONDS_PER_SECOND);
        $remainingNs = ($startedAt + $scheduledOffsetNs) - hrtime(true);
        if ($remainingNs <= 0) {
            return 0;
        }

        return (int) ceil($remainingNs / self::NANOSECONDS_PER_MICROSECOND);
    }

    /**
     * Resolves the effective target rate (req/sec) from targetRps / targetTps.
     * Returns null when no rate limit is configured.
     */
    private function resolveTargetRate(RequestOptions $options): ?float
    {
        if ($options->targetRps !== null && $options->targetTps !== null) {
            return min($options->targetRps, $options->targetTps);
        }

        return $options->targetRps ?? $options->targetTps;
    }

    /**
     * Returns true when the run has finished (all requests done, or duration elapsed and no in-flight).
     */
    private function isComplete(
        RequestOptions $options,
        int $startedAt,
        int $completed,
        int $inFlightCount,
        bool $durationMode
    ): bool {
        if ($durationMode) {
            $elapsedSec = (hrtime(true) - $startedAt) / 1_000_000_000;
            return $elapsedSec >= (float)$options->durationSec && $inFlightCount === 0;
        }

        return $completed >= $options->requests;
    }

    /**
     * Creates and configures a curl handle for a single request.
     *
     * @throws \RuntimeException
     */
    private function createHandle(RequestOptions $options): CurlHandle
    {
        $url = $options->grpcMethod !== null
            ? rtrim($options->url, '/') . '/' . ltrim($options->grpcMethod, '/')
            : $options->url;

        $ch = curl_init($url);
        if (!$ch instanceof CurlHandle) {
            throw new RuntimeException('Failed to initialize curl handle.');
        }

        $curlOptions = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $options->grpcMethod !== null ? 'POST' : $options->method,
            CURLOPT_TIMEOUT => $options->timeout,
            CURLOPT_CONNECTTIMEOUT => $options->connectTimeout ?? min($options->timeout, 5),
            CURLOPT_HEADER => false,
            CURLOPT_FOLLOWLOCATION => $options->followRedirects,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
            CURLOPT_TCP_KEEPALIVE => $options->tcpKeepaliveSec > 0 ? 1 : 0,
            CURLOPT_TCP_KEEPIDLE => $options->tcpKeepaliveSec,
            CURLOPT_HTTP_VERSION => $options->grpcMethod !== null
                ? CURL_HTTP_VERSION_2_0
                : $this->resolveCurlHttpVersion($options->httpVersion),
            CURLOPT_DNS_CACHE_TIMEOUT => $options->dnsCacheTtl,
            CURLOPT_ENCODING => $options->noDecompress ? '' : ($options->acceptEncoding === 'none' ? '' : $options->acceptEncoding),
            CURLOPT_MAXCONNECTS => $options->maxConnections,
        ];

        if ($options->grpcMethod !== null) {
            // gRPC unary RPC over HTTP/2: apply gRPC framing and mandatory headers
            $rawBody = $options->body ?? '';
            $curlOptions[CURLOPT_POSTFIELDS] = GrpcFramer::encode($rawBody);
            $curlOptions[CURLOPT_HTTPHEADER] = array_merge(
                [
                    'Content-Type: application/grpc+proto',
                    'TE: trailers',
                    'grpc-encoding: identity',
                    'grpc-accept-encoding: identity',
                ],
                $options->resolveHeaders()
            );
        } else {
            $headers = $options->resolveHeaders();
            if (!empty($headers)) {
                $curlOptions[CURLOPT_HTTPHEADER] = $headers;
            }

            if ($options->body !== null) {
                $curlOptions[CURLOPT_POSTFIELDS] = $options->body;
            }
        }

        // @phpstan-ignore-next-line (CURLOPT_CUSTOMREQUEST accepts any non-empty string; method is validated at parse time)
        curl_setopt_array($ch, $curlOptions);

        return $ch;
    }

    private function resolveCurlHttpVersion(string $version): int
    {
        return match ($version) {
            '1.0' => CURL_HTTP_VERSION_1_0,
            '1.1' => CURL_HTTP_VERSION_1_1,
            '2.0' => CURL_HTTP_VERSION_2_0,
            '3.0' => defined('CURL_HTTP_VERSION_3') ? CURL_HTTP_VERSION_3 : CURL_HTTP_VERSION_2_0,
            default => CURL_HTTP_VERSION_2_0,
        };
    }
}
